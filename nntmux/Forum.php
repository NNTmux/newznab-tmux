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

	/**
	 * Add post to forum
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

	/**
	 * Get parent of the forum post
	 *
	 * @param $parent
	 *
	 * @return array|bool
	 */
	public function getParent($parent)
	{
		return $this->pdo->queryOneRow(
			sprintf(
				"SELECT f.*, u.username FROM forumpost f LEFT OUTER JOIN users u ON u.id = f.users_id WHERE f.id = %d",
				$parent
			)
		);
	}

	/**
	 * Get forum posts for a parent category
	 *
	 * @param $parent
	 *
	 * @return array
	 */
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

	/**
	 * Get post from forum
	 *
	 * @param $id
	 *
	 * @return array|bool
	 */
	public function getPost($id)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT * FROM forumpost WHERE id = %d", $id));
	}

	/**
	 * Get count of posts for parent forum
	 *
	 * @return int
	 */
	public function getBrowseCount()
	{
		$res = $this->pdo->queryOneRow(sprintf("SELECT COUNT(id) AS num FROM forumpost WHERE parentid = 0"));
		return ($res === false ? 0 : $res["num"]);
	}

	/**
	 * Get browse range for forum
	 *
	 * @param $start
	 * @param $num
	 *
	 * @return array
	 */
	public function getBrowseRange($start, $num)
	{
		return $this->pdo->query(
			sprintf("
				SELECT f.*, u.username
				FROM forumpost f
				LEFT OUTER JOIN users u ON u.id = f.users_id
				WHERE parentid = 0
				ORDER BY updateddate DESC %s",
				($start === false ? '' : (" LIMIT " . $num . " OFFSET " . $start))
			)
		);
	}

	/**
	 * Delete parent category from forum
	 *
	 * @param $parent
	 */
	public function deleteParent($parent)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM forumpost WHERE id = %d OR parentid = %d", $parent, $parent));
	}

	/**
	 * Delete post from forum
	 *
	 * @param $id
	 */
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

	/**
	 * Delete user from forum
	 *
	 * @param $id
	 */
	public function deleteUser($id)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM forumpost WHERE users_id = %d", $id));
	}

	/**
	 * Get count of posts for user
	 *
	 * @param $uid
	 *
	 * @return int
	 */
	public function getCountForUser($uid)
	{
		$res = $this->pdo->queryOneRow(sprintf("SELECT COUNT(id) AS num FROM forumpost WHERE users_id = %d", $uid));
		return ($res === false ? 0 : $res["num"]);
	}

	/**
	 * Get range of posts for user
	 *
	 * @param $uid
	 * @param $start
	 * @param $num
	 *
	 * @return array
	 */
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

	/**
	 * Edit forum post for user
	 *
	 * @param $id
	 * @param $message
	 * @param $uid
	 */
	public function editPost($id, $message, $uid)
	{
		$post = $this->getPost($id);
		if ($post) {
			$this->pdo->queryExec(sprintf('
							UPDATE forumpost
							SET message = %s
							WHERE id = %d
							AND users_id = %d',
				$this->pdo->escapeString($message),
				$post['id'],
				$uid
			)
			);
		}
	}

	/**
	 * Lock forum topic
	 *
	 * @param $id
	 * @param $lock
	 */
	public function lockUnlockTopic($id, $lock)
	{
		$this->pdo->queryExec(sprintf('
						UPDATE forumpost
						SET locked = %d
						WHERE id = %d
						OR parentid = %d',
				$lock,
				$id,
				$id
			)
		);
	}
}
