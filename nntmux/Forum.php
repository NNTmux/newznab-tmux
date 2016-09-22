<?php
namespace nntmux;

use nntmux\db\DB;

class Forum
{
	/**
	 * @var DB
	 */
	public $pdo;

	/**
	 * @param array $options Class instances.
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Settings' => null
		];
		$options += $defaults;

		$this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
	}

	public function add($parentid, $userid, $subject, $message, $locked = 0, $sticky = 0, $replies = 0)
	{
		if ($message == "") {
			return -1;
		}

		if ($parentid != 0) {
			$par = $this->getParent($parentid);
			if ($par == false) {
				return -1;
			}

			$this->pdo->queryExec(sprintf("UPDATE forumpost SET replies = replies + 1, updateddate = NOW() WHERE id = %d", $parentid));
		}

		return $this->pdo->queryInsert(
			sprintf("
				INSERT INTO forumpost (forumid, parentid, users_id, subject, message, locked, sticky, replies, createddate, updateddate)
				VALUES (1, %d, %d, %s, %s, %d, %d, %d, NOW(), NOW())",
				$parentid, $userid, $this->pdo->escapeString($subject), $this->pdo->escapeString($message), $locked, $sticky, $replies
			)
		);
	}

	public function getParent($parent)
	{
		return $this->pdo->queryOneRow(
			sprintf(
				"SELECT forumpost.*, users.username FROM forumpost LEFT OUTER JOIN users ON users.id = forumpost.users_id WHERE forumpost.id = %d",
				$parent
			)
		);
	}

	public function getPosts($parent)
	{
		return $this->pdo->query(
			sprintf("
				SELECT forumpost.*, users.username
				FROM forumpost
				LEFT OUTER JOIN users ON users.id = forumpost.users_id
				WHERE forumpost.id = %d OR parentid = %d
				ORDER BY createddate ASC
				LIMIT 250",
				$parent,
				$parent
			)
		);
	}

	public function getPost($id)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT * FROM forumpost WHERE id = %d", $id));
	}

	public function getBrowseCount()
	{
		$res = $this->pdo->queryOneRow(sprintf("SELECT COUNT(id) AS num FROM forumpost WHERE parentid = 0"));
		return ($res === false ? 0 : $res["num"]);
	}

	public function getBrowseRange($start, $num)
	{
		return $this->pdo->query(
			sprintf("
				SELECT forumpost.*, users.username
				FROM forumpost
				LEFT OUTER JOIN users ON users.id = forumpost.users_id
				WHERE parentid = 0
				ORDER BY updateddate DESC %s",
				($start === false ? '' : (" LIMIT " . $num . " OFFSET " . $start))
			)
		);
	}

	public function deleteParent($parent)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM forumpost WHERE id = %d OR parentid = %d", $parent, $parent));
	}

	public function deletePost($id)
	{
		$post = $this->getPost($id);
		if ($post) {
			if ($post["parentid"] == "0") {
				$this->deleteParent($id);
			} else {
				$this->pdo->queryExec(sprintf("DELETE FROM forumpost WHERE id = %d", $id));
			}
		}
	}

	public function deleteUser($id)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM forumpost WHERE users_id = %d", $id));
	}

	public function getCountForUser($uid)
	{
		$res = $this->pdo->queryOneRow(sprintf("SELECT COUNT(id) AS num FROM forumpost WHERE users_id = %d", $uid));
		return ($res === false ? 0 : $res["num"]);
	}

	public function getForUserRange($uid, $start, $num)
	{
		return $this->pdo->query(
			sprintf("
				SELECT forumpost.*, users.username
				FROM forumpost
				LEFT OUTER JOIN users ON users.id = forumpost.users_id
				WHERE users_id = %d
				ORDER BY forumpost.createddate DESC %s",
				($start === false ? '' : (" LIMIT " . $num . " OFFSET " . $start)),
				$uid
			)
		);
	}
}
