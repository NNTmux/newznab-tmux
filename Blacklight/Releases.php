<?php

namespace Blacklight;

use App\Models\Group;
use App\Models\Video;
use App\Models\Release;
use App\Models\Category;
use App\Models\Settings;
use Illuminate\Support\Carbon;
use Blacklight\utility\Utility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Class Releases.
 */
class Releases
{
    // RAR/ZIP Passworded indicator.
    public const PASSWD_NONE = 0; // No password.
    public const PASSWD_POTENTIAL = 1; // Might have a password.
    public const BAD_FILE = 2; // Possibly broken RAR/ZIP.
    public const PASSWD_RAR = 10; // Definitely passworded.

    /**
     * @var \Blacklight\db\DB
     */
    public $pdo;

    /**
     * @var \Blacklight\ReleaseSearch
     */
    public $releaseSearch;

    /**
     * @var \Blacklight\SphinxSearch
     */
    public $sphinxSearch;

    /**
     * @var string
     */
    public $showPasswords;

    /**
     * @var int
     */
    public $passwordStatus;

    /**
     * @var array Class instances.
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Settings' => null,
            'Groups'   => null,
        ];
        $options += $defaults;
        $this->pdo = DB::connection()->getPdo();

        $this->sphinxSearch = new SphinxSearch();
        $this->releaseSearch = new ReleaseSearch();
        $this->showPasswords = self::showPasswords();
    }

    /**
     * Used for browse results.
     *
     * @param              $page
     * @param array        $cat
     * @param              $start
     * @param              $num
     * @param string|array $orderBy
     * @param int          $maxAge
     * @param array        $excludedCats
     * @param string|int   $groupName
     * @param int          $minSize
     *
     * @return array
     */
    public function getBrowseRange($page, $cat, $start, $num, $orderBy, $maxAge = -1, array $excludedCats = [], $groupName = -1, $minSize = 0): array
    {
        $orderBy = $this->getBrowseOrder($orderBy);
        $qry = sprintf(
            "SELECT r.*,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				CONCAT(cp.id, ',', c.id) AS category_ids,
				df.failed AS failed,
				rn.releases_id AS nfoid,
				re.releases_id AS reid,
				v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.imdb, v.tmdb,
				tve.title, tve.firstaired
			FROM
			(
				SELECT r.*, g.name AS group_name
				FROM releases r
				LEFT JOIN groups g ON g.id = r.groups_id
				WHERE r.nzbstatus = %d
				AND r.passwordstatus %s
				%s %s %s %s %s
				ORDER BY %s %s %s
			) r
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN categories cp ON cp.id = c.parentid
			LEFT OUTER JOIN videos v ON r.videos_id = v.id
			LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
			LEFT OUTER JOIN video_data re ON re.releases_id = r.id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
			GROUP BY r.id
			ORDER BY %8\$s %9\$s",
            NZB::NZB_ADDED,
            $this->showPasswords,
            Category::getCategorySearch($cat),
            ($maxAge > 0 ? (' AND postdate > NOW() - INTERVAL '.$maxAge.' DAY ') : ''),
            (\count($excludedCats) ? (' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')') : ''),
            ((int) $groupName !== -1 ? sprintf(' AND g.name = %s ', $this->pdo->quote($groupName)) : ''),
            ($minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : ''),
            $orderBy[0],
            $orderBy[1],
            ($start === false ? '' : ' LIMIT '.$num.' OFFSET '.$start)
        );
        $releases = Cache::get(md5($qry.$page));
        if ($releases !== null) {
            return $releases;
        }
        $sql = DB::select($qry);
        if (\count($sql) > 0) {
            $possibleRows = $this->getBrowseCount($cat, $maxAge, $excludedCats, $groupName);
            $sql[0]->_totalcount = $sql[0]->_totalrows = $possibleRows;
        }
        $expiresAt = Carbon::now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($qry.$page), $sql, $expiresAt);

        return $sql;
    }

