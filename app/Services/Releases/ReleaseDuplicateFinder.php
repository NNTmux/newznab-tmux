<?php

declare(strict_types=1);

namespace App\Services\Releases;

use App\Models\Release;
use App\Services\Nzb\NzbService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds an existing release that should be treated as a duplicate of an incoming import.
 *
 * Uses (predb_id OR searchname) within a configurable size band. Falls back to raw {@see Release::$name}
 * when {@see Release::$searchname} is empty and there is no predb id. Disabled entirely when
 * `nntmux.release_dedupe_enabled` is false (NZB import then checks article identity itself).
 */
final class ReleaseDuplicateFinder
{
    /**
     * @return array{0: ?Release, 1: ?string} Tuple of matched release (if any) and dedupe reason for logging.
     */
    public function findDuplicate(
        string $cleanRelName,
        string $searchName,
        int $predbId,
        int $filesize,
        bool $publishedOnly = false,
    ): array {
        if (! (bool) config('nntmux.release_dedupe_enabled', true)) {
            return [null, null];
        }

        $query = $this->matchingQuery($cleanRelName, $searchName, $predbId, $filesize);
        if ($publishedOnly) {
            $query->where('nzbstatus', NzbService::NZB_ADDED);
        }

        $dup = $query->first(['id', 'predb_id', 'searchname', 'fromname', 'size', 'name']);

        if ($dup === null) {
            return [null, null];
        }

        $reason = $this->resolveReason($dup, $searchName, $predbId);

        return [$dup, $reason];
    }

    /**
     * Iterate a fixed snapshot of matching published releases in bounded keyset pages.
     *
     * @return iterable<Release>
     */
    public function publishedCandidates(string $cleanRelName, string $searchName, int $predbId, int $filesize): iterable
    {
        if (! (bool) config('nntmux.release_dedupe_enabled', true)) {
            return;
        }
        $query = $this->matchingQuery($cleanRelName, $searchName, $predbId, $filesize)
            ->where('nzbstatus', NzbService::NZB_ADDED);
        $ceiling = (clone $query)->max('id');
        if ($ceiling === null) {
            return;
        }
        $after = 0;
        do {
            $page = (clone $query)->where('id', '>', $after)->where('id', '<=', $ceiling)
                ->orderBy('id')->limit(100)->get(['id', 'guid']);
            foreach ($page as $release) {
                $after = (int) $release->id;
                yield $release;
            }
        } while ($page->count() === 100);
    }

    /** @return Builder<Release> */
    private function matchingQuery(string $cleanRelName, string $searchName, int $predbId, int $filesize): Builder
    {
        $tolerance = (float) config('nntmux.release_dedupe_size_tolerance', 0.05);
        $lowSize = (int) floor($filesize * (1 - $tolerance));
        $highSize = (int) ceil($filesize * (1 + $tolerance));

        $query = Release::query()
            ->whereBetween('size', [$lowSize, $highSize]);

        if ($predbId > 0 || $searchName !== '') {
            $query->where(function (Builder $w) use ($searchName, $predbId): void {
                if ($predbId > 0) {
                    $w->where('predb_id', $predbId);
                    if ($searchName !== '') {
                        $w->orWhere('searchname', $searchName);
                    }
                } elseif ($searchName !== '') {
                    $w->where('searchname', $searchName);
                } else {
                    $w->whereRaw('1 = 0');
                }
            });
        } else {
            $query->where('name', $cleanRelName);
        }

        return $query;
    }

    private function resolveReason(Release $dup, string $searchName, int $predbId): string
    {
        if ($predbId > 0 && (int) $dup->predb_id === $predbId) {
            return 'predb_id_match';
        }

        if ($searchName !== '' && (string) $dup->searchname === $searchName) {
            return 'searchname_match';
        }

        return 'name_match_fallback';
    }
}
