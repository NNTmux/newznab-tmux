<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use App\Models\UsenetGroup;
use Elasticsearch;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Class Releases.
 */
class Releases extends Release
{
    private const CACHE_VERSION_KEY = 'releases:cache_version';

    // RAR/ZIP Password indicator.
    public const PASSWD_NONE = 0; // No password.

    public const PASSWD_RAR = 1; // Definitely passworded.

    public int $passwordStatus;

    private ManticoreSearch $manticoreSearch;

    private ElasticSearchSiteSearch $elasticSearch;

    /**
     * @var array Class instances.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->manticoreSearch = new ManticoreSearch;
        $this->elasticSearch = new ElasticSearchSiteSearch;
    }

    /**
     * Used for Browse results.
     *
     *
     * @return Collection|mixed
     */
    public function getBrowseRange($page, $cat, $start, $num, $orderBy, int $maxAge = -1, array $excludedCats = [], int|string $groupName = -1, int $minSize = 0): mixed
    {
        $cacheVersion = $this->getCacheVersion();
        $page = max(1, $page);
        $start = max(0, $start);

        $orderBy = $this->getBrowseOrder($orderBy);

        $qry = sprintf(
            "SELECT r.id, r.searchname, r.groups_id, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus, r.nfostatus, cp.title AS parent_category, c.title AS sub_category, r.group_name,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				CONCAT(cp.id, ',', c.id) AS category_ids,
				df.failed AS failed,
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
				WHERE r.passwordstatus %s
				%s %s %s %s %s
				ORDER BY %s %s %s
			) r
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
			LEFT OUTER JOIN videos v ON r.videos_id = v.id
			LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
			LEFT OUTER JOIN movieinfo m ON m.id = r.movieinfo_id
			LEFT OUTER JOIN video_data re ON re.releases_id = r.id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
			GROUP BY r.id
			ORDER BY %7\$s %8\$s",
            $this->showPasswords(),
            Category::getCategorySearch($cat),
            ($maxAge > 0 ? (' AND postdate > NOW() - INTERVAL '.$maxAge.' DAY ') : ''),
            (\count($excludedCats) ? (' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')') : ''),
            ((int) $groupName !== -1 ? sprintf(' AND g.name = %s ', escapeString($groupName)) : ''),
            ($minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : ''),
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
            $possibleRows = $this->getBrowseCount($cat, $maxAge, $excludedCats, $groupName);
            $sql[0]->_totalcount = $sql[0]->_totalrows = $possibleRows;
        }
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put($cacheKey, $sql, $expiresAt);

        return $sql;
    }

