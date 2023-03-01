<?php

namespace App\Models;

use Blacklight\ElasticSearchSiteSearch;
use Blacklight\NZB;
use Blacklight\SphinxSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\Release.
 *
 * @property int $id
 * @property string $name
 * @property string $searchname
 * @property int|null $totalpart
 * @property int $groups_id FK to groups.id
 * @property int $size
 * @property string|null $postdate
 * @property string|null $adddate
 * @property string $updatetime
 * @property string|null $gid
 * @property string $guid
 * @property string $leftguid The first letter of the release guid
 * @property string|null $fromname
 * @property float $completion
 * @property int $categories_id
 * @property int $videos_id FK to videos.id of the parent series.
 * @property int $tv_episodes_id FK to tv_episodes.id for the episode.
 * @property int|null $imdbid
 * @property int $xxxinfo_id
 * @property int|null $musicinfo_id FK to musicinfo.id
 * @property int|null $consoleinfo_id FK to consoleinfo.id
 * @property int $gamesinfo_id
 * @property int|null $bookinfo_id FK to bookinfo.id
 * @property int|null $anidbid FK to anidb_titles.anidbid
 * @property int $predb_id FK to predb.id
 * @property int $grabs
 * @property int $comments
 * @property bool $passwordstatus
 * @property int $rarinnerfilecount
 * @property bool $haspreview
 * @property bool $nfostatus
 * @property bool $jpgstatus
 * @property bool $videostatus
 * @property bool $audiostatus
 * @property bool $dehashstatus
 * @property bool $reqidstatus
 * @property bool $nzbstatus
 * @property bool $iscategorized
 * @property bool $isrenamed
 * @property bool $ishashed
 * @property bool $proc_pp
 * @property bool $proc_sorter
 * @property bool $proc_par2
 * @property bool $proc_nfo
 * @property bool $proc_files
 * @property bool $proc_uid
 * @property bool $proc_srr Has the release been srr
 *                          processed
 * @property bool $proc_hash16k Has the release been hash16k
 *                              processed
 * @property mixed|null $nzb_guid
 * @property-read \App\Models\Category                                                    $category
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ReleaseComment[]   $comment
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\UserDownload[]     $download
 * @property-read \App\Models\TvEpisode                                                   $episode
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\DnzbFailure[]      $failed
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ReleaseFile[]      $file
 * @property-read \App\Models\UsenetGroup                                                 $group
 * @property-read \App\Models\ReleaseNfo                                                  $nfo
 * @property-read \App\Models\Predb                                                       $predb
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ReleaseExtraFull[] $releaseExtra
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ReleasesGroups[]   $releaseGroup
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\UsersRelease[]     $userRelease
 * @property-read \App\Models\Video                                                       $video
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereAdddate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereAnidbid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereAudiostatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereBookinfoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereCategoriesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereComments($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereCompletion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereConsoleinfoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereDehashstatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereFromname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereGamesinfoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereGid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereGrabs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereGroupsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereGuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereHaspreview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereImdbid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereIscategorized($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereIshashed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereIsrenamed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereJpgstatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereLeftguid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereMusicinfoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereNfostatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereNzbGuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereNzbstatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release wherePasswordstatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release wherePostdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release wherePredbId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereProcFiles($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereProcHash16k($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereProcNfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereProcPar2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereProcPp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereProcSorter($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereProcSrr($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereProcUid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereRarinnerfilecount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereReqidstatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereSearchname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereTotalpart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereTvEpisodesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereUpdatetime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereVideosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereVideostatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereXxxinfoId($value)
 * @mixin \Eloquent
 *
 * @property int|null $movieinfo_id FK to movieinfo.id
 * @property int $proc_crc32 Has the release been crc32 processed
 * @property mixed $tag_names
 * @property-read \Illuminate\Database\Eloquent\Collection
 * @property-read \Illuminate\Database\Eloquent\Collection
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereMovieinfoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release whereProcCrc32($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release withAllTags($tagNames)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release withAnyTag($tagNames)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Release withoutTags($tagNames)
 */
class Release extends Model
{
    use HasFactory;

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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group()
    {
        return $this->belongsTo(UsenetGroup::class, 'groups_id');
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
     * @param  array  $parameters  Insert parameters, must be escaped if string.
     * @return bool|int
     *
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
                    'adddate' => now(),
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

        if (config('nntmux.elasticsearch_enabled') === true) {
            (new ElasticSearchSiteSearch())->insertRelease($parameters);
        } else {
            (new SphinxSearch())->insertRelease($parameters);
        }

        return $parameters['id'];
    }

    /**
     * Used for release edit page on site.
     *
     * @param  int  $id
     * @param  string  $name
     * @param  string  $searchName
     * @param  string  $fromName
     * @param  int  $categoryId
     * @param  int  $parts
     * @param  int  $grabs
     * @param  int  $size
     * @param  string  $postedDate
     * @param  string  $addedDate
     * @param    $videoId
     * @param    $episodeId
     * @param  int  $imDbId
     * @param  int  $aniDbId
     * @param  string  $tags
     *
     * @throws \Exception
     */
    public static function updateRelease(int $id, string $name, string $searchName, string $fromName, int $categoryId, int $parts, int $grabs, int $size, string $postedDate, string $addedDate, $videoId, $episodeId, int $imDbId, int $aniDbId): void
    {
        $movieInfoId = null;
        if (! empty($imDbId)) {
            $movieInfoId = MovieInfo::whereImdbid($imDbId)->first(['id']);
        }
        self::whereId($id)->update(
            [
                'name' => $name,
                'searchname' => $searchName,
                'fromname' => $fromName,
                'categories_id' => $categoryId,
                'totalpart' => $parts,
                'grabs' => $grabs,
                'size' => $size,
                'postdate' => $postedDate,
                'adddate' => $addedDate,
                'videos_id' => $videoId,
                'tv_episodes_id' => $episodeId,
                'imdbid' => $imDbId,
                'anidbid' => $aniDbId,
                'movieinfo_id' => $movieInfoId !== null ? $movieInfoId->id : $movieInfoId,
            ]
        );

        if (config('nntmux.elasticsearch_enabled') === true) {
            (new ElasticSearchSiteSearch())->updateRelease($id);
        } else {
            (new SphinxSearch())->updateRelease($id);
        }
    }

    /**
     * @param  string  $guid
     *
     * @throws \Exception
     */
    public static function updateGrab($guid): void
    {
        $updateGrabs = ((int) Settings::settingValue('..grabstatus') !== 0);
        if ($updateGrabs) {
            self::whereGuid($guid)->increment('grabs');
        }
    }

    /**
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getCatByRelId($id)
    {
        return self::whereId($id)->first(['categories_id']);
    }

    /**
     * @param $videoId
     * @return int
     */
    public static function removeVideoIdFromReleases($videoId): int
    {
        return self::whereVideosId($videoId)->update(['videos_id' => 0, 'tv_episodes_id' => 0]);
    }

    /**
     * @param $anidbID
     * @return int
     */
    public static function removeAnidbIdFromReleases($anidbID): int
    {
        return self::whereAnidbid($anidbID)->update(['anidbid' => -1]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getTopDownloads()
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $releases = Cache::get(md5('topDownloads'));
        if ($releases !== null) {
            return $releases;
        }
        $releases = self::query()
            ->where('grabs', '>', 0)
            ->select(['id', 'searchname', 'guid', 'adddate'])
            ->selectRaw('SUM(grabs) as grabs')
            ->groupBy('id', 'searchname', 'adddate')
            ->havingRaw('SUM(grabs) > 0')
            ->orderBy('grabs', 'desc')
            ->limit(10)
            ->get();

        Cache::put(md5('topDownloads'), $releases, $expiresAt);

        return $releases;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getTopComments()
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $releases = Cache::get(md5('topComments'));
        if ($releases !== null) {
            return $releases;
        }
        $releases = self::query()
            ->where('comments', '>', 0)
            ->select(['id', 'guid', 'searchname'])
            ->selectRaw('SUM(comments) AS comments')
            ->groupBy('id', 'searchname', 'adddate')
            ->havingRaw('SUM(comments) > 0')
            ->orderBy('comments', 'desc')
            ->limit(10)
            ->get();

        Cache::put(md5('topComments'), $releases, $expiresAt);

        return $releases;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Query\Builder[]|\Illuminate\Support\Collection|mixed
     */
    public static function getReleases()
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $releases = Cache::get(md5('releases'));
        if ($releases !== null) {
            return $releases;
        }

        $releases = self::query()

            ->where('nzbstatus', '=', NZB::NZB_ADDED)
            ->select(['releases.*', 'g.name as group_name', 'c.title as category_name'])
            ->leftJoin('categories as c', 'c.id', '=', 'releases.categories_id')
            ->leftJoin('usenet_groups as g', 'g.id', '=', 'releases.groups_id')
            ->get();

        Cache::put(md5('releases'), $releases, $expiresAt);

        return $releases;
    }

    /**
     * Used for admin page release-list.
     *
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|mixed
     */
    public static function getReleasesRange()
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $releases = Cache::get(md5('releasesRange'));
        if ($releases !== null) {
            return $releases;
        }

        $releases = self::query()
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
                    'cp.title as parent_category',
                    'c.title as sub_category',
                    DB::raw('CONCAT(cp.title, ' > ', c.title) AS category_name'),
                ]
            )
            ->leftJoin('categories as c', 'c.id', '=', 'releases.categories_id')
            ->leftJoin('root_categories as cp', 'cp.id', '=', 'c.root_categories_id')
            ->orderByDesc('releases.postdate')
            ->paginate(config('nntmux.items_per_page'));

        Cache::put(md5('releasesRange'), $releases, $expiresAt);

        return $releases;
    }

    /**
     * @param $guid
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|\Illuminate\Support\Collection|null|static|static[]
     */
    public static function getByGuid($guid)
    {
        $sql = self::query()->with('tagged')
            ->select(
                [
                    'releases.*',
                    'g.name as group_name',
                    'v.title as showtitle',
                    'v.tvdb',
                    'v.trakt',
                    'v.tvrage',
                    'v.tvmaze',
                    'v.source',
                    'tvi.summary',
                    'tvi.image',
                    'tve.title',
                    'tve.firstaired',
                    'tve.se_complete',
                    'cp.title as parent_category',
                    'c.title as sub_category',
                    DB::raw("CONCAT(cp.title, ' > ', c.title) AS category_name, CONCAT(cp.id, ',', c.id) AS category_ids,GROUP_CONCAT(g2.name ORDER BY g2.name ASC SEPARATOR ',') AS group_names"),
                ]
            )
            ->leftJoin('usenet_groups as g', 'g.id', '=', 'releases.groups_id')
            ->leftJoin('categories as c', 'c.id', '=', 'releases.categories_id')
            ->leftJoin('root_categories as cp', 'cp.id', '=', 'c.root_categories_id')
            ->leftJoin('videos as v', 'v.id', '=', 'releases.videos_id')
            ->leftJoin('tv_info as tvi', 'tvi.videos_id', '=', 'releases.videos_id')
            ->leftJoin('tv_episodes as tve', 'tve.id', '=', 'releases.tv_episodes_id')
            ->leftJoin('releases_groups as rg', 'rg.releases_id', '=', 'releases.id')
            ->leftJoin('usenet_groups as g2', 'rg.groups_id', '=', 'g2.id');

        if (\is_array($guid)) {
            $tempGuids = [];
            foreach ($guid as $identifier) {
                $tempGuids[] = $identifier;
            }
            $sql->whereIn('releases.guid', $tempGuids);
        } else {
            $sql->where('releases.guid', $guid);
        }
        $sql->groupBy('releases.id');

        return \is_array($guid) ? $sql->get() : $sql->first();
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
            ->select(['name', 'searchname', 'size', 'guid', 'totalpart', 'postdate', 'adddate', 'grabs', 'cp.title as parent_category', 'c.title as sub_category', DB::raw("CONCAT(cp.title, ' > ', c.title) AS category_name")])
            ->rightJoin('dnzb_failures', 'dnzb_failures.release_id', '=', 'releases.id')
            ->leftJoin('categories as c', 'c.id', '=', 'releases.categories_id')
            ->leftJoin('root_categories as cp', 'cp.id', '=', 'c.root_categories_id')
            ->orderBy('postdate', 'desc');

        return $failedList->paginate(config('nntmux.items_per_page'));
    }

    /**
     * Retrieve alternate release with same or similar searchname.
     *
     * @param $guid
     * @param $userid
     * @return false|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     *
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     * @throws \Foolz\SphinxQL\Exception\DatabaseException
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     */
    public static function getAlternate($guid, $userid)
    {
        $rel = self::whereGuid($guid)->first(['id', 'searchname', 'categories_id']);

        if ($rel === null) {
            return false;
        }
        DnzbFailure::insertOrIgnore(['release_id' => $rel['id'], 'users_id' => $userid, 'failed' => 1]);

        preg_match('/(^\w+[-_. ].+?\.(\d+p)).+/i', $rel['searchname'], $similar);

        if (! empty($similar)) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $searchResult = (new ElasticSearchSiteSearch())->indexSearch($similar[1], 10);
            } else {
                $results = (new SphinxSearch())->searchIndexes('releases_rt', $similar[1]);

                $searchResult = Arr::pluck($results, 'id');
            }

            if (empty($searchResult)) {
                return false;
            }

            return self::query()->leftJoin('dnzb_failures as df', 'df.release_id', '=', 'releases.id')->whereIn('releases.id', $searchResult)->where('df.release_id', '=', null)->where('releases.categories_id', $rel['categories_id'])->where('id', '<>', $rel['id'])->orderBy('releases.postdate', 'desc')->first(['guid']);
        }

        return false;
    }

    /**
     * @param $guid
     * @return bool
     */
    public static function checkGuidForApi($guid): bool
    {
        $check = self::whereGuid($guid)->first();

        return $check !== null;
    }
}
