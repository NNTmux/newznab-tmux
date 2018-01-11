<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;

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
     * @param $uid
     * @param $imdbid
     * @param array $catID
     * @return int
     */
    public static function addMovie($uid, $imdbid, array $catID = []): int
    {
        return self::query()
            ->insertGetId(
                [
                    'users_id' => $uid,
                    'imdbid' => $imdbid,
                    'categories' => ! empty($catID) ? implode('|', $catID) : 'NULL',
                    'created_at' => Carbon::now(),
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
     * @param array $catID
     */
    public static function updateMovie($uid, $imdbid, array $catID = [])
    {
        self::query()
            ->where(['users_id' => $uid, 'imdbid' => $imdbid])
            ->update(['categories' => ! empty($catID) ? implode('|', $catID) : 'NULL']);
    }
}
