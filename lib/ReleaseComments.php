<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/site.php");
require_once("functions.php");

class ReleaseComments
{
	// Returns the row associated to the id of a comment.
	public function getCommentById($id)
	{
		$db = new DB();
		return $db->queryOneRow(sprintf("SELECT * FROM releasecomment WHERE ID = %d", $id));
	}

	public function getComments($id)
	{
		$db = new DB();
		return $db->query(sprintf("SELECT releasecomment.* FROM releasecomment WHERE releaseID = %d", $id));
	}

	public function getCommentCount()
	{
		$db = new DB();
		$res = $db->queryOneRow(sprintf("SELECT COUNT(ID) AS num FROM releasecomment"));
		return $res["num"];
	}

	// For deleting a single comment on the site.
	public function deleteComment($id)
	{
		$db = new DB();
		$res = $this->getCommentById($id);
		if ($res)
		{
			$db->exec(sprintf("DELETE FROM releasecomment WHERE ID = %d", $id));
			$this->updateReleaseCommentCount($res["releaseID"]);
		}
	}

	public function deleteCommentsForRelease($id)
	{
		$db = new DB();
		$db->exec(sprintf("DELETE FROM releasecomment WHERE releaseID = %d", $id));
		$this->updateReleaseCommentCount($id);
	}

	public function deleteCommentsForUser($id)
	{

		$numcomments = $this->getCommentCountForUser($id);
		if ($numcomments > 0)
		{
			$comments = $this->getCommentsForUserRange($id, 0, $numcomments);
			foreach ($comments as $comment)
			{
				$this->deleteComment($comment["ID"]);
				$this->updateReleaseCommentCount($comment["releaseID"]);
			}
		}
	}

	public function addComment($id, $text, $userid, $host)
	{
		$db = new DB();

		$site = new Sites();
		$s = $site->get();
		if ($s->storeuserips != "1")
			$host = "";

		$username = $db->queryOneRow(sprintf('SELECT username FROM users WHERE ID = %d', $userid));
		$username = ($username === false ? 'ANON' : $username['username']);

		$comid = $db->queryInsert(
			sprintf("
				INSERT INTO releasecomment (releaseID, text, userID, createddate, host, username)
				VALUES (%d, %s, %d, NOW(), %s, %s)",
				$id,
				$db->escapeString($text),
				$userid,
				$db->escapeString($host),
				$db->escapeString($username)
			)
		);
		$this->updateReleaseCommentCount($id);
		return $comid;
	}

	public function getCommentsRange($start, $num)
	{
		$db = new DB();
		return $db->query(
			sprintf("
				SELECT releasecomment.*, releases.guid
				FROM releasecomment
				LEFT JOIN releases on releases.ID = releasecomment.releaseID
				ORDER BY releasecomment.createddate DESC %s",
				($start === false ? '' : " LIMIT " . $num . " OFFSET " . $start)
			)
		);
	}

	// Updates the amount of comments for the rlease.
	public function updateReleaseCommentCount($relid)
	{
		$db = new DB();
		$db->exec(
			sprintf("
				UPDATE releases
				SET comments = (SELECT COUNT(ID) from releasecomment WHERE releasecomment.releaseID = %d)
				WHERE releases.ID = %d",
				$relid,
				$relid
			)
		);
	}

	public function getCommentCountForUser($uid)
	{
		$db = new DB();
		$res = $db->queryOneRow(
			sprintf("
				SELECT COUNT(ID) AS num
				FROM releasecomment
				WHERE userID = %d",
				$uid
			)
		);
		return $res["num"];
	}

	public function getCommentsForUserRange($uid, $start, $num)
	{
		$db = new DB();

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT ".$num." OFFSET ".$start;

		return $db->query(
			sprintf("
				SELECT releasecomment.*
				FROM releasecomment
				WHERE userID = %d
				ORDER BY releasecomment.createddate DESC %s",
				$uid,
				$limit
			)
		);
	}
}