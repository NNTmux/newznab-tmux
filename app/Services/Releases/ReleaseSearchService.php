<?php

declare(strict_types=1);

namespace App\Services\Releases;

use App\Enums\SecondarySearchIndex;
use App\Facades\Search;
use App\Models\AnidbInfo;
use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use App\Models\UsenetGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for searching releases.
 */
class ReleaseSearchService
{
    private const CACHE_VERSION_KEY = 'releases:cache_version';

    private const SEARCH_INDEX_BUFFER_PAGES = 10;

    private const SEARCH_INDEX_MIN_CANDIDATES = 250;

    private const SEARCH_INDEX_MAX_CANDIDATES = 2000;

    // RAR/ZIP Password indicator.
    public const PASSWD_NONE = 0;

    public const PASSWD_RAR = 1;

    public function __construct() {}

    /**
     * Function for searching on the site (by subject, searchname or advanced).
     *
     * @param  array<int|string, mixed>  $cat  Category IDs (list or associative)
     * @param  array<string, mixed>  $excludedCats
     * @param  array<string, mixed>  $orderBy
     * @param  array<string, mixed>  $searchArr
     * @return array|Collection|mixed
     */
    public function search(
        array $searchArr,
        mixed $groupName,
        mixed $sizeFrom,
        mixed $sizeTo,
        mixed $daysNew,
        mixed $daysOld,
        int $offset = 0,
        int $limit = 1000,
        array|string $orderBy = '',
        int $maxAge = -1,
        array $excludedCats = [],
        string $type = 'basic',
        array $cat = [-1],
        int $minSize = 0
    ): mixed {
        if (config('app.debug')) {
            Log::debug('ReleaseSearchService::search called', [
                'searchArr' => $searchArr,
                'limit' => $limit,
            ]);
        }

        // Filter out -1 values and empty strings
        $searchFields = Arr::where($searchArr, static function ($value) {
            return $value !== -1 && $value !== '' && $value !== null;
        });

        if (empty($searchFields)) {
            return collect();
        }

        $orderBy = $this->getBrowseOrder($orderBy === '' ? 'posted_desc' : $orderBy);

        if (Search::isAvailable()) {
            $groupId = null;
            if ((int) $groupName !== -1) {
                $resolved = UsenetGroup::getIDByName((string) $groupName);
                if ($resolved) {
                    $groupId = (int) $resolved;
                }
            }

            $categoryIdsRaw = $type === 'basic'
                ? Category::getCategorySearch($cat, null, true)
                : (((int) ($cat[0] ?? -1) !== -1) ? [(int) $cat[0]] : null);

            $categoryIds = null;
            if (is_array($categoryIdsRaw)) {
                $categoryIds = array_values(array_filter(
                    array_map(static fn ($id): int => (int) $id, $categoryIdsRaw),
                    static fn (int $id): bool => $id > 0
                ));
            } elseif (is_int($categoryIdsRaw) || (is_string($categoryIdsRaw) && ctype_digit((string) $categoryIdsRaw))) {
                $categoryIds = [(int) $categoryIdsRaw];
            }

            [$sizeMinFromRange, $sizeMaxFromRange] = $this->resolveSizeRangeBounds($sizeFrom, $sizeTo);
            $minSizeCriteria = max((int) $minSize, $sizeMinFromRange);
            $maxSizeCriteria = $sizeMaxFromRange;
            [$minDateCriteria, $maxDateCriteria] = $this->resolvePostdateBounds($daysNew, $daysOld);

            $criteria = [
                'phrases' => $searchFields,
                'category_ids' => $categoryIds,
                'excluded_category_ids' => $excludedCats,
                'min_size' => $minSizeCriteria,
                'max_size' => $maxSizeCriteria,
                'max_age_days' => $maxAge,
                'min_date' => $minDateCriteria,
                'max_date' => $maxDateCriteria,
                'groups_id' => $groupId,
                'password_allow_rar' => str_contains($this->showPasswords(), '<='),
                'sort_field' => $this->browseOrderToIndexSortField((string) $orderBy[0]),
                'sort_dir' => $orderBy[1] ?? 'desc',
                'try_fuzzy' => true,
            ];

            $filtered = Search::searchReleasesFiltered($criteria, $limit, $offset);

            if ($filtered['ids'] !== []) {
                $ids = array_map(static fn (int|string $id): int => (int) $id, $filtered['ids']);
                $idList = implode(',', $ids);
                $baseSql = $this->buildSearchBaseSql(sprintf('WHERE r.id IN (%s)', $idList));
                $sql = sprintf('SELECT * FROM (%s) r ORDER BY FIELD(r.id, %s)', $baseSql, $idList);
                $cacheKey = md5($this->getCacheVersion().$sql);
                $cachedReleases = Cache::get($cacheKey);
                if ($cachedReleases !== null) {
                    return $cachedReleases;
                }

                $releases = Release::fromQuery($sql);
                if ($releases->isNotEmpty()) {
                    $releases[0]->_totalrows = (int) $filtered['total'];
                }

                $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
                Cache::put($cacheKey, $releases, $expiresAt);

                return $releases;
            }

            if (config('nntmux.mysql_search_fallback', false) !== true) {
                return collect();
            }
        }

        $searchLimit = $this->determineSearchCandidateLimit($offset, $limit);
        $searchResult = $this->performIndexSearch($searchFields, $searchLimit);

        if (config('app.debug')) {
            Log::debug('ReleaseSearchService::search after performIndexSearch', [
                'result_count' => count($searchResult),
            ]);
        }

        if (count($searchResult) === 0) {
            return collect();
        }

        // Build WHERE clause
        $whereSql = $this->buildSearchWhereClause(
            $searchResult,
            $groupName,
            $sizeFrom,
            $sizeTo,
            $daysNew,
            $daysOld,
            $maxAge,
            $excludedCats,
            $type,
            $cat,
            $minSize
        );

        // Build base SQL
        $baseSql = $this->buildSearchBaseSql($whereSql);

        // Build final SQL with pagination
        $sql = sprintf(
            'SELECT * FROM (%s) r ORDER BY r.%s %s LIMIT %d OFFSET %d',
            $baseSql,
            $orderBy[0], // @phpstan-ignore offsetAccess.notFound
            $orderBy[1], // @phpstan-ignore offsetAccess.notFound
            $limit,
            $offset
        );

        // Check cache
        $cacheKey = md5($this->getCacheVersion().$sql);
        $releases = Cache::get($cacheKey);
        if ($releases !== null) {
            return $releases;
        }

        // Execute query
        $releases = Release::fromQuery($sql);

        // Add total count for pagination
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount($baseSql);
        }

        // Cache results
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put($cacheKey, $releases, $expiresAt);

