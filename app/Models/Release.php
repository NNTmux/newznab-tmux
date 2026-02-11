<?php

namespace App\Models;

use App\Facades\Search;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Properties from computed columns, joins, and subqueries.
 *
 * @property int|null $relsize From join/computed column
 * @property int|null $_totalrows From subquery count
 * @property string|null $textstring From join
 * @property string|null $episodes From join
 * @property string|null $category_name From join
 * @property int|null $tvrage From join
 * @property int|null $tvmaze From join
 * @property int|null $tvdb From join
 * @property int|null $trakt From join
 * @property int|null $tmdb From join
 * @property int|null $preid From join
 * @property string|null $firstaired From join
 * @property string|null $title From join
 * @property string|null $summary From join
 * @property string|null $sub_category From join
 * @property float|null $size_diff Computed column
 * @property string|null $showtitle From join
 * @property string|null $se_complete From join
 * @property int|null $files_total_size Computed column
 * @property string|null $group_name From join (group relationship)
 * @property string|null $image From join (video.tvInfo relationship)
 * @property string|null $parent_category From join (category.parent relationship)
 * @property string|null $category_ids Computed from category relationship
 * @property string|null $group_names Computed from releaseGroup relationship
 * @property float|null $release_size Computed column (size in GiB)
 * @property float|null $diff_percent Computed column (difference percentage)
 * @property int|null $releases_id From raw query alias
 * @property int|null $_totalcount From subquery count
 */
class Release extends Model
{
    use HasFactory; // @phpstan-ignore missingType.generics

    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\UsenetGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(UsenetGroup::class, 'groups_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\UserDownload, $this>
     */
    public function download(): HasMany
    {
        return $this->hasMany(UserDownload::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\UsersRelease, $this>
     */
    public function userRelease(): HasMany
    {
        return $this->hasMany(UsersRelease::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ReleaseFile, $this>
     */
    public function file(): HasMany
    {
        return $this->hasMany(ReleaseFile::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'categories_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Predb, $this>
     */
    public function predb(): BelongsTo
    {
        return $this->belongsTo(Predb::class, 'predb_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\DnzbFailure, $this>
     */
    public function failed(): HasMany
    {
        return $this->hasMany(DnzbFailure::class, 'release_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\ReleaseNfo, $this>
     */
    public function nfo(): HasOne
    {
        return $this->hasOne(ReleaseNfo::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ReleaseComment, $this>
     */
    public function comment(): HasMany
    {
        return $this->hasMany(ReleaseComment::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ReleaseReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ReleaseReport::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ReleasesGroups, $this>
     */
    public function releaseGroup(): HasMany
    {
        return $this->hasMany(ReleasesGroups::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'videos_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\VideoData, $this>
     */
    public function videoData(): HasOne
    {
        return $this->hasOne(VideoData::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\TvEpisode, $this>
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(TvEpisode::class, 'tv_episodes_id');
    }

    /**
     * Insert a single release returning the ID on success or false on failure.
     *
     * @param  array<string, mixed>  $parameters  Insert parameters, must be escaped if string.
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
                ]
            );

        Search::insertRelease($parameters);

        return $parameters['id'];
    }

    /**
     * @throws \Exception
     */
    public static function updateRelease(mixed $id, mixed $name, mixed $searchName, mixed $fromName, mixed $categoryId, mixed $parts, mixed $grabs, mixed $size, mixed $postedDate, mixed $addedDate, mixed $videoId, mixed $episodeId, mixed $imDbId, mixed $aniDbId): void
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
    public static function getCatByRelId(mixed $id)
    {
        return self::whereId($id)->first(['categories_id']);
    }

    public static function removeVideoIdFromReleases(mixed $videoId): int
    {
        return self::whereVideosId($videoId)->update(['videos_id' => 0, 'tv_episodes_id' => 0]);
    }

    public static function removeAnidbIdFromReleases(mixed $anidbID): int
    {
        return self::whereAnidbid($anidbID)->update(['anidbid' => -1]);
    }

    public static function getTopDownloads(): mixed
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

    public static function getTopComments(): mixed
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

    public static function getByGuid(mixed $guid): mixed
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
    public static function getFailedRange(): LengthAwarePaginator // @phpstan-ignore missingType.generics
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

    public static function checkGuidForApi(mixed $guid): bool
    {
        $check = self::whereGuid($guid)->first();

        return $check !== null;
    }
}
