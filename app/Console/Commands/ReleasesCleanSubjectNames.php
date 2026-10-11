<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Predb;
use App\Services\NameFixing\ReleaseUpdateService;
use App\Services\ReleaseCleaningService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Renames releases whose search name is still a raw `[01/10] - "file.ext" yEnc` subject.
 *
 * Release creation used to keep that subject as the name when no group regular expression
 * matched. ReleaseCleaningService now derives the name from the file, so this re-runs it over
 * the stored subjects. Renames go through the name-fixing update path, which skips implausible
 * (hashed) names, re-categorizes, and queues the search index update. Releases that the
 * name-fixing passes already renamed are left alone.
 *
 * The scan is a keyset walk (`id > ?`), so it stays fast on multi-million-row tables.
 */
#[Signature('releases:clean-subject-names
    {--chunk=1000 : Releases read per batch}
    {--limit=0 : Stop after this many candidate releases (0 = all)}
    {--dry-run : Report the renames without writing anything}')]
#[Description('Rename releases still named after a raw "[n/m] - \"file\"" posting subject')]
class ReleasesCleanSubjectNames extends Command
{
    /** Search names that may still be a raw counter-and-file subject (`[1/9] - "`, `[1/9] "`, `[1/9]-"`, `[1/9] - #34;`). */
    private const string RAW_SUBJECT_LIKE = '[%/%]%';

    public function handle(ReleaseCleaningService $cleaner, ReleaseUpdateService $updater): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $lastId = 0;
        $checked = 0;
        $renamed = 0;
        $linked = 0;

        while ($limit === 0 || $checked < $limit) {
            $rows = DB::table('releases as r')
                ->join('usenet_groups as g', 'g.id', '=', 'r.groups_id')
                ->where('r.id', '>', $lastId)
                ->where('r.isrenamed', 0)
                ->where('r.searchname', 'like', self::RAW_SUBJECT_LIKE)
                ->where(fn ($quoted) => $quoted->where('r.searchname', 'like', '%"%')->orWhere('r.searchname', 'like', '%#34;%'))
                ->orderBy('r.id')
                ->limit($limit === 0 ? $chunk : min($chunk, $limit - $checked))
                ->get(['r.id', 'r.name', 'r.searchname', 'r.fromname', 'r.groups_id', 'r.categories_id', 'g.name as group_name']);

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = (int) $row->id;
                $checked++;

                [$name, $preId] = $this->cleanName($cleaner, $row);
                if ($name === null || strcasecmp($name, (string) $row->searchname) === 0) {
                    continue;
                }

                if ($dryRun) {
                    $renamed++;
                    $linked += $preId > 0 ? 1 : 0;
                    if ($this->output->isVerbose()) {
                        $this->line("{$row->id}: {$row->searchname} -> {$name}");
                    }

                    continue;
                }

                $before = $updater->fixed;
                // A name-fixing worker may have renamed it since this chunk was read. Lock the row, re-check it
                // and rename it in one transaction, so that rename is never overwritten.
                // The search index update waits for the commit, so the row lock is never held across it.
                $updater->deferSearchSync((int) $row->id, function () use ($updater, $row, $name, $preId): void {
                    DB::transaction(function () use ($updater, $row, $name, $preId): void {
                        $unchanged = DB::table('releases')->where('id', $row->id)->where('isrenamed', 0)->where('searchname', $row->searchname)->lockForUpdate()->exists();
                        if (! $unchanged) {
                            return;
                        }
                        // An exact PreDB title is a proper name, as at release creation; its type sets `isrenamed`.
                        $preId > 0
                            ? $updater->updateRelease($row, $name, 'Subject cleaner, PreDB exact', true, 'PreDB FT Exact, ', true, false, $preId)
                            : $updater->updateRelease($row, $name, 'Subject cleaner', true, 'Subject, ', false, false, $preId);
                    });
                });
                if ($updater->fixed > $before) {
                    $renamed++;
                    $linked += $preId > 0 ? 1 : 0;
                }
            }

            $this->info(sprintf('Checked %d, renamed %d (%d linked to PreDB), last id %d', $checked, $renamed, $linked, $lastId));
        }

        $this->info(($dryRun ? 'Dry run: would rename ' : 'Renamed ')."{$renamed} of {$checked} releases ({$linked} linked to PreDB).");

        return self::SUCCESS;
    }

    /**
     * @return array{0: ?string, 1: int} The cleaned name (null when it is still the raw subject) and its PreDB id.
     */
    private function cleanName(ReleaseCleaningService $cleaner, object $row): array
    {
        // The search name kept the subject's original characters; `name` had `#%$@^` and the like removed.
        $meta = $cleaner->releaseCleaner((string) $row->searchname, (string) $row->fromname, (string) $row->group_name);
        $name = is_array($meta) ? trim((string) ($meta['cleansubject'] ?? '')) : '';
        $preId = is_array($meta) ? max(0, (int) ($meta['predb'] ?? 0)) : 0;

        // Still a counter-and-file subject: no cleaner recognised it (for example a hashed file name).
        if ($name === '' || preg_match('/^\[\s*\d+\s*\/\s*\d+\s*\]/', $name) === 1) {
            return [null, 0];
        }

        // The name-fixing update path drops non-ASCII characters, which would mangle an accented title.
        // New releases keep it, so leave these rather than corrupt them.
        if (preg_match('/[^\x20-\x7E]/', $name) === 1) {
            return [null, 0];
        }

        // Link the exact PreDB title, as release creation does. Its title may have accents the ASCII-only
        // update path would strip, so apply the same guard to it.
        if ($preId === 0 && ($pre = Predb::matchPre($name)) !== false) {
            return preg_match('/[^\x20-\x7E]/', $pre['title']) === 1 ? [null, 0] : [$pre['title'], (int) $pre['predb_id']];
        }

        return [$name, $preId];
    }
}
