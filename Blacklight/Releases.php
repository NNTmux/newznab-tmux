<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use App\Models\UsenetGroup;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Class Releases.
 */
class Releases extends Release
{
    // RAR/ZIP Password indicator.
    public const PASSWD_NONE = 0; // No password.
    public const PASSWD_RAR = 1; // Definitely passworded.

    /**
     * @var \Blacklight\SphinxSearch
     */
    public SphinxSearch $sphinxSearch;

    /**
     * @var int
     */
    public int $passwordStatus;
    /**
     * @var ElasticSearchSiteSearch
     */
    private ElasticSearchSiteSearch $elasticSearch;

    /**
     * @var array Class instances.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->sphinxSearch = new SphinxSearch();
        $this->elasticSearch = new ElasticSearchSiteSearch();
    }

    /**
     * Used for Browse results.
     *
     *
     * @param    $page
     * @param    $cat
     * @param    $start
     * @param    $num
     * @param    $orderBy
     * @param  int  $maxAge
     * @param  array  $excludedCats
     * @param  array  $tags
     * @param  int|string  $groupName
     * @param  int  $minSize
     * @return Collection|mixed
     */
    public function getBrowseRange($page, $cat, $start, $num, $orderBy, int $maxAge = -1, array $excludedCats = [], int|string $groupName = -1, int $minSize = 0, array $tags = [])
    {
        $orderBy = $this->getBrowseOrder($orderBy);

        $qry = sprintf(
            "SELECT r.id, r.searchname, r.groups_id, r.guid, r.postdate, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus, cp.title AS parent_category, c.title AS sub_category, r.group_name,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				CONCAT(cp.id, ',', c.id) AS category_ids,
				df.failed AS failed,
				rn.releases_id AS nfoid,
				re.releases_id AS reid,
				v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.imdb, v.tmdb,
				tve.title, tve.firstaired
			FROM
			(
				SELECT r.id, r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus, g.name AS group_name
				FROM releases r
				LEFT JOIN usenet_groups g ON g.id = r.groups_id
				%s
				WHERE r.nzbstatus = %d
				AND r.passwordstatus %s
				%s %s %s %s %s %s
				ORDER BY %s %s %s
			) r
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
			LEFT OUTER JOIN videos v ON r.videos_id = v.id
			LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
			LEFT OUTER JOIN video_data re ON re.releases_id = r.id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
			GROUP BY r.id
			ORDER BY %10\$s %11\$s",
            ! empty($tags) ? ' LEFT JOIN tagging_tagged tt ON tt.taggable_id = r.id' : '',
            NZB::NZB_ADDED,
            $this->showPasswords(),
            ! empty($tags) ? " AND tt.tag_name IN ('".implode("','", $tags)."')" : '',
            Category::getCategorySearch($cat),
            ($maxAge > 0 ? (' AND postdate > NOW() - INTERVAL '.$maxAge.' DAY ') : ''),
            (\count($excludedCats) ? (' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')') : ''),
            ((int) $groupName !== -1 ? sprintf(' AND g.name = %s ', escapeString($groupName)) : ''),
            ($minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : ''),
            $orderBy[0],
            $orderBy[1],
            ($start === 0 ? ' LIMIT '.$num : ' LIMIT '.$num.' OFFSET '.$start)
        );

        $releases = Cache::get(md5($qry.$page));
        if ($releases !== null) {
            return $releases;
        }
        $sql = self::fromQuery($qry);
        if (\count($sql) > 0) {
            $possibleRows = $this->getBrowseCount($cat, $maxAge, $excludedCats, $groupName, $tags);
            $sql[0]->_totalcount = $sql[0]->_totalrows = $possibleRows;
        }
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($qry.$page), $sql, $expiresAt);

        return $sql;
    }

