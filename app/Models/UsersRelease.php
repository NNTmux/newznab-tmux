<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;

class UsersRelease extends Model
{
    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function release()
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }

    /**
     * @param $uid
     */
    public static function delCartForUser($uid): void
    {
        self::query()->where('users_id', $uid)->delete();
    }

    /**
     * @param $uid
     * @param $releaseid
     * @return int
     */
    public static function addCart($uid, $releaseid): int
    {
        return self::query()->insertGetId(
            [
                'users_id' => $uid,
                'releases_id' => $releaseid,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * @param $uid
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getCart($uid)
    {
        return self::query()->with('release')->where(['users_id' => $uid])->get();
    }

    /**
     * @param $guids
     * @param $userID
     * @return bool|mixed
     */
    public static function delCartByGuid($guids, $userID)
    {
        if (! \is_array($guids)) {
            return false;
        }

        $del = [];
        foreach ($guids as $guid) {
            $rel = Release::query()->where('guid', $guid)->first(['id']);
            if ($rel !== null) {
                $del[] = $rel['id'];
            }
        }

        return self::query()->whereIn('releases_id', $del)->where('users_id', $userID)->delete() === 1;
    }

    /**
     * @param $guid
     * @param $uid
     */
    public static function delCartByUserAndRelease($guid, $uid): void
    {
        $rel = Release::query()->where('guid', $guid)->first(['id']);
        if ($rel) {
            self::query()->where(['users_id' => $uid, 'releases_id' => $rel['id']])->delete();
        }
    }

    /**
     * @param $rid
     */
    public static function delCartForRelease($rid): void
    {
        self::query()->where('releases_id', $rid)->delete();
    }
}
