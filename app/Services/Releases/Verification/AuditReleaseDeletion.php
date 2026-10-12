<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use App\Facades\Search;
use App\Models\Release;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseImageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/** Committed audit deletions retain bounded artifact cleanup work until it succeeds. */
final class AuditReleaseDeletion
{
    public function __construct(private NzbService $nzb, private ReleaseImageService $images) {}

    /** Called inside the transaction that marks this audit terminal and advances its cursor. */
    public function commit(Release $release, int $scratchId): void
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Audit deletion requires a database transaction.');
        }
        $current = Release::query()->whereKey($release->id)->where('guid', $release->guid)->lockForUpdate()->first();
        if ($current === null) {
            throw new RuntimeException('Audited release changed before deletion.');
        }
        $nzbPath = $this->nzb->nzbPath($release->guid);
        if (! is_string($nzbPath) || $nzbPath === '') {
            throw new RuntimeException('Audited NZB is unavailable before deletion.');
        }
        $updated = DB::table('release_verification_scratch')->where('id', $scratchId)->where('audit_release_id', $release->id)
            ->where('terminal', false)->where('audit_verdict', 'unrepairable')->update([
                'delete_guid' => $release->guid,
                'delete_paths' => json_encode([$nzbPath, ...$this->images->releaseFiles($release->guid)], JSON_THROW_ON_ERROR),
            ]);
        if ($updated !== 1) {
            throw new RuntimeException('Proven audit deletion state is unavailable.');
        }
        // Match deleteSingle's mass-delete semantics and database cascades; filesystem observers must not run before commit.
        Release::query()->whereKey($release->id)->where('guid', $release->guid)->delete();
    }

    /** Invoked under worker.lock only for a durable terminal audit row. */
    public function cleanup(object $entry): void
    {
        if (! $entry->terminal || $entry->audit_release_id === null || $entry->audit_verdict !== 'unrepairable') {
            throw new RuntimeException('Audit deletion is not committed.');
        }
        if (Release::query()->whereKey($entry->audit_release_id)->exists()
            || Release::query()->where('guid', $entry->delete_guid)->exists()) {
            throw new RuntimeException('Audit cleanup cannot remove a current release.');
        }
        $paths = json_decode((string) $entry->delete_paths, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($paths) || ! array_is_list($paths) || count($paths) !== 9) {
            throw new RuntimeException('Audit artifact inventory is unavailable.');
        }
        foreach (array_unique($paths) as $path) {
            if (! is_string($path) || $path === '') {
                throw new RuntimeException('Audit artifact path is invalid.');
            }
            $parent = dirname($path);
            if (file_exists($parent) && (! is_dir($parent) || ! is_readable($parent) || ! is_executable($parent))) {
                throw new RuntimeException('Audit artifact directory is inaccessible.');
            }
            if ((file_exists($path) || is_link($path)) && ! File::delete($path)) {
                throw new RuntimeException('Audit artifact deletion will retry.');
            }
        }
        Search::deleteRelease((int) $entry->audit_release_id);
    }
}
