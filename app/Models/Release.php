<?php

namespace App\Models;

use App\Facades\Search;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

    public function group(): BelongsTo
    {
        return $this->belongsTo(UsenetGroup::class, 'groups_id');
    }

    public function download(): HasMany
    {
        return $this->hasMany(UserDownload::class, 'releases_id');
    }

    public function userRelease(): HasMany
    {
        return $this->hasMany(UsersRelease::class, 'releases_id');
    }

    public function file(): HasMany
    {
        return $this->hasMany(ReleaseFile::class, 'releases_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'categories_id');
    }

    public function predb(): BelongsTo
    {
        return $this->belongsTo(Predb::class, 'predb_id');
    }

    public function failed(): HasMany
    {
        return $this->hasMany(DnzbFailure::class, 'release_id');
    }

    public function nfo(): HasOne
    {
        return $this->hasOne(ReleaseNfo::class, 'releases_id');
    }

    public function comment(): HasMany
    {
        return $this->hasMany(ReleaseComment::class, 'releases_id');
    }

    public function releaseGroup(): HasMany
    {
        return $this->hasMany(ReleasesGroups::class, 'releases_id');
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'videos_id');
    }

    public function videoData(): HasOne
    {
        return $this->hasOne(VideoData::class, 'releases_id');
    }

    public function episode(): BelongsTo
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
        $passwordStatus = config('nntmux_settings.check_passworded_rars') === true ? -1 : 0;
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
                    'source' => $parameters['source'] ?? null,
                    'ishashed' => $parameters['ishashed'] ?? 0,
                ]
            );

        Search::insertRelease($parameters);

        return $parameters['id'];
    }

    /**
     * @throws \Exception
     */
    public static function updateRelease($id, $name, $searchName, $fromName, $categoryId, $parts, $grabs, $size, $postedDate, $addedDate, $videoId, $episodeId, $imDbId, $aniDbId): void
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

        Search::updateRelease($id);
    }

    /**
     * @throws \Exception
     */
    public static function updateGrab(string $guid): void
    {
        $updateGrabs = ((int) Settings::settingValue('grabstatus') !== 0);
        if ($updateGrabs) {
            self::whereGuid($guid)->increment('grabs');
        }
    }

    /**
     * @return Model|null|static
     */
    public static function getCatByRelId($id)
    {
        return self::whereId($id)->first(['categories_id']);
    }

    public static function removeVideoIdFromReleases($videoId): int
    {
        return self::whereVideosId($videoId)->update(['videos_id' => 0, 'tv_episodes_id' => 0]);
    }

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
            ->orderByDesc('grabs')
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
            ->orderByDesc('comments')
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
     * @param  int  $page  The page number to retrieve
     * @return LengthAwarePaginator|mixed
     */
    public static function getReleasesRange(int $page = 1): mixed
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $cacheKey = md5('releasesRange_'.$page);
        $releases = Cache::get($cacheKey);
        if ($releases !== null) {
            return $releases;
        }

        $releases = self::query()
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
                    DB::raw('CONCAT(cp.title, \' > \', c.title) AS category_name'),
                ]
            )
            ->leftJoin('categories as c', 'c.id', '=', 'releases.categories_id')
            ->leftJoin('root_categories as cp', 'cp.id', '=', 'c.root_categories_id')
            ->orderByDesc('releases.postdate')
            ->paginate(config('nntmux.items_per_page'), ['*'], 'page', $page);

        Cache::put($cacheKey, $releases, $expiresAt);

        return $releases;
    }

    public static function getByGuid($guid)
    {
        $query = self::with([
            'group:id,name',
            'category:id,title,root_categories_id',
            'category.parent:id,title',
            'video:id,title,tvdb,trakt,tvrage,tvmaze,source',
            'video.tvInfo:videos_id,summary,image',
            'episode:id,title,firstaired,se_complete',
        ]);

        if (is_array($guid)) {
            $query->whereIn('guid', $guid);
        } else {
            $query->where('guid', $guid);
        }

        $releases = $query->get();

        $releases->each(function ($release) {
            $release->group_name = $release->group->name ?? null;
            $release->showtitle = $release->video->title ?? null;
            $release->tvdb = $release->video->tvdb ?? null;
            $release->trakt = $release->video->trakt ?? null;
            $release->tvrage = $release->video->tvrage ?? null;
            $release->tvmaze = $release->video->tvmaze ?? null;
            $release->source = $release->video->source ?? null;
            $release->summary = $release->video->tvInfo->summary ?? null;
            $release->image = $release->video->tvInfo->image ?? null;
            $release->title = $release->episode->title ?? null;
            $release->firstaired = $release->episode->firstaired ?? null;
            $release->se_complete = $release->episode->se_complete ?? null;
            $release->parent_category = $release->category->parent->title ?? null;
            $release->sub_category = $release->category->title ?? null;
            $release->category_name = $release->parent_category.' > '.$release->sub_category;
            $release->category_ids = $release->category ? ($release->category->parentid.','.$release->category->id) : '';
            $release->group_names = $release->releaseGroup->map(function ($relGroup) {
                return $relGroup->group ? $relGroup->group->name : null;
            })->implode(',');
        });

        return is_array($guid) ? $releases : $releases->first();
    }

    /**
     * Get a range of releases. used in admin manage list.
     */
    public static function getFailedRange(): LengthAwarePaginator
    {
        $failedList = self::query()
            ->select(['name', 'searchname', 'size', 'guid', 'totalpart', 'postdate', 'adddate', 'grabs', 'cp.title as parent_category', 'c.title as sub_category', DB::raw("CONCAT(cp.title, ' > ', c.title) AS category_name")])
            ->rightJoin('dnzb_failures', 'dnzb_failures.release_id', '=', 'releases.id')
            ->leftJoin('categories as c', 'c.id', '=', 'releases.categories_id')
            ->leftJoin('root_categories as cp', 'cp.id', '=', 'c.root_categories_id')
            ->orderByDesc('postdate');

        return $failedList->paginate(config('nntmux.items_per_page'));
    }

    /**
     * @return Release|false|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public static function getAlternate(string $guid, int $userid)
    {
        $rel = self::whereGuid($guid)->first(['id', 'searchname', 'categories_id']);

        if ($rel === null) {
            return false;
        }
        DnzbFailure::insertOrIgnore(['release_id' => $rel['id'], 'users_id' => $userid, 'failed' => 1]);

        preg_match('/(^\w+[-_. ].+?\.(\d+p)).+/i', $rel['searchname'], $similar);

        if (! empty($similar)) {
            $searchResult = Search::searchReleases($similar[1], 10);

            if (empty($searchResult)) {
                return false;
            }

            return self::query()->leftJoin('dnzb_failures as df', 'df.release_id', '=', 'releases.id')->whereIn('releases.id', $searchResult)->where('df.release_id', '=', null)->where('releases.categories_id', $rel['categories_id'])->where('id', '<>', $rel['id'])->orderByDesc('releases.postdate')->first(['guid']);
        }

        return false;
    }

    public static function checkGuidForApi($guid): bool
    {
        $check = self::whereGuid($guid)->first();

        return $check !== null;
    }
}