        return $releases;
    }

    /**
     * Search function for API.
     *
     * @param  array<int|string, mixed>  $cat  Category IDs (list or associative)
     * @param  array<string, mixed>  $excludedCats
     * @return Collection|mixed
     */
    public function apiSearch(mixed $searchName, mixed $groupName, int $offset = 0, int $limit = 1000, int $maxAge = -1, array $excludedCats = [], array $cat = [-1], int $minSize = 0, string $orderBy = 'posted_desc'): mixed
    {
        if (config('app.debug')) {
            Log::debug('ReleaseSearchService::apiSearch called', [
                'searchName' => $searchName,
                'groupName' => $groupName,
                'offset' => $offset,
                'limit' => $limit,
            ]);
        }

        $hasText = $searchName !== -1 && $searchName !== '' && $searchName !== null;
        [$orderField, $orderDir] = $this->getBrowseOrder($orderBy);

        if (Search::isAvailable()) {
            $groupId = null;
            if ((int) $groupName !== -1) {
                $resolved = UsenetGroup::getIDByName((string) $groupName);
                if ($resolved) {
                    $groupId = (int) $resolved;
                }
            }

            $categoryIdsRaw = Category::getCategorySearch($cat, null, true);
            $categoryIds = null;
            if (is_array($categoryIdsRaw)) {
                $categoryIds = array_map(static fn ($id): int => (int) $id, $categoryIdsRaw);
            } elseif (is_int($categoryIdsRaw) || (is_string($categoryIdsRaw) && ctype_digit((string) $categoryIdsRaw))) {
                $categoryIds = [(int) $categoryIdsRaw];
            }

            $criteria = [
                'phrases' => $hasText ? $searchName : null,
                'category_ids' => $categoryIds,
                'excluded_category_ids' => $excludedCats,
                'min_size' => $minSize,
                'max_age_days' => $maxAge,
                'groups_id' => $groupId,
                'password_allow_rar' => str_contains($this->showPasswords(), '<='),
                'sort_field' => $this->browseOrderToIndexSortField($orderField),
                'sort_dir' => $orderDir,
                'try_fuzzy' => true,
            ];

            $filtered = Search::searchReleasesFiltered($criteria, $limit, $offset);

            if ($filtered['ids'] === [] && $hasText && config('nntmux.mysql_search_fallback', false) === true) {
                return $this->apiSearchLegacyMysql($searchName, $groupName, $offset, $limit, $maxAge, $excludedCats, $cat, $minSize, $orderBy);
            }

            if ($filtered['ids'] === []) {
                return collect();
            }

            $ids = array_map(static fn (int|string $id): int => (int) $id, $filtered['ids']);
            $idList = implode(',', $ids);
            $whereSql = 'WHERE r.id IN ('.$idList.')';

            $sql = sprintf(
                "SELECT r.id, r.searchname, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate,
                    cp.title AS parent_category, c.title AS sub_category,
                    CONCAT(cp.title, ' > ', c.title) AS category_name,
                    g.name AS group_name,
                    m.imdbid, m.tmdbid, m.traktid,
                    v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.imdb, v.tmdb,
                    tve.firstaired, tve.title, tve.series, tve.episode
            FROM releases r
            INNER JOIN categories c ON c.id = r.categories_id
            INNER JOIN root_categories cp ON cp.id = c.root_categories_id
            LEFT JOIN usenet_groups g ON g.id = r.groups_id
            LEFT JOIN videos v ON r.videos_id = v.id AND r.videos_id > 0
            LEFT JOIN tv_episodes tve ON r.tv_episodes_id = tve.id AND r.tv_episodes_id > 0
            LEFT JOIN movieinfo m ON m.id = r.movieinfo_id AND r.movieinfo_id > 0
            %s
            ORDER BY r.%s %s",
                $whereSql,
                $orderField,
                $orderDir
            );

            $cacheKey = md5($this->getCacheVersion().$sql);
            $cachedReleases = Cache::get($cacheKey);
            if ($cachedReleases !== null) {
                return $cachedReleases;
            }

            $releases = Release::fromQuery($sql);

            if ($releases->isNotEmpty()) {
                $releases[0]->_totalrows = $filtered['total'];
            }

            $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
            Cache::put($cacheKey, $releases, $expiresAt);

            return $releases;
        }

        return $this->apiSearchLegacyMysql($searchName, $groupName, $offset, $limit, $maxAge, $excludedCats, $cat, $minSize, $orderBy);
    }

    /**
     * Original API search path (SQL filters + optional index ID list from fuzzy only).
     *
     * @param  array<int|string, mixed>  $cat
     */
    private function apiSearchLegacyMysql(mixed $searchName, mixed $groupName, int $offset, int $limit, int $maxAge, array $excludedCats, array $cat, int $minSize, string $orderBy = 'posted_desc'): mixed
    {
        [$orderField, $orderDir] = $this->getBrowseOrder($orderBy);
        $searchLimit = $this->determineSearchCandidateLimit($offset, $limit);

        $searchResult = [];
        $hasText = $searchName !== -1 && $searchName !== '' && $searchName !== null;
        if ($hasText) {
            $fuzzyResult = Search::searchReleasesWithFuzzy($searchName, $searchLimit);
            $searchResult = $fuzzyResult['ids'] ?? [];

            if (config('app.debug') && ($fuzzyResult['fuzzy'] ?? false)) {
                Log::debug('apiSearch: Using fuzzy search results');
            }

            if ($searchResult === [] && config('nntmux.mysql_search_fallback', false) === true) {
                if (config('app.debug')) {
                    Log::debug('apiSearch: Falling back to MySQL search');
                }
                $searchResult = $this->performMySQLSearch(['searchname' => $searchName], $searchLimit);
            }

            if ($searchResult === []) {
                if (config('app.debug')) {
                    Log::debug('apiSearch: No results from any search engine');
                }

                return collect();
            }
        }

        $conditions = [
            sprintf('r.passwordstatus %s', $this->showPasswords()),
        ];

        if ($maxAge > 0) {
            $conditions[] = sprintf('r.postdate > (NOW() - INTERVAL %d DAY)', $maxAge);
        }

        if ((int) $groupName !== -1) {
            $groupId = UsenetGroup::getIDByName($groupName);
            if ($groupId) {
                $conditions[] = sprintf('r.groups_id = %d', $groupId);
            }
        }

        $catQuery = Category::getCategorySearch($cat);
        $catQuery = preg_replace('/^(WHERE|AND)\s+/i', '', trim((string) $catQuery));
        if (! empty($catQuery) && $catQuery !== '1=1') {
            $conditions[] = $catQuery;
        }

        if ($excludedCats !== []) {
            $conditions[] = sprintf('r.categories_id NOT IN (%s)', implode(',', array_map(static fn ($id): int => (int) $id, $excludedCats)));
        }

        if ($searchResult !== []) {
            $conditions[] = sprintf('r.id IN (%s)', implode(',', array_map(static fn ($id): int => (int) $id, $searchResult)));
        }

        if ($minSize > 0) {
            $conditions[] = sprintf('r.size >= %d', $minSize);
        }

        $whereSql = 'WHERE '.implode(' AND ', $conditions);

        $sql = sprintf(
            "SELECT r.id, r.searchname, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate,
                    cp.title AS parent_category, c.title AS sub_category,
                    CONCAT(cp.title, ' > ', c.title) AS category_name,
                    g.name AS group_name,
                    m.imdbid, m.tmdbid, m.traktid,
                    v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.imdb, v.tmdb,
                    tve.firstaired, tve.title, tve.series, tve.episode
            FROM releases r
            INNER JOIN categories c ON c.id = r.categories_id
            INNER JOIN root_categories cp ON cp.id = c.root_categories_id
            LEFT JOIN usenet_groups g ON g.id = r.groups_id
            LEFT JOIN videos v ON r.videos_id = v.id AND r.videos_id > 0
            LEFT JOIN tv_episodes tve ON r.tv_episodes_id = tve.id AND r.tv_episodes_id > 0
            LEFT JOIN movieinfo m ON m.id = r.movieinfo_id AND r.movieinfo_id > 0
            %s
            ORDER BY r.%s %s
            LIMIT %d OFFSET %d",
            $whereSql,
            $orderField,
            $orderDir,
            $limit,
            $offset
        );

        $cacheKey = md5($this->getCacheVersion().$sql);
        $cachedReleases = Cache::get($cacheKey);
        if ($cachedReleases !== null) {
            return $cachedReleases;
        }

        $releases = Release::fromQuery($sql);

        if ($releases->isNotEmpty()) {
            $countSql = sprintf('SELECT COUNT(*) as count FROM releases r %s', $whereSql);
            $countResult = Release::fromQuery($countSql);
            $releases[0]->_totalrows = $countResult[0]->count ?? 0;
        }

        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put($cacheKey, $releases, $expiresAt);

        return $releases;
    }

    /**
     * API: music releases linked to rows from the music metadata index.
     *
     * @param  array<int|string, mixed>  $cat
     * @param  array<int, int>  $excludedCats
     */
    public function apiMusicSearch(
        string $q,
        mixed $groupName,
        int $offset,
        int $limit,
        int $maxAge,
        array $excludedCats,
        array $cat,
        int $minSize,
        string $orderBy = 'posted_desc'
    ): mixed {
        $q = trim($q);
        if ($q === '' || ! Search::isAvailable()) {
            return collect();
        }

        $musicInfoIds = Search::searchSecondary(SecondarySearchIndex::Music, $q, 2000)['id'];

        return $this->apiSearchByMetadataForeignKey($musicInfoIds, 'musicinfo_id', $groupName, $offset, $limit, $maxAge, $excludedCats, $cat, $minSize, $orderBy);
    }

    /**
     * API: book releases linked to rows from the books metadata index.
     *
     * @param  array<int|string, mixed>  $cat
     * @param  array<int, int>  $excludedCats
     */
    public function apiBookSearch(
        string $q,
        mixed $groupName,
        int $offset,
        int $limit,
        int $maxAge,
        array $excludedCats,
        array $cat,
        int $minSize,
        string $orderBy = 'posted_desc'
    ): mixed {
        $q = trim($q);
        if ($q === '' || ! Search::isAvailable()) {
            return collect();
        }

        $bookIds = Search::searchSecondary(SecondarySearchIndex::Books, $q, 2000)['id'];

        return $this->apiSearchByMetadataForeignKey($bookIds, 'bookinfo_id', $groupName, $offset, $limit, $maxAge, $excludedCats, $cat, $minSize, $orderBy);
    }

    /**
     * @param  list<int>  $metadataIds
     * @param  array<int|string, mixed>  $cat
     * @param  array<int, int>  $excludedCats
     */
    private function apiSearchByMetadataForeignKey(
        array $metadataIds,
        string $column,
        mixed $groupName,
        int $offset,
        int $limit,
        int $maxAge,
        array $excludedCats,
        array $cat,
        int $minSize,
        string $orderBy = 'posted_desc'
    ): mixed {
        [$orderField, $orderDir] = $this->getBrowseOrder($orderBy);
        if ($metadataIds === []) {
            return collect();
        }

        if (! in_array($column, ['musicinfo_id', 'bookinfo_id'], true)) {
            return collect();
        }

        $conditions = [
            sprintf('r.passwordstatus %s', $this->showPasswords()),
            sprintf('r.%s IN (%s)', $column, implode(',', array_map(static fn (int $id): int => $id, $metadataIds))),
        ];

        if ($maxAge > 0) {
            $conditions[] = sprintf('r.postdate > (NOW() - INTERVAL %d DAY)', $maxAge);
        }

        if ((int) $groupName !== -1) {
            $groupId = UsenetGroup::getIDByName($groupName);
            if ($groupId) {
                $conditions[] = sprintf('r.groups_id = %d', $groupId);
            }
        }

        $catQuery = Category::getCategorySearch($cat);
        $catQuery = preg_replace('/^(WHERE|AND)\s+/i', '', trim((string) $catQuery));
        if (! empty($catQuery) && $catQuery !== '1=1') {
            $conditions[] = $catQuery;
        }

        if ($excludedCats !== []) {
            $conditions[] = sprintf('r.categories_id NOT IN (%s)', implode(',', array_map(static fn ($id): int => (int) $id, $excludedCats)));
        }

        if ($minSize > 0) {
            $conditions[] = sprintf('r.size >= %d', $minSize);
        }

        $whereSql = 'WHERE '.implode(' AND ', $conditions);

        $sql = sprintf(
            "SELECT r.id, r.searchname, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate,
                    cp.title AS parent_category, c.title AS sub_category,
                    CONCAT(cp.title, ' > ', c.title) AS category_name,
                    g.name AS group_name,
                    m.imdbid, m.tmdbid, m.traktid,
                    v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.imdb, v.tmdb,
                    tve.firstaired, tve.title, tve.series, tve.episode
            FROM releases r
            INNER JOIN categories c ON c.id = r.categories_id
            INNER JOIN root_categories cp ON cp.id = c.root_categories_id
            LEFT JOIN usenet_groups g ON g.id = r.groups_id
            LEFT JOIN videos v ON r.videos_id = v.id AND r.videos_id > 0
            LEFT JOIN tv_episodes tve ON r.tv_episodes_id = tve.id AND r.tv_episodes_id > 0
            LEFT JOIN movieinfo m ON m.id = r.movieinfo_id AND r.movieinfo_id > 0
            %s
            ORDER BY r.%s %s
            LIMIT %d OFFSET %d",
            $whereSql,
            $orderField,
            $orderDir,
            $limit,
            $offset
        );

        $cacheKey = md5($this->getCacheVersion().$sql);
        $cachedReleases = Cache::get($cacheKey);
        if ($cachedReleases !== null) {
            return $cachedReleases;
        }

        $releases = Release::fromQuery($sql);

        if ($releases->isNotEmpty()) {
            $countSql = sprintf('SELECT COUNT(*) as count FROM releases r %s', $whereSql);
            $countResult = Release::fromQuery($countSql);
            $releases[0]->_totalrows = $countResult[0]->count ?? 0;
        }

        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put($cacheKey, $releases, $expiresAt);

        return $releases;
    }

    /**
     * Search for TV shows via API.
     *
     * @param  array<int|string, mixed>  $cat  Category IDs (list or associative)
     * @param  array<string, mixed>  $excludedCategories
     * @param  array<string, mixed>  $siteIdArr
     * @return array|Collection|\Illuminate\Support\Collection|mixed
     */
    public function tvSearch(array $siteIdArr = [], string $series = '', string $episode = '', string $airDate = '', int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, int $minSize = 0, array $excludedCategories = [], string $orderBy = 'posted_desc'): mixed
    {
        [$orderField, $orderDir] = $this->getBrowseOrder($orderBy);
        $hasStrictTvSelector = $this->hasTvLookupIdentifiers($siteIdArr);
        $shouldCache = ! (isset($siteIdArr['id']) && (int) $siteIdArr['id'] > 0);
        $rawCacheKey = md5(serialize(func_get_args()).'tvSearch');
        $cacheKey = null;
        $searchLimit = $this->determineSearchCandidateLimit($offset, $limit);
        if ($shouldCache) {
            $cacheKey = md5($this->getCacheVersion().'tvSearch:v2:'.$rawCacheKey);
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $conditions = [
            sprintf('r.passwordstatus %s', $this->showPasswords()),
        ];

        $videoJoinCondition = '';
        $episodeJoinCondition = '';
        $needsEpisodeJoin = false;
        $needsDatabaseLookup = false;

        // OPTIMIZATION: Try to find releases using search index external IDs first
        $externalIds = [];
        if (! empty($siteIdArr)) {
            foreach ($siteIdArr as $column => $id) {
                $hasValue = $column === 'imdb'
                    ? ($id !== null && $id !== '' && imdb_id_is_valid((string) $id))
                    : ($id > 0);

                if ($hasValue && $column !== 'id') {
                    // Map column names to search index field names
                    $fieldName = match ($column) {
                        'tvdb' => 'tvdb',
                        'trakt' => 'traktid', // Note: in releases index we use traktid
                        'tvmaze' => 'tvmaze',
                        'tvrage' => 'tvrage',
                        'imdb' => 'imdbid',
                        'tmdb' => 'tmdbid',
                        default => null,
                    };
                    if ($fieldName) {
                        $externalIds[$fieldName] = $column === 'imdb' ? (string) $id : (int) $id;
                    }
                }
            }
        }

        // Try to get releases directly from search index using external IDs
        $searchResult = [];
        if (! empty($externalIds)) {
            $searchResult = Search::searchReleasesByExternalId($externalIds, $searchLimit);

            if (config('app.debug') && ! empty($searchResult)) {
                Log::debug('tvSearch: Found releases via search index by external IDs', [
                    'externalIds' => $externalIds,
                    'count' => count($searchResult),
                ]);
            }
        }

        // If search index didn't return results, fall back to database lookup
        if (empty($searchResult) && $hasStrictTvSelector) {
            $siteConditions = [];
            foreach ($siteIdArr as $column => $id) {
                if ($id > 0) {
                    $siteConditions[] = sprintf('v.%s = %d', $column, (int) $id);
                }
            }

            if (! empty($siteConditions)) {
                $needsDatabaseLookup = true;
                $siteUsesVideoIdOnly = count($siteConditions) === 1 && isset($siteIdArr['id']) && (int) $siteIdArr['id'] > 0;

                $seriesFilter = ($series !== '') ? sprintf('AND tve.series = %d', (int) preg_replace('/^s0*/i', '', $series)) : '';
                $episodeFilter = ($episode !== '') ? sprintf('AND tve.episode = %d', (int) preg_replace('/^e0*/i', '', $episode)) : '';
                $airDateFilter = ($airDate !== '') ? sprintf('AND DATE(tve.firstaired) = %s', escapeString($airDate)) : '';

                $lookupSql = sprintf(
                    'SELECT v.id AS video_id, tve.id AS episode_id FROM videos v LEFT JOIN tv_episodes tve ON v.id = tve.videos_id WHERE (%s) %s %s %s',
                    implode(' OR ', $siteConditions),
                    $seriesFilter,
                    $episodeFilter,
                    $airDateFilter
                );

                $results = Release::fromQuery($lookupSql);

                if ($results->isEmpty()) {
                    $this->logStrictExternalLookupMiss('tvSearch database lookup', $siteIdArr, [
                        'series' => $series,
                        'episode' => $episode,
                        'airDate' => $airDate,
                    ]);

                    return collect();
                }

                $videoIds = $results->pluck('video_id')->filter()->unique()->toArray();
                $episodeIds = $results->pluck('episode_id')->filter()->unique()->toArray();

                if (! empty($videoIds)) {
                    $conditions[] = sprintf('r.videos_id IN (%s)', implode(',', array_map('intval', $videoIds)));
                    $videoJoinCondition = sprintf('AND v.id IN (%s)', implode(',', array_map('intval', $videoIds)));
                }

                if (! empty($episodeIds) && ! $siteUsesVideoIdOnly) {
                    $conditions[] = sprintf('r.tv_episodes_id IN (%s)', implode(',', array_map('intval', $episodeIds)));
                    $episodeJoinCondition = sprintf('AND tve.id IN (%s)', implode(',', array_map('intval', $episodeIds)));
                    $needsEpisodeJoin = true;
                }

                if ($siteUsesVideoIdOnly) {
                    $needsEpisodeJoin = false;
                }
            }
        }

        // If search index found releases via external IDs, add them to conditions
        $hasSearchResultFromExternalIds = ! empty($searchResult);
        if ($hasSearchResultFromExternalIds) {
            if (Search::isAvailable()) {
                $searchResult = $this->intersectReleaseIdsWithSearchFilters(
                    $searchResult,
                    $cat,
                    'tv',
                    $maxAge,
                    $minSize,
                    $excludedCategories
                );
                if ($searchResult === []) {
                    return collect();
                }
            }
            $conditions[] = sprintf('r.id IN (%s)', implode(',', array_map('intval', $searchResult)));

            $episodePredicates = $this->buildEpisodeJoinPredicates($series, $episode, $airDate);
            if ($episodePredicates !== []) {
                foreach ($episodePredicates as $predicate) {
                    $conditions[] = $predicate;
                }
                $needsEpisodeJoin = true;
            }
        }

        // Only do name-based search if we don't already have results from external IDs
        if (! $hasSearchResultFromExternalIds && ! $hasStrictTvSelector && ! empty($name)) {
            $searchName = $name;
            $hasValidSiteIds = false;
            foreach ($siteIdArr as $column => $id) {
                if ($id > 0) {
                    $hasValidSiteIds = true;
                    break;
                }
            }

            if (! $hasValidSiteIds) {
                // Build search name with season/episode for the full-text search
                if (! empty($series) && (int) $series < 1900) {
                    $searchName .= sprintf(' S%s', str_pad($series, 2, '0', STR_PAD_LEFT));
                    $seriesNum = (int) preg_replace('/^s0*/i', '', $series);
                    if (! empty($episode) && ! str_contains($episode, '/')) {
                        $searchName .= sprintf('E%s', str_pad($episode, 2, '0', STR_PAD_LEFT));
                        $episodeNum = (int) preg_replace('/^e0*/i', '', $episode);
                    }
                } elseif (! empty($airDate)) {
                    $searchName .= ' '.str_replace(['/', '-', '.', '_'], ' ', $airDate);
                }
            }

            $searchResult = Search::searchReleases(['searchname' => $searchName], $searchLimit);

            // Fall back to MySQL if search engine failed (only if enabled)
            if (empty($searchResult) && config('nntmux.mysql_search_fallback', false) === true) {
                $searchResult = $this->performMySQLSearch(['searchname' => $searchName], $searchLimit);
            }

            if (empty($searchResult)) {
                return collect();
            }

            if (Search::isAvailable()) {
                $searchResult = $this->intersectReleaseIdsWithSearchFilters(
                    $searchResult,
                    $cat,
                    'tv',
                    $maxAge,
                    $minSize,
                    $excludedCategories
                );
                if ($searchResult === []) {
                    return collect();
                }
            }

            $conditions[] = sprintf('r.id IN (%s)', implode(',', array_map('intval', $searchResult)));

            // Try to add episode conditions if season/episode data is provided and no valid site IDs
            // This will filter results to only those with matching episode data in tv_episodes table
            // If this results in no matches, we'll fall back to results without episode conditions
            if (! $hasValidSiteIds && (! empty($series) || ! empty($airDate))) {
                $episodeConditions = $this->buildEpisodeJoinPredicates($series, $episode, $airDate);

                if (! empty($episodeConditions)) {
                    // Check if any of the found releases have matching episode data
                    $checkSql = sprintf(
                        'SELECT r.id FROM releases r INNER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id WHERE r.id IN (%s) AND %s LIMIT 1',
                        implode(',', array_map('intval', $searchResult)),
                        implode(' AND ', $episodeConditions)
                    );
                    $hasEpisodeMatches = Release::fromQuery($checkSql);

                    if ($hasEpisodeMatches->isNotEmpty()) {
                        // Some releases have matching episode data, add the conditions
                        foreach ($episodeConditions as $cond) {
                            $conditions[] = $cond;
                        }
                        $needsEpisodeJoin = true;
                    }
                    // If no matches with episode data, don't add episode conditions
                    // The search will return results based on searchname match only
                }
            }
        }

        $applySqlCategorySizeExcluded = $needsDatabaseLookup || empty($searchResult) || ! Search::isAvailable();

        $catQuery = Category::getCategorySearch($cat, 'tv');
        $catQuery = preg_replace('/^(WHERE|AND)\s+/i', '', trim($catQuery));
        if ($applySqlCategorySizeExcluded) {
            if (! empty($catQuery) && $catQuery !== '1=1') {
                $conditions[] = $catQuery;
            }

            if ($maxAge > 0) {
                $conditions[] = sprintf('r.postdate > (NOW() - INTERVAL %d DAY)', $maxAge);
            }
            if ($minSize > 0) {
                $conditions[] = sprintf('r.size >= %d', $minSize);
            }
            if (! empty($excludedCategories)) {
                $conditions[] = sprintf('r.categories_id NOT IN (%s)', implode(',', array_map('intval', $excludedCategories)));
            }
        }

        $whereSql = 'WHERE '.implode(' AND ', $conditions);

        $joinType = $needsEpisodeJoin ? 'INNER' : 'LEFT';

        // Optimized select list – only fields required by XML (extended) and transformers
        $baseSql = sprintf(
            "SELECT r.id, r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id,
                    r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments,
                    r.adddate, r.videos_id, r.tv_episodes_id,
                    v.title, v.tvdb, v.trakt, v.imdb, v.tmdb, v.tvmaze, v.tvrage,
                    tve.series, tve.episode, tve.firstaired,
                    cp.title AS parent_category, c.title AS sub_category,
                    CONCAT(cp.title, ' > ', c.title) AS category_name,
                    g.name AS group_name
            FROM releases r
            INNER JOIN categories c ON c.id = r.categories_id
            INNER JOIN root_categories cp ON cp.id = c.root_categories_id
            LEFT JOIN videos v ON r.videos_id = v.id AND v.type = 0 %s
            %s JOIN tv_episodes tve ON r.tv_episodes_id = tve.id %s
            LEFT JOIN usenet_groups g ON g.id = r.groups_id
            %s",
            $videoJoinCondition,
            $joinType,
            $episodeJoinCondition,
            $whereSql
        );

        $limitClause = '';
        if ($limit > 0) {
            $limitClause = sprintf(' LIMIT %d OFFSET %d', $limit, $offset);
        }

        $sql = sprintf('%s ORDER BY r.%s %s%s', $baseSql, $orderField, $orderDir, $limitClause);
        $releases = Release::fromQuery($sql);

        if ($hasStrictTvSelector && $releases->isEmpty()) {
            $this->logStrictExternalLookupMiss('tvSearch release lookup', $siteIdArr, [
                'series' => $series,
                'episode' => $episode,
                'airDate' => $airDate,
            ]);
        }

        if ($releases->isNotEmpty()) {
            $countSql = sprintf(
                'SELECT COUNT(*) as count FROM releases r %s %s %s',
                (! empty($videoJoinCondition) ? 'LEFT JOIN videos v ON r.videos_id = v.id AND v.type = 0' : ''),
                ($needsEpisodeJoin ? sprintf('%s JOIN tv_episodes tve ON r.tv_episodes_id = tve.id %s', $joinType, $episodeJoinCondition) : ''),
                $whereSql
            );
            $countResult = Release::fromQuery($countSql);
            $releases[0]->_totalrows = $countResult[0]->count ?? 0;
        }

        if ($shouldCache && $cacheKey !== null) {
            $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
            Cache::put($cacheKey, $releases, $expiresAt);
        }

        return $releases;
    }

    /**
     * Search TV Shows via APIv2.
     *
     * @param  array<int|string, mixed>  $cat  Category IDs (list or associative)
     * @param  array<string, mixed>  $excludedCategories
     * @param  array<string, mixed>  $siteIdArr
     * @return Collection|mixed
     */
    public function apiTvSearch(array $siteIdArr = [], string $series = '', string $episode = '', string $airDate = '', int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, int $minSize = 0, array $excludedCategories = [], string $orderBy = 'posted_desc'): mixed
    {
        [$orderField, $orderDir] = $this->getBrowseOrder($orderBy);
        $searchLimit = $this->determineSearchCandidateLimit($offset, $limit);
        $hasStrictTvSelector = $this->hasTvLookupIdentifiers($siteIdArr);

        // OPTIMIZATION: Try to find releases using search index external IDs first
        $externalIds = [];
        foreach ($siteIdArr as $column => $Id) {
            $hasValue = $column === 'imdb'
                ? ($Id !== null && $Id !== '' && imdb_id_is_valid((string) $Id))
                : ($Id > 0);

            if ($hasValue && $column !== 'id') {
                $fieldName = match ($column) {
                    'tvdb' => 'tvdb',
                    'trakt' => 'traktid',
                    'tvmaze' => 'tvmaze',
                    'tvrage' => 'tvrage',
                    'imdb' => 'imdbid',
                    'tmdb' => 'tmdbid',
                    default => null,
                };
                if ($fieldName) {
                    $externalIds[$fieldName] = $column === 'imdb' ? (string) $Id : (int) $Id;
                }
            }
        }

        // Try to get releases directly from search index using external IDs
        $indexSearchResult = [];
        if (! empty($externalIds)) {
            $indexSearchResult = Search::searchReleasesByExternalId($externalIds, $searchLimit);

            if (config('app.debug') && ! empty($indexSearchResult)) {
                Log::debug('apiTvSearch: Found releases via search index by external IDs', [
                    'externalIds' => $externalIds,
                    'count' => count($indexSearchResult),
                ]);
            }
        }

        // Resolve show / episode filters from videos + tv_episodes when the index missed, or
        // when the index hit but the client narrowed by season/episode/airdate (index has no S/E).
        $siteSQL = $this->buildApiTvSiteSqlArray($siteIdArr);
        $showSql = '';
        if (\count($siteSQL) > 0) {
            $shouldLookupShow = empty($indexSearchResult)
                || $this->tvApiRequestTargetsEpisode($series, $episode, $airDate);
            if ($shouldLookupShow) {
                $strictEpisodeResolution = ! empty($indexSearchResult)
                    && $this->tvApiRequestTargetsEpisode($series, $episode, $airDate);
                $fragment = $this->lookupApiTvShowSqlFragment(
                    $siteSQL,
                    $series,
                    $episode,
                    $airDate,
                    $strictEpisodeResolution
                );
                if ($fragment === null) {
                    $this->logStrictExternalLookupMiss('apiTvSearch show lookup', $siteIdArr, [
                        'series' => $series,
                        'episode' => $episode,
                        'airDate' => $airDate,
                    ]);

                    return empty($indexSearchResult) ? [] : collect();
                }
                $showSql = $fragment;
            }
        }
        if (! $hasStrictTvSelector && ! empty($name) && $showSql === '' && empty($indexSearchResult)) {
            if (! empty($series) && (int) $series < 1900) {
                $name .= sprintf(' S%s', str_pad($series, 2, '0', STR_PAD_LEFT));
                if (! empty($episode) && ! str_contains($episode, '/')) {
                    $name .= sprintf('E%s', str_pad($episode, 2, '0', STR_PAD_LEFT));
                }
                if (empty($episode)) {
                    $name .= '*';
                }
            } elseif (! empty($airDate)) {
                $name .= sprintf(' %s', str_replace(['/', '-', '.', '_'], ' ', $airDate));
            }
        }
        $searchResult = $indexSearchResult; // Use index search result if we have it
        if (! $hasStrictTvSelector && empty($searchResult) && ! empty($name)) {
            $searchResult = Search::searchReleases(['searchname' => $name], $searchLimit);

            // Fall back to MySQL if search engine failed (only if enabled)
            if (empty($searchResult) && config('nntmux.mysql_search_fallback', false) === true) {
                $searchResult = $this->performMySQLSearch(['searchname' => $name], $searchLimit);
            }

            if (count($searchResult) === 0) {
                return collect();
            }
        }

        $skipSqlReleaseFilters = false;
        if ($searchResult !== [] && Search::isAvailable()) {
            $searchResult = $this->intersectReleaseIdsWithSearchFilters(
                $searchResult,
                $cat,
                'tv',
                $maxAge,
                $minSize,
                $excludedCategories
            );
            $skipSqlReleaseFilters = true;
            if ($searchResult === []) {
                return collect();
            }
        }

        $catSearchSql = $skipSqlReleaseFilters ? '' : Category::getCategorySearch($cat, 'tv');
        $whereSql = sprintf(
            'WHERE r.passwordstatus %s %s %s %s %s %s %s',
            $this->showPasswords(),
            $showSql,
            (! empty($searchResult) ? 'AND r.id IN ('.implode(',', array_map('intval', $searchResult)).')' : ''),
            $catSearchSql,
            ($skipSqlReleaseFilters || $maxAge <= 0 ? '' : sprintf('AND r.postdate > NOW() - INTERVAL %d DAY', $maxAge)),
            ($skipSqlReleaseFilters || $minSize <= 0 ? '' : sprintf('AND r.size >= %d', $minSize)),
            ($skipSqlReleaseFilters || empty($excludedCategories) ? '' : sprintf('AND r.categories_id NOT IN(%s)', implode(',', array_map('intval', $excludedCategories))))
        );
        $baseSql = sprintf(
            "SELECT r.searchname, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate,
                r.tv_episodes_id, v.title, v.tvdb, v.trakt, v.imdb, v.tmdb, v.tvmaze, v.tvrage,
                tve.series, tve.episode, tve.firstaired, cp.title AS parent_category, c.title AS sub_category,
                CONCAT(cp.title, ' > ', c.title) AS category_name, g.name AS group_name
            FROM releases r
            LEFT OUTER JOIN videos v ON r.videos_id = v.id AND v.type = 0
            LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
            LEFT JOIN categories c ON c.id = r.categories_id
            LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
            LEFT JOIN usenet_groups g ON g.id = r.groups_id
            %s",
            $whereSql
        );
        $sql = sprintf('%s ORDER BY r.%s %s LIMIT %d OFFSET %d', $baseSql, $orderField, $orderDir, $limit, $offset);
        $cacheKey = md5($this->getCacheVersion().'apiTvSearch:v2:'.$sql);
        $releases = Cache::get($cacheKey);
        if ($releases !== null) {
            return $releases;
        }
        $releases = Release::fromQuery($sql);
        if ($hasStrictTvSelector && $releases->isEmpty()) {
            $this->logStrictExternalLookupMiss('apiTvSearch release lookup', $siteIdArr, [
                'series' => $series,
                'episode' => $episode,
                'airDate' => $airDate,
            ]);
        }
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount(
                preg_replace('#LEFT(\s+OUTER)?\s+JOIN\s+(?!tv_episodes)\s+.*ON.*=.*\n#i', ' ', $baseSql)
            );
        }
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put($cacheKey, $releases, $expiresAt);

        return $releases;
    }

    /**
     * Search anime releases.
     *
     * @param  array<int|string, mixed>  $cat  Category IDs (list or associative)
     * @param  array<string, mixed>  $excludedCategories
     * @return Collection|mixed
     */
    public function animeSearch(mixed $aniDbID, int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, array $excludedCategories = [], int $anilistId = -1, string $orderBy = 'posted_desc'): mixed
    {
        [$orderField, $orderDir] = $this->getBrowseOrder($orderBy);
        if ($anilistId > 0) {
            $resolved = AnidbInfo::query()->where('anilist_id', $anilistId)->value('anidbid');
            if ($resolved !== null) {
                $aniDbID = (int) $resolved;
            } elseif ($name === '' && (int) $aniDbID <= -1) {
                // AniList id was the only selector but nothing in DB maps to it — do not fall through unfiltered.
                return collect();
            }
        }

        $searchLimit = $this->determineSearchCandidateLimit($offset, $limit);
        $searchResult = [];
        $anidbIdFilter = '';
        if ($name !== '') {
            if (Search::isAvailable()) {
                $anidbIds = Search::searchAnimeTitle($name, $searchLimit);
                if ($anidbIds === []) {
                    return collect();
                }
                $anidbIdFilter = 'AND r.anidbid IN ('.implode(',', array_map('intval', $anidbIds)).')';
            } else {
                $fuzzyResult = Search::searchReleasesWithFuzzy($name, $searchLimit);
                $searchResult = $fuzzyResult['ids'] ?? [];

                if (empty($searchResult) && config('nntmux.mysql_search_fallback', false) === true) {
                    $searchResult = $this->performMySQLSearch(['searchname' => $name], $searchLimit);
                }

                if (count($searchResult) === 0) {
                    return collect();
                }
            }
        }

        $whereSql = sprintf(
            'WHERE r.passwordstatus %s
			%s %s %s %s %s %s',
            $this->showPasswords(),
            ($aniDbID > -1 ? sprintf(' AND r.anidbid = %d ', $aniDbID) : ''),
            $anidbIdFilter,
            (! empty($searchResult) ? 'AND r.id IN ('.implode(',', $searchResult).')' : ''),
            ! empty($excludedCategories) ? sprintf('AND r.categories_id NOT IN('.implode(',', $excludedCategories).')') : '',
            Category::getCategorySearch($cat, 'anime'),
            ($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : '')
        );
        $baseSql = sprintf(
            "SELECT r.id, r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.haspreview, r.jpgstatus,  cp.title AS parent_category, c.title AS sub_category,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				g.name AS group_name,
				rn.releases_id AS nfoid
			FROM releases r
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
			LEFT JOIN usenet_groups g ON g.id = r.groups_id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			%s",
            $whereSql
        );
        $sql = sprintf(
            '%s
			ORDER BY %s %s
			LIMIT %d OFFSET %d',
            $baseSql,
            $orderField,
            $orderDir,
            $limit,
            $offset
        );
        $cacheKey = md5($this->getCacheVersion().$sql);
        $releases = Cache::get($cacheKey);
        if ($releases !== null) {
            return $releases;
        }
        $releases = Release::fromQuery($sql);
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount($baseSql);
        }
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put($cacheKey, $releases, $expiresAt);

        return $releases;
    }

    /**
     * Movies search through API and site.
     *
     * @param  array<int|string, mixed>  $cat  Category IDs (list or associative)
     * @param  array<string, mixed>  $excludedCategories
     * @return Collection|mixed
     */
    public function moviesSearch(string $imDbId = '', int $tmDbId = -1, int $traktId = -1, int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, int $minSize = 0, array $excludedCategories = [], string $orderBy = 'posted_desc'): mixed
    {
        [$orderField, $orderDir] = $this->getBrowseOrder($orderBy);
        $searchLimit = $this->determineSearchCandidateLimit($offset, $limit);
        $searchResult = [];

        // OPTIMIZATION: If we have external IDs, use the search index to find releases directly
        // This avoids expensive database JOINs by using indexed external ID fields in releases_rt
        $externalIds = [];
        if ($imDbId !== '' && imdb_id_is_valid($imDbId)) {
            $externalIds['imdbid'] = $imDbId;
        }
        if ($tmDbId !== -1 && $tmDbId > 0) {
            $externalIds['tmdbid'] = $tmDbId;
        }
        if ($traktId !== -1 && $traktId > 0) {
            $externalIds['traktid'] = $traktId;
        }
        $hasExternalIds = $externalIds !== [];

        // Use search index for external ID lookups (much faster than database JOINs)
        if ($hasExternalIds) {
            $searchResult = Search::searchReleasesByExternalId($externalIds, $searchLimit);

            if (config('app.debug') && ! empty($searchResult)) {
                Log::debug('moviesSearch: Found releases via search index by external IDs', [
                    'externalIds' => $externalIds,
                    'count' => count($searchResult),
                ]);
            }
        }

        // Only perform name searches when the request does not already target a specific external ID.
        if (! $hasExternalIds && ! empty($name)) {
            $searchResult = Search::searchReleases(['searchname' => $name], $searchLimit);

            // Fall back to MySQL if search engine returned no results (only if enabled)
            if (empty($searchResult) && config('nntmux.mysql_search_fallback', false) === true) {
                $searchResult = $this->performMySQLSearch(['searchname' => $name], $searchLimit);
            }

            // Only return empty if we were specifically searching by name but found nothing
            if (empty($searchResult)) {
                return collect();
            }
        }

        if (! empty($searchResult) && Search::isAvailable()) {
            $searchResult = $this->intersectReleaseIdsWithSearchFilters(
                $searchResult,
                $cat,
                'movies',
                $maxAge,
                $minSize,
                $excludedCategories
            );
            if ($searchResult === []) {
                return collect();
            }
        }

        // Build the base conditions for movie search
        // Note: we don't have MOVIE_ROOT constant that marks a parent category,
        // so we'll rely on the category search logic instead
        $conditions = [
            sprintf('r.passwordstatus %s', $this->showPasswords()),
        ];

        if (! empty($searchResult)) {
            $conditions[] = sprintf('r.id IN (%s)', implode(',', array_map('intval', $searchResult)));
        }

        // When we have external IDs but no index results, fall back to database query
        // This handles the case where the index might be empty/out of sync
        $needsMovieJoin = false;
        if (empty($searchResult) && ! empty($externalIds)) {
            $needsMovieJoin = true;
            if ($imDbId !== '' && imdb_id_is_valid($imDbId)) {
                $conditions[] = sprintf('r.imdbid = %s', escapeString($imDbId));
            }
            if ($tmDbId !== -1 && $tmDbId > 0) {
                $conditions[] = sprintf('m.tmdbid = %d', $tmDbId);
            }
            if ($traktId !== -1 && $traktId > 0) {
                $conditions[] = sprintf('m.traktid = %d', $traktId);
            }
        }

        $applySqlMovieFilters = $searchResult === [] || ! Search::isAvailable();

        if ($applySqlMovieFilters) {
            if (! empty($excludedCategories)) {
                $conditions[] = sprintf('r.categories_id NOT IN (%s)', implode(',', array_map('intval', $excludedCategories)));
            }

            $catQuery = Category::getCategorySearch($cat, 'movies');
            $catQuery = preg_replace('/^(WHERE|AND)\s+/i', '', trim($catQuery));
            if (! empty($catQuery) && $catQuery !== '1=1') {
                $conditions[] = $catQuery;
            }
            if ($maxAge > 0) {
                $conditions[] = sprintf('r.postdate > (NOW() - INTERVAL %d DAY)', $maxAge);
            }
            if ($minSize > 0) {
                $conditions[] = sprintf('r.size >= %d', $minSize);
            }
        }

        $whereSql = 'WHERE '.implode(' AND ', $conditions);

        // Only join movieinfo if we need to filter by tmdbid/traktid (database fallback)
        // When using search index, we already have the release IDs and don't need the join
        $joinSql = $needsMovieJoin ? 'INNER JOIN movieinfo m ON m.imdbid = r.imdbid' : 'LEFT JOIN movieinfo m ON m.id = r.movieinfo_id';

        // Select only fields required by XML/API transformers
        $baseSql = sprintf(
            "SELECT r.id, r.searchname, r.guid, r.postdate, r.categories_id,
                    r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments,
                    r.adddate,
                    %s
                    cp.title AS parent_category, c.title AS sub_category,
                    CONCAT(cp.title, ' > ', c.title) AS category_name,
                    g.name AS group_name
             FROM releases r
             INNER JOIN categories c ON c.id = r.categories_id
             INNER JOIN root_categories cp ON cp.id = c.root_categories_id
             %s
             LEFT JOIN usenet_groups g ON g.id = r.groups_id
             %s",
            'm.imdbid, m.tmdbid, m.traktid,',
            $joinSql,
            $whereSql
        );

        $sql = sprintf('%s ORDER BY r.%s %s LIMIT %d OFFSET %d', $baseSql, $orderField, $orderDir, $limit, $offset);
        $cacheKey = md5('moviesSearch:v2:'.$sql.serialize(func_get_args()));
        if (($releases = Cache::get($cacheKey)) !== null) {
            return $releases;
        }

        $releases = Release::fromQuery($sql);

        if ($hasExternalIds && $releases->isEmpty()) {
            $this->logStrictExternalLookupMiss('moviesSearch release lookup', $externalIds, [
                'name' => $name,
            ]);
        }

        if ($releases->isNotEmpty()) {
            // Optimize: Execute count query using same WHERE clause
            $countSql = sprintf(
                'SELECT COUNT(*) as count FROM releases r %s %s',
                $needsMovieJoin ? $joinSql : '',
                $whereSql
            );
            $countResult = DB::selectOne($countSql);
            $releases[0]->_totalrows = $countResult->count ?? 0;
        }

        Cache::put($cacheKey, $releases, now()->addMinutes(config('nntmux.cache_expiry_medium')));

        return $releases;
    }

    /**
     * @param  array<string, mixed>  $excludedCats
     * @return array<string, mixed>
     */
    public function searchSimilar(mixed $currentID, mixed $name, array $excludedCats = []): bool|array
    {
        // Get the category for the parent of this release.
        $ret = false;
        $currRow = Release::getCatByRelId($currentID);
        if ($currRow !== null) {
            $catRow = Category::find($currRow['categories_id']);
            $parentCat = $catRow !== null ? $catRow['root_categories_id'] : null;

            if ($parentCat === null) {
                return $ret;
            }

            $results = $this->search(['searchname' => getSimilarName($name)], -1, '', '', -1, -1, 0, (int) config('nntmux.items_per_page'), '', -1, $excludedCats, 'basic', [$parentCat]);
            if (! $results) {
                return $ret;
            }

            $ret = [];
            foreach ($results as $res) {
                if ($res['id'] !== $currentID && $res['categoryparentid'] === $parentCat) {
                    $ret[] = $res;
                }
            }
        }

        return $ret;
    }

    /**
     * Perform index search using Elasticsearch or Manticore, with MySQL fallback
     *
     * @param  array<string, mixed>  $searchArr
     * @return array<string, mixed>
     */
    private function performIndexSearch(array $searchArr, int $limit): array
    {
        // Filter out -1 values and empty strings
        $searchFields = Arr::where($searchArr, static function ($value) {
            return $value !== -1 && $value !== '' && $value !== null;
        });

        if (empty($searchFields)) {
            if (config('app.debug')) {
                Log::debug('performIndexSearch: searchFields is empty after filtering', [
                    'original' => $searchArr,
                ]);
            }

            return [];
        }

        if (config('app.debug')) {
            Log::debug('performIndexSearch: starting search', [
                'search_driver' => config('search.default'),
                'searchFields' => $searchFields,
                'limit' => $limit,
            ]);
        }

        // Use the unified Search facade with fuzzy fallback
        // This will try exact search first, then fuzzy if no results
        $searchResult = Search::searchReleasesWithFuzzy($searchFields, $limit);
        $result = $searchResult['ids'] ?? [];

        if (config('app.debug')) {
            Log::debug('performIndexSearch: Search result', [
                'count' => count($result),
                'fuzzy_used' => $searchResult['fuzzy'] ?? false,
            ]);
        }

        // If search returned results, use them
        if (! empty($result)) {
            return $result;
        }

        // Fallback to MySQL LIKE search when search engine is unavailable (only if enabled)
        if (config('nntmux.mysql_search_fallback', false) === true) {
            if (config('app.debug')) {
                Log::debug('performIndexSearch: Falling back to MySQL search');
            }

            return $this->performMySQLSearch($searchFields, $limit);
        }

        return [];
    }

    /**
     * SQL predicates on tv_episodes (alias tve) for season / episode / airdate, matching
     * newznab-style tvsearch parameters. Empty array means no narrowing.
     *
     * @return list<string>
     */
    private function buildEpisodeJoinPredicates(string $series, string $episode, string $airDate): array
    {
        $predicates = [];
        if (! empty($series) && (int) $series < 1900) {
            $seriesNum = (int) preg_replace('/^s0*/i', '', $series);
            $predicates[] = sprintf('tve.series = %d', $seriesNum);
            if (! empty($episode) && ! str_contains($episode, '/')) {
                $episodeNum = (int) preg_replace('/^e0*/i', '', $episode);
                $predicates[] = sprintf('tve.episode = %d', $episodeNum);
            }
        } elseif (! empty($airDate)) {
            $predicates[] = sprintf('DATE(tve.firstaired) = %s', escapeString($airDate));
        }

        return $predicates;
    }

    /**
     * @param  array<string, mixed>  $siteIdArr
     * @return list<string>
     */
    private function buildApiTvSiteSqlArray(array $siteIdArr): array
    {
        $siteSQL = [];
        foreach ($siteIdArr as $column => $Id) {
            if ($Id > 0) {
                $siteSQL[] = sprintf('v.%s = %d', $column, $Id);
            }
        }

        return $siteSQL;
    }

    private function tvApiRequestTargetsEpisode(string $series, string $episode, string $airDate): bool
    {
        if ($airDate !== '') {
            return true;
        }
        if ($series !== '' && (int) $series < 1900) {
            return true;
        }
        if ($episode !== '' && ! str_contains($episode, '/')) {
            return true;
        }

        return false;
    }

    /**
     * Build the extra WHERE fragment for API TV search (r.tv_episodes_id / r.videos_id / tve.series).
     *
     * @param  list<string>  $siteSQL
     */
    private function lookupApiTvShowSqlFragment(
        array $siteSQL,
        string $series,
        string $episode,
        string $airDate,
        bool $strictEpisodeResolution
    ): ?string {
        $showQry = sprintf(
            "\n\t\t\t\tSELECT v.id AS video, GROUP_CONCAT(tve.id SEPARATOR ',') AS episodes FROM videos v LEFT JOIN tv_episodes tve ON v.id = tve.videos_id WHERE (%s) %s %s %s GROUP BY v.id LIMIT 1",
            implode(' OR ', $siteSQL),
            ($series !== '' ? sprintf('AND tve.series = %d', (int) preg_replace('/^s0*/i', '', $series)) : ''),
            ($episode !== '' ? sprintf('AND tve.episode = %d', (int) preg_replace('/^e0*/i', '', $episode)) : ''),
            ($airDate !== '' ? sprintf('AND DATE(tve.firstaired) = %s', escapeString($airDate)) : '')
        );
        $show = Release::fromQuery($showQry);
        if ($show->isEmpty()) {
            return null;
        }

        $showSql = '';
        if ((! empty($episode) && ! empty($series)) && $show[0]->episodes !== '') {
            $showSql .= ' AND r.tv_episodes_id IN ('.$show[0]->episodes.') AND tve.series = '.$series;
        } elseif (! empty($episode) && $show[0]->episodes !== '') {
            $showSql = sprintf('AND r.tv_episodes_id IN (%s)', $show[0]->episodes);
        } elseif (! empty($series) && empty($episode)) {
            $showSql .= ' AND r.tv_episodes_id IN ('.$show[0]->episodes.') AND tve.series = '.$series;
        }
        if ((int) ($show[0]->video ?? 0) > 0) {
            $showSql .= ' AND r.videos_id = '.$show[0]->video;
        }

        if ($strictEpisodeResolution
            && $this->tvApiRequestTargetsEpisode($series, $episode, $airDate)
            && ($show[0]->episodes === '' || $show[0]->episodes === null)) {
            return null;
        }

        return $showSql;
    }

    /**
     * Search-backed pages still apply SQL filters and ordering after the index lookup,
     * so fetch a buffered candidate set without handing MySQL thousands of IDs.
     */
    private function determineSearchCandidateLimit(int $offset, int $limit): int
    {
        $pageSize = max(1, $limit);
        $requestedRows = max($pageSize, $offset + $pageSize);
        $bufferedRows = max(
            $requestedRows,
            $offset + ($pageSize * self::SEARCH_INDEX_BUFFER_PAGES),
            self::SEARCH_INDEX_MIN_CANDIDATES
        );

        return min($bufferedRows, self::SEARCH_INDEX_MAX_CANDIDATES);
    }

    /**
     * Narrow release IDs using the search index (category, age, size, password).
     *
     * @param  array<int|string, mixed>  $cat
     * @param  array<int|string, mixed>  $excludedCategories
     * @return list<int>
     */
    private function intersectReleaseIdsWithSearchFilters(
        array $releaseIds,
        array $cat,
        ?string $categorySearchType,
        int $maxAge,
        int $minSize,
        array $excludedCategories
    ): array {
        if ($releaseIds === [] || ! Search::isAvailable()) {
            return array_values(array_unique(array_map(static fn ($id): int => (int) $id, $releaseIds)));
        }

        $unique = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $releaseIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($unique === []) {
            return [];
        }

        $categoryIdsRaw = Category::getCategorySearch($cat, $categorySearchType, true);
        $categoryIds = null;
        if (is_array($categoryIdsRaw)) {
            $categoryIds = array_map(static fn ($id): int => (int) $id, $categoryIdsRaw);
        } elseif (is_int($categoryIdsRaw) || (is_string($categoryIdsRaw) && ctype_digit((string) $categoryIdsRaw))) {
            $categoryIds = [(int) $categoryIdsRaw];
        }

        $maxMatches = (int) config('search.drivers.manticore.max_matches', 10000);
        $intersectLimit = min(max(count($unique), 1), max(1000, $maxMatches));

        $filtered = Search::searchReleasesFiltered([
            'phrases' => null,
            'release_ids' => $unique,
            'category_ids' => $categoryIds,
            'excluded_category_ids' => array_map(static fn ($id): int => (int) $id, $excludedCategories),
            'min_size' => $minSize,
            'max_age_days' => $maxAge,
            'password_allow_rar' => str_contains($this->showPasswords(), '<='),
            'sort_field' => 'postdate_ts',
            'sort_dir' => 'desc',
            'try_fuzzy' => false,
        ], $intersectLimit, 0);

        return $filtered['ids'];
    }

    /**
     * Fallback MySQL search when full-text search engines are unavailable
     *
     * @param  array<string, mixed>  $searchFields
     * @return array<string, mixed>
     */
    private function performMySQLSearch(array $searchFields, int $limit): array
    {
        try {
            $query = Release::query()->select('id');

            foreach ($searchFields as $field => $value) {
                if (! empty($value)) {
                    // Split search terms and search for each
                    $terms = preg_split('/\s+/', trim($value));
                    foreach ($terms as $term) {
                        $term = trim($term);
                        if (strlen($term) >= 2) {
                            $query->where($field, 'LIKE', '%'.$term.'%');
                        }
                    }
                }
            }

            $results = $query->limit($limit)->pluck('id')->toArray();

            if (config('app.debug')) {
                Log::debug('performMySQLSearch: MySQL fallback result count', ['count' => count($results)]);
            }

            return $results;
        } catch (\Throwable $e) {
            Log::error('performMySQLSearch: MySQL fallback failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $siteIdArr
     */
    private function hasTvLookupIdentifiers(array $siteIdArr): bool
    {
        foreach ($siteIdArr as $column => $id) {
            if ($column === 'imdb' && imdb_id_is_valid((string) $id)) {
                return true;
            }

            if ((int) $id > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $identifiers
     * @param  array<string, mixed>  $context
     */
    private function logStrictExternalLookupMiss(string $operation, array $identifiers, array $context = []): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::debug($operation.' returned no releases', array_merge([
            'identifiers' => $identifiers,
        ], $context));
    }

    /**
     * Build WHERE clause for search query
     *
     * @param  array<int|string, mixed>  $cat  Category IDs (list or associative)
     * @param  array<string, mixed>  $excludedCats
     * @param  array<string, mixed>  $searchResult
     */
    private function buildSearchWhereClause(
        array $searchResult,
        mixed $groupName,
        mixed $sizeFrom,
        mixed $sizeTo,
        mixed $daysNew,
        mixed $daysOld,
        int $maxAge,
        array $excludedCats,
        string $type,
        array $cat,
        int $minSize
    ): string {
        $conditions = [
            sprintf('r.passwordstatus %s', $this->showPasswords()),
            sprintf('r.id IN (%s)', implode(',', array_map('intval', $searchResult))),
        ];

        // Add optional conditions
        if ($maxAge > 0) {
            $conditions[] = sprintf('r.postdate > (NOW() - INTERVAL %d DAY)', $maxAge);
        }

        if ((int) $groupName !== -1) {
            $groupId = UsenetGroup::getIDByName($groupName);
            if ($groupId) {
                $conditions[] = sprintf('r.groups_id = %d', $groupId);
            }
        }

        // Size conditions
        $sizeConditions = $this->buildSizeConditions($sizeFrom, $sizeTo);
        if (! empty($sizeConditions)) {
            $conditions = array_merge($conditions, $sizeConditions);
        }

        if ($minSize > 0) {
            $conditions[] = sprintf('r.size >= %d', $minSize);
        }

        // Category conditions - only add if not empty
        $catQuery = $this->buildCategoryCondition($type, $cat);
        if (! empty($catQuery) && $catQuery !== '1=1') {
            $conditions[] = $catQuery;
        }

        // Date conditions
        if ((int) $daysNew !== -1) {
            $conditions[] = sprintf('r.postdate < (NOW() - INTERVAL %d DAY)', $daysNew);
        }

        if ((int) $daysOld !== -1) {
            $conditions[] = sprintf('r.postdate > (NOW() - INTERVAL %d DAY)', $daysOld);
        }

        // Excluded categories
        if (! empty($excludedCats)) {
            $excludedCatsClean = array_map('intval', $excludedCats);
            $conditions[] = sprintf('r.categories_id NOT IN (%s)', implode(',', $excludedCatsClean));
        }

        return 'WHERE '.implode(' AND ', $conditions);
    }

    /**
     * Build size conditions for WHERE clause
     *
     * @return array<string, mixed>
     */
    private function buildSizeConditions(mixed $sizeFrom, mixed $sizeTo): array
    {
        $conditions = [];
        [$sizeMin, $sizeMax] = $this->resolveSizeRangeBounds($sizeFrom, $sizeTo);
        if ($sizeMin > 0) {
            $conditions[] = sprintf('r.size > %d', $sizeMin);
        }
        if ($sizeMax > 0) {
            $conditions[] = sprintf('r.size < %d', $sizeMax);
        }

        return $conditions;
    }

    /**
     * Resolve UI size bucket inputs into byte bounds used by SQL/index filters.
     *
     * @return array{0:int,1:int}
     */
    private function resolveSizeRangeBounds(mixed $sizeFrom, mixed $sizeTo): array
    {
        $sizeRange = [
            1 => 1,
            2 => 2.5,
            3 => 5,
            4 => 10,
            5 => 20,
            6 => 30,
            7 => 40,
            8 => 80,
            9 => 160,
            10 => 320,
            11 => 640,
        ];

        $sizeMin = 0;
        $sizeMax = 0;
        if (array_key_exists($sizeFrom, $sizeRange)) {
            $sizeMin = 104857600 * (int) $sizeRange[$sizeFrom];
        }
        if (array_key_exists($sizeTo, $sizeRange)) {
            $sizeMax = 104857600 * (int) $sizeRange[$sizeTo];
        }

        return [$sizeMin, $sizeMax];
    }

    /**
     * Resolve day-based age inputs to unix timestamps used by indexed filtering.
     *
     * @return array{0:int,1:int}
     */
    private function resolvePostdateBounds(mixed $daysNew, mixed $daysOld): array
    {
        $minDate = 0;
        $maxDate = 0;

        if ((int) $daysOld !== -1 && (int) $daysOld >= 0) {
            $minDate = time() - ((int) $daysOld * 86400);
        }
        if ((int) $daysNew !== -1 && (int) $daysNew >= 0) {
            $maxDate = time() - ((int) $daysNew * 86400);
        }

        return [$minDate, $maxDate];
    }

    /**
     * Build category condition based on search type
     *
     * @param  array<int|string, mixed>  $cat  Category IDs (list or associative)
     */
    private function buildCategoryCondition(string $type, array $cat): string
    {
        if ($type === 'basic') {
            $catSearch = Category::getCategorySearch($cat);
            // Remove WHERE and AND from the beginning as we're building it into a larger WHERE clause
            $catSearch = preg_replace('/^(WHERE|AND)\s+/i', '', trim($catSearch));

            // Don't return '1=1' as it's not needed
            return ($catSearch === '1=1') ? '' : $catSearch;
        }

        if ($type === 'advanced' && (int) $cat[0] !== -1) {
            return sprintf('r.categories_id = %d', (int) $cat[0]);
        }

        return '';
    }

    /**
     * Build base SQL for web search query.
     * Selects only columns needed by search/index.blade.php and browse/index.blade.php views.
     */
    private function buildSearchBaseSql(string $whereSql): string
    {
        return sprintf(
            "SELECT r.id, r.searchname, r.guid, r.postdate, r.categories_id, r.size,
                    r.totalpart, r.fromname, r.grabs, r.comments, r.adddate,
                    r.videos_id, r.haspreview, r.jpgstatus, r.nfostatus,
                    CONCAT(cp.title, ' > ', c.title) AS category_name,
                    df.failed AS failed_count,
                    rr.report_count AS report_count,
                    rr.report_reasons AS report_reasons,
                    g.name AS group_name,
                    rn.releases_id AS nfoid,
                    re.releases_id AS reid,
                    m.imdbid
            FROM releases r
            LEFT OUTER JOIN video_data re ON re.releases_id = r.id
            LEFT OUTER JOIN movieinfo m ON m.id = r.movieinfo_id
            LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
            LEFT JOIN usenet_groups g ON g.id = r.groups_id
            LEFT JOIN categories c ON c.id = r.categories_id
            LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
            LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
            LEFT OUTER JOIN (SELECT releases_id, COUNT(*) AS report_count, GROUP_CONCAT(DISTINCT reason SEPARATOR ', ') AS report_reasons FROM release_reports WHERE status IN ('pending', 'reviewed', 'resolved') GROUP BY releases_id) rr ON rr.releases_id = r.id
            %s",
            $whereSql
        );
    }

    /**
     * Build the lightweight paged ID query used by compatibility tests and targeted callers.
     *
     * @param  array{0: string, 1: string}  $orderBy
     */
    protected function buildSearchPageIdsSql(string $whereSql, array $orderBy, int $limit, int $offset): string
    {
        return sprintf(
            'SELECT r.id FROM releases r %s ORDER BY r.%s %s LIMIT %d OFFSET %d',
            $whereSql,
            $orderBy[0],
            $orderBy[1],
            $limit,
            $offset,
        );
    }

    /**
     * Get a lightweight releases count directly from the releases table for compatibility callers.
     */
    protected function getReleasesCountForWhere(string $whereSql): int
    {
        return $this->getPagerCount(sprintf('SELECT COUNT(*) as count FROM releases r %s', $whereSql));
    }

    /**
     * Get the passworded releases clause.
     */
    public function showPasswords(): string
    {
        $show = (int) Settings::settingValue('showpasswordedrelease');
        $setting = $show;

        return match ($setting) {
            1 => '<= '.self::PASSWD_RAR,
            default => '= '.self::PASSWD_NONE,
        };
    }

    /**
     * Use to order releases on site.
     *
     * @param  array<string, mixed>  $orderBy
     * @return array<string, mixed>
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

        return [$orderField, isset($orderArr[1]) && preg_match('/^(asc|desc)$/i', $orderArr[1]) ? $orderArr[1] : 'desc']; // @phpstan-ignore return.type
    }

    private function browseOrderToIndexSortField(string $orderField): string
    {
        return match ($orderField) {
            'postdate' => 'postdate_ts',
            'adddate' => 'adddate_ts',
            'categories_id' => 'categories_id',
            'searchname' => 'searchname',
            'size' => 'size',
            'totalpart' => 'totalpart',
            'grabs' => 'grabs',
            default => 'postdate_ts',
        };
    }

    private function getCacheVersion(): int
    {
        return (int) Cache::get(self::CACHE_VERSION_KEY, 1);
    }

    /**
     * Get the count of releases for pager.
     *
     * @param  string  $query  The query to get the count from.
     */
    protected function getPagerCount(string $query): int
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

                // Cap the count at max results to prevent extremely large pagers
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
            // Execute the count query directly
            $result = DB::select($countQuery);
            $count = isset($result[0]) ? (int) $result[0]->count : 0;

            // Cap the count at max results to prevent extremely large pagers
            if ($maxResults > 0 && $count > $maxResults) {
                $count = $maxResults;
            }

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

                    $result = DB::select($fallbackQuery);
                    $count = isset($result[0]) ? (int) $result[0]->count : 0;

                    // Cap the count at max results
                    if ($maxResults > 0 && $count > $maxResults) {
                        $count = $maxResults;
                    }

                    Cache::put($cacheKey, $count, now()->addMinutes($cacheExpiry));

                    return $count;
                }
            } catch (\Exception $fallbackException) {
                // Log the error for debugging
                Log::error('getPagerCount failed', [
                    'query' => $query,
                    'error' => $fallbackException->getMessage(),
                ]);
            }

            return 0;
        }
    }
}
