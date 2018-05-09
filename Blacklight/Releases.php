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

        $this->sphinxSearch = new SphinxSearch();
        $this->releaseSearch = new ReleaseSearch();
        $this->showPasswords = self::showPasswords();
    }

    /**
     * @param       $page
     * @param array $cat
     * @param       $orderBy
     * @param int   $maxAge
     * @param array $excludedCats
     * @param int   $groupName
     * @param int   $minSize
     *
     * @return \Illuminate\Contracts\Pagination\Paginator|mixed
     */
    public function getBrowseRange($page, array $cat, $orderBy, $maxAge = -1, array $excludedCats = [], $groupName = -1, $minSize = 0)
    {
        $orderBy = $this->getBrowseOrder($orderBy);
        $qry = Release::query()
            ->fromSub(function ($query) use ($cat, $maxAge, $excludedCats, $groupName, $minSize, $orderBy) {
                $query->select(['r.*', 'g.name as group_name'])
                    ->from('releases as r')
                    ->where('r.nzbstatus', NZB::NZB_ADDED);
                self::showPasswords($query, true);
                if ($cat !== [-1]) {
                    Category::getCategorySearch($cat, $query, true);
                }
                $query->leftJoin('groups as g', 'g.id', '=', 'r.groups_id');
                if ($maxAge > 0) {
                    $query->where('r.postdate', '>', Carbon::now()->subDays($maxAge));
                }
                if (\count($excludedCats) > 0) {
                    $query->whereNotIn('r.categories_id', $excludedCats);
                }
                if ($groupName !== -1) {
                    $query->where('g.name', $groupName);
                }
                if ($minSize > 0) {
                    $query->where('r.size', '>=', $minSize);
                }
                $query->orderBy($orderBy[0], $orderBy[1]);
            }, 'r')
            ->select(['r.*', 'df.failed as failed', 'rn.releases_id as nfoid', 're.releases_id as reid', 'v.tvdb', 'v.trakt', 'v.tvrage', 'v.tvmaze', 'v.imdb', 'v.tmdb', 'tve.title', 'tve.firstaired', DB::raw("CONCAT(cp.title, ' > ', c.title) AS category_name"), DB::raw("CONCAT(cp.id, ',', c.id) AS category_ids")])
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
            ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
            ->leftJoin('videos as v', 'v.id', '=', 'r.videos_id')
            ->leftJoin('tv_episodes as tve', 'tve.id', '=', 'r.tv_episodes_id')
            ->leftJoin('video_data as re', 're.releases_id', '=', 'r.id')
            ->leftJoin('release_nfos as rn', 're.releases_id', '=', 'r.id')
            ->leftJoin('dnzb_failures as df', 'df.release_id', '=', 'r.id')
            ->groupBy('r.id')
            ->orderBy($orderBy[0], $orderBy[1]);
        self::showPasswords($qry, true);
        if ($cat !== [-1]) {
            Category::getCategorySearch($cat, $qry, true);
        }
        if ($maxAge > 0) {
            $qry->where('r.postdate', '>', Carbon::now()->subDays($maxAge));
        }
        if (\count($excludedCats) > 0) {
            $qry->whereNotIn('r.categories_id', $excludedCats);
        }
        if ($groupName !== -1) {
            $qry->where('g.name', $groupName);
        }
        if ($minSize > 0) {
            $qry->where('r.size', '>=', $minSize);
        }
        $releases = Cache::get(md5(implode('.', $cat).implode('.', $orderBy).$maxAge.implode('.', $excludedCats).$minSize.$groupName.$page));
        if ($releases !== null) {
            return $releases;
        }

        $sql = $qry->simplePaginate(config('nntmux.items_per_page'));
        $expiresAt = Carbon::now()->addSeconds(config('nntmux.cache_expiry_medium'));
        Cache::put(md5(implode('.', $cat).implode('.', $orderBy).$maxAge.implode('.', $excludedCats).$minSize.$groupName.$page), $sql, $expiresAt);

        return $sql;
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
        $setting = Settings::settingValue('..showpasswordedrelease', true);
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
            $this->concatenatedCategoryIDsCache = Cache::get('concatenatedcats');
            if ($this->concatenatedCategoryIDsCache !== null) {
                return $this->concatenatedCategoryIDsCache;
            }

            $result = Category::query()
                ->whereNotNull('categories.parentid')
                ->whereNotNull('cp.id')
                ->selectRaw('CONCAT(cp.id, ", ", categories.id) AS category_ids')
                ->leftJoin('categories as cp', 'cp.id', '=', 'categories.parentid')
                ->get();
            if (isset($result[0]['category_ids'])) {
                $this->concatenatedCategoryIDsCache = $result[0]['category_ids'];
            }
        }
        $expiresAt = Carbon::now()->addSeconds(config('nntmux.cache_expiry_long'));
        Cache::put('concatenatedcats', $this->concatenatedCategoryIDsCache, $expiresAt);

        return $this->concatenatedCategoryIDsCache;
    }

    /**
     * @param        $userShows
     * @param string $orderBy
     * @param int    $maxAge
     * @param array  $excludedCats
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|mixed
     * @throws \Exception
     */
    public function getShowsRange($userShows, $orderBy = '', $maxAge = -1, array $excludedCats = [])
    {
        $sql = Release::query()
            ->with('group as g', 'nfo as rn', 'category as c', 'failed as df', 'episode as tve')
            ->select(
                [
                    'r.*',
                    DB::raw("CONCAT(cp.title, '-', c.title) AS category_name"),
                    'g.name as group_name',
                    'rn.releases_id as nfoid',
                    're.releases_id as reid',
                    'tve.firstaired',
                    'df.failed as failed',
                    ]
            )
            ->from('releases as r')
            ->leftJoin('video_data as re', 're.releases_id', '=', 'r.id')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
            ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
            ->whereBetween('r.categories_id', [5000, 5999])
            ->where('r.nzbstatus', NZB::NZB_ADDED);
        self::showPasswords($sql, true);
        if (! empty($userShows)) {
            foreach ($userShows as $query) {
                $sql->orWhere('r.videos_id', '=', $query['videos_id']);
                if ($query['categories'] !== '') {
                    $catsArr = explode('|', $query['categories']);
                    if (\count($catsArr) > 1) {
                        $sql->whereIn('r.categories_id', $catsArr);
                    } else {
                        $sql->where('r.categories_id', $catsArr[0]);
                    }
                }
            }
        }

        if (\count($excludedCats) > 0) {
            $sql->whereNotIn('r.categories_id', $excludedCats);
        }

        if ($maxAge > 0) {
            $sql->where('r.postdate', '>', Carbon::now()->subDays($maxAge));
        }

        if ($orderBy !== '') {
            $order = $this->getBrowseOrder($orderBy);
            $sql->orderBy($order[0], $order[1]);
        }

        return $sql->simplePaginate(config('nntmux.items_per_page'));
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
     * @return \Illuminate\Database\Query\Builder
     */
    public function uSQL($userQuery, $type)
    {
        $query = null;
        foreach ($userQuery as $query) {
            $query->orWhere('r.'.$type, $query[$type]);
            if ($query['categories'] !== '') {
                $catsArr = explode('|', $query['categories']);
                if (\count($catsArr) > 1) {
                    $query->whereIn('r.categories_id', $catsArr);
                } else {
                    $query->where('r.categories_id', $catsArr[0]);
                }
            }
        }

        return $query;
    }

    /**
     * Function for searching on the site (by subject, searchname or advanced).
     *
     *
     *
     * @param        $searchName
     * @param        $usenetName
     * @param        $posterName
     * @param        $fileName
     * @param        $groupName
     * @param        $sizeFrom
     * @param        $sizeTo
     * @param        $hasNfo
     * @param        $hasComments
     * @param        $daysNew
     * @param        $daysOld
     * @param string|array $orderBy
     * @param int    $maxAge
     * @param array  $excludedCats
     * @param string $type
     * @param array  $cat
     * @param int    $minSize
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|mixed
     */
    public function search($searchName, $usenetName, $posterName, $fileName, $groupName, $sizeFrom, $sizeTo, $hasNfo, $hasComments, $daysNew, $daysOld, $orderBy = '', $maxAge = -1, array $excludedCats = [], $type = 'basic', array $cat = [-1], $minSize = 0)
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
            $orderBy[0] = 'postdate';
            $orderBy[1] = 'desc';
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

        $sql = Release::query()
            ->fromSub(function ($query) use ($maxAge, $groupName, $sizeFrom, $sizeRange, $sizeTo, $hasNfo, $hasComments, $cat, $excludedCats, $type, $daysNew, $daysOld, $searchOptions, $minSize) {
                $query->select(['r.*', 'r.categories_id AS category_ids', 'df.failed as failed', 'g.name as group_name', 'rn.releases_id as nfoid', 're.releases_id as reid', 'cp.id as categoryparentid', 'v.tvdb', 'v.trakt', 'v.tvrage', 'v.tvmaze', 'v.imdb', 'v.tmdb', 'tve.firstaired'])
                ->selectRaw("CONCAT(cp.title, ' > ', c.title) AS category_name")
                ->from('releases as r')
                ->leftJoin('video_data as re', 're.releases_id', '=', 'r.id')
                ->leftJoin('videos as v', 'v.id', '=', 'r.videos_id')
                ->leftJoin('tv_episodes as tve', 'tve.id', '=', 'r.tv_episodes_id')
                ->leftJoin('release_nfos as rn', 'rn.releases_id', '=', 'r.id')
                ->leftJoin('groups as g', 'g.id', '=', 'r.groups_id')
                ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
                ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
                ->leftJoin('dnzb_failures as df', 'df.release_id', '=', 'r.id')
                ->join('releases_se as rse', 'rse.id', '=', 'r.id');
                self::showPasswords($query, true);
                $query->where('r.nzbstatus', '=', NZB::NZB_ADDED);

                if ($maxAge > 0) {
                    $query->where('r.postdate', '>', Carbon::now()->subDays($maxAge));
                }

                if ((int) $groupName !== -1) {
                    $query->where('r.groups_id', '=', Group::getIDByName($groupName));
                }

                if (array_key_exists($sizeFrom, $sizeRange)) {
                    $query->where('r.size', '<', (string) (104857600 * (int) $sizeRange[$sizeTo]));
                }

                if ((int) $hasNfo !== 0) {
                    $query->where('r.nfostatus', '=', 1);
                }

                if ((int) $hasComments !== 0) {
                    $query->where('r.comments', '>', 0);
                }

                if ($type === 'basic') {
                    Category::getCategorySearch($cat, $query, true);
                } elseif ($type === 'advanced' && (int) $cat[0] !== -1) {
                    $query->where('r.categories_id', '=', $cat[0]);
                }

                if ((int) $daysNew !== -1) {
                    $query->where('r.postdate', '<', Carbon::now()->subDays($daysNew));
                }

                if ((int) $daysOld !== -1) {
                    $query->where('r.postdate', '>', Carbon::now()->subDays($daysOld));
                }

                if (\count($excludedCats) > 0) {
                    $query->whereNotIn('r.categories_id', $excludedCats);
                }

                if (\count($searchOptions) > 0) {
                    $this->releaseSearch->getSearchSQL($searchOptions, $query, true);
                }

                if ($minSize > 0) {
                    $query->where('r.size', '>=', $minSize);
                }
            }, 'r')
            ->orderBy('r.'.$orderBy[0], $orderBy[1]);

        $releases = Cache::get(md5($searchName.$usenetName.$posterName.$fileName.$groupName.$sizeFrom.$sizeTo.$hasNfo.$hasComments.$daysNew.$daysOld.implode('.', $orderBy).$maxAge.implode('.', $excludedCats).$type.implode('.', $cat).$minSize));
        if ($releases !== null) {
            return $releases;
        }

        $releases = $sql->simplePaginate(config('nntmux.items_per_page'));

        $expiresAt = Carbon::now()->addSeconds(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($searchName.$usenetName.$posterName.$fileName.$groupName.$sizeFrom.$sizeTo.$hasNfo.$hasComments.$daysNew.$daysOld.implode('.', $orderBy).$maxAge.implode('.', $excludedCats).$type.implode('.', $cat).$minSize), $releases, $expiresAt);

        return $releases;
    }

    /**
     * @param        $page
     * @param array  $siteIdArr
     * @param string $series
     * @param string $episode
     * @param string $airdate
     * @param int    $limit
     * @param string $name
     * @param array  $cat
     * @param int    $maxAge
     * @param int    $minSize
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|mixed
     * @throws \Exception
     */
    public function tvSearch($page, array $siteIdArr = [], $series = '', $episode = '', $airdate = '', $limit = 100, $name = '', array $cat = [-1], $maxAge = -1, $minSize = 0)
    {
        $show = null;

        $showQry = Video::query()->where(function ($query) use ($siteIdArr) {
            if (\is_array($siteIdArr) && ! empty($siteIdArr)) {
                foreach ($siteIdArr as $column => $Id) {
                    if ($Id > 0) {
                        $query->orWhere('v.'.$column, $Id);
                    }
                }
            }
        })->from('videos as v')->leftJoin('tv_episodes as tve', 'v.id', '=', 'tve.videos_id')->select([
                    'v.id as video',
                    DB::raw("GROUP_CONCAT(tve.id SEPARATOR ',') AS episodes"),
                ]);

        if ($series !== '') {
            $showQry->where('tve.series', (int) preg_replace('/^s0*/i', '', $series));
        }

        if ($episode !== '') {
            $showQry->where('tve.episode', (int) preg_replace('/^e0*/i', '', $episode));
        }

        if ($airdate !== '') {
            $showQry->whereDate('tve.firstaired', $airdate);
        }

        $show = $showQry->first();

        // If $name is set it is a fallback search, add available SxxExx/airdate info to the query
        if (! empty($name)) {
            if (! empty($series) && (int) $series < 1900) {
                $name .= sprintf(' S%s', str_pad($series, 2, '0', STR_PAD_LEFT));
                if (! empty($episode) && strpos($episode, '/') === false) {
                    $name .= sprintf('E%s', str_pad($episode, 2, '0', STR_PAD_LEFT));
                }
            } elseif (! empty($airdate)) {
                $name .= sprintf(' %s', str_replace(['/', '-', '.', '_'], ' ', $airdate));
            }
        }

        $query = Release::query()
            ->select(
                [
                    'r.*',
                    'v.title',
                    'v.countries_id',
                    'v.started',
                    'v.tvdb',
                    'v.trakt',
                    'v.imdb',
                    'v.tmdb',
                    'v.tvmaze',
                    'v.tvrage',
                    'v.source',
                    'tvi.summary',
                    'tvi.publisher',
                    'tvi.image',
                    'tve.series',
                    'tve.episode',
                    'tve.se_complete',
                    'tve.title',
                    'tve.firstaired',
                    'tve.summary',
                    DB::raw("CONCAT(cp.title, ' > ', c.title) AS category_name"),
                    'g.name as group_name',
                    'rn.releases_id as nfoid',
                    're.releases_id as reid',
                ]
            )
                ->from('releases as r')
                ->leftJoin('videos as v', function ($query) {
                    $query->on('v.id', '=', 'r.videos_id')
                        ->where('v.type', '=', 0);
                })
                ->leftJoin('tv_info as tvi', 'tvi.videos_id', '=', 'v.id')
                ->leftJoin('tv_episodes as tve', 'tve.id', '=', 'r.tv_episodes_id')
                ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
                ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
                ->leftJoin('groups as g', 'g.id', '=', 'r.groups_id')
                ->leftJoin('video_data as re', 're.releases_id', '=', 'r.id')
                ->leftJoin('release_nfos as rn', 'rn.releases_id', '=', 'r.id')
                ->where('r.nzbstatus', NZB::NZB_ADDED);
        self::showPasswords($query, true);
        if ($show !== null) {
            if ((! empty($series) || ! empty($episode) || ! empty($airdate)) && \strlen((string) $show['episodes']) > 0) {
                $query->whereIn('r.tv_episodes_id', $show['episodes']);
            } elseif ((int) $show['video'] > 0) {
                $query->where('r.videos_id', $show['video']);
                // If $series is set but episode is not, return Season Packs only
                if (! empty($series) && empty($episode)) {
                    $query->where('r.tv_epsiodes_id', '=', 0);
                }
            }
        }

        if ($name !== '') {
            $this->releaseSearch->getSearchSQL(['searchname' => $name], $query, true);
            $query->join('releases_se as rse', 'rse.id', '=', 'r.id');
        }

        Category::getCategorySearch($cat, $query, true);

        if ($maxAge > 0) {
            $query->where('r.postdate', '>', Carbon::now()->subDays($maxAge));
        }

        if ($minSize > 0) {
            $query->where('r.size', '>=', $minSize);
        }

        $query->orderByDesc('r.postdate')->limit($limit);
        $releases = Cache::get(md5($page.implode('.', $siteIdArr).$series.$episode.$airdate.$limit.$name.implode('.', $cat).$maxAge.$minSize));
        if ($releases !== null) {
            return $releases;
        }

        $releases = $query->simplePaginate(config('nntmux.items_per_page'));

        $expiresAt = Carbon::now()->addSeconds(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($page.implode('.', $siteIdArr).$series.$episode.$airdate.$limit.$name.implode('.', $cat).$maxAge.$minSize), $releases, $expiresAt);

        return $releases;
    }

    /**
     * @param        $aniDbID
     * @param int    $limit
     * @param string $name
     * @param array  $cat
     * @param int    $maxAge
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|mixed
     * @throws \Exception
     */
    public function animeSearch($aniDbID, $limit = 100, $name = '', array $cat = [-1], $maxAge = -1)
    {
        $query = Release::query()->where('r.nzbstatus', NZB::NZB_ADDED);
        if ($name !== '') {
            $query->join('releases_se as rse', 'rse.id', '=', 'r.id');
            $this->releaseSearch->getSearchSQL(['searchname' => $name], $query, true);
        }

        if ($aniDbID > -1) {
            $query->where('r.anidbid', $aniDbID);
        }

        self::showPasswords($query, true);
        Category::getCategorySearch($cat, $query, true);

        if ($maxAge > 0) {
            $query->where('r.postdate', '>', Carbon::now()->subDays($maxAge));
        }

        $query->select(
            [
                'r.*',
                DB::raw("CONCAT(cp.title, ' > ', c.title) as category_name"),
                'g.name as group_name',
                'rn.releases_id as nfoid',
                're.releases_id as reid',
            ]
        )
            ->from('releases as r')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
            ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
            ->leftJoin('groups as g', 'g.id', '=', 'r.groups_id')
            ->leftJoin('release_nfos as rn', 'rn.releases_id', '=', 'r.id')
            ->leftJoin('releaseextrafull as re', 're.releases_id', '=', 'r.id')
            ->orderByDesc('r.postdate')
            ->limit($limit);

        $releases = Cache::get(md5($aniDbID.$limit.$name.implode('.', $cat).$maxAge));
        if ($releases !== null) {
            return $releases;
        }

        $releases = $query->simplePaginate(config('nntmux.items_per_page'));
        $expiresAt = Carbon::now()->addSeconds(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($aniDbID.$limit.$name.implode('.', $cat).$maxAge), $releases, $expiresAt);

        return $releases;
    }

    /**
     * @param        $imDbId
     * @param int    $limit
     * @param string $name
     * @param array  $cat
     * @param int    $maxAge
     * @param int    $minSize
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|mixed
     * @throws \Exception
     */
    public function moviesSearch($imDbId, $limit = 100, $name = '', array $cat = [-1], $maxAge = -1, $minSize = 0)
    {
        $query = Release::query()->where('r.nzbstatus', NZB::NZB_ADDED)
        ->whereBetween('r.categories_id', [Category::MOVIE_ROOT, Category::MOVIE_OTHER]);
        if ($name !== '') {
            $query->join('releases_se as rse', 'rse.id', '=', 'r.id');
            $this->releaseSearch->getSearchSQL(['searchname' => $name], $query, true);
        }

        if ($imDbId !== -1) {
            $query->where('r.imdbid', str_pad($imDbId, 7, '0', STR_PAD_LEFT));
        }

        self::showPasswords($query, true);
        Category::getCategorySearch($cat, $query, true);

        if ($maxAge > 0) {
            $query->where('r.postdate', '>', Carbon::now()->subDays($maxAge));
        }

        if ($minSize > 0) {
            $query->where('r.size', '>=', $minSize);
        }

        $query->select(
            [
                'r.*',
                DB::raw("CONCAT(cp.title, ' > ', c.title) as category_name"),
                'g.name as group_name',
                'rn.releases_id as nfoid',
            ]
        )
            ->from('releases as r')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
            ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
            ->leftJoin('groups as g', 'g.id', '=', 'r.groups_id')
            ->leftJoin('release_nfos as rn', 'rn.releases_id', '=', 'r.id')
            ->orderByDesc('r.postdate')
            ->limit($limit);

        $releases = Cache::get(md5($imDbId.$limit.$name.implode('.', $cat).$maxAge.$minSize));
        if ($releases !== null) {
            return $releases;
        }

        $releases = $query->simplePaginate(config('nntmux.items_per_page'));

        $expiresAt = Carbon::now()->addSeconds(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($imDbId.$limit.$name.implode('.', $cat).$maxAge.$minSize), $releases, $expiresAt);

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
        $catRow = Category::find($currRow['categories_id']);
        $parentCat = $catRow['parentid'];

        $results = $this->search(
            getSimilarName($name),
            -1,
            -1,
            -1,
            -1,
            -1,
            -1,
            0,
            0,
            -1,
            -1,
            '',
            -1,
            $excludedCats,
            null,
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
}
