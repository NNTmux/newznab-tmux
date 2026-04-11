<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Facades\Search;
use App\Models\Category;
use App\Models\Release;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseSearchService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class RSS -- contains specific functions for RSS.
 */
class RSS extends ApiController
{
    /**
     * @throws \Exception
     */
    public function __construct(
        ReleaseSearchService $releaseSearchService,
        ReleaseBrowseService $releaseBrowseService
    ) {
        parent::__construct($releaseSearchService, $releaseBrowseService);
    }

    /**
     * @return Release[]|Collection|mixed
     */
    public function getRss(mixed $cat, mixed $videosId, mixed $aniDbID, int $userID = 0, int $airDate = -1, int $limit = 100, int $offset = 0)
    {
        $catSearch = $cartSearch = '';
        $catLimit = 'AND r.categories_id BETWEEN '.Category::TV_ROOT.' AND '.Category::TV_OTHER;
        if (\count($cat)) {
            if ((int) $cat[0] === -2) {
                $cartSearch = sprintf(
                    'INNER JOIN users_releases ON users_releases.users_id = %d AND users_releases.releases_id = r.id',
                    $userID
                );
            } elseif ((int) $cat[0] !== -1) {
                $catSearch = Category::getCategorySearch($cat);
            }
        }
        $sql =
            sprintf(
                "SELECT r.searchname, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id,
					m.cover, m.imdbid, m.rating, m.plot, m.year, m.genre, m.director, m.actors,
					g.name AS group_name,
					CONCAT(cp.title, ' > ', c.title) AS category_name,
					COALESCE(cp.id,0) AS parentid,
					mu.title AS mu_title, mu.url AS mu_url, mu.artist AS mu_artist,
					mu.publisher AS mu_publisher, mu.releasedate AS mu_releasedate,
					mu.review AS mu_review, mu.tracks AS mu_tracks, mu.cover AS mu_cover,
					mug.title AS mu_genre, co.title AS co_title, co.url AS co_url,
					co.publisher AS co_publisher, co.releasedate AS co_releasedate,
					co.review AS co_review, co.cover AS co_cover, cog.title AS co_genre,
					bo.cover AS bo_cover
				FROM releases r
				LEFT JOIN categories c ON c.id = r.categories_id
				INNER JOIN root_categories cp ON cp.id = c.root_categories_id
				LEFT JOIN usenet_groups g ON g.id = r.groups_id
				LEFT OUTER JOIN musicinfo mu ON mu.id = r.musicinfo_id
				LEFT OUTER JOIN genres mug ON mug.id = mu.genres_id
				LEFT OUTER JOIN consoleinfo co ON co.id = r.consoleinfo_id
				LEFT JOIN movieinfo m ON m.id = r.movieinfo_id
				LEFT OUTER JOIN genres cog ON cog.id = co.genres_id %s
				LEFT OUTER JOIN tv_episodes tve ON tve.id = r.tv_episodes_id
				LEFT OUTER JOIN bookinfo bo ON bo.id = r.bookinfo_id
				WHERE r.passwordstatus %s
				%s %s %s %s
				ORDER BY postdate DESC %s",
                $cartSearch,
                $this->releaseBrowseService->showPasswords(),
                $catSearch,
                ($videosId > 0 ? sprintf('AND r.videos_id = %d %s', $videosId, ($catSearch === '' ? $catLimit : '')) : ''),
                ($aniDbID > 0 ? sprintf('AND r.anidbid = %d %s', $aniDbID, ($catSearch === '' ? $catLimit : '')) : ''),
                ($airDate > -1 ? sprintf('AND tve.firstaired >= DATE_SUB(CURDATE(), INTERVAL %d DAY)', $airDate) : ''),
                $limit === -1 ? '' : ' LIMIT '.$limit.' OFFSET '.$offset
            );

        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));

        if (Search::isAvailable() && $cartSearch === '' && $videosId <= 0 && $aniDbID <= 0 && $airDate <= -1) {
            $idxCacheKey = md5('rss_search_'.serialize([$cat, $limit, $offset, Search::getCurrentDriver()]));
            $cachedIdx = Cache::get($idxCacheKey);
            if ($cachedIdx !== null) {
                return $cachedIdx;
            }
            try {
                $fromIndex = $this->fetchRssRowsViaSearchIndex($cat, $limit, $offset);
                Cache::put($idxCacheKey, $fromIndex, $expiresAt);

                return $fromIndex;
            } catch (\Throwable $e) {
                if (config('app.debug')) {
                    Log::debug('RSS: search index path failed, falling back to SQL', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $cacheKey = $this->buildVersionedCacheKey($sql);
        $result = Cache::get($cacheKey);
        if ($result !== null) {
            return $result;
        }

        $result = Release::fromQuery($sql);
        Cache::put($cacheKey, $result, $expiresAt);

        return $result;
    }

    /**
     * Hydrate RSS rows using pre-filtered release IDs from the search engine.
     *
     * @param  array<int|string, mixed>  $cat
     * @return Collection<int, Release>
     */
    private function fetchRssRowsViaSearchIndex(array $cat, int $limit, int $offset): Collection
    {
        $categoryIdsRaw = Category::getCategorySearch($cat, null, true);
        $categoryIds = null;
        if (is_array($categoryIdsRaw)) {
            $categoryIds = array_map(static fn ($id): int => (int) $id, $categoryIdsRaw);
        } elseif (is_int($categoryIdsRaw) || (is_string($categoryIdsRaw) && ctype_digit((string) $categoryIdsRaw))) {
            $categoryIds = [(int) $categoryIdsRaw];
        }

        $criteria = [
            'phrases' => null,
            'category_ids' => $categoryIds,
            'excluded_category_ids' => [],
            'min_size' => 0,
            'max_age_days' => 0,
            'groups_id' => null,
            'password_allow_rar' => str_contains($this->releaseBrowseService->showPasswords(), '<='),
            'sort_field' => 'postdate_ts',
            'sort_dir' => 'desc',
            'try_fuzzy' => false,
        ];

        $maxMatches = (int) config('search.drivers.manticore.max_matches', 10000);
        $pageLimit = $limit === -1 ? min($maxMatches, 5000) : max(1, $limit);

        $filtered = Search::searchReleasesFiltered($criteria, $pageLimit, max(0, $offset));
        if ($filtered['ids'] === []) {
            return new Collection;
        }

        $ids = array_map(static fn ($id): int => (int) $id, $filtered['ids']);
        $idList = implode(',', $ids);
        $fieldOrder = implode(',', $ids);

        $sql = sprintf(
            "SELECT r.searchname, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id,
					m.cover, m.imdbid, m.rating, m.plot, m.year, m.genre, m.director, m.actors,
					g.name AS group_name,
					CONCAT(cp.title, ' > ', c.title) AS category_name,
					COALESCE(cp.id,0) AS parentid,
					mu.title AS mu_title, mu.url AS mu_url, mu.artist AS mu_artist,
					mu.publisher AS mu_publisher, mu.releasedate AS mu_releasedate,
					mu.review AS mu_review, mu.tracks AS mu_tracks, mu.cover AS mu_cover,
					mug.title AS mu_genre, co.title AS co_title, co.url AS co_url,
					co.publisher AS co_publisher, co.releasedate AS co_releasedate,
					co.review AS co_review, co.cover AS co_cover, cog.title AS co_genre,
					bo.cover AS bo_cover
				FROM releases r
				LEFT JOIN categories c ON c.id = r.categories_id
				INNER JOIN root_categories cp ON cp.id = c.root_categories_id
				LEFT JOIN usenet_groups g ON g.id = r.groups_id
				LEFT OUTER JOIN musicinfo mu ON mu.id = r.musicinfo_id
				LEFT OUTER JOIN genres mug ON mug.id = mu.genres_id
				LEFT OUTER JOIN consoleinfo co ON co.id = r.consoleinfo_id
				LEFT JOIN movieinfo m ON m.id = r.movieinfo_id
				LEFT OUTER JOIN genres cog ON cog.id = co.genres_id
				LEFT OUTER JOIN tv_episodes tve ON tve.id = r.tv_episodes_id
				LEFT OUTER JOIN bookinfo bo ON bo.id = r.bookinfo_id
				WHERE r.id IN (%s)
				ORDER BY FIELD(r.id, %s)",
            $idList,
            $fieldOrder
        );

        return Release::fromQuery($sql);
    }

    /**
     * @param  array<string, mixed>  $excludedCats
     */
    public function getShowsRss(int $limit, int $userID = 0, array $excludedCats = [], int $airDate = -1): mixed
    {
        $sql = sprintf(

            "SELECT DISTINCT r.searchname, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id, v.id, v.title, g.name AS group_name, CONCAT(cp.title, '-', c.title) AS category_name, COALESCE(cp.id,0) AS parentid FROM releases r INNER JOIN user_series us ON us.videos_id = r.videos_id AND us.users_id = %d LEFT JOIN categories c ON c.id = r.categories_id INNER JOIN root_categories cp ON cp.id = c.root_categories_id LEFT JOIN usenet_groups g ON g.id = r.groups_id LEFT OUTER JOIN videos v ON v.id = r.videos_id LEFT OUTER JOIN tv_episodes tve ON tve.id = r.tv_episodes_id WHERE (us.categories IS NULL OR us.categories = '' OR us.categories = 'NULL' OR FIND_IN_SET(r.categories_id, REPLACE(us.categories,'|',',')) > 0)%s%s AND r.categories_id BETWEEN %d AND %d AND r.passwordstatus %s ORDER BY postdate DESC %s",
            $userID,
            (\count($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
            ($airDate > -1 ? sprintf(' AND tve.firstaired >= DATE_SUB(CURDATE(), INTERVAL %d DAY)', $airDate) : ''),
            Category::TV_ROOT,
            Category::TV_OTHER,
            $this->releaseBrowseService->showPasswords(),
            ! empty($limit) ? sprintf(' LIMIT %d OFFSET 0', min($limit, 100)) : ''
        );

        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $cacheKey = $this->buildVersionedCacheKey($sql);
        $result = Cache::get($cacheKey);
        if ($result !== null) {
            return $result;
        }

        $result = Release::fromQuery($sql);
        Cache::put($cacheKey, $result, $expiresAt);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $excludedCats
     * @return Release[]|Collection|mixed
     */
    public function getMyMoviesRss(int $limit, int $userID = 0, array $excludedCats = [])
    {
        // Use stricter joins: movieinfo joined by imdbid, require non-null imdbid; prevents unrelated titles sharing incorrect links.
        $sql = sprintf(
            "SELECT DISTINCT r.searchname, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id, mi.title AS releasetitle, g.name AS group_name, CONCAT(cp.title, '-', c.title) AS category_name, COALESCE(cp.id,0) AS parentid FROM releases r INNER JOIN user_movies um ON um.imdbid = r.imdbid AND um.users_id = %d LEFT JOIN categories c ON c.id = r.categories_id INNER JOIN root_categories cp ON cp.id = c.root_categories_id LEFT JOIN usenet_groups g ON g.id = r.groups_id LEFT JOIN movieinfo mi ON mi.imdbid = r.imdbid WHERE r.imdbid IS NOT NULL AND (um.categories IS NULL OR um.categories = '' OR um.categories = 'NULL' OR FIND_IN_SET(r.categories_id, REPLACE(um.categories,'|',',')) > 0)%s AND r.categories_id BETWEEN %d AND %d AND r.passwordstatus %s ORDER BY postdate DESC %s",
            $userID,
            (\count($excludedCats) > 0 ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
            Category::MOVIE_ROOT,
            Category::MOVIE_OTHER,
            $this->releaseBrowseService->showPasswords(),
            ! empty($limit) ? sprintf(' LIMIT %d OFFSET 0', min($limit, 100)) : ''
        );

        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $cacheKey = $this->buildVersionedCacheKey($sql);
        $result = Cache::get($cacheKey);
        if ($result !== null) {
            return $result;
        }
        $result = Release::fromQuery($sql);

        Cache::put($cacheKey, $result, $expiresAt);

        return $result;
    }

    /**
     * Get trending movies RSS (top 15 most downloaded in last 48 hours)
     *
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public function getTrendingMoviesRss()
    {
        $cacheKey = 'rss_trending_movies_48h';

        return Cache::remember($cacheKey, 3600, function () {
            $fortyEightHoursAgo = Carbon::now()->subHours(48);

            // First get the top 15 movies by download count
            $topMovies = DB::table('movieinfo as m')
                ->join('releases as r', 'm.imdbid', '=', 'r.imdbid')
                ->leftJoin('user_downloads as ud', 'r.id', '=', 'ud.releases_id')
                ->select([
                    'm.imdbid',
                    DB::raw('COUNT(DISTINCT ud.id) as total_downloads'),
                ])
                ->where('m.title', '!=', '')
                ->whereNotNull('m.imdbid')
                ->where('m.imdbid', '!=', '')
                ->where('ud.timestamp', '>=', $fortyEightHoursAgo)
                ->groupBy('m.imdbid')
                ->havingRaw('COUNT(DISTINCT ud.id) > 0')
                ->orderByDesc('total_downloads')
                ->limit(15)
                ->pluck('imdbid');

            if ($topMovies->isEmpty()) {
                return collect([]);
            }

            // Then get only the latest 5 releases for each of these top movies
            $sql = sprintf(
                "SELECT * FROM (
                    SELECT
                        r.searchname, r.guid, r.postdate, r.categories_id, r.size,
                        r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments,
                        r.adddate, r.imdbid,
                        m.title, m.year, m.rating, m.plot, m.genre, m.cover, m.tmdbid, m.traktid,
                        g.name AS group_name,
                        CONCAT(cp.title, ' > ', c.title) AS category_name,
                        COALESCE(cp.id,0) AS parentid,
                        ROW_NUMBER() OVER (PARTITION BY r.imdbid ORDER BY r.postdate DESC) as rn
                    FROM releases r
                    INNER JOIN movieinfo m ON m.imdbid = r.imdbid
                    LEFT JOIN categories c ON c.id = r.categories_id
                    INNER JOIN root_categories cp ON cp.id = c.root_categories_id
                    LEFT JOIN usenet_groups g ON g.id = r.groups_id
                    WHERE r.imdbid IN ('%s')
                        AND r.passwordstatus %s
                ) AS ranked
                WHERE rn <= 5
                ORDER BY FIELD(imdbid, '%s'), postdate DESC",
                implode("','", $topMovies->toArray()),
                $this->releaseBrowseService->showPasswords(),
                implode("','", $topMovies->toArray())
            );

            return Release::fromQuery($sql);
        });
    }

    /**
     * Get trending TV shows RSS (top 15 most downloaded in last 48 hours)
     *
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public function getTrendingShowsRss()
    {
        $cacheKey = 'rss_trending_shows_48h';

        return Cache::remember($cacheKey, 3600, function () {
            $fortyEightHoursAgo = Carbon::now()->subHours(48);

            // First get the top 15 TV shows by download count
            $topShows = DB::table('videos as v')
                ->join('releases as r', 'v.id', '=', 'r.videos_id')
                ->leftJoin('user_downloads as ud', 'r.id', '=', 'ud.releases_id')
                ->select([
                    'v.id',
                    DB::raw('COUNT(DISTINCT ud.id) as total_downloads'),
                ])
                ->where('v.type', 0) // 0 = TV
                ->where('v.title', '!=', '')
                ->where('ud.timestamp', '>=', $fortyEightHoursAgo)
                ->groupBy('v.id')
                ->havingRaw('COUNT(DISTINCT ud.id) > 0')
                ->orderByDesc('total_downloads')
                ->limit(15)
                ->pluck('id');

            if ($topShows->isEmpty()) {
                return collect([]);
            }

            // Then get only the latest 5 releases for each of these top shows
            $sql = sprintf(
                "SELECT * FROM (
                    SELECT
                        r.searchname, r.guid, r.postdate, r.categories_id, r.size,
                        r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments,
                        r.adddate, r.videos_id, r.tv_episodes_id,
                        v.title, v.started, v.tvdb, v.tvmaze, v.trakt, v.tmdb, v.countries_id,
                        ti.summary, ti.image,
                        g.name AS group_name,
                        CONCAT(cp.title, ' > ', c.title) AS category_name,
                        COALESCE(cp.id,0) AS parentid,
                        ROW_NUMBER() OVER (PARTITION BY r.videos_id ORDER BY r.postdate DESC) as rn
                    FROM releases r
                    INNER JOIN videos v ON v.id = r.videos_id
                    INNER JOIN tv_info ti ON ti.videos_id = v.id
                    LEFT JOIN categories c ON c.id = r.categories_id
                    INNER JOIN root_categories cp ON cp.id = c.root_categories_id
                    LEFT JOIN usenet_groups g ON g.id = r.groups_id
                    WHERE r.videos_id IN (%s)
                        AND v.type = 0
                        AND r.passwordstatus %s
                ) AS ranked
                WHERE rn <= 5
                ORDER BY FIELD(videos_id, %s), postdate DESC",
                implode(',', $topShows->toArray()),
                $this->releaseBrowseService->showPasswords(),
                implode(',', $topShows->toArray())
            );

            return Release::fromQuery($sql);
        });
    }

    private function buildVersionedCacheKey(string $sql): string
    {
        $cacheVersion = (int) Cache::get('release_search_cache_version', 1);

        return md5($cacheVersion.$sql);
    }

    /**
     * Get the first instance of a column from a table where the column is greater than 0, ordered by a specified column.
     *
     * @param  string  $column  The column to select and check
     * @param  string  $table  The table to query
     * @param  string  $order  The column to order by
     * @return object|null The first instance found or null if none found
     */
    public function getFirstInstance(string $column, string $table, string $order)
    {
        return DB::table($table)
            ->select([$column])
            ->where($column, '>', 0)
            ->orderBy($order)
            ->first();
    }
}
