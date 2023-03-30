<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserDownload whereHosthash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserDownload whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserDownload whereReleasesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserDownload whereTimestamp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserDownload whereUsersId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserDownload newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserDownload newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserDownload query()
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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }

    /**
     * Get the COUNT of how many NZB's the user has downloaded in the past day.
     *
     * @param  int  $userID
     *
     * @throws \Exception
     */
    public static function getDownloadRequests(int $userID): int
    {
        // Clear old requests.
        self::whereUsersId($userID)->where('timestamp', '<', now()->subDay())->delete();
        $value = self::whereUsersId($userID)->where('timestamp', '>', now()->subDay())->count('id');

        return $value === false ? 0 : $value;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getDownloadRequestsForUser($userID)
    {
        return self::whereUsersId($userID)->with('release')->orderByDesc('timestamp')->get();
    }

    /**
     * If a user downloads a NZB, log it.
     *
     *
     * @return int|\Illuminate\Database\Eloquent\Builder
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
     * @return mixed
     *
     * @throws \Exception
     */
    public static function delDownloadRequestsForRelease(int $releaseID)
    {
        return self::whereReleasesId($releaseID)->delete();
    }

    /**
     * @throws \Exception
     */
    public static function delDownloadRequests($userID): void
    {
        self::whereUsersId($userID)->delete();
    }
}
