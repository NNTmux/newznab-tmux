<?php

declare(strict_types=1);

namespace App\Support;

use App\Facades\Search;
use App\Models\Category;
use App\Models\Release;
use App\Observers\ReleaseObserver;
use Illuminate\Database\Eloquent\Builder;

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

    public static function forMovieInfo(int $movieInfoId): void
    {
        if ($movieInfoId > 0) {
            self::forQuery(Release::query()->where('movieinfo_id', $movieInfoId));
        }
    }

    public static function forVideo(int $videoId): void
    {
        if ($videoId > 0) {
            self::forQuery(Release::query()->where('videos_id', $videoId));
        }
    }

    public static function forCategory(int $categoryId): void
    {
        if ($categoryId > 0) {
            self::forQuery(Release::query()->where('categories_id', $categoryId));
        }
    }

    public static function forRootCategory(int $rootCategoryId): void
    {
        if ($rootCategoryId <= 0) {
            return;
        }

        $categoryIds = Category::query()
            ->where('root_categories_id', $rootCategoryId)
            ->pluck('id');

        if ($categoryIds->isNotEmpty()) {
            self::forQuery(Release::query()->whereIn('categories_id', $categoryIds));
        }
    }

    public static function forQueryGroup(int $groupId): void
    {
        if ($groupId > 0) {
            self::forQuery(Release::query()->where('groups_id', $groupId));
        }
    }

    /**
     * @param  Builder<Release>  $query
     */
    private static function forQuery($query): void
    {
        $query->select('releases.id')->orderBy('releases.id')->chunkById(500, function ($releases): bool {
            self::forIds($releases->pluck('id'));

            return true;
        }, 'releases.id');
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
