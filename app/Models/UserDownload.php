<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\UserDownload.
 *
 * @property int $id
 * @property int $users_id
 * @property string $hosthash
 * @property string $timestamp
 * @property int $releases_id FK to releases.id
 * @property int|null $count Computed count from aggregate queries
 * @property-read Release $release
 * @property-read User $user
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
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }

    /**
     * Get the COUNT of how many NZB's the user has downloaded in the past day.
     *
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
     * Get hourly download counts for the last 24 hours.
     *
     * @return array Array of hourly counts indexed by hour
     *
     * @throws \Exception
     */
    public static function getHourlyDownloads(int $userID): array
    {
        $hourlyData = [];
        $now = now();

        // Initialize all 24 hours with 0
        for ($i = 23; $i >= 0; $i--) {
            $hour = $now->copy()->subHours($i);
            $hourlyData[$hour->format('H:00')] = 0;
        }

        // Get downloads from the last 24 hours grouped by hour
        $downloads = self::whereUsersId($userID)
            ->where('timestamp', '>', $now->subDay())
            ->get();

        foreach ($downloads as $download) {
            $hourKey = \Carbon\Carbon::parse($download->timestamp)->format('H:00');
            if (isset($hourlyData[$hourKey])) {
                $hourlyData[$hourKey]++;
            }
        }

        return $hourlyData;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
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
