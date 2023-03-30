<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Video.
 *
 * @property int $id Show ID to be used in other tables as reference
 * @property bool $type 0 = TV, 1 = Film, 2 = Anime
 * @property string $title Name of the video.
 * @property string $countries_id Two character country code (FK to countries table).
 * @property string $started Date (UTC) of production's first airing.
 * @property int $anidb ID number for anidb site
 * @property int $imdb ID number for IMDB site (without the 'tt' prefix).
 * @property int $tmdb ID number for TMDB site.
 * @property int $trakt ID number for TraktTV site.
 * @property int $tvdb ID number for TVDB site
 * @property int $tvmaze ID number for TVMaze site.
 * @property int $tvrage ID number for TVRage site.
 * @property bool $source Which site did we use for info?
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\VideoAlias[] $alias
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TvEpisode[] $episode
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Release[] $release
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video whereAnidb($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video whereCountriesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video whereImdb($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video whereStarted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video whereTmdb($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video whereTrakt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video whereTvdb($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video whereTvmaze($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video whereTvrage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video whereType($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Video query()
 */
class Video extends Model
{
    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    public $timestamps = false;

    public function alias(): HasMany
    {
        return $this->hasMany(VideoAlias::class, 'videos_id');
    }

    public function release(): HasMany
    {
        return $this->hasMany(Release::class, 'videos_id');
    }

    public function episode(): HasMany
    {
        return $this->hasMany(TvEpisode::class, 'videos_id');
    }

    /**
     * Get info from tables for the provided ID.
     *
     *
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getByVideoID($id)
    {
        return self::query()
            ->select(['videos.*', 'tv_info.summary', 'tv_info.publisher', 'tv_info.image'])
            ->where('videos.id', $id)
            ->join('tv_info', 'videos.id', '=', 'tv_info.videos_id')
            ->first();
    }

    /**
     * Retrieves a range of all shows for the show-edit admin list.
     */
    public static function getRange(string $showname = ''): LengthAwarePaginator
    {
        $sql = self::query()
            ->select(['videos.*', 'tv_info.summary', 'tv_info.publisher', 'tv_info.image'])
            ->join('tv_info', 'videos.id', '=', 'tv_info.videos_id');

        if ($showname !== '') {
            $sql->where('videos.title', 'like', '%'.$showname.'%');
        }

        return $sql->paginate(config('nntmux.items_per_page'));
    }

    /**
     * Returns a count of all shows -- usually used by pager.
     */
    public static function getCount(string $showname = ''): int
    {
        $res = self::query()->join('tv_info', 'videos.id', '=', 'tv_info.videos_id');

        if ($showname !== '') {
            $res->where('videos.title', 'like', '%'.$showname.'%');
        }

        return $res->count('videos.id');
    }

    /**
     * Retrieves and returns a list of shows with eligible releases.
     */
    public static function getSeriesList($uid, string $letter = '', string $showname = ''): array
    {
        if (($letter !== '') && $letter === '0-9') {
            $letter = '[0-9]';
        }

        $qry = self::query()
            ->select(['videos.*', 'tve.firstaired as prevdate', 'tve.title as previnfo', 'tvi.publisher', 'us.id as userseriesid'])
            ->join('tv_info as tvi', 'videos.id', '=', 'tvi.videos_id')
            ->join('tv_episodes as tve', 'videos.id', '=', 'tve.videos_id')
            ->leftJoin('user_series as us', function ($join) use ($uid) {
                $join->on('videos.id', '=', 'us.videos_id')->where('us.users_id', '=', $uid);
            })
            ->whereBetween('r.categories_id', [Category::TV_ROOT, Category::TV_OTHER])
            ->where('tve.firstaired', '<', now())
            ->leftJoin('releases as r', 'r.videos_id', '=', 'videos.id')
            ->orderBy('videos.title')
            ->orderByDesc('tve.firstaired')
            ->groupBy(['videos.id']);

        if ($letter !== '') {
            $qry->whereRaw('videos.title REGEXP ?', ['^'.$letter]);
        }

        if ($showname !== '') {
            $qry->where('videos.title', 'like', '%'.$showname.'%');
        }

        return $qry->get()->toArray();
    }
}
