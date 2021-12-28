<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\UserSerie.
 *
 * @property int $id
 * @property int $users_id
 * @property int $videos_id FK to videos.id
 * @property string|null $categories List of categories for user tv shows
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserSerie whereCategories($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserSerie whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserSerie whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserSerie whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserSerie whereUsersId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserSerie whereVideosId($value)
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserSerie newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserSerie newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserSerie query()
 */
class UserSerie extends Model
{
    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * When a user wants to add a show to "my shows" insert it into the user series table.
     *
     *
     * @param $userId
     * @param $videoId
     * @param  array  $catID
     * @return int|\Illuminate\Database\Eloquent\Builder
     */
    public static function addShow($userId, $videoId, array $catID = [])
    {
        return self::query()
            ->insertGetId(
                [
                    'users_id' => $userId,
                    'videos_id' => $videoId,
                    'categories' => ! empty($catID) ? implode('|', $catID) : 'NULL',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
    }

    /**
     * Get all the user's "my shows".
     *
     *
     * @param $userId
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getShows($userId)
    {
        return self::query()
            ->where('user_series.users_id', $userId)
            ->select(['user_series.*', 'v.title'])
            ->join('videos as v', 'v.id', '=', 'user_series.videos_id')
            ->orderBy('v.title')
            ->get();
    }

    /**
     * Delete a tv show from the user's "my shows".
     *
     * @param $users_id
     * @param $videos_id
     */
    public static function delShow($users_id, $videos_id): void
    {
        self::query()->where(compact('users_id', 'videos_id'))->delete();
    }

    /**
     * Get tv show information for a user.
     *
     *
     * @param $userId
     * @param $videoId
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getShow($userId, $videoId)
    {
        return self::query()
            ->where(['user_series.users_id' => $userId, 'user_series.videos_id' => $videoId])
            ->select(['user_series.*', 'v.title'])
            ->leftJoin('videos as v', 'v.id', '=', 'user_series.videos_id')->first();
    }

    /**
     * Delete all shows from the user's "my shows".
     *
     *
     * @param $userId
     */
    public static function delShowForUser($userId): void
    {
        self::query()->where('users_id', $userId)->delete();
    }

    /**
     * Delete TV shows from all user's "my shows" that match a TV id.
     *
     *
     * @param $videoId
     */
    public static function delShowForSeries($videoId): void
    {
        self::query()->where('videos_id', $videoId)->delete();
    }

    /**
     * Update a TV show category ID for a user's "my show" TV show.
     *
     * @param  $users_id
     * @param  $videos_id
     * @param  array  $catID  List of category ID's.
     */
    public static function updateShow($users_id, $videos_id, array $catID = []): void
    {
        self::query()->where(compact('users_id', 'videos_id'))->update(['categories' =>  ! empty($catID) ? implode('|', $catID) : 'NULL']);
    }
}
