<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\UserMovie.
 *
 * @property int $id
 * @property int $users_id
 * @property int|null $imdbid
 * @property string|null $categories List of categories for user movies
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserMovie whereCategories($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserMovie whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserMovie whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserMovie whereImdbid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserMovie whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserMovie whereUsersId($value)
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserMovie newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserMovie newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserMovie query()
 */
class UserMovie extends Model
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
     * @param  $uid
     * @param  $imdbid
     * @param  array  $catID
     * @return int|\Illuminate\Database\Eloquent\Builder
     */
    public static function addMovie($uid, $imdbid, array $catID = [])
    {
        return self::query()
            ->insertGetId(
                [
                    'users_id' => $uid,
                    'imdbid' => $imdbid,
                    'categories' => ! empty($catID) ? implode('|', $catID) : 'NULL',
                    'created_at' => now(),
                ]
            );
    }

    /**
     * @param $uid
     * @return array
     */
    public static function getMovies($uid): array
    {
        return self::query()
            ->where('users_id', $uid)
            ->leftJoin('movieinfo as mi', 'mi.imdbid', '=', 'user_movies.imdbid')
            ->orderBy('mi.title')
            ->get(['user_movies.*', 'mi.year', 'mi.plot', 'mi.cover', 'mi.title'])
            ->toArray();
    }

    /**
     * @param $uid
     * @param $imdbid
     * @return mixed
     */
    public static function delMovie($uid, $imdbid)
    {
        return self::query()->where(['users_id' => $uid, 'imdbid' => $imdbid])->delete();
    }

    /**
     * @param $uid
     * @param $imdbid
     * @return array
     */
    public static function getMovie($uid, $imdbid): array
    {
        return self::query()
            ->where(['user_movies.users_id' => $uid, 'user_movies.imdbid' => $imdbid])
            ->leftJoin('movieinfo as mi', 'mi.imdbid', '=', 'user_movies.imdbid')
            ->get(['user_movies.*', 'mi.title'])
            ->toArray();
    }

    /**
     * @param $uid
     */
    public static function delMovieForUser($uid)
    {
        self::query()->where('users_id', $uid)->delete();
    }

    /**
     * @param $uid
     * @param $imdbid
     * @param  array  $catID
     */
    public static function updateMovie($uid, $imdbid, array $catID = [])
    {
        self::query()
            ->where(['users_id' => $uid, 'imdbid' => $imdbid])
            ->update(['categories' => ! empty($catID) ? implode('|', $catID) : 'NULL']);
    }
}
