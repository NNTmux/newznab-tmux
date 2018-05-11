<?php

namespace Blacklight\http;

use Blacklight\NZB;
use App\Models\Release;
use App\Models\Category;
use Blacklight\Releases;
use App\Models\UserMovie;
use App\Models\UserSerie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Class RSS -- contains specific functions for RSS.
 */
class RSS extends Capabilities
{
    /** Releases class
     * @var Releases
     */
    public $releases;

    /**
     * @param array $options
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $defaults = [
            'Settings' => null,
            'Releases' => null,
        ];
        $options += $defaults;

        $this->releases = ($options['Releases'] instanceof Releases ? $options['Releases'] : new Releases(['Settings' => $this->pdo]));
    }

    /**
     * Get releases for RSS.
     *
     *
     * @param     $cat
     * @param     $offset
     * @param     $videosId
     * @param     $aniDbID
     * @param int $userID
     * @param int $airDate
     *
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Query\Builder[]|\Illuminate\Support\Collection|mixed
     * @throws \Exception
     */
    public function getRss($cat, $offset, $videosId, $aniDbID, $userID = 0, $airDate = -1)
    {
        $catSearch = $cartSearch = '';

        $sql = Release::query()->select(
            [
                'r.*',
                'm.cover',
                'm.imdbid',
                'm.rating',
                'm.plot',
                'm.year',
                'm.genre',
                'm.director',
                'm.actors',
                'g.name as group_name',
                DB::raw("CONCAT(cp.title, '-', c.title) as category_name"),
                DB::raw('COALESCE(cp.id,0) as parentid'),
                'mu.title as mu_title',
                'mu.url as mu_url',
                'mu.artist as mu_artist',
                'mu.publisher as mu_publisher',
                'mu.releasedate as mu_releasedate',
                'mu.review as mu_review',
                'mu.tracks as mu_tracks',
                'mu.cover as mu_cover',
                'mug.title as mu_genre',
                'co.title as co_title',
                'co.url as co_url',
                'co.publisher as co_publisher',
                'co.releasedate as co_releasedate',
                'co.review as co_review',
                'co.cover as co_cover',
                'cog.title as co_genre',
                'bo.cover as bo_cover', ])
            ->from('releases as r')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
            ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
            ->leftJoin('groups as g', 'g.id', '=', 'r.groups_id')
            ->leftJoin('movieinfo as m', function ($q) {
                $q->on('m.imdbid', '=', 'r.imdbid')
                    ->where('m.title', '<>', '');
            })
            ->leftJoin('musicinfo as mu', 'mu.id', '=', 'r.musicinfo_id')
            ->leftJoin('genres as mug', 'mug.id', '=', 'mu.genres_id')
            ->leftJoin('consoleinfo as co', 'co.id', '=', 'r.consoleinfo_id')
            ->leftJoin('genres as cog', 'cog.id', '=', 'co.genres_id')
            ->leftJoin('tv_episodes as tve', 'tve.id', '=', 'r.tv_episodes_id')
            ->leftJoin('bookinfo as bo', 'bo.id', '=', 'r.bookinfo_id');
        if (\count($cat)) {
            if ((int) $cat[0] === -2) {
                $sql->join('users_releases as ur', function ($join) use ($userID) {
                    $join->on('ur.users_id', '=', $userID)
                        ->on('ur.releases_id', '=', 'r.id');
                });
            } elseif ((int) $cat[0] !== -1) {
                Category::getCategorySearch($cat, $sql, true);
            }
        }
        Releases::showPasswords($sql, true);
        $sql->where('r.nzbstatus', NZB::NZB_ADDED);

        if ($videosId > 0) {
            $sql->where('r.videos_id', $videosId);
            if ($catSearch === '') {
                $sql->whereBetween('r.categories_id', [Category::TV_ROOT, Category::TV_OTHER]);
            }
        }

        if ($aniDbID > 0) {
            $sql->where('r.anidbid', $aniDbID);
            if ($catSearch === '') {
                $sql->whereBetween('r.categories_id', [Category::TV_ROOT, Category::TV_OTHER]);
            }
        }

        if ($airDate > -1) {
            $sql->where('tve.firstaired', '>=', Carbon::now()->subDays($airDate));
        }

        $sql->orderByDesc('r.postdate')
            ->offset(0)
            ->limit($offset > 100 ? 100 : $offset);

        $expiresAt = Carbon::now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $result = Cache::get(md5(implode('.', $cat).$offset.$videosId.$aniDbID.$userID.$airDate));
        if ($result !== null) {
            return $result;
        }

        $result = $sql->get();
        Cache::put(md5(implode('.', $cat).$offset.$videosId.$aniDbID.$userID.$airDate), $result, $expiresAt);

        return $result;
    }

