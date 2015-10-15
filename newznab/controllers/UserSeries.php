<?php
namespace newznab\controllers;

use newznab\db\Settings;

/**
 * Class UserSeries
 */
class UserSeries
{
	/**
	 * @var \newznab\db\Settings
	 */
	public $pdo;

	/**
	 * @param array $options Class instances.
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Settings' => null,
		];
		$options += $defaults;

		$this->pdo = ($options['Settings'] instanceof Settings ? $options['Settings'] : new Settings());
	}

	public function addShow($uid, $rageid, $catid= [])
	{

		$catid = (!empty($catid)) ? $this->pdo->escapeString(implode('|', $catid)) : "null";

		return $this->pdo->queryInsert(sprintf("INSERT INTO userseries (userid, rageid, categoryid, createddate) VALUES (%d, %d, %s, now())", $uid, $rageid, $catid));
	}

	public function getShows($uid)
	{
		return $this->pdo->query(sprintf("SELECT userseries.*, tvrage_titles.releasetitle FROM userseries INNER JOIN (SELECT id, releasetitle, rageid FROM tvrage_titles GROUP BY rageid) tvrage_titles ON tvrage_titles.rageid = userseries.rageid WHERE userid = %d ORDER BY tvrage_titles.releasetitle ASC", $uid));
	}

	public function delShow($uid, $rageid)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM userseries WHERE userid = %d AND rageid = %d ", $uid, $rageid));
	}

	public function getShow($uid, $rageid)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT userseries.*, tvrage_titles.releasetitle FROM userseries LEFT OUTER JOIN (SELECT id, releasetitle, rageid FROM tvrage_titles GROUP BY rageid) tvrage_titles ON tvrage_titles.rageid = userseries.rageid WHERE userseries.userid = %d AND userseries.rageid = %d ", $uid, $rageid));
	}

	public function delShowForUser($uid)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM userseries WHERE userid = %d", $uid));
	}

	public function delShowForSeries($sid)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM userseries WHERE rageid = %d", $sid));
	}

	public function updateShow($uid, $rageid, $catid= [])
	{

		$catid = (!empty($catid)) ? $this->pdo->escapeString(implode('|', $catid)) : "null";

		$this->pdo->queryExec(sprintf("UPDATE userseries SET categoryid = %s WHERE userid = %d AND rageid = %d", $catid, $uid, $rageid));
	}
}