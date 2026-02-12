<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\UserRequest.
 *
 * @property int $id
 * @property int $users_id
 * @property string $hosthash
 * @property string $request
 * @property string $timestamp
 * @property int|null $count Computed count from aggregate queries
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRequest whereHosthash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRequest whereRequest($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRequest whereTimestamp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRequest whereUsersId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRequest query()
 */
class UserRequest extends Model
{
    /**
     * @var string
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['id', 'users_id', 'request', 'hosthash', 'timestamp'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * @throws \Throwable
     */
    public static function delApiRequests(mixed $userID): void
    {
        DB::transaction(function () use ($userID) {
            self::query()->where('users_id', $userID)->delete();
        }, 3);
    }

    /**
     * Get the quantity of API requests in the last day for the users_id.
     * Note: Old request cleanup is no longer done inline to avoid blocking API responses.
     * Use clearApiRequests() via a scheduled command or queue job instead.
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public static function getApiRequests(int $userID): int
    {
        $requests = self::query()
            ->where('users_id', $userID)
            ->where('timestamp', '>', now()->subDay())
            ->count('id');

        return $requests ?: 0;
    }

    /**
     * Get hourly API request counts for the last 24 hours.
     *
     * @return array<string, mixed> Array of hourly counts indexed by hour
     *
     * @throws \Exception
     */
    public static function getHourlyApiRequests(int $userID): array
    {
        $hourlyData = [];
        $now = now();

        // Initialize all 24 hours with 0
        for ($i = 23; $i >= 0; $i--) {
            $hour = $now->copy()->subHours($i);
            $hourlyData[$hour->format('H:00')] = 0;
        }

        // Get API requests from the last 24 hours grouped by hour
        $requests = self::query()
            ->where('users_id', $userID)
            ->where('timestamp', '>', $now->subDay())
            ->get();

        foreach ($requests as $request) {
            $hourKey = \Carbon\Carbon::parse($request->timestamp)->format('H:00');
            if (isset($hourlyData[$hourKey])) {
                $hourlyData[$hourKey]++;
            }
        }

        return $hourlyData;
    }

    /**
     * If a user accesses the API, log it.
     *
     * @param  string|int  $tokenOrUserId  API token string or user ID integer
     * @param  string  $request  The API request.
     */
    public static function addApiRequest(string|int $tokenOrUserId, string $request): void
    {
        if (is_int($tokenOrUserId)) {
            $userID = $tokenOrUserId;
        } else {
            $userID = User::query()->select(['id'])->where('api_token', $tokenOrUserId)->value('id');
        }
        self::query()->insert(['users_id' => $userID, 'request' => $request, 'timestamp' => now()]);
    }

    /**
     * Delete api requests older than a day.
     *
     * @param  int|bool  $userID
     *                            int The users ID.
     *                            bool false do all user ID's..
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public static function clearApiRequests($userID): void
    {
        DB::transaction(function () use ($userID) {
            if ($userID === false) {
                self::query()->where('timestamp', '<', now()->subDay())->delete();
            } else {
                self::query()->where('users_id', $userID)->where('timestamp', '<', now()->subDay())->delete();
            }
        }, 3);
    }
}
