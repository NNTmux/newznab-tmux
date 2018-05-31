<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\UserDownload.
 *
 * @property int $id
 * @property int $users_id
 * @property string $hosthash
 * @property string $timestamp
 * @property int $releases_id FK to releases.id
 * @property-read \App\Models\Release $release
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserDownload whereHosthash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserDownload whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserDownload whereReleasesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserDownload whereTimestamp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserDownload whereUsersId($value)
 * @mixin \Eloquent
 */
class UserDownload extends Model
{
    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function release()
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }

    /**
     * Get the COUNT of how many NZB's the user has downloaded in the past day.
     *
     * @param int $userID
     *
     * @return int
     */
    public static function getDownloadRequests($userID): int
    {
        // Clear old requests.
        self::query()->where('users_id', $userID)->where('timestamp', '<', now()->subDay())->delete();
        $value = self::query()->where('users_id', $userID)->where('timestamp', '>', now()->subDay())->count('id');

        return $value === false ? 0 : $value;
    }

    /**
     * @param $userID
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getDownloadRequestsForUser($userID)
    {
        return self::query()->where('users_id', $userID)->with('release')->orderBy('timestamp', 'DESC')->get();
    }

    /**
     * If a user downloads a NZB, log it.
     *
     * @param int $userID id of the user.
     *
     * @param     $releaseID
     *
     * @return bool|int
     */
    public static function addDownloadRequest($userID, $releaseID)
    {
        return self::query()
            ->insertGetId(
                [
                    'users_id' => $userID,
                    'releases_id' => $releaseID,
                    'timestamp' => now(),
                ]
            );
    }

    /**
     * @param int $releaseID
     * @return mixed
     */
    public static function delDownloadRequestsForRelease(int $releaseID)
    {
        return self::query()->where('releases_id', $releaseID)->delete();
    }

    /**
     * @param $userID
     */
    public static function delDownloadRequests($userID): void
    {
        self::query()->where('users_id', $userID)->delete();
    }
}
