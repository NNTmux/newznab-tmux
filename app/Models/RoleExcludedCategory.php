<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\RoleExcludedCategory
 *
 * @property int $id
 * @property int $user_roles_id
 * @property int|null $categories_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Category[] $category
 * @property-read \App\Models\UserRole $role
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExcludedCategory whereCategoriesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExcludedCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExcludedCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExcludedCategory whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\RoleExcludedCategory whereUserRolesId($value)
 * @mixin \Eloquent
 */
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
                self::create(['user_roles_id' => $role, 'categories_id' => $catid]);
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
