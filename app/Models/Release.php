<?php

namespace App\Models;

use Blacklight\NZB;
use Blacklight\SphinxSearch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Watson\Rememberable\Rememberable;

class Release extends Model
{
    use Rememberable;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $guarded = [];

    protected $rememberCacheDriver = 'redis';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'groups_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function download()
    {
        return $this->hasMany(UserDownload::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userRelease()
    {
        return $this->hasMany(UsersRelease::class, 'releases_id');
    }

    public function file()
    {
        return $this->hasMany(ReleaseFile::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'categories_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function predb()
    {
        return $this->belongsTo(Predb::class, 'predb_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function failed()
    {
        return $this->hasMany(DnzbFailure::class, 'release_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function releaseExtra()
    {
        return $this->hasMany(ReleaseExtraFull::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function nfo()
    {
        return $this->hasOne(ReleaseNfo::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comment()
    {
        return $this->hasMany(ReleaseComment::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function releaseGroup()
    {
        return $this->hasMany(ReleasesGroups::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function video()
    {
        return $this->belongsTo(Video::class, 'videos_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function episode()
    {
        return $this->belongsTo(TvEpisode::class, 'tv_episodes_id');
    }

    /**
     * Insert a single release returning the ID on success or false on failure.
     *
     * @param array $parameters Insert parameters, must be escaped if string.
     *
     * @return bool|int
     * @throws \Exception
     */
    public static function insertRelease(array $parameters = [])
    {
        $passwordStatus = ((int) Settings::settingValue('..checkpasswordedrar') === 1 ? -1 : 0);
        $parameters['id'] = self::query()
            ->insertGetId(
                [
                    'name' => $parameters['name'],
                    'searchname' => $parameters['searchname'],
                    'totalpart' => $parameters['totalpart'],
                    'groups_id' => $parameters['groups_id'],
                    'adddate' => Carbon::now(),
                    'guid' => $parameters['guid'],
                    'leftguid' => $parameters['guid'][0],
                    'postdate' => $parameters['postdate'],
                    'fromname' => $parameters['fromname'],
                    'size' => $parameters['size'],
                    'passwordstatus' => $passwordStatus,
                    'haspreview' => -1,
                    'categories_id' => $parameters['categories_id'],
                    'nfostatus' => -1,
                    'nzbstatus' => $parameters['nzbstatus'],
                    'isrenamed' => $parameters['isrenamed'],
                    'iscategorized' => 1,
                    'predb_id' => $parameters['predb_id'],
                ]
            );

        (new SphinxSearch())->insertRelease($parameters);

        return $parameters['id'];
    }

    /**
     * Used for release edit page on site.
     *
     * @param int $ID
     * @param string $name
     * @param string $searchName
     * @param string $fromName
     * @param int $categoryID
     * @param int $parts
     * @param int $grabs
     * @param int $size
     * @param string $postedDate
     * @param string $addedDate
     * @param        $videoId
     * @param        $episodeId
     * @param int $imDbID
     * @param int $aniDbID
     * @throws \Exception
     */
    public static function updateRelease($ID, $name, $searchName, $fromName, $categoryID, $parts, $grabs, $size, $postedDate, $addedDate, $videoId, $episodeId, $imDbID, $aniDbID): void
    {
        self::query()->where('id', $ID)->update(
            [
                'name' => $name,
                'searchname' => $searchName,
                'fromname' => $fromName,
                'categories_id' => $categoryID,
                'totalpart' => $parts,
                'grabs' => $grabs,
                'size' => $size,
                'postdate' => $postedDate,
                'adddate' => $addedDate,
                'videos_id' => $videoId,
                'tv_episodes_id' => $episodeId,
                'imdbid' => $imDbID,
                'anidbid' => $aniDbID,
            ]
        );
        (new SphinxSearch())->updateRelease($ID);
    }

    /**
     * @param string $guid
     * @throws \Exception
     */
    public static function updateGrab($guid): void
    {
        $updateGrabs = ((int) Settings::settingValue('..grabstatus') !== 0);
        if ($updateGrabs) {
            self::query()->where('guid', $guid)->increment('grabs');
        }
    }

    /**
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getCatByRelId($id)
    {
        return self::query()->where('id', $id)->first(['categories_id']);
    }

    /**
     * @param $videoId
     * @return int
     */
    public static function removeVideoIdFromReleases($videoId): int
    {
        return self::query()->where('videos_id', $videoId)->update(['videos_id' => 0, 'tv_episodes_id' => 0]);
    }

    /**
     * @param $anidbID
     * @return int
     */
    public static function removeAnidbIdFromReleases($anidbID): int
    {
        return self::query()->where('anidbid', $anidbID)->update(['anidbid' => -1]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getTopDownloads()
    {
        return self::query()
            ->remember(15)
            ->where('grabs', '>', 0)
            ->select(['id', 'searchname', 'guid', 'adddate'])
            ->selectRaw('SUM(grabs) as grabs')
            ->groupBy('id', 'searchname', 'adddate')
            ->havingRaw('SUM(grabs) > 0')
            ->orderBy('grabs', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getTopComments()
    {


        return self::query()
            ->remember(15)
            ->where('comments', '>', 0)
            ->select(['id', 'guid', 'searchname'])
            ->selectRaw('SUM(comments) AS comments')
            ->groupBy('id', 'searchname', 'adddate')
            ->havingRaw('SUM(comments) > 0')
            ->orderBy('comments', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * @return array
     */
    public static function getReleases(): array
    {

        return self::query()
            ->remember(15)
            ->where('nzbstatus', '=', NZB::NZB_ADDED)
            ->select(['releases.*', 'g.name as group_name', 'c.title as category_name'])
            ->leftJoin('categories as c', 'c.id', '=', 'releases.categories_id')
            ->leftJoin('groups as g', 'g.id', '=', 'releases.groups_id')
            ->get();
    }

    /**
     * Used for admin page release-list.
     *
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|mixed
     */
    public static function getReleasesRange()
    {
       return self::query()
           ->remember(10)
           ->where('nzbstatus', '=', NZB::NZB_ADDED)
           ->select(
                [
                    'releases.id',
                    'releases.name',
                    'releases.searchname',
                    'releases.size',
                    'releases.guid',
                    'releases.totalpart',
                    'releases.postdate',
                    'releases.adddate',
                    'releases.grabs',
                ]
            )
            ->selectRaw('CONCAT(cp.title, ' > ', c.title) AS category_name')
            ->leftJoin('categories as c', 'c.id', '=', 'releases.categories_id')
            ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
            ->orderByDesc('releases.postdate')
            ->paginate(config('nntmux.items_per_page'));

    }

    /**
     * Get count for admin release list page.
     *
     * @return int
     */
    public static function getReleasesCount(): int
    {

        $res = self::query()->remember(10)->count(['id']);

        return $res ?? 0;
    }

    /**
     * @param $guid
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|\Illuminate\Support\Collection|null|static|static[]
     */
    public static function getByGuid($guid)
    {
        $sql = self::query()
            ->remember(5)
            ->select(['releases.*', 'g.name as group_name', 'v.title as showtitle', 'v.tvdb', 'v.trakt', 'v.tvrage', 'v.tvmaze', 'v.source', 'tvi.summary', 'tvi.image', 'tve.title', 'tve.firstaired', 'tve.se_complete'])
            ->selectRaw("CONCAT(cp.title, ' > ', c.title) AS category_name, CONCAT(cp.id, ',', c.id) AS category_ids,GROUP_CONCAT(g2.name ORDER BY g2.name ASC SEPARATOR ',') AS group_names")
            ->leftJoin('groups as g', 'g.id', '=', 'releases.groups_id')
            ->leftJoin('categories as c', 'c.id', '=', 'releases.categories_id')
            ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
            ->leftJoin('videos as v', 'v.id', '=', 'releases.videos_id')
            ->leftJoin('tv_info as tvi', 'tvi.videos_id', '=', 'releases.videos_id')
            ->leftJoin('tv_episodes as tve', 'tve.id', '=', 'releases.tv_episodes_id')
            ->leftJoin('releases_groups as rg', 'rg.releases_id', '=', 'releases.id')
            ->leftJoin('groups as g2', 'rg.groups_id', '=', 'g2.id');

        if (\is_array($guid)) {
            $tempGuids = [];
            foreach ($guid as $identifier) {
                $tempGuids[] = $identifier;
            }
            $sql->whereIn('releases.guid', $tempGuids);
        } else {
            $sql->where('releases.guid', '=', $guid);
        }

        $result = \is_array($guid) ? $sql->groupBy('releases.id')->get() : $sql->groupBy('releases.id')->first();

        return $result;
    }

    /**
     * Get a range of releases. used in admin manage list.
     *
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getFailedRange()
    {
        $failedList = self::query()
            ->select(['name', 'searchname', 'size', 'guid', 'totalpart', 'postdate', 'adddate', 'grabs', DB::raw("CONCAT(cp.title, ' > ', c.title) AS category_name")])
            ->rightJoin('dnzb_failures', 'dnzb_failures.release_id', '=', 'releases.id')
            ->leftJoin('categories as c', 'c.id', '=', 'releases.categories_id')
            ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
            ->orderBy('postdate', 'desc');

        return $failedList->paginate(config('nntmux.items_per_page'));
    }

    /**
     * Retrieve alternate release with same or similar searchname.
     *
     *
     * @param $guid
     * @param $userid
     * @return bool|\Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getAlternate($guid, $userid)
    {
        $rel = self::query()->where('guid', $guid)->first(['id', 'searchname', 'categories_id']);

        if ($rel === null) {
            return false;
        }
        DnzbFailure::insertIgnore(['release_id' => $rel['id'], 'users_id' => $userid, 'failed' => 1]);

        $alternate = self::query()
            ->leftJoin('dnzb_failures as df', 'df.release_id', '=', 'releases.id')
            ->where('searchname', 'LIKE', $rel['searchname'])
            ->where('df.release_id', '=', null)
            ->where('categories_id', $rel['categories_id'])
            ->where('id', $rel['id'])
            ->orderBy('postdate', 'desc')
            ->first(['guid']);

        return $alternate;
    }

    /**
     * @param $guid
     * @return bool
     */
    public static function checkGuidForApi($guid): bool
    {
        $check = self::query()->where('guid', $guid)->first();

        return $check !== null;
    }
}
