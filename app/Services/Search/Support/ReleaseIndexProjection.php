<?php

declare(strict_types=1);

namespace App\Services\Search\Support;

use App\Support\ReleaseSearchIndexDocument;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single database projection used to build complete release search documents.
 */
final class ReleaseIndexProjection
{
    public static function query(): Builder
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $filename = $isSqlite
            ? "COALESCE((SELECT GROUP_CONCAT(rf.name, ' ') FROM release_files rf WHERE rf.releases_id = r.id), '')"
            : "COALESCE((SELECT GROUP_CONCAT(rf.name SEPARATOR ' ') FROM release_files rf WHERE rf.releases_id = r.id), '')";
        $categoryName = $isSqlite
            ? "cp.title || ' > ' || c.title"
            : "CONCAT(cp.title, ' > ', c.title)";

        return DB::table('releases as r')
            ->leftJoin('usenet_groups as g', 'g.id', '=', 'r.groups_id')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
            ->leftJoin('root_categories as cp', 'cp.id', '=', 'c.root_categories_id')
            ->leftJoin('movieinfo as mi', function ($join): void {
                $join->on('mi.id', '=', 'r.movieinfo_id')
                    ->where('r.movieinfo_id', '>', 0);
            })
            ->leftJoin('videos as v', function ($join): void {
                $join->on('v.id', '=', 'r.videos_id')
                    ->where('r.videos_id', '>', 0);
            })
            ->leftJoin('tv_episodes as tve', function ($join): void {
                $join->on('tve.id', '=', 'r.tv_episodes_id')
                    ->where('r.tv_episodes_id', '>', 0);
            })
            ->leftJoin('release_nfos as rn', 'rn.releases_id', '=', 'r.id')
            ->leftJoin('video_data as vd', 'vd.releases_id', '=', 'r.id')
            ->select([
                'r.id', 'r.guid', 'r.name', 'r.searchname', 'r.fromname', 'r.categories_id',
                'r.groups_id', 'r.size', 'r.postdate', 'r.adddate', 'r.totalpart', 'r.grabs',
                'r.comments', 'r.passwordstatus', 'r.nzbstatus', 'r.nfostatus', 'r.haspreview',
                'r.jpgstatus', 'r.videos_id', 'r.tv_episodes_id', 'r.movieinfo_id', 'r.imdbid',
                'r.anidbid', 'g.name as group_name', 'c.root_categories_id as parentid',
                'c.title as sub_category', 'tve.title as episode_title', 'tve.series',
                'tve.episode', 'tve.firstaired',
                'cp.title as parent_category', DB::raw("{$categoryName} AS category_name"),
                DB::raw("{$filename} AS filename"),
                DB::raw('COALESCE(mi.tmdbid, 0) AS tmdbid'),
                DB::raw('COALESCE(mi.traktid, 0) AS traktid'),
                DB::raw('COALESCE(v.tvdb, 0) AS tvdb'),
                DB::raw('COALESCE(v.tvmaze, 0) AS tvmaze'),
                DB::raw('COALESCE(v.tvrage, 0) AS tvrage'),
                DB::raw('COALESCE(v.trakt, 0) AS trakt'),
                DB::raw("COALESCE(v.imdb, '') AS imdb"),
                DB::raw('COALESCE(v.tmdb, 0) AS tmdb'),
                DB::raw('COALESCE(rn.releases_id, 0) AS nfoid'),
                DB::raw('COALESCE(vd.releases_id, 0) AS reid'),
            ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forId(int $releaseId): ?array
    {
        if ($releaseId <= 0) {
            return null;
        }

        $row = self::query()->where('r.id', $releaseId)->first();

        return $row === null ? null : ReleaseSearchIndexDocument::normalize((array) $row);
    }
}
