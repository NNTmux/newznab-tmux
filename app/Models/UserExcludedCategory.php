<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class UserExcludedCategory extends Model
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'categories_id');
    }

    /**
     * @param $uid
     */
    public static function delUserCategoryExclusions($uid): void
    {
        self::query()->where('users_id', $uid)->delete();
    }

    /**
     * @param       $uid
     * @param array $catids
     */
    public static function addCategoryExclusions($uid, array $catids): void
    {
        self::delUserCategoryExclusions($uid);
        if (\count($catids) > 0) {
            foreach ($catids as $catid) {
                self::query()->insertGetId(['users_id' => $uid, 'categories_id' => $catid, 'created_at' => Carbon::now()]);
            }
        }
    }

    /**
     * Get list of category names excluded by the user.
     *
     * @param int $userID ID of the user.
     *
     * @return array
     * @throws \Exception
     */
    public static function getCategoryExclusionNames($userID): array
    {
        $categories = self::with('category')->where('users_id', $userID)->get();
        $ret = [];
        if ($categories !== null) {
            foreach ($categories as $cat) {
                $ret[] = $cat->category->title;
            }
        }

        return $ret;
    }

    /**
     * @param $uid
     * @param $catid
     */
    public static function delCategoryExclusion($uid, $catid): void
    {
        self::query()->where(['users_id'=> $uid, 'categories_id' => $catid])->delete();
    }
}