    /**
     * Used for pager on browse page.
     *
     * @param  array  $cat
     * @param  int  $maxAge
     * @param  array  $excludedCats
     * @param  int|string  $groupName
     * @param  array  $tags
     * @return int
     */
    public function getBrowseCount(array $cat, int $maxAge = -1, array $excludedCats = [], int|string $groupName = '', array $tags = []): int
    {
        return $this->getPagerCount(sprintf(
            'SELECT COUNT(r.id) AS count
				FROM releases r
				%s %s
				WHERE r.nzbstatus = %d
				AND r.passwordstatus %s
				%s
				%s %s %s %s ',
            ($groupName !== -1 ? 'LEFT JOIN usenet_groups g ON g.id = r.groups_id' : ''),
            ! empty($tags) ? ' LEFT JOIN tagging_tagged tt ON tt.taggable_id = r.id' : '',
            NZB::NZB_ADDED,
            $this->showPasswords(),
            ($groupName !== -1 ? sprintf(' AND g.name = %s', escapeString($groupName)) : ''),
            ! empty($tags) ? ' AND tt.tag_name IN ('.escapeString(implode(',', $tags)).')' : '',
            Category::getCategorySearch($cat),
            ($maxAge > 0 ? (' AND r.postdate > NOW() - INTERVAL '.$maxAge.' DAY ') : ''),
            (\count($excludedCats) ? (' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')') : '')
        ));
    }

    /**
     * @return string
     */
    public function showPasswords()
    {
        $show = (int) Settings::settingValue('..showpasswordedrelease');
        $setting = $show ?? 0;

        return match ($setting) {
            1 => '<= '.self::PASSWD_RAR,
            default => '= '.self::PASSWD_NONE,
        };
    }

    /**
     * Use to order releases on site.
     *
     * @param  array|string  $orderBy
     * @return array
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
     * Get list of releases available for export.
     *
     *
     * @param  string  $postFrom
     * @param  string  $postTo
     * @param  string  $groupID
     * @return Collection|\Illuminate\Support\Collection|static[]
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
     * Get date in this format : 01/01/2014 of the oldest release.
     *
     * @note Used for exporting NZBs.
     *
     * @return mixed
     */
    public function getEarliestUsenetPostDate()
    {
        $row = self::query()->selectRaw("DATE_FORMAT(min(postdate), '%d/%m/%Y') AS postdate")->first();

        return $row === null ? '01/01/2014' : $row['postdate'];
    }

    /**
     * Get date in this format : 01/01/2014 of the newest release.
     *
     * @note Used for exporting NZBs.
     *
     * @return mixed
     */
    public function getLatestUsenetPostDate()
    {
        $row = self::query()->selectRaw("DATE_FORMAT(max(postdate), '%d/%m/%Y') AS postdate")->first();

        return $row === null ? '01/01/2014' : $row['postdate'];
    }

    /**
     * Gets all groups for drop down selection on NZB-Export web page.
     *
     * @param  bool  $blnIncludeAll
     *
     * @note Used for exporting NZBs.
     *
     * @return array
     */
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
     * Get TV for My Shows page.
     *
     *
     * @param $userShows
     * @param $offset
     * @param $limit
     * @param $orderBy
     * @param  int  $maxAge
     * @param  array  $excludedCats
     * @return Collection|mixed
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
				AND r.nzbstatus = %d
				AND r.categories_id BETWEEN %d AND %d
				AND r.passwordstatus %s
				%s
				GROUP BY r.id
				ORDER BY %s %s %s",
            $this->uSQL($userShows, 'videos_id'),
            (! empty($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
            NZB::NZB_ADDED,
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
        $result = self::fromQuery($sql);
        Cache::put(md5($sql), $result, $expiresAt);

        return $result;
    }

    /**
     * Get count for my shows page pagination.
     *
     * @param    $userShows
     * @param  int  $maxAge
     * @param  array  $excludedCats
     * @return int
     */
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
     * Delete multiple releases, or a single by ID.
     *
     * @param  int|array|string  $list  Array of GUID or ID of releases to delete.
     *
     * @throws \Exception
     */
    public function deleteMultiple(int|array|string $list): void
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
     * @param  array  $identifiers  ['g' => Release GUID(mandatory), 'id => ReleaseID(optional, pass
     *                              false)]
     * @param  \Blacklight\NZB  $nzb
     * @param  \Blacklight\ReleaseImage  $releaseImage
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
                    \Elasticsearch::delete($params);
                } catch (Missing404Exception $e) {
                    //we do nothing here just catch the error, we don't care if release is missing from ES, we are deleting it anyway
                }
            }
        } else {
            // Delete from sphinx.
            $this->sphinxSearch->deleteRelease($identifiers);
        }

        // Delete from DB.
        self::whereGuid($identifiers['g'])->delete();
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

        return self::query()->whereIn('guid', $guids)->update($update);
    }

    /**
     * Creates part of a query for some functions.
     *
     * @param  array|Collection  $userQuery
     * @param  string  $type
     * @return string
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
     *
     * @param  array  $searchArr
     * @param    $groupName
     * @param    $sizeFrom
     * @param    $sizeTo
     * @param    $daysNew
     * @param    $daysOld
     * @param  int  $offset
     * @param  int  $limit
     * @param  array|string  $orderBy
     * @param  int  $maxAge
     * @param  array  $excludedCats
     * @param  string  $type
     * @param  array  $cat
     * @param  int  $minSize
     * @param  array  $tags
     * @return array|Collection|mixed
     *
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     * @throws \Foolz\SphinxQL\Exception\DatabaseException
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     */
    public function search(array $searchArr, $groupName, $sizeFrom, $sizeTo, $daysNew, $daysOld, int $offset = 0, int $limit = 1000, array|string $orderBy = '', int $maxAge = -1, array $excludedCats = [], string $type = 'basic', array $cat = [-1], int $minSize = 0, array $tags = [])
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

        $searchFields = Arr::where($searchArr, static function ($value) {
            return $value !== -1;
        });

        $phrases = array_values($searchFields);

        if (config('nntmux.elasticsearch_enabled') === true) {
            $searchResult = $this->elasticSearch->indexSearch($phrases, $limit);
        } else {
            $results = $this->sphinxSearch->searchIndexes('releases_rt', '', [], $searchFields);

            $searchResult = Arr::pluck($results, 'id');
        }

        if (empty($searchResult)) {
            return collect();
        }

        $catQuery = '';
        if ($type === 'basic') {
            $catQuery = Category::getCategorySearch($cat);
        } elseif ($type === 'advanced' && (int) $cat[0] !== -1) {
            $catQuery = sprintf('AND r.categories_id = %d', $cat[0]);
        }
        $whereSql = sprintf(
            'WHERE r.passwordstatus %s AND r.nzbstatus = %d %s %s %s %s %s %s %s %s %s %s %s',
            $this->showPasswords(),
            NZB::NZB_ADDED,
            ! empty($tags) ? " AND tt.tag_name IN ('".implode("','", $tags)."')" : '',
            ($maxAge > 0 ? sprintf(' AND r.postdate > (NOW() - INTERVAL %d DAY) ', $maxAge) : ''),
            ((int) $groupName !== -1 ? sprintf(' AND r.groups_id = %d ', UsenetGroup::getIDByName($groupName)) : ''),
            (array_key_exists($sizeFrom, $sizeRange) ? ' AND r.size > '.(104857600 * (int) $sizeRange[$sizeFrom]).' ' : ''),
            (array_key_exists($sizeTo, $sizeRange) ? ' AND r.size < '.(104857600 * (int) $sizeRange[$sizeTo]).' ' : ''),
            $catQuery,
            ((int) $daysNew !== -1 ? sprintf(' AND r.postdate < (NOW() - INTERVAL %d DAY) ', $daysNew) : ''),
            ((int) $daysOld !== -1 ? sprintf(' AND r.postdate > (NOW() - INTERVAL %d DAY) ', $daysOld) : ''),
            (\count($excludedCats) > 0 ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
            ('AND r.id IN ('.implode(',', $searchResult).')'),
            ($minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : '')
        );
        $baseSql = sprintf(
            "SELECT r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus,  cp.title AS parent_category, c.title AS sub_category,
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
			%s %s",
            ! empty($tags) ? ' LEFT JOIN tagging_tagged tt ON tt.taggable_id = r.id' : '',
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
        $releases = self::fromQuery($sql);
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount($baseSql);
        }
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($sql), $releases, $expiresAt);

        return $releases;
    }

    /**
     * Search function for API.
     *
     *
     * @param    $searchName
     * @param    $groupName
     * @param  int  $offset
     * @param  int  $limit
     * @param  int  $maxAge
     * @param  array  $excludedCats
     * @param  array  $cat
     * @param  int  $minSize
     * @param  array  $tags
     * @return Collection|mixed
     *
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     * @throws \Foolz\SphinxQL\Exception\DatabaseException
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     */
    public function apiSearch($searchName, $groupName, int $offset = 0, int $limit = 1000, int $maxAge = -1, array $excludedCats = [], array $cat = [-1], int $minSize = 0, array $tags = []): mixed
    {
        if ($searchName !== -1) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $searchResult = $this->elasticSearch->indexSearchApi($searchName, $limit);
            } else {
                $searchResult = Arr::pluck($this->sphinxSearch->searchIndexes('releases_rt', $searchName, ['searchname']), 'id');
            }
        }

        $catQuery = Category::getCategorySearch($cat);

        $whereSql = sprintf(
            'WHERE r.passwordstatus %s AND r.nzbstatus = %d %s %s %s %s %s %s %s',
            $this->showPasswords(),
            NZB::NZB_ADDED,
            ! empty($tags) ? " AND tt.tag_name IN ('".implode("','", $tags)."')" : '',
            ($maxAge > 0 ? sprintf(' AND r.postdate > (NOW() - INTERVAL %d DAY) ', $maxAge) : ''),
            ((int) $groupName !== -1 ? sprintf(' AND r.groups_id = %d ', UsenetGroup::getIDByName($groupName)) : ''),
            $catQuery,
            (\count($excludedCats) > 0 ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
            (! empty($searchResult) ? 'AND r.id IN ('.implode(',', $searchResult).')' : ''),
            ($minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : '')
        );
        $baseSql = sprintf(
            "SELECT r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus, m.imdbid, m.tmdbid, m.traktid, cp.title AS parent_category, c.title AS sub_category,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				g.name AS group_name,
				cp.id AS categoryparentid,
				v.tvdb, v.trakt, v.tvrage, v.tvmaze, v.imdb, v.tmdb,
				tve.firstaired, tve.title, tve.series, tve.episode
			FROM releases r
			LEFT OUTER JOIN videos v ON r.videos_id = v.id
			LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
			LEFT JOIN movieinfo m ON m.id = r.movieinfo_id
			LEFT JOIN usenet_groups g ON g.id = r.groups_id
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
			%s %s",
            ! empty($tags) ? ' LEFT JOIN tagging_tagged tt ON tt.taggable_id = r.id' : '',
            $whereSql
        );
        $sql = sprintf(
            'SELECT * FROM (
				%s
			) r
			ORDER BY r.postdate DESC
			LIMIT %d OFFSET %d',
            $baseSql,
            $limit,
            $offset
        );
        $releases = Cache::get(md5($sql));
        if ($releases !== null) {
            return $releases;
        }
        if ($searchName !== -1 && ! empty($searchResult)) {
            $releases = self::fromQuery($sql);
        } elseif ($searchName !== -1 && empty($searchResult)) {
            $releases = collect();
        } elseif ($searchName === -1) {
            $releases = self::fromQuery($sql);
        } else {
            $releases = collect();
        }
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount($baseSql);
        }
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($sql), $releases, $expiresAt);

        return $releases;
    }

    /**
     * Search TV Shows via API.
     *
     *
     * @param  array  $siteIdArr
     * @param  string  $series
     * @param  string  $episode
     * @param  string  $airDate
     * @param  int  $offset
     * @param  int  $limit
     * @param  string  $name
     * @param  array  $cat
     * @param  int  $maxAge
     * @param  int  $minSize
     * @param  array  $excludedCategories
     * @param  array  $tags
     * @return Collection|mixed
     *
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     * @throws \Foolz\SphinxQL\Exception\DatabaseException
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     */
    public function tvSearch(array $siteIdArr = [], string $series = '', string $episode = '', string $airDate = '', int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, int $minSize = 0, array $excludedCategories = [], array $tags = [])
    {
        $siteSQL = [];
        $showSql = '';
        foreach ($siteIdArr as $column => $Id) {
            if ($Id > 0) {
                $siteSQL[] = sprintf('v.%s = %d', $column, $Id);
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
                ($airDate !== '' ? sprintf('AND DATE(tve.firstaired) = %s', escapeString($airDate)) : '')
            );
            $show = self::fromQuery($showQry);
            if (! empty($show[0]) && $show->isNotEmpty()) {
                if ((! empty($series) || ! empty($episode) || ! empty($airDate)) && $show[0]->episodes !== '') {
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
            } elseif (! empty($airDate)) {
                $name .= sprintf(' %s', str_replace(['/', '-', '.', '_'], ' ', $airDate));
            }
        }
        if (! empty($name)) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $searchResult = $this->elasticSearch->indexSearchTMA($name, $limit);
            } else {
                $searchResult = Arr::pluck($this->sphinxSearch->searchIndexes('releases_rt', $name, ['searchname']), 'id');
            }

            if (empty($searchResult)) {
                return collect();
            }
        }
        $whereSql = sprintf(
            'WHERE r.nzbstatus = %d
			AND r.passwordstatus %s
			%s %s %s %s %s %s %s',
            NZB::NZB_ADDED,
            $this->showPasswords(),
            ! empty($tags) ? " AND tt.tag_name IN ('".implode("','", $tags)."')" : '',
            $showSql,
            (! empty($name) && ! empty($searchResult)) ? 'AND r.id IN ('.implode(',', $searchResult).')' : '',
            Category::getCategorySearch($cat),
            $maxAge > 0 ? sprintf('AND r.postdate > NOW() - INTERVAL %d DAY', $maxAge) : '',
            $minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : '',
            ! empty($excludedCategories) ? sprintf('AND r.categories_id NOT IN('.implode(',', $excludedCategories).')') : ''
        );
        $baseSql = sprintf(
            "SELECT r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus,
				v.title, v.countries_id, v.started, v.tvdb, v.trakt,
					v.imdb, v.tmdb, v.tvmaze, v.tvrage, v.source,
				tvi.summary, tvi.publisher, tvi.image,
				tve.series, tve.episode, tve.se_complete, tve.title, tve.firstaired, tve.summary, cp.title AS parent_category, c.title AS sub_category,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				g.name AS group_name,
				rn.releases_id AS nfoid,
				re.releases_id AS reid
			FROM releases r
			LEFT OUTER JOIN videos v ON r.videos_id = v.id AND v.type = 0
			LEFT OUTER JOIN tv_info tvi ON v.id = tvi.videos_id
			LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
			LEFT JOIN usenet_groups g ON g.id = r.groups_id
			LEFT OUTER JOIN video_data re ON re.releases_id = r.id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			%s %s",
            ! empty($tags) ? ' LEFT JOIN tagging_tagged tt ON tt.taggable_id = r.id' : '',
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
        $releases = ((! empty($name) && ! empty($searchResult)) || empty($name)) ? self::fromQuery($sql) : [];
        if (count($releases) !== 0 && $releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount(
                preg_replace('#LEFT(\s+OUTER)?\s+JOIN\s+(?!tv_episodes)\s+.*ON.*=.*\n#i', ' ', $baseSql)
            );
        }
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($sql), $releases, $expiresAt);

        return $releases;
    }

    /**
     * Search TV Shows via APIv2.
     *
     *
     * @param  array  $siteIdArr
     * @param  string  $series
     * @param  string  $episode
     * @param  string  $airDate
     * @param  int  $offset
     * @param  int  $limit
     * @param  string  $name
     * @param  array  $cat
     * @param  int  $maxAge
     * @param  int  $minSize
     * @param  array  $excludedCategories
     * @param  array  $tags
     * @return Collection|mixed
     *
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     * @throws \Foolz\SphinxQL\Exception\DatabaseException
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     */
    public function apiTvSearch(array $siteIdArr = [], string $series = '', string $episode = '', string $airDate = '', int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, int $minSize = 0, array $excludedCategories = [], array $tags = [])
    {
        $siteSQL = [];
        $showSql = '';
        foreach ($siteIdArr as $column => $Id) {
            if ($Id > 0) {
                $siteSQL[] = sprintf('v.%s = %d', $column, $Id);
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
                ($airDate !== '' ? sprintf('AND DATE(tve.firstaired) = %s', escapeString($airDate)) : '')
            );
            $show = self::fromQuery($showQry);
            if ($show->isNotEmpty()) {
                if ((! empty($series) || ! empty($episode) || ! empty($airDate)) && $show[0]->episodes != '') {
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
                if (! empty($episode) && ! str_contains($episode, '/')) {
                    $name .= sprintf('E%s', str_pad($episode, 2, '0', STR_PAD_LEFT));
                }
            } elseif (! empty($airDate)) {
                $name .= sprintf(' %s', str_replace(['/', '-', '.', '_'], ' ', $airDate));
            }
        }
        if (! empty($name)) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $searchResult = $this->elasticSearch->indexSearchTMA($name, $limit);
            } else {
                $searchResult = Arr::pluck($this->sphinxSearch->searchIndexes('releases_rt', $name, ['searchname']), 'id');
            }

            if (empty($searchResult)) {
                return collect();
            }
        }
        $whereSql = sprintf(
            'WHERE r.nzbstatus = %d
			AND r.passwordstatus %s
			%s %s %s %s %s %s %s',
            NZB::NZB_ADDED,
            $this->showPasswords(),
            ! empty($tags) ? " AND tt.tag_name IN ('".implode("','", $tags)."')" : '',
            $showSql,
            (! empty($searchResult) ? 'AND r.id IN ('.implode(',', $searchResult).')' : ''),
            Category::getCategorySearch($cat),
            ($maxAge > 0 ? sprintf('AND r.postdate > NOW() - INTERVAL %d DAY', $maxAge) : ''),
            ($minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : ''),
            ! empty($excludedCategories) ? sprintf('AND r.categories_id NOT IN('.implode(',', $excludedCategories).')') : ''
        );
        $baseSql = sprintf(
            "SELECT r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.tv_episodes_id, r.haspreview, r.jpgstatus,
				v.title, v.type, v.tvdb, v.trakt,v.imdb, v.tmdb, v.tvmaze, v.tvrage,
				tve.series, tve.episode, tve.se_complete, tve.title, tve.firstaired, cp.title AS parent_category, c.title AS sub_category,
				CONCAT(cp.title, ' > ', c.title) AS category_name,
				g.name AS group_name
			FROM releases r
			LEFT OUTER JOIN videos v ON r.videos_id = v.id AND v.type = 0
			LEFT OUTER JOIN tv_info tvi ON v.id = tvi.videos_id
			LEFT OUTER JOIN tv_episodes tve ON r.tv_episodes_id = tve.id
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
			LEFT JOIN usenet_groups g ON g.id = r.groups_id
			%s %s",
            ! empty($tags) ? ' LEFT JOIN tagging_tagged tt ON tt.taggable_id = r.id' : '',
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
        $releases = self::fromQuery($sql);
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
     * @param $aniDbID
     * @param  int  $offset
     * @param  int  $limit
     * @param  string  $name
     * @param  array  $cat
     * @param  int  $maxAge
     * @param  array  $excludedCategories
     * @return Collection|mixed
     *
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     * @throws \Foolz\SphinxQL\Exception\DatabaseException
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     */
    public function animeSearch($aniDbID, int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, array $excludedCategories = [])
    {
        if (! empty($name)) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $searchResult = $this->elasticSearch->indexSearchTMA($name, $limit);
            } else {
                $searchResult = Arr::pluck($this->sphinxSearch->searchIndexes('releases_rt', $name, ['searchname']), 'id');
            }

            if (empty($searchResult)) {
                return collect();
            }
        }

        $whereSql = sprintf(
            'WHERE r.passwordstatus %s
			AND r.nzbstatus = %d
			%s %s %s %s %s',
            $this->showPasswords(),
            NZB::NZB_ADDED,
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
				rn.releases_id AS nfoid,
				re.releases_id AS reid
			FROM releases r
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
			LEFT JOIN usenet_groups g ON g.id = r.groups_id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			LEFT OUTER JOIN releaseextrafull re ON re.releases_id = r.id
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
        $releases = self::fromQuery($sql);
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
     *
     * @param  int  $imDbId
     * @param  int  $tmDbId
     * @param  int  $traktId
     * @param  int  $offset
     * @param  int  $limit
     * @param  string  $name
     * @param  array  $cat
     * @param  int  $maxAge
     * @param  int  $minSize
     * @param  array  $excludedCategories
     * @param  array  $tags
     * @return Collection|mixed
     *
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     * @throws \Foolz\SphinxQL\Exception\DatabaseException
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     */
    public function moviesSearch(int $imDbId = -1, int $tmDbId = -1, int $traktId = -1, int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, int $minSize = 0, array $excludedCategories = [], array $tags = [])
    {
        if (! empty($name)) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $searchResult = $this->elasticSearch->indexSearchTMA($name, $limit);
            } else {
                $searchResult = Arr::pluck($this->sphinxSearch->searchIndexes('releases_rt', $name, ['searchname']), 'id');
            }

            if (empty($searchResult)) {
                return collect();
            }
        }

        $whereSql = sprintf(
            'WHERE r.categories_id BETWEEN '.Category::MOVIE_ROOT.' AND '.Category::MOVIE_OTHER.'
			AND r.nzbstatus = %d
			AND r.passwordstatus %s
			%s %s %s %s %s %s %s %s',
            NZB::NZB_ADDED,
            $this->showPasswords(),
            (! empty($searchResult) ? 'AND r.id IN ('.implode(',', $searchResult).')' : ''),
            ! empty($tags) ? " AND tt.tag_name IN ('".implode("','", $tags)."')" : '',
            ($imDbId !== -1 && is_numeric($imDbId)) ? sprintf(' AND m.imdbid = %d ', $imDbId) : '',
            ($tmDbId !== -1 && is_numeric($tmDbId)) ? sprintf(' AND m.tmdbid = %d ', $tmDbId) : '',
            ($traktId !== -1 && is_numeric($traktId)) ? sprintf(' AND m.traktid = %d ', $traktId) : '',
            ! empty($excludedCategories) ? sprintf('AND r.categories_id NOT IN('.implode(',', $excludedCategories).')') : '',
            Category::getCategorySearch($cat),
            $maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : '',
            $minSize > 0 ? sprintf('AND r.size >= %d', $minSize) : ''
        );
        $baseSql = sprintf(
            "SELECT r.id, r.searchname, r.guid, r.postdate, r.groups_id, r.categories_id, r.size, r.totalpart, r.fromname, r.passwordstatus, r.grabs, r.comments, r.adddate, r.imdbid, r.videos_id, r.tv_episodes_id, r.haspreview, r.jpgstatus, m.imdbid, m.tmdbid, m.traktid, cp.title AS parent_category, c.title AS sub_category,
				concat(cp.title, ' > ', c.title) AS category_name,
				g.name AS group_name,
				rn.releases_id AS nfoid
			FROM releases r
			LEFT JOIN movieinfo m ON m.id = r.movieinfo_id
			LEFT JOIN usenet_groups g ON g.id = r.groups_id
			LEFT JOIN categories c ON c.id = r.categories_id
			LEFT JOIN root_categories cp ON cp.id = c.root_categories_id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			%s %s",
            ! empty($tags) ? ' LEFT JOIN tagging_tagged tt ON tt.taggable_id = r.id' : '',
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
        $releases = self::fromQuery($sql);
        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $this->getPagerCount($baseSql);
        }
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($sql), $releases, $expiresAt);

        return $releases;
    }

    /**
     * @param $currentID
     * @param $name
     * @param  array  $excludedCats
     * @return array|bool
     *
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     * @throws \Foolz\SphinxQL\Exception\DatabaseException
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     */
    public function searchSimilar($currentID, $name, array $excludedCats = [])
    {
        // Get the category for the parent of this release.
        $ret = false;
        $currRow = self::getCatByRelId($currentID);
        if ($currRow !== null) {
            $catRow = Category::find($currRow['categories_id']);
            $parentCat = $catRow['root_categories_id'];

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
     * Get count of releases for pager.
     *
     *
     * @param  string  $query  The query to get the count from.
     * @return int
     */
    private function getPagerCount(string $query): int
    {
        $sql = sprintf(
            'SELECT COUNT(z.id) AS count FROM (%s LIMIT %s) z',
            preg_replace('/SELECT.+?FROM\s+releases/is', 'SELECT r.id FROM releases', $query),
            (int) config('nntmux.max_pager_results')
        );
        $count = Cache::get(md5($sql));
        if ($count !== null) {
            return $count;
        }
        $count = self::fromQuery($sql);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_short'));
        Cache::put(md5($sql), $count[0]->count, $expiresAt);

        return $count[0]->count ?? 0;
    }
}
