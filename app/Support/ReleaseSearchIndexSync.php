<?php

declare(strict_types=1);

namespace App\Support;

use App\Facades\Search;
use App\Models\Release;
use App\Observers\ReleaseObserver;

/**
 * Re-sync releases_rt / ES release documents after query-builder or raw SQL updates
 * that bypass Eloquent {@see ReleaseObserver} events.
 */
final class ReleaseSearchIndexSync
{
    /**
     * @param  iterable<int|string>  $releaseIds
     */
    public static function forIds(iterable $releaseIds): void
    {
        foreach ($releaseIds as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                Search::updateRelease($intId);
            }
        }
    }

    /**
     * Reindex every release (chunked). Use after mass UPDATEs that match an optional raw WHERE suffix.
     *
     * @param  string  $whereSuffix  SQL fragment starting with "AND ..." or empty for all rows
     */
    public static function reindexMatchingWhere(string $whereSuffix = ''): void
    {
        $query = Release::query()->select('releases.id')->orderBy('releases.id');
        $trimmed = trim($whereSuffix);
        if ($trimmed !== '') {
            $condition = preg_replace('/^\s*AND\s+/i', '', $trimmed);
            if ($condition !== '') {
                $query->whereRaw($condition);
            }
        }

        $query->chunkById(500, function ($releases): bool {
            foreach ($releases as $release) {
                Search::updateRelease((int) $release->id);
            }

            return true;
        });
    }
}
