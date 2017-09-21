<?php

namespace nntmux;

use nntmux\db\DB;
use Carbon\Carbon;
use App\Models\Forumpost;

class Forum
{
    /**
     * @var DB
     */
    public $pdo;

    /**
     * @param array $options Class instances.
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Settings' => null,
        ];
        $options += $defaults;

        $this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
    }

    /**
     * Add post to forum.
     *
     * @param     $parentid
     * @param     $userid
     * @param     $subject
     * @param     $message
     * @param int $locked
     * @param int $sticky
     * @param int $replies
     *
     * @return bool|int
     */
    public function add($parentid, $userid, $subject, $message, $locked = 0, $sticky = 0, $replies = 0)
    {
        if ($message === '') {
            return -1;
        }

        if ($parentid !== 0) {
            $par = $this->getParent($parentid);
            if ($par === false) {
                return -1;
            }

            Forumpost::query()->where('id', $parentid)->increment('replies', 1, ['updateddate' => Carbon::now()]);
        }

        return Forumpost::query()->insertGetId(
            [
             'forumid' => 1,
             'parentid' => $parentid,
             'users_id' => $userid,
             'subject' => $subject,
             'message' => $message,
             'locked' => $locked,
             'sticky' => $sticky,
             'replies' => $replies,
             'createddate' => Carbon::now(),
             'updateddate' => Carbon::now(),
            ]
        );
    }

    /**
     * Get parent of the forum post.
     *
     * @param $parent
     *
     * @return array|bool
     */
    public function getParent($parent)
    {
        return $this->pdo->queryOneRow(
            sprintf(
                'SELECT f.*, u.username FROM forumpost f LEFT OUTER JOIN users u ON u.id = f.users_id WHERE f.id = %d',
                $parent
            )
        );
    }

    /**
     * Get forum posts for a parent category.
     *
     * @param $parent
     *
     * @return array
     */
    public function getPosts($parent): array
    {
        return $this->pdo->query(
            sprintf(
                '
				SELECT f.*, u.username, ur.name AS rolename
				FROM forumpost f
				LEFT OUTER JOIN users u ON u.id = f.users_id
				LEFT JOIN user_roles ur ON ur.id = u.user_roles_id
				WHERE f.id = %d OR f.parentid = %d
				ORDER BY f.createddate ASC
				LIMIT 250',
                $parent,
                $parent
            )
        );
    }

    /**
     * Get post from forum.
     *
     * @param $id
     *
     * @return array|bool
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
     * @param $start
     * @param $num
     *
     * @return array
     */
    public function getBrowseRange($start, $num): array
    {
        return $this->pdo->query(
            sprintf(
                '
				SELECT f.*, u.username, ur.name AS rolename
				FROM forumpost f
				LEFT OUTER JOIN users u ON u.id = f.users_id
				LEFT JOIN user_roles ur ON ur.id = u.user_roles_id
				WHERE f.parentid = 0
				ORDER BY f.updateddate DESC %s',
                ($start === false ? '' : (' LIMIT '.$num.' OFFSET '.$start))
            )
        );
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
     * @param $uid
     * @param $start
     * @param $num
     *
     * @return array
     */
    public function getForUserRange($uid, $start, $num): array
    {
        return $this->pdo->query(
            sprintf(
                '
				SELECT forumpost.*, users.username
				FROM forumpost
				LEFT OUTER JOIN users ON users.id = forumpost.users_id
				WHERE users_id = %d
				ORDER BY forumpost.createddate DESC %s',
                ($start === false ? '' : (' LIMIT '.$num.' OFFSET '.$start)),
                $uid
            )
        );
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
