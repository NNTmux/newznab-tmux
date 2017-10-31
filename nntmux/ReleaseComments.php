<?php

namespace nntmux;

use nntmux\db\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Release;
use App\Models\Settings;
use App\Models\ReleaseComment;

/**
 * This class handles storage and retrieval of release comments.
 */
class ReleaseComments
{
    /**
     * @var DB|null
     */
    public $pdo;

    /**
     * ReleaseComments constructor.
     *
     * @param null $settings
     * @throws \Exception
     */
    public function __construct($settings = null)
    {
        $this->pdo = ($settings instanceof DB ? $settings : new DB());
    }

    /**
     * Get a comment by id.
     *
     *
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getCommentById($id)
    {
        return ReleaseComment::query()->where('id', $id)->first();
    }

    /**
     * Get all comments for a GID.
     *
     * @param $gid
     *
     * @return array
     */
    public function getCommentsByGid($gid): array
    {
        return $this->pdo->query(sprintf("SELECT rc.id, text, created_at, updated_at, sourceid, CASE WHEN sourceid = 0 THEN (SELECT username FROM users WHERE id = users_id) ELSE username END AS username, CASE WHEN sourceid = 0 THEN (SELECT user_roles_id FROM users WHERE id = users_id) ELSE '-1' END AS role, CASE WHEN sourceid =0 THEN (SELECT r.name AS rolename FROM users AS u LEFT JOIN user_roles AS r ON r.id = u.user_roles_id WHERE u.id = users_id) ELSE (SELECT description AS rolename FROM spotnabsources WHERE id = sourceid) END AS rolename FROM release_comments rc WHERE isvisible = 1  AND gid = %s AND (users_id IN (SELECT id FROM users) OR rc.username IS NOT NULL) ORDER BY created_at DESC LIMIT 100", $this->pdo->escapeString($gid)));
    }

    /**
     * @param null|string|bool|int $refdate
     * @param null $localOnly
     * @return mixed
     */
    public function getCommentCount($refdate = null, $localOnly = null)
    {
        if ($refdate !== null) {
            if (is_string($refdate)) {
                // ensure we're in the right format
                $refdate = date('Y-m-d H:i:s', strtotime($refdate));
            } elseif (is_int($refdate)) {
                // ensure we're in the right format
                $refdate = date('Y-m-d H:i:s', $refdate);
            } else {
                // leave it as null (bad content anyhow)
                $refdate = null;
            }
        }

        $q = 'SELECT count(id) AS num FROM release_comments';
        $clause = [];
        if ($refdate !== null) {
            $clause[] = "created_at >= '$refdate'";
        }

        // set localOnly to null to include both local and remote
        // set localOnly to true to only receive local comment count
        // set localOnly to false to only receive remote comment count

        $clause[] = $localOnly === true ? 'sourceid = 0' : 'sourceid != 0';

        if (count($clause)) {
            $q .= ' WHERE '.implode('AND ', $clause);
        }

        $res = $this->pdo->queryOneRow($q);

        return $res['num'];
    }

    /**
     * Delete single comment on the site.
     *
     * @param $id
     */
    public function deleteComment($id): void
    {
        $res = $this->getCommentById($id);
        if ($res) {
            ReleaseComment::query()->where('id', $id)->delete();
            $this->updateReleaseCommentCount($res['gid']);
        }
    }

    /**
     * Delete all comments for a release.id.
     *
     * @param $id
     */
    public function deleteCommentsForRelease($id): void
    {
        $res = $this->getCommentById($id);
        if ($res) {
            ReleaseComment::query()
                ->join('releases as r', 'r.gid', '=', 'release_comments.gid')
                ->where('releases.id', $id)
                ->delete();
            $this->updateReleaseCommentCount($res['gid']);
        }
    }

    /**
     * Delete all comments for a users.id.
     *
     * @param $id
     */
    public function deleteCommentsForUser($id): void
    {
        $numcomments = $this->getCommentCountForUser($id);
        if ($numcomments > 0) {
            $comments = $this->getCommentsForUserRange($id, 0, $numcomments);
            foreach ($comments as $comment) {
                $this->deleteComment($comment['id']);
                $this->updateReleaseCommentCount($comment['gid']);
            }
        }
    }

    /**
     * Add a release_comments row.
     *
     *
     * @param $id
     * @param $gid
     * @param $text
     * @param $userid
     * @param $host
     * @return int
     * @throws \Exception
     */
    public function addComment($id, $gid, $text, $userid, $host): int
    {
        if ((int) Settings::settingValue('..storeuserips') !== 1) {
            $host = '';
        }

        $username = User::query()->where('id', $userid)->first(['username']);
        $username = ($username === null ? 'ANON' : $username['username']);

        $comid = ReleaseComment::query()
            ->insertGetId(
                [
                    'releases_id' => $id,
                    'gid' => $gid,
                    'text' => $text,
                    'users_id' => $userid,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'host' => $host,
                    'username' => $username,
                ]
            );
        $this->updateReleaseCommentCount($id);

        return $comid;
    }

    /**
     * Get release_comments rows by limit.
     *
     *
     * @param $start
     * @param $num
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public function getCommentsRange($start, $num)
    {
        $range = ReleaseComment::query()
            ->select(['*', 'r.guid'])
            ->leftJoin('releases as r', 'r.id', '=', 'release_comments.releases_id')
            ->orderBy('release_comments.created_at', 'desc');
        if ($start !== false) {
            $range->limit($num)->offset($start);
        }

        return $range->get();
    }

    /**
     * Update the denormalised count of comments for a release.
     *
     * @param $gid
     */
    public function updateReleaseCommentCount($gid): void
    {
        $commentCount = ReleaseComment::query()->where('gid', '=', 'releases.gid')->where('isvisible', '=', 1)->count(['id']);
        Release::query()->where('gid', $gid)->update(['comments' => $commentCount]);
    }

    /**
     * Get a count of all comments for a user.
     *
     * @param $uid
     * @return int
     */
    public function getCommentCountForUser($uid): int
    {
        $res = ReleaseComment::query()->where(['users_id' => $uid, 'isvisible' => 1])->count(['id']);

        return $res;
    }

    /**
     * Get comments for a user by limit.
     *
     *
     * @param $uid
     * @param $start
     * @param $num
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public function getCommentsForUserRange($uid, $start, $num)
    {
        $comments = ReleaseComment::query()
            ->select(['release_comments.*', 'r.guid', 'r.searchname', 'u.username'])
            ->join('releases as r', 'r.id', '=', 'release_comments.releases_id')
            ->leftJoin('users as u', 'u.id', '=', 'release_comments.users_id')
            ->where('users_id', $uid)
            ->orderBy('created_at', 'desc');

        if ($start !== false) {
            $comments->limit($num)->offset($start);
        }

        return $comments->get();
    }
}
