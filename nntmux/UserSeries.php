<?php

namespace nntmux;

use nntmux\db\DB;
use Carbon\Carbon;
use App\Models\UserSerie;

/**
 * Class UserSeries.
 *
 * Sets and Gets data from and to the DB "user_series" table and the "my shows" web-page.
 */
class UserSeries
{
    /**
     * UserSeries constructor.
     */
    public function __construct()
    {
    }

    /**
     * When a user wants to add a show to "my shows" insert it into the user series table.
     *
     *
     * @param $userId
     * @param $videoId
     * @param array $catID
     * @return int
     */
    public function addShow($userId, $videoId, array $catID = []): int
    {
        return UserSerie::query()
            ->insertGetId(
                [
                    'users_id' => $userId,
                    'videos_id' => $videoId,
                    'categories' => ! empty($catID) ? implode('|', $catID) : 'NULL',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
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
    public function getShows($userId)
    {
        return UserSerie::query()
            ->where('user_series.users_id', $userId)
            ->select(['user_series.*', 'v.title'])
            ->join('videos as v', 'v.id', '=', 'user_series.videos_id')
            ->orderBy('v.title')
            ->get();
    }

    /**
     * Delete a tv show from the user's "my shows".
     *
     * @param int $userId    ID of user.
     * @param int $videoId ID of tv show.
     */
    public function delShow($userId, $videoId): void
    {
        UserSerie::query()->where(['users_id' => $userId, 'videos_id' => $videoId])->delete();
    }

    /**
     * Get tv show information for a user.
     *
     *
     * @param $userId
     * @param $videoId
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getShow($userId, $videoId)
    {
        return UserSerie::query()
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
    public function delShowForUser($userId): void
    {
        UserSerie::query()->where('users_id', $userId)->delete();
    }

    /**
     * Delete TV shows from all user's "my shows" that match a TV id.
     *
     *
     * @param $videoId
     */
    public function delShowForSeries($videoId): void
    {
        UserSerie::query()->where('videos_id', $videoId)->delete();
    }

    /**
     * Update a TV show category ID for a user's "my show" TV show.
     *
     * @param int   $userId    ID of the user.
     * @param int $videoId ID of the TV show.
     * @param array $catID  List of category ID's.
     */
    public function updateShow($userId, $videoId, array $catID = []): void
    {
        UserSerie::query()->where(['users_id' => $userId, 'videos_id' => $videoId])->update(['categories' =>  ! empty($catID) ? implode('|', $catID) : 'NULL']);
    }
}