    /**
     * Used for pager on browse page.
     */
    public function getBrowseCount(array $cat, int $maxAge = -1, array $excludedCats = [], int|string $groupName = ''): int
    {
        return $this->getPagerCount(sprintf(
            'SELECT COUNT(r.id) AS count
				FROM releases r
				%s
				WHERE r.passwordstatus %s
				%s
				%s %s %s ',
            ($groupName !== -1 ? 'LEFT JOIN usenet_groups g ON g.id = r.groups_id' : ''),
            $this->showPasswords(),
            ($groupName !== -1 ? sprintf(' AND g.name = %s', escapeString($groupName)) : ''),
            Category::getCategorySearch($cat),
            ($maxAge > 0 ? (' AND r.postdate > NOW() - INTERVAL '.$maxAge.' DAY ') : ''),
            (\count($excludedCats) ? (' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')') : '')
        ));
    }

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
            'size_asc',
            'size_desc',
            'files_asc',
            'files_desc',
            'stats_asc',
            'stats_desc',
        ];
    }

    /**
     * @return Release[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Query\Builder[]|\Illuminate\Support\Collection
     */
    public function getForExport(string $postFrom = '', string $postTo = '', string $groupID = '')
    {
        $query = self::query()
            ->select(['r.searchname', 'r.guid', 'g.name as gname', DB::raw("CONCAT(cp.title,'_',c.title) AS catName")])
            ->from('releases as r')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
            ->leftJoin('root_categories as cp', 'cp.id', '=', 'c.root_categories_id')
            ->leftJoin('usenet_groups as g', 'g.id', '=', 'r.groups_id');

        if ($groupID !== '') {
            $query->where('r.groups_id', $groupID);
        }

        if ($postFrom !== '') {
            $dateParts = explode('/', $postFrom);
            if (\count($dateParts) === 3) {
                $query->where('r.postdate', '>', $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0].'00:00:00');
            }
        }

        if ($postTo !== '') {
            $dateParts = explode('/', $postTo);
            if (\count($dateParts) === 3) {
                $query->where('r.postdate', '<', $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0].'23:59:59');
            }
        }

        return $query->get();
    }

    /**
     * @return mixed|string
     */
    public function getEarliestUsenetPostDate(): mixed
    {
        $row = self::query()->selectRaw("DATE_FORMAT(min(postdate), '%d/%m/%Y') AS postdate")->first();

        return $row === null ? '01/01/2014' : $row['postdate'];
    }

    /**
     * @return mixed|string
     */
    public function getLatestUsenetPostDate(): mixed
    {
        $row = self::query()->selectRaw("DATE_FORMAT(max(postdate), '%d/%m/%Y') AS postdate")->first();

        return $row === null ? '01/01/2014' : $row['postdate'];
    }

    public function getReleasedGroupsForSelect(bool $blnIncludeAll = true): array
    {
        $groups = self::query()
            ->selectRaw('DISTINCT g.id, g.name')
            ->leftJoin('usenet_groups as g', 'g.id', '=', 'releases.groups_id')
            ->get();
        $temp_array = [];

        if ($blnIncludeAll) {
            $temp_array[-1] = '--All Groups--';
        }

        foreach ($groups as $group) {
            $temp_array[$group['id']] = $group['name'];
        }

        return $temp_array;
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
        $result = $this->fromQuery($sql);
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
     * @throws \Exception
     */
    public function deleteMultiple(int|array|string $list): void
    {
        $list = (array) $list;

        $nzb = new NZB;
        $releaseImage = new ReleaseImage;

        foreach ($list as $identifier) {
            $this->deleteSingle(['g' => $identifier, 'i' => false], $nzb, $releaseImage);
        }
    }

    /**
     * Deletes a single release by GUID, and all the corresponding files.
     *
     * @param  array  $identifiers  ['g' => Release GUID(mandatory), 'id => ReleaseID(optional, pass
     *                              false)]
     *
     * @throws \Exception
     */
    public function deleteSingle(array $identifiers, NZB $nzb, ReleaseImage $releaseImage): void
    {
        // Delete NZB from disk.
        $nzbPath = $nzb->NZBPath($identifiers['g']);
        if (! empty($nzbPath)) {
            File::delete($nzbPath);
        }

        // Delete images.
        $releaseImage->delete($identifiers['g']);

        if (config('nntmux.elasticsearch_enabled') === true) {
            if ($identifiers['i'] === false) {
                $identifiers['i'] = Release::query()->where('guid', $identifiers['g'])->first(['id']);
                if ($identifiers['i'] !== null) {
                    $identifiers['i'] = $identifiers['i']['id'];
                }
            }
            if ($identifiers['i'] !== null) {
                $params = [
                    'index' => 'releases',
                    'id' => $identifiers['i'],
                ];

                try {
                    Elasticsearch::delete($params);
                } catch (Missing404Exception $e) {
                    // we do nothing here just catch the error, we don't care if release is missing from ES, we are deleting it anyway
                }
            }
        } else {
            // Delete from sphinx.
            $this->manticoreSearch->deleteRelease($identifiers);
        }

        // Delete from DB.
        self::whereGuid($identifiers['g'])->delete();
    }

    /**
     * @return bool|int
     */
    public function updateMulti($guids, $category, $grabs, $videoId, $episodeId, $anidbId, $imdbId)
    {
        if (! \is_array($guids) || \count($guids) < 1) {
            return false;
        }

        $update = [
            'categories_id' => $category === -1 ? 'categories_id' : $category,
            'grabs' => $grabs,
            'videos_id' => $videoId,
            'tv_episodes_id' => $episodeId,
            'anidbid' => $anidbId,
            'imdbid' => $imdbId,
        ];

        return self::query()->whereIn('guid', $guids)->update($update);
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

    /**
     * Function for searching on the site (by subject, searchname or advanced).
     *
     * @return array|Collection|mixed
     */
    public function search(
        array $searchArr,
        $groupName,
        $sizeFrom,
        $sizeTo,
        $daysNew,
        $daysOld,
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
            Log::debug('Releases::search called', [
                'searchArr' => $searchArr,
                'limit' => $limit,
            ]);
        }

        // Get search results from index
        $searchResult = $this->performIndexSearch($searchArr, $limit);

        if (config('app.debug')) {
            Log::debug('Releases::search after performIndexSearch', [
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

        // Get order by clause
        $orderBy = $this->getBrowseOrder($orderBy === '' ? 'posted_desc' : $orderBy);

        // Build final SQL with pagination
        $sql = sprintf(
            'SELECT * FROM (%s) r ORDER BY r.%s %s LIMIT %d OFFSET %d',
            $baseSql,
            $orderBy[0],
            $orderBy[1],
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
        $releases = $this->fromQuery($sql);

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
     * Perform index search using Elasticsearch or Manticore
     */
    private function performIndexSearch(array $searchArr, int $limit): array
    {
        $searchFields = Arr::where($searchArr, static function ($value) {
            return $value !== -1;
        });

        if (empty($searchFields)) {
            if (config('app.debug')) {
                Log::debug('performIndexSearch: searchFields is empty');
            }
            return [];
        }

        $phrases = array_values($searchFields);

        $esEnabled = config('nntmux.elasticsearch_enabled');
        if (config('app.debug')) {
            Log::debug('performIndexSearch: starting search', [
                'elasticsearch_enabled' => $esEnabled,
                'elasticsearch_enabled_type' => gettype($esEnabled),
                'phrases' => $phrases,
                'limit' => $limit,
            ]);
        }

        if ($esEnabled === true) {
            $result = $this->elasticSearch->indexSearch($phrases, $limit);
            if (config('app.debug')) {
                Log::debug('performIndexSearch: Elasticsearch result count', ['count' => count($result)]);
            }
            return $result;
        }

        $searchResult = $this->manticoreSearch->searchIndexes('releases_rt', '', [], $searchFields);

        return ! empty($searchResult) ? Arr::wrap(Arr::get($searchResult, 'id')) : [];
    }

    /**
     * Build WHERE clause for search query
     */
    private function buildSearchWhereClause(
        array $searchResult,
        $groupName,
        $sizeFrom,
        $sizeTo,
        $daysNew,
        $daysOld,
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
     */
    private function buildSizeConditions($sizeFrom, $sizeTo): array
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

        $conditions = [];

        if (array_key_exists($sizeFrom, $sizeRange)) {
            $conditions[] = sprintf('r.size > %d', 104857600 * (int) $sizeRange[$sizeFrom]);
        }

        if (array_key_exists($sizeTo, $sizeRange)) {
            $conditions[] = sprintf('r.size < %d', 104857600 * (int) $sizeRange[$sizeTo]);
        }

        return $conditions;
    }

    /**
     * Build category condition based on search type
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
     * Build base SQL for search query
     */
    private function buildSearchBaseSql(string $whereSql): string
    {
        return sprintf(
            "SELECT r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id, r.size,
                    r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate,
                    r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus,
                    cp.title AS parent_category, c.title AS sub_category,
                    CONCAT(cp.title, ' > ', c.title) AS category_name,
                    df.failed AS failed,
                    g.name AS group_name,
                    rn.releases_id AS nfoid,
                    re.releases_id AS reid,
                    cp.id AS categoryparentid,
                    v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.imdb, v.tmdb,
                    tve.firstaired
            FROM releases r
            LEFT OUTER JOIN video_data re ON re.releases_id = r.id
            LEFT OUTER JOIN videos v ON r.videos_id = v.id
            LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
            LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
            LEFT JOIN usenet_groups g ON g.id = r.groups_id
            LEFT JOIN categories c ON c.id = r.categories_id
            LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
            LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
            %s",
            $whereSql
        );
    }

    /**
     * Search function for API.
     *
     * @return Collection|mixed
     */
    public function apiSearch($searchName, $groupName, int $offset = 0, int $limit = 1000, int $maxAge = -1, array $excludedCats = [], array $cat = [-1], int $minSize = 0): mixed
    {
        // Early return if searching with no results
        $searchResult = [];
        if ($searchName !== -1) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $searchResult = $this->elasticSearch->indexSearchApi($searchName, $limit);
            } else {
                $searchResult = $this->manticoreSearch->searchIndexes('releases_rt', $searchName, ['searchname']);
                if (! empty($searchResult)) {
                    $searchResult = Arr::wrap(Arr::get($searchResult, 'id'));
                }
            }

            if (empty($searchResult)) {
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
        $catQuery = preg_replace('/^(WHERE|AND)\s+/i', '', trim($catQuery));
        if (! empty($catQuery) && $catQuery !== '1=1') {
            $conditions[] = $catQuery;
        }

        if (! empty($excludedCats)) {
            $conditions[] = sprintf('r.categories_id NOT IN (%s)', implode(',', array_map('intval', $excludedCats)));
        }

        if (! empty($searchResult)) {
            $conditions[] = sprintf('r.id IN (%s)', implode(',', array_map('intval', $searchResult)));
        }

        if ($minSize > 0) {
            $conditions[] = sprintf('r.size >= %d', $minSize);
        }

        $whereSql = 'WHERE '.implode(' AND ', $conditions);

        // Optimized query: remove unused columns/joins (haspreview, jpgstatus, *_id columns, nfo/video_data/failures)
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
            ORDER BY r.postdate DESC
            LIMIT %d OFFSET %d",
            $whereSql,
            $limit,
            $offset
        );

        $cacheKey = md5($sql);
        $cachedReleases = Cache::get($cacheKey);
        if ($cachedReleases !== null) {
            return $cachedReleases;
        }

        $releases = $this->fromQuery($sql);

        if ($releases->isNotEmpty()) {
            $countSql = sprintf('SELECT COUNT(*) as count FROM releases r %s', $whereSql);
            $countResult = $this->fromQuery($countSql);
            $releases[0]->_totalrows = $countResult[0]->count ?? 0;
        }

        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put($cacheKey, $releases, $expiresAt);

        return $releases;
    }

    /**
     * Search for TV shows via API.
     *
     * @return array|\Illuminate\Cache\|\Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|mixed
     */
    public function tvSearch(array $siteIdArr = [], string $series = '', string $episode = '', string $airDate = '', int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, int $minSize = 0, array $excludedCategories = []): mixed
    {
        $shouldCache = ! (isset($siteIdArr['id']) && (int) $siteIdArr['id'] > 0);
        $rawCacheKey = md5(serialize(func_get_args()).'tvSearch');
        $cacheKey = null;
        if ($shouldCache) {
            $cacheKey = md5($this->getCacheVersion().$rawCacheKey);
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

        if (! empty($siteIdArr)) {
            $siteConditions = [];
            foreach ($siteIdArr as $column => $id) {
                if ($id > 0) {
                    $siteConditions[] = sprintf('v.%s = %d', $column, (int) $id);
                }
            }

            if (! empty($siteConditions)) {
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

                $results = $this->fromQuery($lookupSql);

                if ($results->isEmpty()) {
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

        $searchResult = [];
        if (! empty($name)) {
            $searchName = $name;
            $hasValidSiteIds = false;
            foreach ($siteIdArr as $column => $id) {
                if ($id > 0) {
                    $hasValidSiteIds = true;
                    break;
                }
            }

            if (! $hasValidSiteIds) {
                if (! empty($series) && (int) $series < 1900) {
                    $searchName .= sprintf(' S%s', str_pad($series, 2, '0', STR_PAD_LEFT));
                    $seriesNum = (int) preg_replace('/^s0*/i', '', $series);
                    $conditions[] = sprintf('tve.series = %d', $seriesNum);
                    $needsEpisodeJoin = true;
                    if (! empty($episode) && ! str_contains($episode, '/')) {
                        $searchName .= sprintf('E%s', str_pad($episode, 2, '0', STR_PAD_LEFT));
                        $episodeNum = (int) preg_replace('/^e0*/i', '', $episode);
                        $conditions[] = sprintf('tve.episode = %d', $episodeNum);
                    }
                } elseif (! empty($airDate)) {
                    $searchName .= ' '.str_replace(['/', '-', '.', '_'], ' ', $airDate);
                    $conditions[] = sprintf('DATE(tve.firstaired) = %s', escapeString($airDate));
                    $needsEpisodeJoin = true;
                }
            }

            if (config('nntmux.elasticsearch_enabled') === true) {
                $searchResult = $this->elasticSearch->indexSearchTMA($searchName, $limit);
            } else {
                $searchResult = $this->manticoreSearch->searchIndexes('releases_rt', $searchName, ['searchname']);
                if (! empty($searchResult)) {
                    $searchResult = Arr::wrap(Arr::get($searchResult, 'id'));
                }
            }

            if (empty($searchResult)) {
                return collect();
            }

            $conditions[] = sprintf('r.id IN (%s)', implode(',', array_map('intval', $searchResult)));
        }

        $catQuery = Category::getCategorySearch($cat, 'tv');
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
        if (! empty($excludedCategories)) {
            $conditions[] = sprintf('r.categories_id NOT IN (%s)', implode(',', array_map('intval', $excludedCategories)));
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

        $sql = sprintf('%s ORDER BY r.postdate DESC%s', $baseSql, $limitClause);
        $releases = $this->fromQuery($sql);

        if ($releases->isNotEmpty()) {
            $countSql = sprintf(
                'SELECT COUNT(*) as count FROM releases r %s %s %s',
                (! empty($videoJoinCondition) ? 'LEFT JOIN videos v ON r.videos_id = v.id AND v.type = 0' : ''),
                ($needsEpisodeJoin ? sprintf('%s JOIN tv_episodes tve ON r.tv_episodes_id = tve.id %s', $joinType, $episodeJoinCondition) : ''),
                $whereSql
            );
            $countResult = $this->fromQuery($countSql);
            $releases[0]->_totalrows = $countResult[0]->count ?? 0;
        }

        if ($shouldCache && $cacheKey !== null) {
            $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
            Cache::put($cacheKey, $releases, $expiresAt);
        }

        return $releases;
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
     * Search TV Shows via APIv2.
     *
     *
     * @return Collection|mixed
     */
    public function apiTvSearch(array $siteIdArr = [], string $series = '', string $episode = '', string $airDate = '', int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, int $minSize = 0, array $excludedCategories = []): mixed
    {
        $siteSQL = [];
        $showSql = '';
        foreach ($siteIdArr as $column => $Id) {
            if ($Id > 0) {
                $siteSQL[] = sprintf('v.%s = %d', $column, $Id);
            }
        }

        if (\count($siteSQL) > 0) {
            $showQry = sprintf(
                "\n\t\t\t\tSELECT v.id AS video, GROUP_CONCAT(tve.id SEPARATOR ',') AS episodes FROM videos v LEFT JOIN tv_episodes tve ON v.id = tve.videos_id WHERE (%s) %s %s %s GROUP BY v.id LIMIT 1",
                implode(' OR ', $siteSQL),
                ($series !== '' ? sprintf('AND tve.series = %d', (int) preg_replace('/^s0*/i', '', $series)) : ''),
                ($episode !== '' ? sprintf('AND tve.episode = %d', (int) preg_replace('/^e0*/i', '', $episode)) : ''),
                ($airDate !== '' ? sprintf('AND DATE(tve.firstaired) = %s', escapeString($airDate)) : '')
            );
            $show = $this->fromQuery($showQry);
            if ($show->isNotEmpty()) {
                if ((! empty($episode) && ! empty($series)) && $show[0]->episodes !== '') {
                    $showSql .= ' AND r.tv_episodes_id IN ('.$show[0]->episodes.') AND tve.series = '.$series;
                } elseif (! empty($episode) && $show[0]->episodes !== '') {
                    $showSql = sprintf('AND r.tv_episodes_id IN (%s)', $show[0]->episodes);
                } elseif (! empty($series) && empty($episode)) {
                    $showSql .= ' AND r.tv_episodes_id IN ('.$show[0]->episodes.') AND tve.series = '.$series;
                }
                if ($show[0]->video > 0) {
                    $showSql .= ' AND r.videos_id = '.$show[0]->video;
                }
            } else {
                return [];
            }
        }
        if (! empty($name) && $showSql === '') {
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
        if (! empty($name)) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $searchResult = $this->elasticSearch->indexSearchTMA($name, $limit);
            } else {
                $searchResult = $this->manticoreSearch->searchIndexes('releases_rt', $name, ['searchname']);
                if (! empty($searchResult)) {
                    $searchResult = Arr::wrap(Arr::get($searchResult, 'id'));
                }
            }

            if (count($searchResult) === 0) {
                return collect();
            }
        }
        $whereSql = sprintf(
            'WHERE r.passwordstatus %s %s %s %s %s %s %s',
            $this->showPasswords(),
            $showSql,
            (! empty($searchResult) ? 'AND r.id IN ('.implode(',', $searchResult).')' : ''),
            Category::getCategorySearch($cat, 'tv'),
            ($maxAge > 0 ? sprintf('AND r.postdate > NOW() - INTERVAL %d DAY', $maxAge) : ''),
            ($minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : ''),
            ! empty($excludedCategories) ? sprintf('AND r.categories_id NOT IN('.implode(',', $excludedCategories).')') : ''
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
        $sql = sprintf('%s ORDER BY postdate DESC LIMIT %d OFFSET %d', $baseSql, $limit, $offset);
        $releases = Cache::get(md5($sql));
        if ($releases !== null) {
            return $releases;
        }
        $releases = $this->fromQuery($sql);
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount(
                preg_replace('#LEFT(\s+OUTER)?\s+JOIN\s+(?!tv_episodes)\s+.*ON.*=.*\n#i', ' ', $baseSql)
            );
        }
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($sql), $releases, $expiresAt);

        return $releases;
    }

    /**
     * Search anime releases.
     *
     *
     * @return Collection|mixed
     */
    public function animeSearch($aniDbID, int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, array $excludedCategories = []): mixed
    {
        if (! empty($name)) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $searchResult = $this->elasticSearch->indexSearchTMA($name, $limit);
            } else {
                $searchResult = $this->manticoreSearch->searchIndexes('releases_rt', $name, ['searchname']);
                if (! empty($searchResult)) {
                    $searchResult = Arr::wrap(Arr::get($searchResult, 'id'));
                }
            }

            if (count($searchResult) === 0) {
                return collect();
            }
        }

        $whereSql = sprintf(
            'WHERE r.passwordstatus %s
			%s %s %s %s %s',
            $this->showPasswords(),
            ($aniDbID > -1 ? sprintf(' AND r.anidbid = %d ', $aniDbID) : ''),
            (! empty($searchResult) ? 'AND r.id IN ('.implode(',', $searchResult).')' : ''),
            ! empty($excludedCategories) ? sprintf('AND r.categories_id NOT IN('.implode(',', $excludedCategories).')') : '',
            Category::getCategorySearch($cat),
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
			ORDER BY postdate DESC
			LIMIT %d OFFSET %d',
            $baseSql,
            $limit,
            $offset
        );
        $releases = Cache::get(md5($sql));
        if ($releases !== null) {
            return $releases;
        }
        $releases = $this->fromQuery($sql);
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount($baseSql);
        }
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($sql), $releases, $expiresAt);

        return $releases;
    }

    /**
     * Movies search through API and site.
     *
     * @return Collection|mixed
     */
    public function moviesSearch(int $imDbId = -1, int $tmDbId = -1, int $traktId = -1, int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, int $minSize = 0, array $excludedCategories = []): mixed
    {
        // Early return if searching by name yields no results
        $searchResult = [];
        if (! empty($name)) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $searchResult = $this->elasticSearch->indexSearchTMA($name, $limit);
            } else {
                $searchResult = $this->manticoreSearch->searchIndexes('releases_rt', $name, ['searchname']);
                if (! empty($searchResult)) {
                    $searchResult = Arr::wrap(Arr::get($searchResult, 'id'));
                }
            }

            if (empty($searchResult)) {
                return collect();
            }
        }

        $conditions = [
            sprintf('r.categories_id BETWEEN %d AND %d', Category::MOVIE_ROOT, Category::MOVIE_OTHER),
            sprintf('r.passwordstatus %s', $this->showPasswords()),
        ];

        if (! empty($searchResult)) {
            $conditions[] = sprintf('r.id IN (%s)', implode(',', array_map('intval', $searchResult)));
        }

        // Optimize: Join on imdbid directly (both tables have indexed imdbid columns)
        // This is more efficient than joining through movieinfo_id
        // Always join movieinfo to get imdbid, tmdbid, and traktid fields consistently

        if ($imDbId !== -1 && $imDbId) {
            // Filter on r.imdbid (uses index ix_releases_imdbid)
            // The join on m.imdbid = r.imdbid will also use the index
            $conditions[] = sprintf('r.imdbid = %d', $imDbId);
        }

        if ($tmDbId !== -1 && $tmDbId) {
            $conditions[] = sprintf('m.tmdbid = %d', $tmDbId);
        }

        if ($traktId !== -1 && $traktId) {
            $conditions[] = sprintf('m.traktid = %d', $traktId);
        }

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

        $whereSql = 'WHERE '.implode(' AND ', $conditions);
        // Always join on imdbid directly (both tables have indexed imdbid) - more efficient than movieinfo_id
        // Both ix_releases_imdbid and ix_movieinfo_imdbid are indexed, making this join very fast
        // This ensures we always get imdbid, tmdbid, and traktid fields in results
        $joinSql = 'INNER JOIN movieinfo m ON m.imdbid = r.imdbid';

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

        $sql = sprintf('%s ORDER BY r.postdate DESC LIMIT %d OFFSET %d', $baseSql, $limit, $offset);
        $cacheKey = md5($sql.serialize(func_get_args()));
        if (($releases = Cache::get($cacheKey)) !== null) {
            return $releases;
        }

        $releases = $this->fromQuery($sql);

        if ($releases->isNotEmpty()) {
            // Optimize: Execute count query using same WHERE clause (uses same indexes)
            // The count query is lightweight and can use index-only scans when possible
            // Use same join logic as main query (join on imdbid when needed)
            $countSql = sprintf(
                'SELECT COUNT(*) as count FROM releases r %s %s',
                $joinSql,
                $whereSql
            );
            $countResult = DB::selectOne($countSql);
            $releases[0]->_totalrows = $countResult->count ?? 0;
        }

        Cache::put($cacheKey, $releases, now()->addMinutes(config('nntmux.cache_expiry_medium')));

        return $releases;
    }

    public function searchSimilar($currentID, $name, array $excludedCats = []): bool|array
    {
        // Get the category for the parent of this release.
        $ret = false;
        $currRow = self::getCatByRelId($currentID);
        if ($currRow !== null) {
            $catRow = Category::find($currRow['categories_id']);
            $parentCat = $catRow !== null ? $catRow['root_categories_id'] : null;

            if ($parentCat === null) {
                return $ret;
            }

            $results = $this->search(['searchname' => getSimilarName($name)], -1, '', '', -1, -1, 0, config('nntmux.items_per_page'), '', -1, $excludedCats, 'basic', [$parentCat]);
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
                Log::error('getPagerCount failed', [
                    'query' => $query,
                    'error' => $fallbackException->getMessage(),
                ]);
            }

            return 0;
        }
    }
}
