<?php

namespace nntmux;

use Carbon\Carbon;
use App\Models\Forumpost;

class Forum
{
    /**
     * Forum constructor.
     */
    public function __construct()
    {
    }

    /**
     * Add post to forum.
     *
     * @param     $parentId
     * @param     $userid
     * @param     $subject
     * @param     $message
     * @param int $locked
     * @param int $sticky
     * @param int $replies
     *
     * @return bool|int
     */
    public function add($parentId, $userid, $subject, $message, $locked = 0, $sticky = 0, $replies = 0)
    {
        if ($message === '') {
            return -1;
        }

        if ($parentId !== 0) {
            $par = $this->getParent($parentId);
            if ($par === false) {
                return -1;
            }

            Forumpost::query()->where('id', $parentId)->increment('replies', 1, ['updated_at' => Carbon::now()]);
        }

        return Forumpost::query()->insertGetId(
            [
             'forumid' => 1,
             'parentid' => $parentId,
             'users_id' => $userid,
             'subject' => $subject,
             'message' => $message,
             'locked' => $locked,
             'sticky' => $sticky,
             'replies' => $replies,
             'created_at' => Carbon::now(),
             'updated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Get parent of the forum post.
     *
     *
     * @param $parent
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getParent($parent)
    {
        return Forumpost::query()
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
    public function getPosts($parent)
    {
        return Forumpost::query()
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
    public function getPost($id)
    {
        return Forumpost::query()->where('id', $id)->first();
    }

    /**
     * Get count of posts for parent forum.
     *
     * @return int
     */
    public function getBrowseCount(): int
    {
        $res = Forumpost::query()->count('id');

        return $res === false ? 0 : $res;
    }

    /**
     * Get browse range for forum.
     *
     *
     * @param $start
     * @param $num
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public function getBrowseRange($start, $num)
    {
        $range = Forumpost::query()
            ->where('forumpost.parentid', '=', 0)
            ->leftJoin('users', 'users.id', '=', 'forupost.users_id')
            ->leftJoin('user_roles', 'user_roles.id', '=', 'users.user_roles_id')
            ->select(['forumpost.*', 'users.username', 'user_roles.name as rolename'])
            ->orderBy('forumpost.updated_at', 'desc');
        if ($start !== false) {
            $range->limit($num)->offset($start);
        }

        return $range->get();
    }

    /**
     * Delete parent category from forum.
     *
     * @param $parent
     */
    public function deleteParent($parent): void
    {
        Forumpost::query()->where('id', $parent)->orWhere('parentid', $parent)->delete();
    }

    /**
     * Delete post from forum.
     *
     * @param $id
     */
    public function deletePost($id): void
    {
        $post = $this->getPost($id);
        if ($post) {
            if ((int) $post['parentid'] === 0) {
                $this->deleteParent($id);
            } else {
                Forumpost::query()->where('id', $id)->delete();
            }
        }
    }

    /**
     * Delete user from forum.
     *
     * @param $id
     */
    public function deleteUser($id): void
    {
        Forumpost::query()->where('users_id', $id)->delete();
    }

    /**
     * Get count of posts for user.
     *
     * @param $uid
     *
     * @return int
     */
    public function getCountForUser($uid): int
    {
        $res = Forumpost::query()->where('users_id', $uid)->count('id');

        return $res === false ? 0 : $res;
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
    public function getForUserRange($uid, $start, $num)
    {
        $range = Forumpost::query()
            ->where('forumpost.users_id', $uid)
            ->select(['forumpost.*', 'users.username'])
            ->leftJoin('users', 'users.id', '=', 'forumpost.users_id')
            ->orderBy('forumpost.created_at', 'desc');
        if ($start  !== false) {
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
    public function editPost($id, $message, $uid): void
    {
        $post = $this->getPost($id);
        if ($post) {
            Forumpost::query()->where(['id' => $id, 'users_id' => $uid])->update(['message' => $message]);
        }
    }

    /**
     * Lock forum topic.
     *
     * @param $id
     * @param $lock
     */
    public function lockUnlockTopic($id, $lock): void
    {
        Forumpost::query()->where('id', $id)->orWhere('parentid', $id)->update(['locked' => $lock]);
    }
}
