<?php
namespace nntmux;

use app\models\Settings;
use nntmux\db\DB;


/**
 * This class handles storage and retrieval of release comments.
 */
class ReleaseComments
{

	/**
	 * @var \nntmux\db\Settings
	 */
	public $pdo;

	/**
	 * @param \nntmux\db\Settings $settings
	 */
	public function __construct($settings = null)
	{
		$this->pdo = ($settings instanceof DB ? $settings : new DB());
	}

	/**
	 * Get a comment by id.
	 *
	 * @param $id
	 *
	 * @return array|bool
	 */
	public function getCommentById($id)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT * FROM release_comments WHERE id = %d", $id));
	}

	/**
	 * Get all comments for a GID.
	 *
	 * @param $gid
	 *
	 * @return array
	 */
	public function getCommentsByGid($gid)
	{
		return $this->pdo->query(sprintf("SELECT rc.id, text, createddate, sourceid, CASE WHEN sourceid = 0 THEN (SELECT username FROM users WHERE id = users_id) ELSE username END AS username, CASE WHEN sourceid = 0 THEN (SELECT role FROM users WHERE id = users_id) ELSE '-1' END AS role, CASE WHEN sourceid =0 THEN (SELECT r.name AS rolename FROM users AS u LEFT JOIN user_roles AS r ON r.id = u.role WHERE u.id = users_id) ELSE (SELECT description AS rolename FROM spotnabsources WHERE id = sourceid) END AS rolename FROM release_comments rc WHERE isvisible = 1  AND gid = %s AND (users_id IN (SELECT id FROM users) OR rc.username IS NOT NULL) ORDER BY createddate DESC LIMIT 100", $this->pdo->escapeString($gid)));
	}

	/**
	 * Get all comments for a release.GUID.
	 *
	 * @param $guid
	 *
	 * @return array
	 */
	public function getCommentsByGuid($guid)
	{
		return $this->pdo->query(sprintf("SELECT rc.id, text, createddate, sourceid, CASE WHEN sourceid = 0 THEN (SELECT username FROM users WHERE id = users_id) ELSE username END AS username FROM release_comments rc LEFT JOIN releases r ON r.gid = rc.gid WHERE isvisible = 1 AND guid = %s AND (users_id IN (SELECT id FROM users) OR rc.username IS NOT NULL) ORDER BY createddate DESC LIMIT 100", $this->pdo->escapeString($guid)));
	}

	/**
	 * Get all count of all comments.
	 *
	 * @param null $refdate
	 * @param null $localOnly
	 *
	 * @return
	 */
	public function getCommentCount($refdate=Null, $localOnly=Null)
	{
		if($refdate !== Null){
			if(is_string($refdate)){
			    // ensure we're in the right format
				$refdate=date("Y-m-d H:i:s", strtotime($refdate));
			}else if(is_int($refdate)){
			    // ensure we're in the right format
				$refdate=date("Y-m-d H:i:s", $refdate);
			}else{
				// leave it as null (bad content anyhow)
				$refdate = Null;
			}
		}

		$q = "SELECT count(id) AS num FROM release_comments";
		$clause = [];
		if($refdate !== Null)
			$clause[] = "createddate >= '$refdate'";

        // set localOnly to Null to include both local and remote
        // set localOnly to true to only receive local comment count
        // set localOnly to false to only receive remote comment count
		if($localOnly === true){
			$clause[] = "sourceid = 0";
		}else if($localOnly === false){
			$clause[] = "sourceid != 0";
		}

		if(count($clause))
			$q .= " WHERE ".implode(" AND ", $clause);

		$res = $this->pdo->queryOneRow($q);
		return $res["num"];
	}

	/**
	 * Delete single comment on the site.
	 *
	 * @param $id
	 */
	public function deleteComment($id)
	{
		$res = $this->getCommentById($id);
		if ($res) {
			$this->pdo->queryExec(sprintf("DELETE FROM release_comments WHERE id = %d", $id));
			$this->updateReleaseCommentCount($res["gid"]);
		}
	}

	/**
	 * Delete all comments for a release.id.
	 *
	 * @param $id
	 */
	public function deleteCommentsForRelease($id)
	{
		$res = $this->getCommentById($id);
		if ($res)
		{
			$this->pdo->queryExec(sprintf("DELETE rc.* FROM release_comments rc JOIN releases r ON r.gid = rc.gid WHERE r.id = %d", $id));
			$this->updateReleaseCommentCount($res["gid"]);
		}
	}

	/**
	 * Delete all comments for a users.id.
	 *
	 * @param $id
	 */
	public function deleteCommentsForUser($id)
	{
		$numcomments = $this->getCommentCountForUser($id);
		if ($numcomments > 0)
		{
			$comments = $this->getCommentsForUserRange($id, 0, $numcomments);
			foreach ($comments as $comment)
			{
				$this->deleteComment($comment["id"]);
				$this->updateReleaseCommentCount($comment["gid"]);
			}
		}
	}

	/**
	 * Add a release_comments row.
	 *
	 * @param $id
	 * @param $gid
	 * @param $text
	 * @param $userid
	 * @param $host
	 *
	 * @return bool|int
	 */
	public function addComment($id, $gid, $text, $userid, $host)
	{
		if (Settings::value('..storeuserips') != "1") {
			$host = "";
		}

		$username = $this->pdo->queryOneRow(sprintf('SELECT username FROM users WHERE id = %d', $userid));
		$username = ($username === false ? 'ANON' : $username['username']);

		$comid = $this->pdo->queryInsert(
			sprintf("
				INSERT INTO release_comments (releases_id, gid, text, users_id, createddate, host, username)
				VALUES (%d, %s, %s, %d, NOW(), %s, %s)",
				$id,
				$this->pdo->escapeString($gid),
				$this->pdo->escapeString($text),
				$userid,
				$this->pdo->escapeString($host),
				$this->pdo->escapeString($username)
			)
		);
		$this->updateReleaseCommentCount($id);
		return $comid;
	}

	/**
	 * Get release_comments rows by limit.
	 *
	 * @param $start
	 * @param $num
	 *
	 * @return array
	 */
	public function getCommentsRange($start, $num)
	{
		return $this->pdo->query(
			sprintf("
				SELECT rc.*, r.guid
				FROM release_comments rc
				LEFT JOIN releases r on r.id = rc.releases_id
				ORDER BY rc.createddate DESC %s",
				($start === false ? '' : " LIMIT " . $num . " OFFSET " . $start)
			)
		);
	}

	/**
	 * Update the denormalised count of comments for a release.
	 *
	 * @param $gid
	 */
	public function updateReleaseCommentCount($gid)
	{
		$this->pdo->queryExec(sprintf("update releases
				SET comments = (SELECT count(id) FROM release_comments WHERE release_comments.gid = releases.gid AND isvisible = 1)
				WHERE releases.gid = %s", $this->pdo->escapeString($gid) ));
	}

	/**
	 * Get a count of all comments for a user.
	 *
	 * @param $uid
	 *
	 * @return
	 */
	public function getCommentCountForUser($uid)
	{
		$res = $this->pdo->queryOneRow(sprintf("SELECT count(id) AS num FROM release_comments WHERE users_id = %d AND isvisible = 1", $uid));
		return $res["num"];
	}

	/**
	 * Get comments for a user by limit.
	 *
	 * @param $uid
	 * @param $start
	 * @param $num
	 *
	 * @return array
	 */
	public function getCommentsForUserRange($uid, $start, $num)
	{
		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT ".$start.",".$num;

		return $this->pdo->query(sprintf("SELECT release_comments.*, r.guid, r.searchname, users.username FROM release_comments INNER JOIN releases r ON r.id = release_comments.releases_id LEFT OUTER JOIN users ON users.id = release_comments.users_id WHERE users_id = %d ORDER BY release_comments.createddate DESC ".$limit, $uid));
	}
}
