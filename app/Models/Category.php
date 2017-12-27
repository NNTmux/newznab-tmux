<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];

    public function releases()
    {
        return $this->hasMany(Release::class, 'categories_id');
    }

    public function parent()
    {
        return $this->belongsTo(static::class, 'parentid');
    }

    public function children()
    {
        return $this->hasMany(static::class, 'parentid');
    }

    public function userExcludedCategory()
    {
        return $this->hasMany(UserExcludedCategory::class, 'categories_id');
    }

    public function roleExcludedCategory()
    {
        return $this->belongsTo(RoleExcludedCategory::class, 'categories_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getRecentlyAdded()
    {
        $recent = Cache::get('recentlyadded');
        if ($recent !== null) {
            return $recent;
        }

        $recent = self::query()
            ->where('r.adddate', '>', Carbon::now()->subWeek())
            ->selectRaw('CONCAT(cp.title, " > ", categories.title) as title')
            ->selectRaw('COUNT(r.id) as count')
            ->join('categories as cp', 'cp.id', '=', 'categories.parentid')
            ->join('releases as r', 'r.categories_id', '=', 'categories.id')
            ->groupBy('title')
            ->orderBy('count', 'desc')
            ->get();

        $expiresAt = Carbon::now()->addSeconds(NN_CACHE_EXPIRY_LONG);
        Cache::put('recentlyadded', $recent, $expiresAt);

        return $recent;
    }
}
