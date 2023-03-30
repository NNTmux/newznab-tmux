<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Forumpost.
 *
 * @property int $id
 * @property int $forumid
 * @property int $parentid
 * @property int $users_id
 * @property string $subject
 * @property string $message
 * @property bool $locked
 * @property bool $sticky
 * @property int $replies
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost whereForumid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost whereLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost whereParentid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost whereReplies($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost whereSticky($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost whereUsersId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Forumpost query()
 */
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
     * @param  int  $locked
     * @param  int  $sticky
     * @param  int  $replies
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

            self::query()->where('id', $parentId)->increment('replies', 1, ['updated_at' => now()]);
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
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getPosts($parent)
    {
        return self::query()
            ->where('forumpost.id', $parent)
            ->orWhere('forumpost.parentid', $parent)
            ->leftJoin('users', 'users.id', '=', 'forumpost.users_id')
            ->leftJoin('roles', 'roles.id', '=', 'users.roles_id')
            ->orderBy('forumpost.created_at')
            ->limit(250)
            ->select(['forumpost.*', 'users.username', 'roles.name as rolename'])
            ->get();
    }

    /**
     * Get post from forum.
     *
     *
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
     * @param $start
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getBrowseRange()
    {
        return self::query()
            ->where('forumpost.parentid', '=', 0)
            ->leftJoin('users', 'users.id', '=', 'forumpost.users_id')
            ->leftJoin('roles', 'roles.id', '=', 'users.roles_id')
            ->select(['forumpost.*', 'users.username', 'roles.name as rolename'])
            ->orderByDesc('forumpost.updated_at')
            ->paginate(config('nntmux.items_per_page'));
    }

    /**
     * Delete parent category from forum.
     */
    public static function deleteParent($parent): void
    {
        self::query()->where('id', $parent)->orWhere('parentid', $parent)->delete();
    }

    /**
     * Delete post from forum.
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
     */
    public static function deleteUser($id): void
    {
        self::query()->where('users_id', $id)->delete();
    }

    public static function getCountForUser($uid): int
    {
        $res = self::query()->where('users_id', $uid)->count('id');

        return $res ?? 0;
    }

    /**
     * Get range of posts for user.
     *
     *
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getForUserRange($uid, $start, $num)
    {
        $range = self::query()
            ->where('forumpost.users_id', $uid)
            ->select(['forumpost.*', 'users.username'])
            ->leftJoin('users', 'users.id', '=', 'forumpost.users_id')
            ->orderByDesc('forumpost.created_at');
        if ($start !== false) {
            $range->limit($num)->offset($start);
        }

        return $range->get();
    }

    /**
     * Edit forum post for user.
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
     */
    public static function lockUnlockTopic($id, $lock): void
    {
        self::query()->where('id', $id)->orWhere('parentid', $id)->update(['locked' => $lock]);
    }
}
