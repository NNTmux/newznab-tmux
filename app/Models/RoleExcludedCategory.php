<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class RoleExcludedCategory extends Model
{
    protected $dateFormat = false;

    protected $guarded = [];

    public function role()
    {
        return $this->belongsTo(UserRole::class, 'user_roles_id');
    }

    public function category()
    {
        return $this->hasMany(Category::class, 'categories_id');
    }

    /**
     * @param $role
     *
     * @return array
     */
    public static function getRoleCategoryExclusion($role): array
    {
        $ret = [];
        $categories = self::query()->where('user_roles_id', $role)->get(['categories_id']);
        foreach ($categories as $category) {
            $ret[] = $category['categories_id'];
        }

        return $ret;
    }

    /**
     * @param $role
     * @param $catids
     */
    public static function addRoleCategoryExclusions($role, array $catids): void
    {
        self::delRoleCategoryExclusions($role);
        if (\count($catids) > 0) {
            foreach ($catids as $catid) {
                self::query()->insertGetId(['user_roles_id' => $role, 'categories_id' => $catid, 'created_at' => Carbon::now()]);
            }
        }
    }

    /**
     * @param $role
     */
    public static function delRoleCategoryExclusions($role): void
    {
        self::query()->where('user_roles_id', $role)->delete();
    }
}
