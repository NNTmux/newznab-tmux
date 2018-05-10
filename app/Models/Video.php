<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Support\Database\CacheQueryBuilder;

class Video extends Model
{
    use CacheQueryBuilder;
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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function alias()
    {
        return $this->hasMany(VideoAlias::class, 'videos_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function release()
    {
        return $this->hasMany(Release::class, 'videos_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function episode()
    {
        return $this->hasMany(TvEpisode::class, 'videos_id');
    }

    /**
     * Get info from tables for the provided ID.
     *
     *
     * @param $id
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
     *
     *
     * @param string $showname
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getRange($showname = '')
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
     *
     *
     * @param string $showname
     * @return int
     */
    public static function getCount($showname = ''): int
    {
        $res = self::query()->join('tv_info', 'videos.id', '=', 'tv_info.videos_id');

        if ($showname !== '') {
            $res->where('videos.title', 'like', '%'.$showname.'%');
        }

        return $res->count('videos.id');
    }

    /**
     * Retrieves and returns a list of shows with eligible releases.
     *
     * @param        $uid
     * @param string $letter
     * @param string $showname
     *
     * @return array
     */
    public static function getSeriesList($uid, $letter = '', $showname = ''): array
    {
        if ($letter !== '') {
            if ($letter === '0-9') {
                $letter = '[0-9]';
            }
        }

        $qry = self::query()
            ->select(['videos.*', 'tve.firstaired as prevdate', 'tve.title as previnfo', 'tvi.publisher', 'us.id as userseriesid'])
            ->join('tv_info as tvi', 'videos.id', '=', 'tvi.videos_id')
            ->join('tv_episodes as tve', 'videos.id', '=', 'tve.videos_id')
            ->leftJoin('user_series as us', function ($join) use ($uid) {
                $join->on('videos.id', '=', 'us.videos_id')->where('us.users_id', '=', $uid);
            })
            ->whereBetween('r.categories_id', [Category::TV_ROOT, Category::TV_OTHER])
            ->where('tve.firstaired', '<', Carbon::now())
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
