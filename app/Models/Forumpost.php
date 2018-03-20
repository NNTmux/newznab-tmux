<?php

namespace App\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;

class Forumpost extends Model
{
    /**
     * @var string
     */
    protected $table = 'forumpost';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @param $parentId
     * @param $userid
     * @param $subject
     * @param $message
     * @param int $locked
     * @param int $sticky
     * @param int $replies
     * @return int
     */
    public static function add($parentId, $userid, $subject, $message, $locked = 0, $sticky = 0, $replies = 0): int
    {
        if ($message === '') {
            return -1;
        }

        if ($parentId !== 0) {
            $par = self::getParent($parentId);
            if ($par === false) {
                return -1;
            }

            self::query()->where('id', $parentId)->increment('replies', 1, ['updated_at' => Carbon::now()]);
        }

        return self::create(
            [
                'forumid' => 1,
                'parentid' => $parentId,
                'users_id' => $userid,
                'subject' => $subject,
                'message' => $message,
                'locked' => $locked,
                'sticky' => $sticky,
                'replies' => $replies,
            ]
        )->id;
    }

    /**
     * Get parent of the forum post.
     *
     *
     * @param $parent
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getParent($parent)
    {
        return self::query()
            ->where('forumpost.id', $parent)
            ->select(['forumpost.*', 'users.username'])
            ->leftJoin('users', 'users.id', '=', 'forumpost.users_id')
            ->first();
    }

    /**
     * Get forum posts for a parent category.
     *
     *
     * @param $parent
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getPosts($parent)
    {
        return self::query()
            ->where('forumpost.id', $parent)
            ->orWhere('forumpost.parentid', $parent)
            ->leftJoin('users', 'users.id', '=', 'forumpost.users_id')
            ->leftJoin('user_roles', 'user_roles.id', '=', 'users.user_roles_id')
            ->orderBy('forumpost.created_at')
            ->limit(250)
            ->select(['forumpost.*', 'users.username', 'user_roles.name as rolename'])
            ->get();
    }

    /**
     * Get post from forum.
     *
     *
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getPost($id)
    {
        return self::query()->where('id', $id)->first();
    }

    /**
     * Get browse range for forum.
     *
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getBrowseRange(): LengthAwarePaginator
    {
        $range = self::query()
            ->where('forumpost.parentid', '=', 0)
            ->leftJoin('users', 'users.id', '=', 'forumpost.users_id')
            ->leftJoin('user_roles', 'user_roles.id', '=', 'users.user_roles_id')
            ->select(['forumpost.*', 'users.username', 'user_roles.name as rolename'])
            ->orderBy('forumpost.updated_at', 'desc');

        return $range->paginate(config('nntmux.items_per_page'));
    }

    /**
     * Delete parent category from forum.
     *
     * @param $parent
     */
    public static function deleteParent($parent): void
    {
        self::query()->where('id', $parent)->orWhere('parentid', $parent)->delete();
    }

    /**
     * Delete post from forum.
     *
     * @param $id
     */
    public static function deletePost($id): void
    {
        $post = self::getPost($id);
        if ($post) {
            if ((int) $post['parentid'] === 0) {
                self::deleteParent($id);
            } else {
                self::query()->where('id', $id)->delete();
            }
        }
    }

    /**
     * Delete user from forum.
     *
     * @param $id
     */
    public static function deleteUser($id): void
    {
        self::query()->where('users_id', $id)->delete();
    }

    /**
     * @param $uid
     * @return int
     */
    public static function getCountForUser($uid): int
    {
        $res = self::query()->where('users_id', $uid)->count('id');

        return $res ?? 0;
    }

    /**
     * Get range of posts for user.
     *
     *
     * @param $uid
     * @param $start
     * @param $num
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getForUserRange($uid, $start, $num)
    {
        $range = self::query()
            ->where('forumpost.users_id', $uid)
            ->select(['forumpost.*', 'users.username'])
            ->leftJoin('users', 'users.id', '=', 'forumpost.users_id')
            ->orderBy('forumpost.created_at', 'desc');
        if ($start !== false) {
            $range->limit($num)->offset($start);
        }

        return $range->get();
    }

    /**
     * Edit forum post for user.
     *
     * @param $id
     * @param $message
     * @param $uid
     */
    public static function editPost($id, $message, $uid): void
    {
        $post = self::getPost($id);
        if ($post) {
            self::query()->where(['id' => $id, 'users_id' => $uid])->update(['message' => $message]);
        }
    }

    /**
     * Lock forum topic.
     *
     * @param $id
     * @param $lock
     */
    public static function lockUnlockTopic($id, $lock): void
    {
        self::query()->where('id', $id)->orWhere('parentid', $id)->update(['locked' => $lock]);
    }
}
