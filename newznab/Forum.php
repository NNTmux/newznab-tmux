<?php
namespace newznab;

use newznab\db\Settings;

/**
 * This class handles data access for forum and post data.
 */
class Forum
{

	/**
	 * @var Settings
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

		$this->pdo = ($options['Settings'] instanceof Settings ? $options['Settings'] : new Settings());
	}
	/**
	 * Add a forum post.
	 */
	public function add($parentid, $userid, $subject, $message, $locked = 0, $sticky = 0, $replies = 0)
	{
		if ($message == "")
			return -1;

		if ($parentid != 0)
		{
			$par = $this->getParent($parentid);
			if ($par == false)
				return -1;

			$this->pdo->queryExec(sprintf("update forumpost set replies = replies + 1, updateddate = now() where id = %d", $parentid));
		}

		$this->pdo->queryInsert(sprintf("INSERT INTO forumpost (forumid, parentid, userid, subject, message, locked, sticky, replies, createddate, updateddate) VALUES ( 1,  %d, %d,  %s,  %s, %d, %d, %d,NOW(),  NOW())",
			$parentid, $userid, $this->pdo->escapeString($subject)	, $this->pdo->escapeString($message), $locked, $sticky, $replies));
	}

	/**
	 * Get the top level post in a thread.
	 */
	public function getParent($parent)
	{
		return $this->pdo->queryOneRow(sprintf(" SELECT forumpost.*, users.username from forumpost left outer join users on users.id = forumpost.userid where forumpost.id = %d ", $parent));
	}

	/**
	 * Get recent posts.
	 */
	public function getRecentPosts($limit)
	{
		return $this->pdo->query(sprintf("select forumpost.*, users.username from forumpost join (select case when parentid = 0 then id else parentid end as id, max(createddate) from forumpost group by case when parentid = 0 then id else parentid end order by max(createddate) desc) x on x.id = forumpost.id inner join users on userid = users.id limit %d", $limit));
	}


	/**
	 * Get all child posts for a parent.
	 */
	public function getPosts($parent)
	{
		return $this->pdo->query(sprintf(" SELECT forumpost.*, CASE WHEN role=%d THEN 1 ELSE 0 END  AS 'isadmin', users.username from forumpost left outer join users on users.id = forumpost.userid where forumpost.id = %d or parentid = %d order by createddate asc limit 250", Users::ROLE_ADMIN, $parent, $parent));
	}

	/**
	 * Get a forumpost by its id.
	 */
	public function getPost($id)
	{
		return $this->pdo->queryOneRow(sprintf(" SELECT * from forumpost where id = %d", $id));
	}

	/**
	 * Get a count of all forum posts.
	 */
	public function getBrowseCount()
	{
		$res = $this->pdo->queryOneRow(sprintf("select count(id) as num from forumpost where parentid = 0"));
		return $res["num"];
	}

	/**
	 * Get a list of forum posts for browse list by limit.
	 */
	public function getBrowseRange($start, $num)
	{

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT ".$start.",".$num;

		return $this->pdo->query(sprintf(" SELECT forumpost.*, users.username from forumpost left outer join users on users.id = forumpost.userid where parentid = 0 order by updateddate desc".$limit ));
	}

	/**
	 * Delete an entire thread.
	 */
	public function deleteParent($parent)
	{
		$this->pdo->queryExec(sprintf("DELETE from forumpost where id = %d or parentid = %d", $parent, $parent));
	}

	/**
	 * Delete a forumpost row.
	 */
	public function deletePost($id)
	{
		$post = $this->getPost($id);
		if ($post)
		{
			if ($post["parentid"] == "0")
				$this->deleteParent($id);
			else
				$this->pdo->queryExec(sprintf("DELETE from forumpost where id = %d", $id));
		}
	}

	/**
	 * Delete all forumposts for a user.
	 */
	public function deleteUser($id)
	{
		$this->pdo->queryExec(sprintf("DELETE from forumpost where userid = %d", $id));
	}

	/**
	 * Count of all posts for a user.
	 */
	public function getCountForUser($uid)
	{
		$res = $this->pdo->queryOneRow(sprintf("select count(id) as num from forumpost where userid = %d", $uid));
		return $res["num"];
	}

	/**
	 * Get forum posts for a user for paged list in profile.
	 */
	public function getForUserRange($uid, $start, $num)
	{

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT ".$start.",".$num;

		return $this->pdo->query(sprintf(" SELECT forumpost.*, users.username FROM forumpost LEFT OUTER JOIN users ON users.id = forumpost.userid where userid = %d order by forumpost.createddate desc ".$limit, $uid));
	}
}