    /**
     * @param       $limit
     * @param int   $userID
     * @param array $excludedCats
     * @param int   $airDate
     *
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     * @throws \Exception
     */
    public function getShowsRss($limit, $userID = 0, array $excludedCats = [], $airDate = -1)
    {
        $query = Release::query()
            ->where('r.nzbstatus', NZB::NZB_ADDED)
            ->whereBetween('r.categories_id', [Category::TV_ROOT, Category::TV_OTHER]);
        Releases::showPasswords($query, true);
        $this->releases->uSQL(UserSerie::query()->where('users_id', $userID)->select(['videos_id', 'categories'])->get(), 'videos_id');
        if (\count($excludedCats) > 0) {
            $query->whereNotIn('r.categories_id', $excludedCats);
        }

        if ($airDate > -1) {
            $query->where('tve.firstaired', '>=', Carbon::now()->subDays($airDate));
        }

        $query->select(
            [
                'r.*',
                'v.id',
                'v.title',
                'g.name as group_name',
                DB::raw("CONCAT(cp.title, '-', c.title) as category_name"),
                DB::raw('COALESCE(cp.id,0) as parentid'),
            ])
            ->from('releases as r')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
            ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
            ->leftJoin('groups as g', 'g.id', '=', 'r.groups_id')
            ->leftJoin('videos as v', 'v.id', '=', 'r.videos_id')
            ->leftJoin('tv_episodes as tve', 'tve.id', '=', 'r.tv_episodes_id')
            ->orderByDesc('r.postdate')
            ->limit($limit > 100 ? 100 : $limit)
            ->offset(0);

        $expiresAt = Carbon::now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $result = Cache::get(md5($limit.$userID.implode('.', $excludedCats).$airDate));
        if ($result !== null) {
            return $result;
        }

        $result = $query->get();
        Cache::put(md5($limit.$userID.implode('.', $excludedCats).$airDate), $result, $expiresAt);

        return $result;
    }

    /**
     * Get movies for RSS.
     *
     *
     * @param       $limit
     * @param int   $userID
     * @param array $excludedCats
     *
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|mixed
     * @throws \Exception
     */
    public function getMyMoviesRss($limit, $userID = 0, array $excludedCats = [])
    {
        $query = Release::query()
            ->where('r.nzbstatus', NZB::NZB_ADDED)
            ->whereBetween('r.categories_id', [Category::MOVIE_ROOT, Category::MOVIE_OTHER]);
        Releases::showPasswords($query, true);
        $this->releases->uSQL(UserMovie::query()->where('users_id', $userID)->select(['imdbid', 'categories'])->get(), 'imdbid');
        if (\count($excludedCats) > 0) {
            $query->whereNotIn('r.categories_id', $excludedCats);
        }

        $query->select(
            [
                'r.*',
                'mi.title',
                'g.name as group_name',
                DB::raw("CONCAT(cp.title, '-', c.title) as category_name"),
                DB::raw('COALESCE(cp.id,0) as parentid'),
            ])
            ->from('releases as r')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
            ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
            ->leftJoin('groups as g', 'g.id', '=', 'r.groups_id')
            ->leftJoin('movieinfo as mi', 'mi.imdbid', '=', 'r.imdbid')
            ->orderByDesc('r.postdate')
            ->limit($limit > 100 ? 100 : $limit)
            ->offset(0);

        $expiresAt = Carbon::now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $result = Cache::get(md5($limit.$userID.implode('.', $excludedCats)));
        if ($result !== null) {
            return $result;
        }

        $result = $query->get();
        Cache::put(md5($limit.$userID.implode('.', $excludedCats)), $result, $expiresAt);

        return $result;
    }

    /**
     * @param $column
     * @param $table
     * @param $order
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null
     */
    public function getFirstInstance($column, $table, $order)
    {
        return DB::table($table)
            ->select([$column])
            ->where($column, '>', 0)
            ->orderBy($order, 'asc')
            ->first();
    }
}
