<?php

use newznab\db\DB;

class UserSeries
{
	public function addShow($uid, $rageid, $catid=array())
	{
		$db = new DB();

		$catid = (!empty($catid)) ? $db->escapeString(implode('|', $catid)) : "null";

		$sql = sprintf("insert into userseries (userid, rageid, categoryid, createddate) values (%d, %d, %s, now())", $uid, $rageid, $catid);
		return $db->queryInsert($sql);
	}

	public function getShows($uid)
	{
		$db = new DB();
		$sql = sprintf("select userseries.*, tvrage.releasetitle from userseries inner join (SELECT id, releasetitle, rageid FROM tvrage GROUP BY rageid) tvrage on tvrage.rageid = userseries.rageid where userid = %d order by tvrage.releasetitle asc", $uid);
		return $db->query($sql);
	}

	public function delShow($uid, $rageid)
	{
		$db = new DB();
		$db->queryExec(sprintf("DELETE from userseries where userid = %d and rageid = %d ", $uid, $rageid));
	}

	public function getShow($uid, $rageid)
	{
		$db = new DB();
		$sql = sprintf("select userseries.*, tvrage.releasetitle from userseries left outer join (SELECT id, releasetitle, rageid FROM tvrage GROUP BY rageid) tvrage on tvrage.rageid = userseries.rageid where userseries.userid = %d and userseries.rageid = %d ", $uid, $rageid);
		return $db->queryOneRow($sql);
	}

	public function delShowForUser($uid)
	{
		$db = new DB();
		$db->queryExec(sprintf("DELETE from userseries where userid = %d", $uid));
	}

	public function delShowForSeries($sid)
	{
		$db = new DB();
		$db->queryExec(sprintf("DELETE from userseries where rageid = %d", $sid));
	}

	public function updateShow($uid, $rageid, $catid=array())
	{
		$db = new DB();

		$catid = (!empty($catid)) ? $db->escapeString(implode('|', $catid)) : "null";

		$sql = sprintf("update userseries set categoryid = %s where userid = %d and rageid = %d", $catid, $uid, $rageid);
		$db->queryExec($sql);
	}
}