    /**
     * Used for pager on browse page.
     *
     * @param array  $cat
     * @param int    $maxAge
     * @param array  $excludedCats
     * @param string|int $groupName
     *
     * @return int
     */
    public function getBrowseCount($cat, $maxAge = -1, array $excludedCats = [], $groupName = ''): int
    {
        $sql = sprintf(
            'SELECT COUNT(r.id) AS count
				FROM releases r
				%s
				WHERE r.nzbstatus = %d
				AND r.passwordstatus %s
				%s %s %s %s',
            ($groupName !== -1 ? 'LEFT JOIN groups g ON g.id = r.groups_id' : ''),
            NZB::NZB_ADDED,
            $this->showPasswords,
            ($groupName !== -1 ? sprintf(' AND g.name = %s', $this->pdo->quote($groupName)) : ''),
            Category::getCategorySearch($cat),
            ($maxAge > 0 ? (' AND r.postdate > NOW() - INTERVAL '.$maxAge.' DAY ') : ''),
            (\count($excludedCats) ? (' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')') : '')
        );
        $count = Cache::get(md5($sql));
        if ($count !== null) {
            return $count;
        }
        $count = DB::select($sql);
        $expiresAt = Carbon::now()->addMinutes(config('nntmux.cache_expiry_short'));
        Cache::put(md5($sql), $count[0]->count, $expiresAt);

        return $count[0]->count ?? 0;
    }

    /**
     * @param null $query
     * @param bool $builder
     *
     * @return string|\Illuminate\Database\Query\Builder
     * @throws \Exception
     */
    public static function showPasswords($query = null, $builder = false)
    {
        $setting = Settings::settingValue('..showpasswordedrelease');
        $setting = ($setting !== null && is_numeric($setting)) ? $setting : 10;
        switch ($setting) {
            case 0: // Hide releases with a password or a potential password (Hide unprocessed releases).
                if ($builder === false) {
                    return '='.self::PASSWD_NONE;
                }

                return $query->where('r.passwordstatus', self::PASSWD_NONE);
            case 1: // Show releases with no password or a potential password (Show unprocessed releases).
                if ($builder === false) {
                    return '<= '.self::PASSWD_POTENTIAL;
                }

                return $query->where('r.passwordstatus', '=<', self::PASSWD_POTENTIAL);
            case 2: // Hide releases with a password or a potential password (Show unprocessed releases).
                if ($builder === false) {
                    return '<= '.self::PASSWD_NONE;
                }

                return $query->where('r.passwordstatus', '=<', self::PASSWD_NONE);
            case 10: // Shows everything.
            default:
                if ($builder === false) {
                    return '<= '.self::PASSWD_RAR;
                }

                return $query->where('r.passwordstatus', '=<', self::PASSWD_RAR);
        }
    }

    /**
     * Use to order releases on site.
     *
     * @param string|array $orderBy
     *
     * @return array
     */
    public function getBrowseOrder($orderBy): array
    {
        $orderArr = explode('_', ($orderBy === '' ? 'posted_desc' : $orderBy));
        switch ($orderArr[0]) {
            case 'cat':
                $orderField = 'categories_id';
                break;
            case 'name':
                $orderField = 'searchname';
                break;
            case 'size':
                $orderField = 'size';
                break;
            case 'files':
                $orderField = 'totalpart';
                break;
            case 'stats':
                $orderField = 'grabs';
                break;
            case 'posted':
            default:
                $orderField = 'postdate';
                break;
        }

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
     * Get list of releases available for export.
     *
     *
     * @param string $postFrom
     * @param string $postTo
     * @param string $groupID
     *
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public function getForExport($postFrom = '', $postTo = '', $groupID = '')
    {
        $query = Release::query()
            ->where('r.nzbstatus', NZB::NZB_ADDED)
            ->select(['r.searchname', 'r.guid', 'g.name as gname', DB::raw("CONCAT(cp.title,'_',c.title) AS catName")])
            ->from('releases as r')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
            ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
            ->leftJoin('groups as g', 'g.id', '=', 'r.groups_id');

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
     * Get date in this format : 01/01/2014 of the oldest release.
     *
     * @note Used for exporting NZB's.
     * @return mixed
     */
    public function getEarliestUsenetPostDate()
    {
        $row = Release::query()->selectRaw("DATE_FORMAT(min(postdate), '%d/%m/%Y') AS postdate")->first();

        return $row === null ? '01/01/2014' : $row['postdate'];
    }

    /**
     * Get date in this format : 01/01/2014 of the newest release.
     *
     * @note Used for exporting NZB's.
     * @return mixed
     */
    public function getLatestUsenetPostDate()
    {
        $row = Release::query()->selectRaw("DATE_FORMAT(max(postdate), '%d/%m/%Y') AS postdate")->first();

        return $row === null ? '01/01/2014' : $row['postdate'];
    }

    /**
     * Gets all groups for drop down selection on NZB-Export web page.
     *
     * @param bool $blnIncludeAll
     *
     * @note Used for exporting NZB's.
     * @return array
     */
    public function getReleasedGroupsForSelect($blnIncludeAll = true): array
    {
        $groups = Release::query()
            ->selectRaw('DISTINCT g.id, g.name')
            ->leftJoin('groups as g', 'g.id', '=', 'releases.groups_id')
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
     * Cache of concatenated category ID's used in queries.
     * @var null|array
     */
    private $concatenatedCategoryIDsCache = null;

    /**
     * Gets / sets a string of concatenated category ID's used in queries.
     *
     * @return array|null|string
     */
    public function getConcatenatedCategoryIDs()
    {
        if ($this->concatenatedCategoryIDsCache === null) {
            $result = Category::query()
                ->remember(config('nntmux.cache_expiry_long'))
                ->whereNotNull('categories.parentid')
                ->whereNotNull('cp.id')
                ->selectRaw('CONCAT(cp.id, ", ", categories.id) AS category_ids')
                ->leftJoin('categories as cp', 'cp.id', '=', 'categories.parentid')
                ->get();
            if (isset($result[0]['category_ids'])) {
                $this->concatenatedCategoryIDsCache = $result[0]['category_ids'];
            }
        }

        return $this->concatenatedCategoryIDsCache;
    }

    /**
     * Get TV for my shows page.
     *
     * @param          $userShows
     * @param int|bool $offset
     * @param int      $limit
     * @param string|array   $orderBy
     * @param int      $maxAge
     * @param array    $excludedCats
     *
     * @return array
     */
    public function getShowsRange($userShows, $offset, $limit, $orderBy, $maxAge = -1, $excludedCats = [])
    {
        $orderBy = $this->getBrowseOrder($orderBy);
        $sql = sprintf(
                "SELECT r.*,
					CONCAT(cp.title, '-', c.title) AS category_name,
					%s AS category_ids,
					groups.name AS group_name,
					rn.releases_id AS nfoid, re.releases_id AS reid,
					tve.firstaired,
					df.failed AS failed
				FROM releases r
				LEFT OUTER JOIN video_data re ON re.releases_id = r.id
				LEFT JOIN groups ON groups.id = r.groups_id
				LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
				LEFT OUTER JOIN tv_episodes tve ON tve.videos_id = r.videos_id
				LEFT JOIN categories c ON c.id = r.categories_id
				LEFT JOIN categories cp ON cp.id = c.parentid
				LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
				WHERE %s %s
				AND r.nzbstatus = %d
				AND r.categories_id BETWEEN %d AND %d
				AND r.passwordstatus %s
				%s
				GROUP BY r.id
				ORDER BY %s %s %s",
                $this->getConcatenatedCategoryIDs(),
                $this->uSQL($userShows, 'videos_id'),
                (\count($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
                NZB::NZB_ADDED,
                Category::TV_ROOT,
                Category::TV_OTHER,
                $this->showPasswords,
                ($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : ''),
                $orderBy[0],
                $orderBy[1],
                ($offset === false ? '' : (' LIMIT '.$limit.' OFFSET '.$offset))
        );

        $expiresAt = Carbon::now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $result = Cache::get(md5($sql));
        if ($result !== null) {
            return $result;
        }

        $result = DB::select($sql);
        Cache::put(md5($sql), $result, $expiresAt);

        return $result;
    }

    /**
     * Get count for my shows page pagination.
     *
     * @param       $userShows
     * @param int   $maxAge
     * @param array $excludedCats
     *
     * @return int
     */
    public function getShowsCount($userShows, $maxAge = -1, $excludedCats = [])
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
                $this->showPasswords,
                ($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : '')
            )
        );
    }

    /**
     * Delete multiple releases, or a single by ID.
     *
     * @param array|int|string $list   Array of GUID or ID of releases to delete.
     * @throws \Exception
     */
    public function deleteMultiple($list): void
    {
        $list = (array) $list;

        $nzb = new NZB();
        $releaseImage = new ReleaseImage();

        foreach ($list as $identifier) {
            $this->deleteSingle(['g' => $identifier, 'i' => false], $nzb, $releaseImage);
        }
    }

    /**
     * Deletes a single release by GUID, and all the corresponding files.
     *
     * @param array        $identifiers ['g' => Release GUID(mandatory), 'id => ReleaseID(optional, pass false)]
     * @param NZB          $nzb
     * @param ReleaseImage $releaseImage
     */
    public function deleteSingle($identifiers, $nzb, $releaseImage): void
    {
        // Delete NZB from disk.
        $nzbPath = $nzb->NZBPath($identifiers['g']);
        if ($nzbPath) {
            @unlink($nzbPath);
        }

        // Delete images.
        $releaseImage->delete($identifiers['g']);

        // Delete from sphinx.
        $this->sphinxSearch->deleteRelease($identifiers);

        // Delete from DB.
        Release::query()->where('guid', $identifiers['g'])->delete();
    }

    /**
     * @param $guids
     * @param $category
     * @param $grabs
     * @param $videoId
     * @param $episodeId
     * @param $anidbId
     * @param $imdbId
     * @return bool|int
     */
    public function updateMulti($guids, $category, $grabs, $videoId, $episodeId, $anidbId, $imdbId)
    {
        if (! \is_array($guids) || \count($guids) < 1) {
            return false;
        }

        $update = [
            'categories_id'     => $category === -1 ? 'categories_id' : $category,
            'grabs'          => $grabs,
            'videos_id'      => $videoId,
            'tv_episodes_id' => $episodeId,
            'anidbid'        => $anidbId,
            'imdbid'         => $imdbId,
        ];

        return Release::query()->whereIn('guid', $guids)->update($update);
    }

    /**
     * Creates part of a query for some functions.
     *
     * @param array  $userQuery
     * @param string $type
     *
     * @return string
     */
    public function uSQL($userQuery, $type)
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
     * @param string|int $searchName
     * @param string|int $usenetName
     * @param string|int $posterName
     * @param string|int $fileName
     * @param string|int $groupName
     * @param int $sizeFrom
     * @param int $sizeTo
     * @param int $hasNfo
     * @param int $hasComments
     * @param int $daysNew
     * @param int $daysOld
     * @param int $offset
     * @param int $limit
     * @param string|array $orderBy
     * @param int $maxAge
     * @param int|array $excludedCats
     * @param string $type
     * @param array $cat
     *
     * @param int $minSize
     * @return array
     */
    public function search($searchName, $usenetName, $posterName, $fileName, $groupName, $sizeFrom, $sizeTo, $hasNfo, $hasComments, $daysNew, $daysOld, $offset = 0, $limit = 1000, $orderBy = '', $maxAge = -1, array $excludedCats = [], $type = 'basic', array $cat = [-1], $minSize = 0): array
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
        if ($orderBy === '') {
            $orderBy = [];
            $orderBy[0] = 'postdate ';
            $orderBy[1] = 'desc ';
        } else {
            $orderBy = $this->getBrowseOrder($orderBy);
        }
        $searchOptions = [];
        if ($searchName !== -1) {
            $searchOptions['searchname'] = $searchName;
        }
        if ($usenetName !== -1) {
            $searchOptions['name'] = $usenetName;
        }
        if ($posterName !== -1) {
            $searchOptions['fromname'] = $posterName;
        }
        if ($fileName !== -1) {
            $searchOptions['filename'] = $fileName;
        }
        $catQuery = '';
        if ($type === 'basic') {
            $catQuery = Category::getCategorySearch($cat);
        } elseif ($type === 'advanced' && (int) $cat[0] !== -1) {
            $catQuery = sprintf('AND r.categories_id = %d', $cat[0]);
        }
        $whereSql = sprintf(
            '%s WHERE r.passwordstatus %s AND r.nzbstatus = %d %s %s %s %s %s %s %s %s %s %s %s %s',
            $this->releaseSearch->getFullTextJoinString(),
            $this->showPasswords,
            NZB::NZB_ADDED,
            ($maxAge > 0 ? sprintf(' AND r.postdate > (NOW() - INTERVAL %d DAY) ', $maxAge) : ''),
            ((int) $groupName !== -1 ? sprintf(' AND r.groups_id = %d ', Group::getIDByName($groupName)) : ''),
            (array_key_exists($sizeFrom, $sizeRange) ? ' AND r.size > '.(string) (104857600 * (int) $sizeRange[$sizeFrom]).' ' : ''),
            (array_key_exists($sizeTo, $sizeRange) ? ' AND r.size < '.(string) (104857600 * (int) $sizeRange[$sizeTo]).' ' : ''),
            ((int) $hasNfo !== 0 ? ' AND r.nfostatus = 1 ' : ''),
            ((int) $hasComments !== 0 ? ' AND r.comments > 0 ' : ''),
            $catQuery,
            ((int) $daysNew !== -1 ? sprintf(' AND r.postdate < (NOW() - INTERVAL %d DAY) ', $daysNew) : ''),
            ((int) $daysOld !== -1 ? sprintf(' AND r.postdate > (NOW() - INTERVAL %d DAY) ', $daysOld) : ''),
            (\count($excludedCats) > 0 ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
            (\count($searchOptions) > 0 ? $this->releaseSearch->getSearchSQL($searchOptions) : ''),
            ($minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : '')
        );
        $baseSql = sprintf(
            "SELECT r.*,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				%s AS category_ids,
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
			LEFT JOIN groups g ON g.id = r.groups_id
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN categories cp ON cp.id = c.parentid
			LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
			%s",
            $this->getConcatenatedCategoryIDs(),
            $whereSql
        );
        $sql = sprintf(
            'SELECT * FROM (
				%s
			) r
			ORDER BY r.%s %s
			LIMIT %d OFFSET %d',
            $baseSql,
            $orderBy[0],
            $orderBy[1],
            $limit,
            $offset
        );
        $releases = Cache::get(md5($sql));
        if ($releases !== null) {
            return $releases;
        }
        $releases = DB::select($sql);
        if (! empty($releases) && \count($releases) > 0) {
            $releases[0]->_totalrows = $this->getPagerCount($baseSql);
        }
        $expiresAt = Carbon::now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($sql), $releases, $expiresAt);

        return $releases;
    }

    /**
     * Search TV Shows via the API.
     *
     * @param array  $siteIdArr Array containing all possible TV Processing site IDs desired
     * @param string $series The series or season number requested
     * @param string $episode The episode number requested
     * @param string $airdate The airdate of the episode requested
     * @param int    $offset Skip this many releases
     * @param int    $limit Return this many releases
     * @param string $name The show name to search
     * @param array  $cat The category to search
     * @param int    $maxAge The maximum age of releases to be returned
     * @param int    $minSize The minimum size of releases to be returned
     *
     * @return array
     */
    public function tvSearch(array $siteIdArr = [], $series = '', $episode = '', $airdate = '', $offset = 0, $limit = 100, $name = '', array $cat = [-1], $maxAge = -1, $minSize = 0): array
    {
        $siteSQL = [];
        $showSql = '';
        if (\is_array($siteIdArr)) {
            foreach ($siteIdArr as $column => $Id) {
                if ($Id > 0) {
                    $siteSQL[] = sprintf('v.%s = %d', $column, $Id);
                }
            }
        }
        if (\count($siteSQL) > 0) {
            // If we have show info, find the Episode ID/Video ID first to avoid table scans
            $showQry = sprintf(
                "
				SELECT
					v.id AS video,
					GROUP_CONCAT(tve.id SEPARATOR ',') AS episodes
				FROM videos v
				LEFT JOIN tv_episodes tve ON v.id = tve.videos_id
				WHERE (%s) %s %s %s
				GROUP BY v.id
				LIMIT 1",
                implode(' OR ', $siteSQL),
                ($series !== '' ? sprintf('AND tve.series = %d', (int) preg_replace('/^s0*/i', '', $series)) : ''),
                ($episode !== '' ? sprintf('AND tve.episode = %d', (int) preg_replace('/^e0*/i', '', $episode)) : ''),
                ($airdate !== '' ? sprintf('AND DATE(tve.firstaired) = %s', $this->pdo->escapeString($airdate)) : '')
            );
            $show = DB::select($showQry);

            if (! empty($show)) {
                if ((! empty($series) || ! empty($episode) || ! empty($airdate)) && \strlen($show[0]->episodes) > 0) {
                    $showSql = sprintf('AND r.tv_episodes_id IN (%s)', $show[0]->episodes);
                } elseif ((int) $show[0]->video > 0) {
                    $showSql = 'AND r.videos_id = '.$show[0]->video;
                    // If $series is set but episode is not, return Season Packs only
                    if (! empty($series) && empty($episode)) {
                        $showSql .= ' AND r.tv_episodes_id = 0';
                    }
                } else {
                    // If we were passed Episode Info and no match was found, do not run the query
                    return [];
                }
            } else {
                // If we were passed Site ID Info and no match was found, do not run the query
                return [];
            }
        }
        // If $name is set it is a fallback search, add available SxxExx/airdate info to the query
        if (! empty($name) && $showSql === '') {
            if (! empty($series) && (int) $series < 1900) {
                $name .= sprintf(' S%s', str_pad($series, 2, '0', STR_PAD_LEFT));
                if (! empty($episode) && strpos($episode, '/') === false) {
                    $name .= sprintf('E%s', str_pad($episode, 2, '0', STR_PAD_LEFT));
                }
            } elseif (! empty($airdate)) {
                $name .= sprintf(' %s', str_replace(['/', '-', '.', '_'], ' ', $airdate));
            }
        }
        $whereSql = sprintf(
            '%s
			WHERE r.nzbstatus = %d
			AND r.passwordstatus %s
			%s %s %s %s %s',
            ($name !== '' ? $this->releaseSearch->getFullTextJoinString() : ''),
            NZB::NZB_ADDED,
            $this->showPasswords,
            $showSql,
            ($name !== '' ? $this->releaseSearch->getSearchSQL(['searchname' => $name]) : ''),
            Category::getCategorySearch($cat),
            ($maxAge > 0 ? sprintf('AND r.postdate > NOW() - INTERVAL %d DAY', $maxAge) : ''),
            ($minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : '')
        );
        $baseSql = sprintf(
            "SELECT r.*,
				v.title, v.countries_id, v.started, v.tvdb, v.trakt,
					v.imdb, v.tmdb, v.tvmaze, v.tvrage, v.source,
				tvi.summary, tvi.publisher, tvi.image,
				tve.series, tve.episode, tve.se_complete, tve.title, tve.firstaired, tve.summary,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				%s AS category_ids,
				g.name AS group_name,
				rn.releases_id AS nfoid,
				re.releases_id AS reid
			FROM releases r
			LEFT OUTER JOIN videos v ON r.videos_id = v.id AND v.type = 0
			LEFT OUTER JOIN tv_info tvi ON v.id = tvi.videos_id
			LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN categories cp ON cp.id = c.parentid
			LEFT JOIN groups g ON g.id = r.groups_id
			LEFT OUTER JOIN video_data re ON re.releases_id = r.id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			%s",
            $this->getConcatenatedCategoryIDs(),
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
        /*$releases = Cache::get(md5($sql));
        if ($releases !== null) {
            return $releases;
        }*/
        $releases = DB::select($sql);
        if (! empty($releases) && \count($releases) > 0) {
            $releases[0]->_totalrows = $this->getPagerCount(
                preg_replace('#LEFT(\s+OUTER)?\s+JOIN\s+(?!tv_episodes)\s+.*ON.*=.*\n#i', ' ', $baseSql)
            );
        }

        $expiresAt = Carbon::now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($sql), $releases, $expiresAt);

        return $releases;
    }

    /**
     * @param        $aniDbID
     * @param int    $offset
     * @param int    $limit
     * @param string $name
     * @param array  $cat
     * @param int    $maxAge
     *
     * @return array
     */
    public function animeSearch($aniDbID, $offset = 0, $limit = 100, $name = '', array $cat = [-1], $maxAge = -1): array
    {
        $whereSql = sprintf(
            '%s
			WHERE r.passwordstatus %s
			AND r.nzbstatus = %d
			%s %s %s %s',
            ($name !== '' ? $this->releaseSearch->getFullTextJoinString() : ''),
            $this->showPasswords,
            NZB::NZB_ADDED,
            ($aniDbID > -1 ? sprintf(' AND r.anidbid = %d ', $aniDbID) : ''),
            ($name !== '' ? $this->releaseSearch->getSearchSQL(['searchname' => $name]) : ''),
            Category::getCategorySearch($cat),
            ($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : '')
        );
        $baseSql = sprintf(
            "SELECT r.*,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				%s AS category_ids,
				g.name AS group_name,
				rn.releases_id AS nfoid,
				re.releases_id AS reid
			FROM releases r
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN categories cp ON cp.id = c.parentid
			LEFT JOIN groups g ON g.id = r.groups_id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			LEFT OUTER JOIN releaseextrafull re ON re.releases_id = r.id
			%s",
            $this->getConcatenatedCategoryIDs(),
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
        $releases = DB::select($sql);
        if (! empty($releases) && \count($releases) > 0) {
            $releases[0]->_totalrows = $this->getPagerCount($baseSql);
        }
        $expiresAt = Carbon::now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($sql), $releases, $expiresAt);

        return $releases;
    }

    /**
     * @param int $imDbId
     * @param int $offset
     * @param int $limit
     * @param string $name
     * @param array $cat
     * @param int $maxAge
     * @param int $minSize
     *
     * @return array
     */
    public function moviesSearch($imDbId, $offset = 0, $limit = 100, $name = '', array $cat = [-1], $maxAge = -1, $minSize = 0): array
    {
        $whereSql = sprintf(
            '%s
            WHERE r.categories_id BETWEEN '.Category::MOVIE_ROOT.' AND '.Category::MOVIE_OTHER.'
			AND r.nzbstatus = %d
			AND r.passwordstatus %s
			%s %s %s %s %s',
            ($name !== '' ? $this->releaseSearch->getFullTextJoinString() : ''),
            NZB::NZB_ADDED,
            $this->showPasswords,
            ($name !== '' ? $this->releaseSearch->getSearchSQL(['searchname' => $name]) : ''),
            (($imDbId !== -1 && is_numeric($imDbId)) ? sprintf(' AND imdbid = %d ', str_pad($imDbId, 7, '0', STR_PAD_LEFT)) : ''),
            Category::getCategorySearch($cat),
            ($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : ''),
            ($minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : '')
        );
        $baseSql = sprintf(
            "SELECT r.*,
				concat(cp.title, ' > ', c.title) AS category_name,
				%s AS category_ids,
				g.name AS group_name,
				rn.releases_id AS nfoid
			FROM releases r
			LEFT JOIN groups g ON g.id = r.groups_id
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN categories cp ON cp.id = c.parentid
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			%s",
            $this->getConcatenatedCategoryIDs(),
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
        $releases = DB::select($sql);
        if (! empty($releases) && \count($releases) > 0) {
            $releases[0]->_totalrows = $this->getPagerCount($baseSql);
        }
        $expiresAt = Carbon::now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($sql), $releases, $expiresAt);

        return $releases;
    }

    /**
     * @param       $currentID
     * @param       $name
     *
     * @param array $excludedCats
     *
     * @return array
     * @throws \Exception
     */
    public function searchSimilar($currentID, $name, array $excludedCats = []): array
    {
        // Get the category for the parent of this release.
        $currRow = Release::getCatByRelId($currentID);
        $catRow = (new Category)->find($currRow['categories_id']);
        $parentCat = $catRow['parentid'];

        $results = $this->search(
            getSimilarName($name),
            -1,
            -1,
            -1,
            -1,
            '',
            '',
            0,
            0,
            -1,
            -1,
            0,
            '',
            '',
            -1,
            $excludedCats,
            [$parentCat]
        );
        if (! $results) {
            return $results;
        }

        $ret = [];
        foreach ($results as $res) {
            if ($res['id'] !== $currentID && $res['categoryparentid'] === $parentCat) {
                $ret[] = $res;
            }
        }

        return $ret;
    }

    /**
     * @param array $guids
     * @return string
     * @throws \Exception
     */
    public function getZipped($guids): string
    {
        $nzb = new NZB();
        $zipFile = new \ZipFile();

        foreach ($guids as $guid) {
            $nzbPath = $nzb->NZBPath($guid);

            if ($nzbPath) {
                $nzbContents = Utility::unzipGzipFile($nzbPath);

                if ($nzbContents) {
                    $filename = $guid;
                    $r = Release::getByGuid($guid);
                    if ($r) {
                        $filename = $r['searchname'];
                    }
                    $zipFile->addFile($nzbContents, $filename.'.nzb');
                }
            }
        }

        return $zipFile->file();
    }

    /**
     * Get count of releases for pager.
     *
     * @param string $query The query to get the count from.
     *
     * @return int
     */
    private function getPagerCount($query): int
    {
        $sql = sprintf(
            'SELECT COUNT(z.id) AS count FROM (%s LIMIT %s) z',
            preg_replace('/SELECT.+?FROM\s+releases/is', 'SELECT r.id FROM releases', $query),
            config('nntmux.max_pager_results')
        );
        $count = Cache::get(md5($sql));
        if ($count !== null) {
            return $count;
        }
        $count = DB::select($sql);
        $expiresAt = Carbon::now()->addMinutes(config('nntmux.cache_expiry_short'));
        Cache::put(md5($sql), $count[0]->count, $expiresAt);

        return $count[0]->count ?? 0;
    }
}
