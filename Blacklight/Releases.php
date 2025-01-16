<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use Elasticsearch;
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

    public int $passwordStatus;

    private ManticoreSearch $manticoreSearch;

    private ElasticSearchSiteSearch $elasticSearch;

    public function __construct()
    {
        parent::__construct();
        $this->manticoreSearch = new ManticoreSearch;
        $this->elasticSearch = new ElasticSearchSiteSearch;
    }

    /**
     * Used for Browse results.
     *
     * @return Collection|mixed
     */
    public function getBrowseRange($page, $cat, $start, $num, $orderBy, int $maxAge = -1, array $excludedCats = [], int|string $groupName = -1, int $minSize = 0): mixed
    {
        $page = max(1, $page);
        $start = max(0, $start);

        $orderBy = $this->getBrowseOrder($orderBy);

        $query = self::query()
            ->with(['group', 'category', 'category.parent', 'video', 'video.episode', 'videoData', 'nfo', 'failed'])
            ->where('nzbstatus', NZB::NZB_ADDED)
            ->where('passwordstatus', $this->showPasswords());

        if ($cat) {
            $categories = Category::getCategorySearch($cat, null, true);
            // If categories is empty, we don't want to return anything.
            if ($categories !== null) {
                // if we have more than one category, we need to use whereIn
                if (count(Arr::wrap($categories)) > 1) {
                    $query->whereIn('categories_id', $categories);
                } else {
                    $query->where('categories_id', $categories);
                }
            }
        }

        if ($maxAge > 0) {
            $query->where('postdate', '>', now()->subDays($maxAge));
        }

        if (! empty($excludedCats)) {
            $query->whereNotIn('categories_id', $excludedCats);
        }

        if ($groupName !== -1) {
            $query->whereHas('group', function ($q) use ($groupName) {
                $q->where('name', $groupName);
            });
        }

        if ($minSize > 0) {
            $query->where('size', '>=', $minSize);
        }

        $query->orderBy($orderBy[0], $orderBy[1])
            ->skip($start)
            ->take($num);
        $releases = Cache::get(md5($query->toRawSql().$page));
        if ($releases !== null) {
            return $releases;
        }

        $sql = $query->get();
        if ($sql->isNotEmpty()) {
            $possibleRows = $sql->count();
            $sql[0]->_totalcount = $sql[0]->_totalrows = $possibleRows;
        }

        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put(md5($query->toRawSql().$page), $sql, $expiresAt);

        return $sql;
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
     * @return Collection|\Illuminate\Support\Collection|Release[]
     */
    public function getForExport(string $postFrom = '', string $postTo = '', string $groupID = ''): Collection|array|\Illuminate\Support\Collection
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
     * @return Collection|mixed
     */
    public function getShowsRange($userShows, $offset, $limit, $orderBy, int $maxAge = -1, array $excludedCats = []): mixed
    {
        $orderBy = $this->getBrowseOrder($orderBy);

        $query = self::query()
            ->with(['group', 'category', 'category.parent', 'video', 'video.episode'])
            ->where('nzbstatus', NZB::NZB_ADDED)
            ->where('passwordstatus', $this->showPasswords())
            ->whereBetween('categories_id', [Category::TV_ROOT, Category::TV_OTHER])
            ->when($maxAge > 0, function ($q) use ($maxAge) {
                $q->where('postdate', '>', now()->subDays($maxAge));
            })
            ->when(! empty($excludedCats), function ($q) use ($excludedCats) {
                $q->whereNotIn('categories_id', $excludedCats);
            })
            ->whereRaw($this->uSQL($userShows, 'videos_id'))
            ->orderBy($orderBy[0], $orderBy[1])
            ->offset($offset)
            ->limit($limit);

        $cacheKey = md5($query->toRawSql());
        $cacheTTL = now()->addMinutes(config('nntmux.cache_expiry_medium'));

        $releases = Cache::get($cacheKey);
        if ($releases !== null) {
            return $releases;
        }

        $releases = $query->get();

        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $query->count();
        }

        Cache::put($cacheKey, $releases, $cacheTTL);

        return $releases;
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
     * Function for searching on the site (by subject, searchname or advanced).
     *
     * @return array|Collection|mixed
     */
    public function search(array $searchArr, $groupName, $sizeFrom, $sizeTo, $daysNew, $daysOld, int $offset = 0, int $limit = 1000, array|string $orderBy = '', int $maxAge = -1, array $excludedCats = [], string $type = 'basic', array $cat = [-1], int $minSize = 0): mixed
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
            $orderBy = ['postdate', 'desc'];
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
            $searchResult = $this->manticoreSearch->searchIndexes('releases_rt', '', [], $searchFields);
            if (! empty($searchResult)) {
                $searchResult = Arr::wrap(Arr::get($searchResult, 'id'));
            }
        }

        if (count($searchResult) === 0) {
            return collect();
        }

        $query = self::query()
            ->with(['group', 'category', 'category.parent', 'video', 'video.episode', 'nfo', 'failed'])
            ->where('nzbstatus', NZB::NZB_ADDED)
            ->where('passwordstatus', $this->showPasswords())
            ->whereIn('id', $searchResult);

        if ($type === 'basic') {
            $categories = Category::getCategorySearch($cat, null, true);
            if ($categories !== null) {
                $query->whereIn('categories_id', Arr::wrap($categories));
            }
        } elseif ($type === 'advanced' && (int) $cat[0] !== -1) {
            $query->where('categories_id', $cat[0]);
        }

        if ($maxAge > 0) {
            $query->where('postdate', '>', now()->subDays($maxAge));
        }

        if (! empty($excludedCats)) {
            $query->whereNotIn('categories_id', $excludedCats);
        }

        if ((int) $groupName !== -1) {
            $query->whereHas('group', function ($q) use ($groupName) {
                $q->where('name', $groupName);
            });
        }

        if ($sizeFrom > 0 && array_key_exists($sizeFrom, $sizeRange)) {
            $query->where('size', '>', 104857600 * (int) $sizeRange[$sizeFrom]);
        }

        if ($sizeTo > 0 && array_key_exists($sizeTo, $sizeRange)) {
            $query->where('size', '<', 104857600 * (int) $sizeRange[$sizeTo]);
        }

        if ($daysNew !== -1) {
            $query->where('postdate', '<', now()->subDays($daysNew));
        }

        if ($daysOld !== -1) {
            $query->where('postdate', '>', now()->subDays($daysOld));
        }

        if ($minSize > 0) {
            $query->where('size', '>=', $minSize);
        }

        $query->orderBy($orderBy[0], $orderBy[1])
            ->offset($offset)
            ->limit($limit);

        $cacheKey = md5($query->toRawSql());
        $cacheTTL = now()->addMinutes(config('nntmux.cache_expiry_medium'));

        $releases = Cache::get($cacheKey);
        if ($releases !== null) {
            return $releases;
        }

        $releases = $query->get();

        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $query->count();
        }

        Cache::put($cacheKey, $releases, $cacheTTL);

        return $releases;
    }

    /**
     * Search function for API.
     *
     * @return Collection|mixed
     */
    public function apiSearch($searchName, $groupName, int $offset = 0, int $limit = 1000, int $maxAge = -1, array $excludedCats = [], array $cat = [-1], int $minSize = 0): mixed
    {
        $query = self::query()
            ->with(['video', 'video.episode', 'movieinfo', 'group', 'category', 'category.parent'])
            ->where('nzbstatus', NZB::NZB_ADDED)
            ->where('passwordstatus', $this->showPasswords());

        if ($searchName !== -1) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $searchResult = $this->elasticSearch->indexSearchApi($searchName, $limit);
            } else {
                $searchResult = $this->manticoreSearch->searchIndexes('releases_rt', $searchName, ['searchname']);
                if (! empty($searchResult)) {
                    $searchResult = Arr::wrap(Arr::get($searchResult, 'id'));
                }
            }
            if (count($searchResult) !== 0) {
                $query->whereIn('id', $searchResult);
            } else {
                return collect();
            }
        }

        if ($maxAge > 0) {
            $query->where('postdate', '>', now()->subDays($maxAge));
        }

        if (! empty($excludedCats)) {
            $query->whereNotIn('categories_id', $excludedCats);
        }

        if ((int) $groupName !== -1) {
            $query->whereHas('group', function ($q) use ($groupName) {
                $q->where('name', $groupName);
            });
        }

        if ($cat !== [-1]) {
            $query->whereIn('categories_id', $cat);
        }

        if ($minSize > 0) {
            $query->where('size', '>=', $minSize);
        }

        $query->orderBy('postdate', 'desc')
            ->offset($offset)
            ->limit($limit);

        $cacheKey = md5($query->toRawSql());
        $cacheTTL = now()->addMinutes(config('nntmux.cache_expiry_medium'));

        $releases = Cache::get($cacheKey);
        if ($releases !== null) {
            return $releases;
        }

        $releases = $query->get();

        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $query->count();
        }

        Cache::put($cacheKey, $releases, $cacheTTL);

        return $releases;
    }

    /**
     * Search for TV shows via API.
     *
     * @return array|\Illuminate\Cache\|Collection|\Illuminate\Support\Collection|mixed
     */
    public function tvSearch(array $siteIdArr = [], string $series = '', string $episode = '', string $airDate = '', int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, int $minSize = 0, array $excludedCategories = []): mixed
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
                if ((! empty($episode) && ! empty($series)) && $show[0]->episodes !== '') {
                    $showSql .= ' AND r.tv_episodes_id IN ('.$show[0]->episodes.') AND tve.series = '.$series;
                } elseif (! empty($episode) && $show[0]->episodes !== '') {
                    $showSql = sprintf('AND r.tv_episodes_id IN (%s)', $show[0]->episodes);
                } elseif (! empty($series) && empty($episode)) {
                    // If $series is set but episode is not, return Season Packs and Episodes
                    $showSql .= ' AND r.tv_episodes_id IN ('.$show[0]->episodes.') AND tve.series = '.$series;
                }
                if ($show[0]->video > 0) {
                    $showSql .= ' AND r.videos_id = '.$show[0]->video;
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
                // If season is not empty but episode is, add a wildcard to the search
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

            if (empty($searchResult)) {
                return collect();
            }
        }
        $whereSql = sprintf(
            'WHERE r.nzbstatus = %d
			AND r.passwordstatus %s
			%s %s %s %s %s %s',
            NZB::NZB_ADDED,
            $this->showPasswords(),
            $showSql,
            (! empty($name) && ! empty($searchResult)) ? 'AND r.id IN ('.implode(',', $searchResult).')' : '',
            Category::getCategorySearch($cat, 'tv'),
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
     * @return Collection|mixed
     */
    public function apiTvSearch(array $siteIdArr = [], string $series = '', string $episode = '', string $airDate = '', int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, int $minSize = 0, array $excludedCategories = []): mixed
    {
        $query = Release::query()
            ->with(['video', 'video.episode', 'category', 'category.parent', 'group'])
            ->where('nzbstatus', NZB::NZB_ADDED)
            ->where('passwordstatus', $this->showPasswords())
            ->whereIn('categories_id', Category::getCategorySearch($cat, 'tv', true));

        // Check if siteIdArr contains id key
        if (! empty($siteIdArr) && array_key_exists('id', $siteIdArr) && $siteIdArr['id'] > 0) {
            $query->where('videos_id', $siteIdArr['id']);
        }
        if (! empty($series)) {
            $query->whereHas('episode', function ($q) use ($series, $episode, $airDate) {
                $q->where('series', (int) preg_replace('/^s0*/i', '', $series));
                if (! empty($episode)) {
                    $q->where('episode', (int) preg_replace('/^e0*/i', '', $episode));
                }
                if (! empty($airDate)) {
                    $q->whereDate('firstaired', $airDate);
                }
            });
        }

        if (! empty(array_filter($siteIdArr))) {
            $query->whereHas('video', function ($q) use ($siteIdArr) {
                foreach ($siteIdArr as $column => $id) {
                    if ($id > 0 && $column !== 'id') {
                        $q->orWhere($column, $id);
                    }

                }
            });
        }

        if (! empty($name)) {
            // If $name is set it is a fallback search, add available SxxExx/airdate info to the query
            if (! empty($series) && (int) $series < 1900) {
                $name .= sprintf(' S%s', str_pad($series, 2, '0', STR_PAD_LEFT));
                if (! empty($episode) && ! str_contains($episode, '/')) {
                    $name .= sprintf('E%s', str_pad($episode, 2, '0', STR_PAD_LEFT));
                }
                // If season is not empty but episode is, add a wildcard to the search
                if (empty($episode)) {
                    $name .= '*';
                }
            } elseif (! empty($airDate)) {
                $name .= sprintf(' %s', str_replace(['/', '-', '.', '_'], ' ', $airDate));
            }

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

            $query->whereIn('id', $searchResult);
        }

        if ($maxAge > 0) {
            $query->where('postdate', '>', now()->subDays($maxAge));
        }

        if (! empty($excludedCategories)) {
            $query->whereNotIn('categories_id', $excludedCategories);
        }

        if ($cat !== [-1]) {
            $query->whereIn('categories_id', $cat);
        }

        if ($minSize > 0) {
            $query->where('size', '>=', $minSize);
        }

        $query->orderBy('postdate', 'desc')
            ->offset($offset)
            ->limit($limit);

        $cacheKey = md5($query->toRawSql());
        $cacheTTL = now()->addMinutes(config('nntmux.cache_expiry_medium'));

        $releases = Cache::get($cacheKey);
        if ($releases !== null) {
            return $releases;
        }

        $releases = $query->get();

        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $query->count();
        }

        Cache::put($cacheKey, $releases, $cacheTTL);

        return $releases;
    }

    public function moviesSearch(int $imDbId = -1, int $tmDbId = -1, int $traktId = -1, int $offset = 0, int $limit = 100, string $name = '', array $cat = [-1], int $maxAge = -1, int $minSize = 0, array $excludedCategories = []): mixed
    {
        $query = self::query()
            ->with(['movieinfo', 'group', 'category', 'category.parent', 'nfo'])
            ->whereBetween('categories_id', [Category::MOVIE_ROOT, Category::MOVIE_OTHER])
            ->where('nzbstatus', NZB::NZB_ADDED)
            ->where('passwordstatus', $this->showPasswords());

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

            $query->whereIn('id', $searchResult);
        }

        if ($imDbId !== -1 && is_numeric($imDbId)) {
            $query->whereHas('movieinfo', function ($q) use ($imDbId) {
                $q->where('imdbid', $imDbId);
            });
        }

        if ($tmDbId !== -1 && is_numeric($tmDbId)) {
            $query->whereHas('movieinfo', function ($q) use ($tmDbId) {
                $q->where('tmdbid', $tmDbId);
            });
        }

        if ($traktId !== -1 && is_numeric($traktId)) {
            $query->whereHas('movieinfo', function ($q) use ($traktId) {
                $q->where('traktid', $traktId);
            });
        }

        if (! empty($excludedCategories)) {
            $query->whereNotIn('categories_id', $excludedCategories);
        }

        if ($cat !== [-1]) {
            $query->whereIn('categories_id', $cat);
        }

        if ($maxAge > 0) {
            $query->where('postdate', '>', now()->subDays($maxAge));
        }

        if ($minSize > 0) {
            $query->where('size', '>=', $minSize);
        }

        $totalRows = $query->count();

        $query->orderBy('postdate', 'desc')
            ->offset($offset)
            ->limit($limit);

        $cacheKey = md5($query->toRawSql());
        $cacheTTL = now()->addMinutes(config('nntmux.cache_expiry_medium'));

        $releases = Cache::get($cacheKey);
        if ($releases !== null) {
            return $releases;
        }

        $releases = $query->get();

        if ($releases->isNotEmpty()) {
            $releases[0]->_totalrows = $totalRows;
        }

        Cache::put($cacheKey, $releases, $cacheTTL);

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
     * Get count of releases for pager.
     *
     * @param  string  $query  The query to get the count from.
     */
    private function getPagerCount(string $query): int
    {
        $queryBuilder = DB::table(DB::raw('('.preg_replace(
            '/SELECT.+?FROM\s+releases/is',
            'SELECT r.id FROM releases',
            $query
        ).' LIMIT '.(int) config('nntmux.max_pager_results').') as z'))
            ->selectRaw('COUNT(z.id) as count');

        $sql = $queryBuilder->toSql();
        $count = Cache::get(md5($sql));

        if ($count !== null) {
            return $count;
        }

        $result = $queryBuilder->first();
        $count = $result->count ?? 0;

        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_short'));
        Cache::put(md5($sql), $count, $expiresAt);

        return $count;
    }
}
