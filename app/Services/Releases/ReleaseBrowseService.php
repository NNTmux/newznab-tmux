<?php

namespace App\Services\Releases;

use App\Facades\Search;
use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for browsing and ordering releases on the frontend.
 */
class ReleaseBrowseService
{
    private const CACHE_VERSION_KEY = 'releases:cache_version';

    // RAR/ZIP Password indicator.
    public const PASSWD_NONE = 0; // No password.

    public const PASSWD_RAR = 1; // Definitely passworded.

    public function __construct() {}

    /**
     * Used for Browse results with optional search term filtering via search index.
     *
     * @param  string|null  $searchTerm  Optional search term to filter by (uses search index)
     * @return Collection|mixed
     */
    public function getBrowseRange($page, $cat, $start, $num, $orderBy, int $maxAge = -1, array $excludedCats = [], int|string $groupName = -1, int $minSize = 0, ?string $searchTerm = null): mixed
    {
        $cacheVersion = $this->getCacheVersion();
        $page = max(1, $page);
        $start = max(0, $start);

        $orderBy = $this->getBrowseOrder($orderBy);

        // Use search index filtering when a search term is provided
        $searchIndexFilter = '';
        $searchIndexIds = [];
        if (! empty($searchTerm) && Search::isAvailable()) {
            $searchResult = Search::searchReleasesWithFuzzy(['searchname' => $searchTerm], $num * 10);
            $searchIndexIds = $searchResult['ids'] ?? [];

            if (config('app.debug') && ($searchResult['fuzzy'] ?? false)) {
                Log::debug('getBrowseRange: Using fuzzy search results for browse filtering');
            }

            if (empty($searchIndexIds)) {
                // No results from search index, return empty result
                return [];
            }

            $searchIndexFilter = sprintf(' AND r.id IN (%s)', implode(',', array_map('intval', $searchIndexIds)));
        }

        $qry = sprintf(
            "SELECT r.id, r.searchname, r.groups_id, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus, r.nfostatus, cp.title AS parent_category, c.title AS sub_category, r.group_name,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				CONCAT(cp.id, ',', c.id) AS category_ids,
                            df.failed AS failed_count,
                            rr.report_count AS report_count,
                            rr.report_reasons AS report_reasons,
				rn.releases_id AS nfoid,
				re.releases_id AS reid,
				v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.imdb, v.tmdb,
				m.imdbid, m.tmdbid, m.traktid,
				tve.title, tve.firstaired
			FROM
			(
				SELECT r.id, r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus, r.nfostatus, g.name AS group_name, r.movieinfo_id
				FROM releases r
				LEFT JOIN usenet_groups g ON g.id = r.groups_id
				WHERE r.passwordstatus %1\$s
				%2\$s %3\$s %4\$s %5\$s %6\$s %7\$s
				ORDER BY %8\$s %9\$s %10\$s
			) r
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
			LEFT OUTER JOIN videos v ON r.videos_id = v.id
			LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
			LEFT OUTER JOIN movieinfo m ON m.id = r.movieinfo_id
			LEFT OUTER JOIN video_data re ON re.releases_id = r.id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
			LEFT OUTER JOIN (SELECT releases_id, COUNT(*) AS report_count, GROUP_CONCAT(DISTINCT reason SEPARATOR ', ') AS report_reasons FROM release_reports WHERE status IN ('pending', 'reviewed', 'resolved') GROUP BY releases_id) rr ON rr.releases_id = r.id
			GROUP BY r.id
			ORDER BY %8\$s %9\$s",
            $this->showPasswords(),
            Category::getCategorySearch($cat),
            ($maxAge > 0 ? (' AND postdate > NOW() - INTERVAL '.$maxAge.' DAY ') : ''),
            (\count($excludedCats) ? (' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')') : ''),
            ((int) $groupName !== -1 ? sprintf(' AND g.name = %s ', escapeString($groupName)) : ''),
            ($minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : ''),
            $searchIndexFilter,
            $orderBy[0],
            $orderBy[1],
            ($start === 0 ? ' LIMIT '.$num : ' LIMIT '.$num.' OFFSET '.$start)
        );

        $cacheKey = md5($cacheVersion.$qry.$page);
        $releases = Cache::get($cacheKey);
        if ($releases !== null) {
            return $releases;
        }
        $sql = DB::select($qry);
        if (\count($sql) > 0) {
            // When using search index, use the ID count for total rows
            if (! empty($searchIndexIds)) {
                $sql[0]->_totalcount = $sql[0]->_totalrows = count($searchIndexIds);
            } else {
                $possibleRows = $this->getBrowseCount($cat, $maxAge, $excludedCats, $groupName);
                $sql[0]->_totalcount = $sql[0]->_totalrows = $possibleRows;
            }
        }
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put($cacheKey, $sql, $expiresAt);

        return $sql;
    }

    /**
     * Used for pager on browse page.
     * Optimized to avoid expensive COUNT queries on large tables.
     * Uses sample-based counting and avoids JOINs whenever possible.
     */
    public function getBrowseCount(array $cat, int $maxAge = -1, array $excludedCats = [], int|string $groupName = ''): int
    {
        $maxResults = (int) config('nntmux.max_pager_results', 500000);
        $cacheExpiry = (int) config('nntmux.cache_expiry_short', 5);

        // Build a unique cache key for this specific query
        $cacheKey = 'browse_count_'.md5(serialize($cat).$maxAge.serialize($excludedCats).$groupName);

        // Check cache first - use longer cache time for count queries since they're expensive
        $count = Cache::get($cacheKey);
        if ($count !== null) {
            return (int) $count;
        }

        // Build optimized count query - avoid JOINs when possible
        $conditions = ['r.passwordstatus '.$this->showPasswords()];

        // Add category conditions
        $catQuery = Category::getCategorySearch($cat);
        $catQuery = preg_replace('/^(WHERE|AND)\s+/i', '', trim($catQuery));
        if (! empty($catQuery) && $catQuery !== '1=1') {
            $conditions[] = $catQuery;
        }

        if ($maxAge > 0) {
            $conditions[] = 'r.postdate > NOW() - INTERVAL '.$maxAge.' DAY';
        }

        if (! empty($excludedCats)) {
            $conditions[] = 'r.categories_id NOT IN ('.implode(',', array_map('intval', $excludedCats)).')';
        }

        // Only add group filter if specified - this requires a JOIN
        $needsGroupJoin = (int) $groupName !== -1;
        if ($needsGroupJoin) {
            $conditions[] = sprintf('g.name = %s', escapeString($groupName));
        }

        $whereSql = 'WHERE '.implode(' AND ', $conditions);

        try {
            // For queries with maxResults limit, use sample-based counting
            if ($maxResults > 0) {
                // Quick check using a small sample (1000 rows) to determine if we should
                // just return maxResults or do a full count
                $sampleLimit = min(1000, $maxResults);

                if ($needsGroupJoin) {
                    // Need JOIN for group filtering
                    $sampleQuery = sprintf(
                        'SELECT r.id FROM releases r INNER JOIN usenet_groups g ON g.id = r.groups_id %s ORDER BY r.id DESC LIMIT %d',
                        $whereSql,
                        $sampleLimit
                    );
                } else {
                    // No JOIN needed - much faster query on releases table only
                    $sampleQuery = sprintf(
                        'SELECT r.id FROM releases r %s ORDER BY r.id DESC LIMIT %d',
                        $whereSql,
                        $sampleLimit
                    );
                }

                $sampleResult = DB::select($sampleQuery);
                $sampleCount = count($sampleResult);

                // If we got the full sample, assume there are more rows than maxResults
                if ($sampleCount >= $sampleLimit) {
                    Cache::put($cacheKey, $maxResults, now()->addMinutes($cacheExpiry * 2));

                    return $maxResults;
                }

                // Fewer than sample limit - this is the actual count
                $count = $sampleCount;
            } else {
                // No max limit set, need full count
                if ($needsGroupJoin) {
                    $query = sprintf(
                        'SELECT COUNT(r.id) AS count FROM releases r INNER JOIN usenet_groups g ON g.id = r.groups_id %s',
                        $whereSql
                    );
                } else {
                    // No JOIN needed - simple count on releases table
                    $query = sprintf('SELECT COUNT(r.id) AS count FROM releases r %s', $whereSql);
                }
                $result = DB::select($query);
                $count = isset($result[0]) ? (int) $result[0]->count : 0;
            }

            // Cache with longer expiry for count queries
            Cache::put($cacheKey, $count, now()->addMinutes($cacheExpiry * 2));

            return $count;
        } catch (\Exception $e) {
            Log::error('getBrowseCount failed', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * Get the passworded releases clause.
     */
    public function showPasswords(): string
    {
        $show = (int) Settings::settingValue('showpasswordedrelease');
        $setting = $show ?? 0;

        return match ($setting) {
            1 => '<= '.self::PASSWD_RAR,
            default => '= '.self::PASSWD_NONE,
        };
    }

    /**
     * Use to order releases on site.
     */
    public function getBrowseOrder(array|string $orderBy): array
    {
        $orderArr = explode('_', ($orderBy === '' ? 'posted_desc' : $orderBy));
        $orderField = match ($orderArr[0]) {
            'cat' => 'categories_id',
            'name' => 'searchname',
            'size' => 'size',
            'files' => 'totalpart',
            'stats' => 'grabs',
            'added' => 'adddate',
            default => 'postdate',
        };

        return [$orderField, isset($orderArr[1]) && preg_match('/^(asc|desc)$/i', $orderArr[1]) ? $orderArr[1] : 'desc'];
    }

    /**
     * Return ordering types usable on site.
     *
     * @return string[]
     */
    public function getBrowseOrdering(): array
    {
        return [
            'name_asc',
            'name_desc',
            'cat_asc',
            'cat_desc',
            'posted_asc',
            'posted_desc',
            'added_asc',
            'added_desc',
            'size_asc',
            'size_desc',
            'files_asc',
            'files_desc',
            'stats_asc',
            'stats_desc',
        ];
    }

    /**
     * @return \Illuminate\Cache\|\Illuminate\Database\Eloquent\Collection|mixed
     */
    public function getShowsRange($userShows, $offset, $limit, $orderBy, int $maxAge = -1, array $excludedCats = [])
    {
        $orderBy = $this->getBrowseOrder($orderBy);
        $sql = sprintf(
            "SELECT r.id, r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus,  cp.title AS parent_category, c.title AS sub_category,
					CONCAT(cp.title, '->', c.title) AS category_name
				FROM releases r
				LEFT JOIN categories c ON c.id = r.categories_id
				LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
				WHERE %s %s
				AND r.categories_id BETWEEN %d AND %d
				AND r.passwordstatus %s
				%s
				GROUP BY r.id
				ORDER BY %s %s %s",
            $this->uSQL($userShows, 'videos_id'),
            (! empty($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
            Category::TV_ROOT,
            Category::TV_OTHER,
            $this->showPasswords(),
            ($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : ''),
            $orderBy[0],
            $orderBy[1],
            ($offset === false ? '' : (' LIMIT '.$limit.' OFFSET '.$offset))
        );
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $result = Cache::get(md5($sql));
        if ($result !== null) {
            return $result;
        }
        $result = Release::fromQuery($sql);
        Cache::put(md5($sql), $result, $expiresAt);

        return $result;
    }

    public function getShowsCount($userShows, int $maxAge = -1, array $excludedCats = []): int
    {
        return $this->getPagerCount(
            sprintf(
                'SELECT r.id
				FROM releases r
				WHERE %s %s
				AND r.categories_id BETWEEN %d AND %d
				AND r.passwordstatus %s
				%s',
                $this->uSQL($userShows, 'videos_id'),
                (\count($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
                Category::TV_ROOT,
                Category::TV_OTHER,
                $this->showPasswords(),
                ($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : '')
            )
        );
    }

    /**
     * Creates part of a query for some functions.
     */
    public function uSQL(Collection|array $userQuery, string $type): string
    {
        $sql = '(1=2 ';
        foreach ($userQuery as $query) {
            $sql .= sprintf('OR (r.%s = %d', $type, $query->$type);
            if (! empty($query->categories)) {
                $catsArr = explode('|', $query->categories);
                if (\count($catsArr) > 1) {
                    $sql .= sprintf(' AND r.categories_id IN (%s)', implode(',', $catsArr));
                } else {
                    $sql .= sprintf(' AND r.categories_id = %d', $catsArr[0]);
                }
            }
            $sql .= ') ';
        }
        $sql .= ') ';

        return $sql;
    }

    public static function bumpCacheVersion(): void
    {
        $current = Cache::get(self::CACHE_VERSION_KEY, 1);
        Cache::forever(self::CACHE_VERSION_KEY, $current + 1);
    }

    private function getCacheVersion(): int
    {
        return Cache::get(self::CACHE_VERSION_KEY, 1);
    }

    /**
     * Search releases using the search index with category filtering.
     * This method pre-filters results via the search index before hitting the database,
     * significantly improving performance for searches with text terms.
     *
     * @param  string  $searchTerm  Search term to match
     * @param  array  $categories  Category IDs to filter by (optional)
     * @param  int  $limit  Maximum number of results
     * @return array Array of release IDs matching the search criteria
     */
    public function searchByIndexWithCategories(string $searchTerm, array $categories = [], int $limit = 1000): array
    {
        if (empty($searchTerm) || ! Search::isAvailable()) {
            return [];
        }

        $searchResult = Search::searchReleasesWithFuzzy(['searchname' => $searchTerm], $limit);
        $releaseIds = $searchResult['ids'] ?? [];

        if (empty($releaseIds)) {
            return [];
        }

        // If categories are specified, filter the results by querying the database for just the IDs
        if (! empty($categories) && ! in_array(-1, $categories, true)) {
            $filteredIds = Release::query()
                ->select('id')
                ->whereIn('id', $releaseIds)
                ->whereIn('categories_id', $categories)
                ->pluck('id')
                ->toArray();

            return $filteredIds;
        }

        return $releaseIds;
    }

    /**
     * Get releases by external media ID using search index.
     * Useful for movie/TV browse pages that need to find all releases for a specific movie/show.
     *
     * @param  array  $externalIds  Associative array of external IDs (e.g., ['imdbid' => 123456])
     * @param  int  $limit  Maximum number of results
     * @return array Array of release IDs
     */
    public function getReleasesByExternalId(array $externalIds, int $limit = 100): array
    {
        if (empty($externalIds) || ! Search::isAvailable()) {
            return [];
        }

        return Search::searchReleasesByExternalId($externalIds, $limit);
    }

    /**
     * Get the count of releases for pager.
     *
     * @param  string  $query  The query to get the count from.
     */
    private function getPagerCount(string $query): int
    {
        $maxResults = (int) config('nntmux.max_pager_results');
        $cacheExpiry = config('nntmux.cache_expiry_short');

        // Generate cache key from original query
        $cacheKey = 'pager_count_'.md5($query);

        // Check cache first
        $count = Cache::get($cacheKey);
        if ($count !== null) {
            return (int) $count;
        }

        // Check if this is already a COUNT query
        if (preg_match('/SELECT\s+COUNT\s*\(/is', $query)) {
            // It's already a COUNT query, just execute it
            try {
                $result = DB::select($query);
                if (isset($result[0])) {
                    // Handle different possible column names
                    $count = $result[0]->count ?? $result[0]->total ?? 0;
                    // Check for COUNT(*) result without alias
                    if ($count === 0) {
                        foreach ($result[0] as $value) {
                            $count = (int) $value;
                            break;
                        }
                    }
                } else {
                    $count = 0;
                }

                // Cap the count at max results if applicable
                if ($maxResults > 0 && $count > $maxResults) {
                    $count = $maxResults;
                }

                // Cache the result
                Cache::put($cacheKey, $count, now()->addMinutes($cacheExpiry));

                return $count;
            } catch (\Exception $e) {
                return 0;
            }
        }

        // For regular SELECT queries, optimize for counting
        $countQuery = $query;

        // Remove ORDER BY clause (not needed for COUNT)
        $countQuery = preg_replace('/ORDER\s+BY\s+[^)]+$/is', '', $countQuery);

        // Remove GROUP BY if it's only grouping by r.id
        $countQuery = preg_replace('/GROUP\s+BY\s+r\.id\s*$/is', '', $countQuery);

        // Check if query has DISTINCT in SELECT
        $hasDistinct = preg_match('/SELECT\s+DISTINCT/is', $countQuery);

        // Replace SELECT clause with COUNT
        if ($hasDistinct || preg_match('/GROUP\s+BY/is', $countQuery)) {
            // For queries with DISTINCT or GROUP BY, count distinct r.id
            $countQuery = preg_replace(
                '/SELECT\s+.+?\s+FROM/is',
                'SELECT COUNT(DISTINCT r.id) as count FROM',
                $countQuery
            );
        } else {
            // For simple queries, use COUNT(*)
            $countQuery = preg_replace(
                '/SELECT\s+.+?\s+FROM/is',
                'SELECT COUNT(*) as count FROM',
                $countQuery
            );
        }

        // Remove LIMIT/OFFSET from the count query
        $countQuery = preg_replace('/LIMIT\s+\d+(\s+OFFSET\s+\d+)?$/is', '', $countQuery);

        try {
            // If max results is set and query might return too many results
            if ($maxResults > 0) {
                // First check if count would exceed max
                $testQuery = sprintf('SELECT 1 FROM (%s) as test LIMIT %d',
                    preg_replace('/SELECT\s+COUNT.+?\s+FROM/is', 'SELECT 1 FROM', $countQuery),
                    $maxResults + 1
                );

                $testResult = DB::select($testQuery);
                if (count($testResult) > $maxResults) {
                    Cache::put($cacheKey, $maxResults, now()->addMinutes($cacheExpiry));

                    return $maxResults;
                }
            }

            // Execute the count query
            $result = DB::select($countQuery);
            $count = isset($result[0]) ? (int) $result[0]->count : 0;

            // Cache the result
            Cache::put($cacheKey, $count, now()->addMinutes($cacheExpiry));

            return $count;
        } catch (\Exception $e) {
            // If optimization fails, try a simpler approach
            try {
                // Extract the core table and WHERE conditions
                if (preg_match('/FROM\s+releases\s+r\s+(.+?)(?:ORDER\s+BY|LIMIT|$)/is', $query, $matches)) {
                    $conditions = $matches[1];
                    // Remove JOINs but keep WHERE
                    $conditions = preg_replace('/(?:LEFT\s+|INNER\s+)?(?:OUTER\s+)?JOIN\s+.+?(?=WHERE|LEFT|INNER|JOIN|$)/is', '', $conditions);

                    $fallbackQuery = sprintf('SELECT COUNT(*) as count FROM releases r %s', trim($conditions));

                    if ($maxResults > 0) {
                        $fallbackQuery = sprintf('SELECT COUNT(*) as count FROM (SELECT 1 FROM releases r %s LIMIT %d) as limited',
                            trim($conditions),
                            $maxResults
                        );
                    }

                    $result = DB::select($fallbackQuery);
                    $count = isset($result[0]) ? (int) $result[0]->count : 0;

                    Cache::put($cacheKey, $count, now()->addMinutes($cacheExpiry));

                    return $count;
                }
            } catch (\Exception $fallbackException) {
                // Log the error for debugging
                \Illuminate\Support\Facades\Log::error('getPagerCount failed', [
                    'query' => $query,
                    'error' => $fallbackException->getMessage(),
                ]);
            }

            return 0;
        }
    }
}
