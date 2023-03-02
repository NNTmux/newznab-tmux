<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\UserRequest.
 *
 * @property int $id
 * @property int $users_id
 * @property string $hosthash
 * @property string $request
 * @property string $timestamp
 * @property-read \App\Models\User $user
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
    protected $table = 'user_requests';

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
    protected $fillable = ['id', 'users_id', 'request', 'hosthash', 'timestamp'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * @throws \Throwable
     */
    public static function delApiRequests($userID): void
    {
        DB::transaction(function () use ($userID) {
            self::query()->where('users_id', $userID)->delete();
        }, 3);
    }

    /**
     * Get the quantity of API requests in the last day for the users_id.
     *
     * @param  int  $userID
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public static function getApiRequests($userID): int
    {
        // Clear old requests.
        self::clearApiRequests($userID);
        $requests = self::query()->where('users_id', $userID)->count('id');

        return ! $requests ? 0 : $requests;
    }

    /**
     * If a user accesses the API, log it.
     *
     * @param  string  $token  API token of the user
     * @param  string  $request  The API request.
     */
    public static function addApiRequest($token, $request): void
    {
        $userID = User::query()->select(['id'])->where('api_token', $token)->value('id');
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
