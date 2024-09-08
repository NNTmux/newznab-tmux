<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class SiteStat extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getTopGrabbers()
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $result = Cache::get(md5('topGrabbers'));
        if ($result !== null) {
            return $result;
        }
        $result =  User::query()->selectRaw('id, username, SUM(grabs) as grabs')->groupBy('id', 'username')->having('grabs', '>', 0)->orderByDesc('grabs')->limit(10)->get();
        Cache::put(md5('topGrabbers'), $result, $expiresAt);

        return $result;
    }

    /**
     * @return Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getUsersByMonth()
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $result = Cache::get(md5('usersByMonth'));
        if ($result !== null) {
            return $result;
        }
        $result =  User::query()->whereNotNull('created_at')->where('created_at', '<>', '0000-00-00 00:00:00')->selectRaw("DATE_FORMAT(created_at, '%M %Y') as mth, COUNT(id) as num")->groupBy(['mth'])->orderByDesc('created_at')->get();

        Cache::put(md5('usersByMonth'), $result, $expiresAt);

        return $result;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getTopDownloads()
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $result = Cache::get(md5('topDownloads'));
        if ($result !== null) {
            return $result;
        }
        $result = Release::query()
            ->where('grabs', '>', 0)
            ->select(['id', 'searchname', 'guid', 'adddate'])
            ->selectRaw('SUM(grabs) as grabs')
            ->groupBy('id', 'searchname', 'adddate')
            ->havingRaw('SUM(grabs) > 0')
            ->orderByDesc('grabs')
            ->limit(10)
            ->get();

        Cache::put(md5('topDownloads'), $result, $expiresAt);

        return $result;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getRecentlyAdded()
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $result = Cache::get(md5('RecentlyAdded'));
        if ($result !== null) {
            return $result;
        }

        $result = Category::query()->with('parent')->where('r.adddate', '>', now()->subWeek())->select([
                'root_categories_id', DB::raw('COUNT(r.id) as count'), 'title'
            ])->join('releases as r', 'r.categories_id', '=',
                'categories.id')->groupBy('title')->orderByDesc('count')->get();

        Cache::put(md5('RecentlyAdded'), $result, $expiresAt);

        return $result;
    }

        public static function usersByRole()
        {
            $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
            $result = Cache::get(md5('usersByRole'));
            if ($result !== null) {
                return $result;
            }

            $result = Role::query()->select(['name'])->withCount('users')->groupBy('name')->having('users_count', '>', 0)->orderByDesc('users_count')->get();

            Cache::put(md5('usersByRole'), $result, $expiresAt);

            return $result;
        }

}
