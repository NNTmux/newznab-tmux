<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use App\Models\UsenetGroup;
use Elasticsearch;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Class Releases.
 */
class Releases extends Release
{
    // RAR/ZIP Password indicator.
    public const PASSWD_NONE = 0; // No password.

    public const PASSWD_RAR = 1; // Definitely passworded.

    public int $passwordStatus;

    private ManticoreSearch $manticoreSearch;

    private ElasticSearchSiteSearch $elasticSearch;

    // Define byte multiplier for size ranges (in MB)
    private const SIZE_RANGES = [
        1 => 1, 2 => 2.5, 3 => 5, 4 => 10, 5 => 20,
        6 => 30, 7 => 40, 8 => 80, 9 => 160, 10 => 320, 11 => 640,
    ];

    private const BYTES_PER_MB = 104857600;

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
     * Get paginated browse results with filters.
     *
     * @param  int  $page  Current page number
     * @param  array  $cat  Category IDs to filter by
     * @param  int  $start  Offset for pagination
     * @param  int  $num  Number of items per page
     * @param  array|string  $orderBy  Sort order specification
     * @param  int  $maxAge  Maximum age in days (-1 for no limit)
     * @param  array  $excludedCats  Category IDs to exclude
     * @param  int|string  $groupName  Group name or ID to filter by (-1 for all)
     * @param  int  $minSize  Minimum size in bytes
     * @return mixed Query results
     */
    public function getBrowseRange(
        int $page,
        array $cat,
        int $start,
        int $num,
        array|string $orderBy,
        int $maxAge = -1,
        array $excludedCats = [],
        int|string $groupName = -1,
        int $minSize = 0
    ): mixed {
        // Normalize input parameters
        $page = max(1, $page);
        $start = max(0, $start);
        $orderBy = $this->getBrowseOrder($orderBy);

        // Build the SQL query
        $qry = sprintf(
            "SELECT r.id, r.searchname, r.groups_id, r.guid, r.postdate, r.categories_id,
                    r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments,
                    r.adddate, r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus,
                    cp.title AS parent_category, c.title AS sub_category, g.name as group_name,
                    CONCAT(cp.title, ' > ', c.title) AS category_name,
                    CONCAT(cp.id, ',', c.id) AS category_ids,
                    df.failed AS failed,
                    rn.releases_id AS nfoid,
                    re.releases_id AS reid,
                    v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.imdb, v.tmdb,
                    tve.title, tve.firstaired
            FROM releases r
            LEFT JOIN usenet_groups g ON g.id = r.groups_id
            LEFT JOIN categories c ON c.id = r.categories_id
            LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
            LEFT OUTER JOIN videos v ON r.videos_id = v.id
            LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
            LEFT OUTER JOIN video_data re ON re.releases_id = r.id
            LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
            LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
            WHERE r.nzbstatus = %d
            AND r.passwordstatus %s
            %s %s %s %s %s
            GROUP BY r.id
            ORDER BY %s %s
            LIMIT %d OFFSET %d",
            NZB::NZB_ADDED,
            $this->showPasswords(),
            Category::getCategorySearch($cat),
            $maxAge > 0 ? " AND r.postdate > NOW() - INTERVAL {$maxAge} DAY" : '',
            count($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : '',
            (int) $groupName !== -1 ? ' AND g.name = '.escapeString($groupName) : '',
            $minSize > 0 ? " AND r.size >= {$minSize}" : '',
            $orderBy[0],
            $orderBy[1],
            $num,
            $start
        );

        // Check cache first
        $cacheKey = md5($qry.$page);
        $releases = Cache::get($cacheKey);
        if ($releases !== null) {
            return $releases;
        }

        // Execute query
        $sql = $this->fromQuery($qry);

        // Add pagination metadata if results exist
        if (count($sql) > 0) {
            $totalRows = $this->getBrowseCount($cat, $maxAge, $excludedCats, $groupName);
            $sql[0]->_totalcount = $sql[0]->_totalrows = $totalRows;
        }

        // Cache the results
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put($cacheKey, $sql, $expiresAt);

        return $sql;
    }

    /**
     * Get total count of releases for browse pagination.
     *
     * @param  array  $cat  Category IDs to filter by
     * @param  int  $maxAge  Maximum age in days (-1 for no limit)
     * @param  array  $excludedCats  Category IDs to exclude
     * @param  int|string  $groupName  Group name or ID to filter by (-1 for all)
     * @return int Total count of matching releases
     */
    public function getBrowseCount(array $cat, int $maxAge = -1, array $excludedCats = [], int|string $groupName = -1): int
    {
        // Build the base query
        $sql = 'SELECT COUNT(r.id) AS count FROM releases r';

        // Add group join if needed
        if ($groupName !== -1) {
            $sql .= ' LEFT JOIN usenet_groups g ON g.id = r.groups_id';
        }

        // Build where clause
        $conditions = [];
        $conditions[] = 'r.nzbstatus = '.NZB::NZB_ADDED;
        $conditions[] = 'r.passwordstatus '.$this->showPasswords();

        // Add group filter
        if ($groupName !== -1) {
            $conditions[] = 'g.name = '.escapeString($groupName);
        }

        // Add category filter
        $catQuery = Category::getCategorySearch($cat);
        if (! empty($catQuery)) {
            $conditions[] = $catQuery;
        }

        // Add age filter
        if ($maxAge > 0) {
            $conditions[] = 'r.postdate > NOW() - INTERVAL '.$maxAge.' DAY';
        }

        // Add excluded categories
        if (! empty($excludedCats)) {
            $conditions[] = 'r.categories_id NOT IN ('.implode(',', $excludedCats).')';
        }

        // Combine all conditions
        $sql .= ' WHERE '.implode(' AND ', $conditions);

        return $this->getPagerCount($sql);
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
            ->where('r.nzbstatus', NZB::NZB_ADDED)
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
     * Get a range of TV shows based on user preferences.
     *
     * @param  Collection|array  $userShows  Collection of user's show preferences
     * @param  int|bool  $offset  Pagination offset (false for no pagination)
     * @param  int  $limit  Number of results per page
     * @param  array|string  $orderBy  Sort order specification
     * @param  int  $maxAge  Maximum age in days (-1 for no limit)
     * @param  array  $excludedCats  Category IDs to exclude
     * @return Collection Query results
     */
    public function getShowsRange(
        Collection|array $userShows,
        int|bool $offset,
        int $limit,
        array|string $orderBy,
        int $maxAge = -1,
        array $excludedCats = []
    ): Collection {
        // Normalize ordering
        $orderBy = $this->getBrowseOrder($orderBy);

        // Build the SQL query
        $sql = sprintf(
            "SELECT r.id, r.searchname, r.guid, r.postdate, r.groups_id,
                    r.categories_id, r.size, r.totalpart, r.fromname,
                    r.passwordstatus, r.grabs, r.comments, r.adddate,
                    r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus,
                    cp.title AS parent_category,
                    c.title AS sub_category,
                    CONCAT(cp.title, ' > ', c.title) AS category_name
            FROM releases r
            LEFT JOIN categories c ON c.id = r.categories_id
            LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
            WHERE %s %s
            AND r.nzbstatus = %d
            AND r.categories_id BETWEEN %d AND %d
            AND r.passwordstatus %s
            %s
            GROUP BY r.id
            ORDER BY %s %s %s",
            $this->uSQL($userShows, 'videos_id'),
            ! empty($excludedCats) ? sprintf(' AND r.categories_id NOT IN (%s)', implode(',', $excludedCats)) : '',
            NZB::NZB_ADDED,
            Category::TV_ROOT,
            Category::TV_OTHER,
            $this->showPasswords(),
            $maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY', $maxAge) : '',
            $orderBy[0],
            $orderBy[1],
            $offset === false ? '' : sprintf(' LIMIT %d OFFSET %d', $limit, $offset)
        );

        // Generate cache key
        $cacheKey = md5($sql);

        // Try to get from cache
        $result = Cache::get($cacheKey);
        if ($result !== null) {
            return $result;
        }

        // Execute query and cache results
        $result = $this->fromQuery($sql);
        Cache::put(
            $cacheKey,
            $result,
            now()->addMinutes(config('nntmux.cache_expiry_long'))
        );

        return $result;
    }

    public function getShowsCount($userShows, int $maxAge = -1, array $excludedCats = []): int
    {
        return $this->getPagerCount(
            sprintf(
                'SELECT r.id
				FROM releases r
				WHERE %s %s
				AND r.nzbstatus = %d
				AND r.categories_id BETWEEN %d AND %d
				AND r.passwordstatus %s
				%s',
                $this->uSQL($userShows, 'videos_id'),
                (\count($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
                NZB::NZB_ADDED,
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
     * Search for releases using various criteria.
     *
     * @param  array  $searchArr  Search terms array
     * @param  int|string  $groupName  Group name or ID to filter by (-1 for all)
     * @param  int  $sizeFrom  Minimum size range index (1-11)
     * @param  int  $sizeTo  Maximum size range index (1-11)
     * @param  int  $daysNew  Number of days for newer than filter (-1 for no filter)
     * @param  int  $daysOld  Number of days for older than filter (-1 for no filter)
     * @param  int  $offset  Pagination offset
     * @param  int  $limit  Number of results per page
     * @param  array|string  $orderBy  Sort order specification
     * @param  int  $maxAge  Maximum age in days (-1 for no limit)
     * @param  array  $excludedCats  Category IDs to exclude
     * @param  string  $type  Search type ('basic' or 'advanced')
     * @param  array  $cat  Category IDs to include
     * @param  int  $minSize  Minimum size in bytes
     * @return Collection Query results
     */
    public function search(
        array $searchArr,
        int|string $groupName,
        int $sizeFrom,
        int $sizeTo,
        int $daysNew,
        int $daysOld,
        int $offset = 0,
        int $limit = 1000,
        array|string $orderBy = '',
        int $maxAge = -1,
        array $excludedCats = [],
        string $type = 'basic',
        array $cat = [-1],
        int $minSize = 0
    ): Collection {

        // Normalize ordering
        $orderBy = $orderBy === '' ? ['postdate', 'desc'] : $this->getBrowseOrder($orderBy);

        // Filter and prepare search terms
        $searchFields = Arr::where($searchArr, fn ($value) => $value !== -1);
        $phrases = array_values($searchFields);

        // Perform search using configured search engine
        $searchResult = config('nntmux.elasticsearch_enabled')
            ? $this->elasticSearch->indexSearch($phrases, $limit)
            : $this->getManticoreResults($searchFields);

        if (count($searchResult) === 0) {
            return collect();
        }

        // Build category filter
        $catQuery = match ($type) {
            'basic' => Category::getCategorySearch($cat),
            'advanced' => (int) $cat[0] !== -1 ? sprintf('AND r.categories_id = %d', $cat[0]) : '',
            default => ''
        };

        // Build SQL conditions
        $conditions = $this->buildSearchConditions(
            $groupName, $sizeFrom, $sizeTo, $daysNew, $daysOld,
            $maxAge, $excludedCats, $minSize, $catQuery, $searchResult
        );

        // Construct full query
        $baseSql = $this->buildBaseQuery($conditions);
        $sql = $this->buildPaginatedQuery($baseSql, $orderBy, $limit, $offset);

        // Check cache
        $cacheKey = md5($sql);
        if ($releases = Cache::get($cacheKey)) {
            return $releases;
        }

        // Execute query and cache results
        $releases = $this->fromQuery($sql);
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount($baseSql);
        }

        Cache::put($cacheKey, $releases, now()->addMinutes(config('nntmux.cache_expiry_medium')));

        return $releases;
    }

    /**
     * Build paginated query with ordering.
     *
     * @param  string  $baseSql  Base SQL query
     * @param  array  $orderBy  Array containing ['field', 'direction']
     * @param  int  $limit  Number of results per page
     * @param  int  $offset  Pagination offset
     * @return string Complete paginated SQL query
     */
    private function buildPaginatedQuery(string $baseSql, array $orderBy, int $limit, int $offset): string
    {
        // Sanitize parameters
        $limit = max(1, min((int) $limit, 1000)); // Limit between 1-1000
        $offset = max(0, (int) $offset);
        $orderField = $this->validateOrderField($orderBy[0]);
        $orderDirection = strtoupper($orderBy[1]) === 'ASC' ? 'ASC' : 'DESC';

        return sprintf(
            '%s ORDER BY %s %s LIMIT %d OFFSET %d',
            $baseSql,
            $orderField,
            $orderDirection,
            $limit,
            $offset
        );
    }

    /**
     * Validate and sanitize order field name.
     *
     * @param  string  $field  Field name to validate
     * @return string Validated field name
     */
    private function validateOrderField(string $field): string
    {
        $allowedFields = [
            'postdate',
            'size',
            'totalpart',
            'grabs',
            'comments',
            'searchname',
            'categories_id',
        ];

        return in_array($field, $allowedFields, true)
            ? $field
            : 'postdate'; // Default sort field
    }

    /**
     * Build base search query for releases.
     *
     * @param  string  $conditions  WHERE clause conditions
     * @return string Complete base SQL query
     */
    private function buildBaseQuery(string $conditions): string
    {
        return sprintf(
            'SELECT r.id, r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id,
                r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments,
                r.adddate, r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus,
                v.title as video_title, v.type as video_type,
                cp.title AS parent_category, c.title AS sub_category,
                CONCAT(cp.title, " > ", c.title) AS category_name,
                g.name AS group_name,
                rn.releases_id AS nfoid,
                df.failed AS failed
            FROM releases r
            LEFT JOIN categories c ON c.id = r.categories_id
            LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
            LEFT JOIN usenet_groups g ON g.id = r.groups_id
            LEFT OUTER JOIN videos v ON v.id = r.videos_id
            LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
            LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
            %s',
            $conditions
        );
    }

    /**
     * Get search results from Manticore.
     */
    private function getManticoreResults(array $searchFields): array
    {
        $result = $this->manticoreSearch->searchIndexes('releases_rt', '', [], $searchFields);

        return ! empty($result) ? Arr::wrap(Arr::get($result, 'id')) : [];
    }

    /**
     * Build search conditions for the query.
     */
    private function buildSearchConditions(
        int|string $groupName,
        int $sizeFrom,
        int $sizeTo,
        int $daysNew,
        int $daysOld,
        int $maxAge,
        array $excludedCats,
        int $minSize,
        string $catQuery,
        array $searchResult
    ): string {
        return sprintf(
            'WHERE r.passwordstatus %s AND r.nzbstatus = %d %s %s %s %s %s %s %s %s %s %s',
            $this->showPasswords(),
            NZB::NZB_ADDED,
            $maxAge > 0 ? sprintf(' AND r.postdate > (NOW() - INTERVAL %d DAY) ', $maxAge) : '',
            (int) $groupName !== -1 ? sprintf(' AND r.groups_id = %d ', UsenetGroup::getIDByName($groupName)) : '',
            array_key_exists($sizeFrom, self::SIZE_RANGES) ? sprintf(' AND r.size > %d ', self::BYTES_PER_MB * self::SIZE_RANGES[$sizeFrom]) : '',
            array_key_exists($sizeTo, self::SIZE_RANGES) ? sprintf(' AND r.size < %d ', self::BYTES_PER_MB * self::SIZE_RANGES[$sizeTo]) : '',
            $catQuery,
            $daysNew !== -1 ? sprintf(' AND r.postdate < (NOW() - INTERVAL %d DAY) ', $daysNew) : '',
            $daysOld !== -1 ? sprintf(' AND r.postdate > (NOW() - INTERVAL %d DAY) ', $daysOld) : '',
            ! empty($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : '',
            'AND r.id IN ('.implode(',', $searchResult).')',
            $minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : ''
        );
    }

    /**
     * Search for releases via API.
     *
     * @param  string|int  $searchName  Search term or -1 for all
     * @param  string|int  $groupName  Group name or ID to filter by (-1 for all)
     * @param  int  $offset  Pagination offset
     * @param  int  $limit  Number of results per page
     * @param  int  $maxAge  Maximum age in days (-1 for no limit)
     * @param  array  $excludedCats  Category IDs to exclude
     * @param  array  $cat  Category IDs to include
     * @param  int  $minSize  Minimum size in bytes
     * @return Collection Query results
     */
    public function apiSearch(
        string|int $searchName,
        string|int $groupName,
        int $offset = 0,
        int $limit = 1000,
        int $maxAge = -1,
        array $excludedCats = [],
        array $cat = [-1],
        int $minSize = 0
    ): Collection {
        // Perform search if searchName provided
        $searchResult = [];
        if ($searchName !== -1) {
            $searchResult = config('nntmux.elasticsearch_enabled')
                ? $this->elasticSearch->indexSearchApi($searchName, $limit)
                : $this->getManticoreResults(['searchname' => $searchName]);

            if (empty($searchResult)) {
                return collect();
            }
        }

        // Build query conditions
        $conditions = $this->buildApiSearchConditions(
            $this->showPasswords(),
            NZB::NZB_ADDED,
            $maxAge,
            $groupName,
            Category::getCategorySearch($cat),
            $excludedCats,
            $searchResult,
            $minSize
        );

        // Build complete query
        $query = $this->buildApiSearchQuery($conditions, $limit, $offset);

        // Check cache
        $cacheKey = md5($query);
        if ($releases = Cache::get($cacheKey)) {
            return $releases;
        }

        // Execute query based on search conditions
        $releases = match (true) {
            $searchName !== -1 && ! empty($searchResult) => $this->fromQuery($query),
            $searchName === -1 => $this->fromQuery($query),
            default => collect()
        };

        // Add total rows count if results exist
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount($this->buildBaseApiSearchQuery($conditions));
        }

        // Cache results
        Cache::put(
            $cacheKey,
            $releases,
            now()->addMinutes(config('nntmux.cache_expiry_medium'))
        );

        return $releases;
    }

    /**
     * Build API search conditions.
     */
    private function buildApiSearchConditions(
        string $passwordStatus,
        int $nzbStatus,
        int $maxAge,
        string|int $groupName,
        string $catQuery,
        array $excludedCats,
        array $searchResult,
        int $minSize
    ): string {
        return sprintf(
            'WHERE r.passwordstatus %s AND r.nzbstatus = %d %s %s %s %s %s %s',
            $passwordStatus,
            $nzbStatus,
            $maxAge > 0 ? sprintf(' AND r.postdate > (NOW() - INTERVAL %d DAY) ', $maxAge) : '',
            (int) $groupName !== -1 ? sprintf(' AND r.groups_id = %d ', UsenetGroup::getIDByName($groupName)) : '',
            $catQuery,
            ! empty($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : '',
            ! empty($searchResult) ? 'AND r.id IN ('.implode(',', $searchResult).')' : '',
            $minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : ''
        );
    }

    /**
     * Build base API search query.
     */
    private function buildBaseApiSearchQuery(string $conditions): string
    {
        return sprintf(
            "SELECT r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id,
                    r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs,
                    r.comments, r.adddate, r.videos_id, r.tv_episodes_id, r.haspreview,
                    r.jpgstatus, m.imdbid, m.tmdbid, m.traktid, cp.title AS parent_category,
                    c.title AS sub_category, CONCAT(cp.title, ' > ', c.title) AS category_name,
                    g.name AS group_name, cp.id AS categoryparentid,
                    v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.imdb, v.tmdb,
                    tve.firstaired, tve.title, tve.series, tve.episode
             FROM releases r
             LEFT OUTER JOIN videos v ON r.videos_id = v.id
             LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
             LEFT JOIN movieinfo m ON m.id = r.movieinfo_id
             LEFT JOIN usenet_groups g ON g.id = r.groups_id
             LEFT JOIN categories c ON c.id = r.categories_id
             LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
             %s",
            $conditions
        );
    }

    /**
     * Build paginated API search query.
     */
    private function buildApiSearchQuery(string $conditions, int $limit, int $offset): string
    {
        return sprintf(
            'SELECT * FROM (%s) r ORDER BY r.postdate DESC LIMIT %d OFFSET %d',
            $this->buildBaseApiSearchQuery($conditions),
            $limit,
            $offset
        );
    }

    /**
     * Search for TV shows via API.
     *
     * @param  array  $siteIdArr  External site IDs to search by
     * @param  string  $series  Series number
     * @param  string  $episode  Episode number
     * @param  string  $airDate  Episode air date
     * @param  int  $offset  Pagination offset
     * @param  int  $limit  Results per page
     * @param  string  $name  Search term
     * @param  array  $cat  Category IDs to include
     * @param  int  $maxAge  Maximum age in days (-1 for no limit)
     * @param  int  $minSize  Minimum size in bytes
     * @param  array  $excludedCategories  Category IDs to exclude
     */
    public function tvSearch(
        array $siteIdArr = [],
        string $series = '',
        string $episode = '',
        string $airDate = '',
        int $offset = 0,
        int $limit = 100,
        string $name = '',
        array $cat = [-1],
        int $maxAge = -1,
        int $minSize = 0,
        array $excludedCategories = []
    ): Collection {
        // Build site ID conditions
        $showInfo = $this->buildShowInfo($siteIdArr, $series, $episode, $airDate);
        if ($showInfo === null) {
            return collect();
        }

        // Build search name
        $searchName = $this->buildSearchName($name, $series, $episode, $airDate, $showInfo['sql']);

        // Perform search if name provided
        $searchResult = [];
        if ($searchName !== '') {
            $searchResult = $this->performTvSearch($searchName, $limit);
            if (empty($searchResult)) {
                return collect();
            }
        }

        // Build and execute main query
        return $this->executeTvSearch(
            $showInfo['sql'],
            $searchResult,
            $searchName,
            $cat,
            $maxAge,
            $minSize,
            $excludedCategories,
            $limit,
            $offset
        );
    }

    /**
     * Build show information from site IDs and episode data.
     */
    private function buildShowInfo(array $siteIdArr, string $series, string $episode, string $airDate): ?array
    {
        $siteSQL = [];
        foreach ($siteIdArr as $column => $id) {
            if ($id > 0) {
                $siteSQL[] = sprintf('v.%s = %d', $column, $id);
            }
        }

        if (empty($siteSQL)) {
            return ['sql' => ''];
        }

        $showQry = sprintf(
            'SELECT v.id AS video, GROUP_CONCAT(tve.id SEPARATOR ",") AS episodes
             FROM videos v
             LEFT JOIN tv_episodes tve ON v.id = tve.videos_id
             WHERE (%s) %s %s %s
             GROUP BY v.id
             LIMIT 1',
            implode(' OR ', $siteSQL),
            $series !== '' ? sprintf('AND tve.series = %d', (int) preg_replace('/^s0*/i', '', $series)) : '',
            $episode !== '' ? sprintf('AND tve.episode = %d', (int) preg_replace('/^e0*/i', '', $episode)) : '',
            $airDate !== '' ? sprintf('AND DATE(tve.firstaired) = %s', escapeString($airDate)) : ''
        );

        $show = $this->fromQuery($showQry);
        if ($show->isEmpty()) {
            return null;
        }

        return [
            'show' => $show[0],
            'sql' => $this->buildShowSql($show[0], $series, $episode),
        ];
    }

    /**
     * Build show SQL conditions.
     */
    private function buildShowSql(object $show, string $series, string $episode): string
    {
        if ($show->episodes === '') {
            return '';
        }

        $sql = [];

        if (! empty($episode) && ! empty($series)) {
            $sql[] = "r.tv_episodes_id IN ({$show->episodes})";
            $sql[] = "tve.series = {$series}";
        } elseif (! empty($episode)) {
            $sql[] = "r.tv_episodes_id IN ({$show->episodes})";
        } elseif (! empty($series)) {
            $sql[] = "r.tv_episodes_id IN ({$show->episodes})";
            $sql[] = "tve.series = {$series}";
        }

        if ($show->video > 0) {
            $sql[] = "r.videos_id = {$show->video}";
        }

        return ! empty($sql) ? ' AND '.implode(' AND ', $sql) : '';
    }

    /**
     * Build search name with additional info.
     */
    private function buildSearchName(string $name, string $series, string $episode, string $airDate, string $showSql): string
    {
        if (empty($name) || ! empty($showSql)) {
            return '';
        }

        if (! empty($series) && (int) $series < 1900) {
            $name .= sprintf(' S%s', str_pad($series, 2, '0', STR_PAD_LEFT));
            if (! empty($episode) && ! str_contains($episode, '/')) {
                $name .= sprintf('E%s', str_pad($episode, 2, '0', STR_PAD_LEFT));
            } elseif (empty($episode)) {
                $name .= '*';
            }
        } elseif (! empty($airDate)) {
            $name .= ' '.str_replace(['/', '-', '.', '_'], ' ', $airDate);
        }

        return $name;
    }

    /**
     * Perform TV search using configured search engine.
     */
    private function performTvSearch(string $name, int $limit): array
    {
        $result = config('nntmux.elasticsearch_enabled')
            ? $this->elasticSearch->indexSearchTMA($name, $limit)
            : $this->getManticoreResults(['searchname' => $name]);

        return $result;
    }

    /**
     * Execute main TV search query.
     */
    private function executeTvSearch(
        string $showSql,
        array $searchResult,
        string $searchName,
        array $cat,
        int $maxAge,
        int $minSize,
        array $excludedCategories,
        int $limit,
        int $offset
    ): Collection {
        $conditions = $this->buildTvSearchConditions(
            $showSql,
            $searchResult,
            $searchName,
            $cat,
            $maxAge,
            $minSize,
            $excludedCategories
        );

        $sql = $this->buildTvSearchQuery($conditions, $limit, $offset);

        // Check cache
        $cacheKey = md5($sql);
        if ($releases = Cache::get($cacheKey)) {
            return $releases;
        }

        $releases = $this->fromQuery($sql);
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount(
                preg_replace('#LEFT(\s+OUTER)?\s+JOIN\s+(?!tv_episodes)\s+.*ON.*=.*\n#i', ' ', $sql)
            );
        }

        Cache::put($cacheKey, $releases, now()->addMinutes(config('nntmux.cache_expiry_medium')));

        return $releases;
    }

    /**
     * Search TV Shows via APIv2.
     *
     * @param  array  $siteIdArr  External site IDs to search by
     * @param  string  $series  Series number
     * @param  string  $episode  Episode number
     * @param  string  $airDate  Episode air date
     * @param  int  $offset  Pagination offset
     * @param  int  $limit  Results per page
     * @param  string  $name  Search term
     * @param  array  $cat  Category IDs to include
     * @param  int  $maxAge  Maximum age in days (-1 for no limit)
     * @param  int  $minSize  Minimum size in bytes
     * @param  array  $excludedCategories  Category IDs to exclude
     * @return Collection Query results
     */
    public function apiTvSearch(
        array $siteIdArr = [],
        string $series = '',
        string $episode = '',
        string $airDate = '',
        int $offset = 0,
        int $limit = 100,
        string $name = '',
        array $cat = [-1],
        int $maxAge = -1,
        int $minSize = 0,
        array $excludedCategories = []
    ): Collection {
        // Build site ID conditions
        $showInfo = $this->buildShowInfo($siteIdArr, $series, $episode, $airDate);
        if ($showInfo === null && ! empty($siteIdArr)) {
            return collect();
        }

        // Build search name with fallback info
        $searchName = $this->buildSearchNameWithFallback(
            $name,
            $series,
            $episode,
            $airDate,
            $showInfo['sql'] ?? ''
        );

        // Perform search if name provided
        $searchResult = [];
        if (! empty($searchName)) {
            $searchResult = $this->performTvSearch($searchName, $limit);
            if (empty($searchResult)) {
                return collect();
            }
        }

        // Build and execute main query
        return $this->executeApiTvSearch(
            $showInfo['sql'] ?? '',
            $searchResult,
            $cat,
            $maxAge,
            $minSize,
            $excludedCategories,
            $limit,
            $offset
        );
    }

    /**
     * Build search name with fallback information.
     */
    private function buildSearchNameWithFallback(
        string $name,
        string $series,
        string $episode,
        string $airDate,
        string $showSql
    ): string {
        if (empty($name) || ! empty($showSql)) {
            return '';
        }

        if (! empty($series) && (int) $series < 1900) {
            $name .= sprintf(' S%s', str_pad($series, 2, '0', STR_PAD_LEFT));
            if (! empty($episode) && ! str_contains($episode, '/')) {
                $name .= sprintf('E%s', str_pad($episode, 2, '0', STR_PAD_LEFT));
            } elseif (empty($episode)) {
                $name .= '*';
            }
        } elseif (! empty($airDate)) {
            $name .= ' '.str_replace(['/', '-', '.', '_'], ' ', $airDate);
        }

        return $name;
    }

    /**
     * Execute API TV search query.
     */
    private function executeApiTvSearch(
        string $showSql,
        array $searchResult,
        array $cat,
        int $maxAge,
        int $minSize,
        array $excludedCategories,
        int $limit,
        int $offset
    ): Collection {
        $whereSql = $this->buildApiTvSearchConditions(
            $showSql,
            $searchResult,
            $cat,
            $maxAge,
            $minSize,
            $excludedCategories
        );

        $baseSql = $this->buildApiTvSearchQuery($whereSql);
        $sql = $this->buildPaginatedApiTvQuery($baseSql, $limit, $offset);

        // Check cache
        $cacheKey = md5($sql);
        if ($releases = Cache::get($cacheKey)) {
            return $releases;
        }

        $releases = $this->fromQuery($sql);
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount(
                preg_replace('#LEFT(\s+OUTER)?\s+JOIN\s+(?!tv_episodes)\s+.*ON.*=.*\n#i', ' ', $baseSql)
            );
        }

        Cache::put($cacheKey, $releases, now()->addMinutes(config('nntmux.cache_expiry_medium')));

        return $releases;
    }

    /**
     * Build API TV search conditions.
     */
    private function buildApiTvSearchConditions(
        string $showSql,
        array $searchResult,
        array $cat,
        int $maxAge,
        int $minSize,
        array $excludedCategories
    ): string {
        return sprintf(
            'WHERE r.nzbstatus = %d
            AND r.passwordstatus %s
            %s %s %s %s %s %s',
            NZB::NZB_ADDED,
            $this->showPasswords(),
            $showSql,
            ! empty($searchResult) ? 'AND r.id IN ('.implode(',', $searchResult).')' : '',
            Category::getCategorySearch($cat, 'tv'),
            $maxAge > 0 ? sprintf('AND r.postdate > NOW() - INTERVAL %d DAY', $maxAge) : '',
            $minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : '',
            ! empty($excludedCategories) ? sprintf('AND r.categories_id NOT IN('.implode(',', $excludedCategories).')') : ''
        );
    }

    /**
     * Build base API TV search query.
     */
    private function buildApiTvSearchQuery(string $whereSql): string
    {
        return sprintf(
            'SELECT r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id,
            r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments,
            r.adddate, r.tv_episodes_id, r.haspreview, r.jpgstatus,
            v.title, v.type, v.tvdb, v.trakt, v.imdb, v.tmdb, v.tvmaze, v.tvrage,
            tve.series, tve.episode, tve.se_complete, tve.title, tve.firstaired,
            cp.title AS parent_category, c.title AS sub_category,
            CONCAT(cp.title, " > ", c.title) AS category_name,
            g.name AS group_name
            FROM releases r
            LEFT OUTER JOIN videos v ON r.videos_id = v.id AND v.type = 0
            LEFT OUTER JOIN tv_info tvi ON v.id = tvi.videos_id
            LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
            LEFT JOIN categories c ON c.id = r.categories_id
            LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
            LEFT JOIN usenet_groups g ON g.id = r.groups_id
            %s',
            $whereSql
        );
    }

    /**
     * Build paginated API TV query.
     */
    private function buildPaginatedApiTvQuery(string $baseSql, int $limit, int $offset): string
    {
        return sprintf(
            '%s ORDER BY postdate DESC LIMIT %d OFFSET %d',
            $baseSql,
            $limit,
            $offset
        );
    }

    /**
     * Search anime releases.
     *
     * @param  int  $aniDbID  AniDB ID to filter by (-1 for all)
     * @param  int  $offset  Pagination offset
     * @param  int  $limit  Results per page
     * @param  string  $name  Search term
     * @param  array  $cat  Category IDs to include
     * @param  int  $maxAge  Maximum age in days (-1 for no limit)
     * @param  array  $excludedCategories  Category IDs to exclude
     * @return Collection Query results
     */
    public function animeSearch(
        int $aniDbID = -1,
        int $offset = 0,
        int $limit = 100,
        string $name = '',
        array $cat = [-1],
        int $maxAge = -1,
        array $excludedCategories = []
    ): Collection {
        // Perform search if name provided
        $searchResult = [];
        if (! empty($name)) {
            $searchResult = $this->performAnimeSearch($name, $limit);
            if (empty($searchResult)) {
                return collect();
            }
        }

        // Build and execute main query
        return $this->executeAnimeSearch(
            $aniDbID,
            $searchResult,
            $cat,
            $maxAge,
            $excludedCategories,
            $limit,
            $offset
        );
    }

    /**
     * Perform anime search using configured search engine.
     */
    private function performAnimeSearch(string $name, int $limit): array
    {
        return config('nntmux.elasticsearch_enabled')
            ? $this->elasticSearch->indexSearchTMA($name, $limit)
            : $this->getManticoreResults(['searchname' => $name]);
    }

    /**
     * Execute main anime search query.
     */
    private function executeAnimeSearch(
        int $aniDbID,
        array $searchResult,
        array $cat,
        int $maxAge,
        array $excludedCategories,
        int $limit,
        int $offset
    ): Collection {
        $conditions = $this->buildAnimeSearchConditions(
            $aniDbID,
            $searchResult,
            $cat,
            $maxAge,
            $excludedCategories
        );

        $baseSql = $this->buildAnimeSearchQuery($conditions);
        $sql = $this->buildPaginatedAnimeQuery($baseSql, $limit, $offset);

        // Check cache
        $cacheKey = md5($sql);
        if ($releases = Cache::get($cacheKey)) {
            return $releases;
        }

        $releases = $this->fromQuery($sql);
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount($baseSql);
        }

        Cache::put($cacheKey, $releases, now()->addMinutes(config('nntmux.cache_expiry_medium')));

        return $releases;
    }

    /**
     * Build anime search conditions.
     */
    private function buildAnimeSearchConditions(
        int $aniDbID,
        array $searchResult,
        array $cat,
        int $maxAge,
        array $excludedCategories
    ): string {
        return sprintf(
            'WHERE r.passwordstatus %s
            AND r.nzbstatus = %d
            %s %s %s %s %s',
            $this->showPasswords(),
            NZB::NZB_ADDED,
            $aniDbID > -1 ? sprintf('AND r.anidbid = %d', $aniDbID) : '',
            ! empty($searchResult) ? 'AND r.id IN ('.implode(',', $searchResult).')' : '',
            ! empty($excludedCategories) ? sprintf('AND r.categories_id NOT IN('.implode(',', $excludedCategories).')') : '',
            Category::getCategorySearch($cat),
            $maxAge > 0 ? sprintf('AND r.postdate > NOW() - INTERVAL %d DAY', $maxAge) : ''
        );
    }

    /**
     * Build base anime search query.
     */
    private function buildAnimeSearchQuery(string $conditions): string
    {
        return sprintf(
            'SELECT r.id, r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id,
            r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments,
            r.adddate, r.haspreview, r.jpgstatus, cp.title AS parent_category,
            c.title AS sub_category, CONCAT(cp.title, " > ", c.title) AS category_name,
            g.name AS group_name, rn.releases_id AS nfoid
            FROM releases r
            LEFT JOIN categories c ON c.id = r.categories_id
            LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
            LEFT JOIN usenet_groups g ON g.id = r.groups_id
            LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
            %s',
            $conditions
        );
    }

    /**
     * Build paginated anime query.
     */
    private function buildPaginatedAnimeQuery(string $baseSql, int $limit, int $offset): string
    {
        return sprintf(
            '%s ORDER BY postdate DESC LIMIT %d OFFSET %d',
            $baseSql,
            $limit,
            $offset
        );
    }

    /**
     * Search for movie releases via API and site.
     *
     * @param  int  $imDbId  IMDB ID to filter by (-1 for all)
     * @param  int  $tmDbId  TMDB ID to filter by (-1 for all)
     * @param  int  $traktId  Trakt ID to filter by (-1 for all)
     * @param  int  $offset  Pagination offset
     * @param  int  $limit  Results per page
     * @param  string  $name  Search term
     * @param  array  $cat  Category IDs to include
     * @param  int  $maxAge  Maximum age in days (-1 for no limit)
     * @param  int  $minSize  Minimum size in bytes
     * @param  array  $excludedCategories  Category IDs to exclude
     * @return Collection Query results
     */
    public function moviesSearch(
        int $imDbId = -1,
        int $tmDbId = -1,
        int $traktId = -1,
        int $offset = 0,
        int $limit = 100,
        string $name = '',
        array $cat = [-1],
        int $maxAge = -1,
        int $minSize = 0,
        array $excludedCategories = []
    ): Collection {
        // Perform search if name provided
        $searchResult = [];
        if (! empty($name)) {
            $searchResult = $this->performMovieSearch($name, $limit);
            if (empty($searchResult)) {
                return collect();
            }
        }

        // Build and execute main query
        return $this->executeMovieSearch(
            $imDbId,
            $tmDbId,
            $traktId,
            $searchResult,
            $cat,
            $maxAge,
            $minSize,
            $excludedCategories,
            $limit,
            $offset
        );
    }

    /**
     * Perform movie search using configured search engine.
     */
    private function performMovieSearch(string $name, int $limit): array
    {
        $result = config('nntmux.elasticsearch_enabled')
            ? $this->elasticSearch->indexSearchTMA($name, $limit)
            : $this->getManticoreResults(['searchname' => $name]);

        return $result;
    }

    /**
     * Execute main movie search query.
     */
    private function executeMovieSearch(
        int $imDbId,
        int $tmDbId,
        int $traktId,
        array $searchResult,
        array $cat,
        int $maxAge,
        int $minSize,
        array $excludedCategories,
        int $limit,
        int $offset
    ): Collection {
        $conditions = $this->buildMovieSearchConditions(
            $imDbId,
            $tmDbId,
            $traktId,
            $searchResult,
            $cat,
            $maxAge,
            $minSize,
            $excludedCategories
        );

        $baseSql = $this->buildMovieSearchQuery($conditions);
        $sql = $this->buildPaginatedMovieQuery($baseSql, $limit, $offset);

        // Check cache
        $cacheKey = md5($sql);
        if ($releases = Cache::get($cacheKey)) {
            return $releases;
        }

        $releases = $this->fromQuery($sql);
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount($baseSql);
        }

        Cache::put($cacheKey, $releases, now()->addMinutes(config('nntmux.cache_expiry_medium')));

        return $releases;
    }

    /**
     * Build movie search conditions.
     */
    private function buildMovieSearchConditions(
        int $imDbId,
        int $tmDbId,
        int $traktId,
        array $searchResult,
        array $cat,
        int $maxAge,
        int $minSize,
        array $excludedCategories
    ): string {
        return sprintf(
            'WHERE r.categories_id BETWEEN %d AND %d
            AND r.nzbstatus = %d
            AND r.passwordstatus %s
            %s %s %s %s %s %s %s %s',
            Category::MOVIE_ROOT,
            Category::MOVIE_OTHER,
            NZB::NZB_ADDED,
            $this->showPasswords(),
            ! empty($searchResult) ? 'AND r.id IN ('.implode(',', $searchResult).')' : '',
            ($imDbId !== -1 && $imDbId) ? sprintf(' AND m.imdbid = %s', escapeString($imDbId)) : '',
            ($tmDbId !== -1 && $tmDbId) ? sprintf(' AND m.tmdbid = %d', $tmDbId) : '',
            ($traktId !== -1 && $traktId) ? sprintf(' AND m.traktid = %d', $traktId) : '',
            ! empty($excludedCategories) ? sprintf('AND r.categories_id NOT IN(%s)', implode(',', $excludedCategories)) : '',
            Category::getCategorySearch($cat, 'movies'),
            $maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY', $maxAge) : '',
            $minSize > 0 ? sprintf(' AND r.size >= %d', $minSize) : ''
        );
    }

    /**
     * Build base movie search query.
     */
    private function buildMovieSearchQuery(string $conditions): string
    {
        return sprintf(
            'SELECT r.id, r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id,
            r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments,
            r.adddate, r.imdbid, r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus,
            m.imdbid, m.tmdbid, m.traktid,
            cp.title AS parent_category, c.title AS sub_category,
            CONCAT(cp.title, " > ", c.title) AS category_name,
            g.name AS group_name,
            rn.releases_id AS nfoid
            FROM releases r
            LEFT JOIN movieinfo m ON m.id = r.movieinfo_id
            LEFT JOIN usenet_groups g ON g.id = r.groups_id
            LEFT JOIN categories c ON c.id = r.categories_id
            LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
            LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
            %s',
            $conditions
        );
    }

    /**
     * Build paginated movie query.
     */
    private function buildPaginatedMovieQuery(string $baseSql, int $limit, int $offset): string
    {
        return sprintf(
            '%s ORDER BY postdate DESC LIMIT %d OFFSET %d',
            $baseSql,
            $limit,
            $offset
        );
    }

    /**
     * Search for similar releases in the same parent category.
     *
     * @param  int  $currentID  Current release ID
     * @param  string  $name  Release name to find similar matches for
     * @param  array  $excludedCats  Category IDs to exclude
     * @return Collection|false Collection of similar releases or false if none found
     */
    public function searchSimilar(int $currentID, string $name, array $excludedCats = []): Collection|false
    {
        // Get release category info
        $currRow = self::getCatByRelId($currentID);
        if ($currRow === null) {
            return false;
        }

        // Get parent category
        $parentCat = Category::query()
            ->where('id', $currRow['categories_id'])
            ->value('root_categories_id');

        if ($parentCat === null) {
            return false;
        }

        // Search for similar releases
        $searchResults = $this->search(
            ['searchname' => getSimilarName($name)],
            groupName: -1,
            sizeFrom: -1,
            sizeTo: -1,
            daysNew: -1,
            daysOld: -1,
            offset: 0,
            limit: config('nntmux.items_per_page'),
            orderBy: '',
            maxAge: -1,
            excludedCats: $excludedCats,
            type: 'basic',
            cat: [$parentCat]
        );

        if ($searchResults->isEmpty()) {
            return false;
        }

        // Filter results to only include items in same parent category
        return $searchResults->filter(function ($result) use ($currentID, $parentCat) {
            return $result['id'] !== $currentID && $result['categoryparentid'] === $parentCat;
        });
    }

    /**
     * Get the count of releases for pager with a maximum limit.
     *
     * @param  string  $query  Base query to count from
     * @return int Total number of matches (limited by max_pager_results)
     *
     * @throws \RuntimeException If query rewriting fails
     */
    private function getPagerCount(string $query): int
    {
        // Get configuration values
        $maxResults = (int) config('nntmux.max_pager_results', 25000);
        $cacheExpiry = (int) config('nntmux.cache_expiry_short', 15);

        try {
            // Optimize query by selecting only IDs and applying limit early
            $rewrittenQuery = preg_replace(
                '/SELECT\s+.*?\s+FROM\s+releases/is',
                'SELECT r.id FROM releases',
                $query,
                1,
                $count
            );

            if ($rewrittenQuery === null || $count !== 1) {
                throw new \RuntimeException('Failed to rewrite query for counting');
            }

            // Build optimized counting query
            $countQuery = sprintf(
                'SELECT COUNT(*) as count FROM (%s LIMIT %d) z',
                $rewrittenQuery,
                $maxResults
            );

            // Generate cache key from final query
            $cacheKey = md5($countQuery);

            // Try to get cached count
            if ($cachedCount = Cache::get($cacheKey)) {
                return (int) $cachedCount;
            }

            // Execute count query and handle result
            $result = DB::selectOne($countQuery);
            $count = (int) ($result->count ?? 0);

            // Cache positive counts
            if ($count > 0) {
                Cache::put($cacheKey, $count, now()->addMinutes($cacheExpiry));
            }

            return $count;
        } catch (\Exception $e) {
            // Log error and return 0 on failure
            Log::error('getPagerCount failed: '.$e->getMessage());

            return 0;
        }
    }
}
