<?php

use newznab\db\Settings;

class UserMovies
{
	public function addMovie($uid, $imdbid, $catid=array())
	{
		$db = new Settings();

		$catid = (!empty($catid)) ? $db->escapeString(implode('|', $catid)) : "null";

		$sql = sprintf("insert into usermovies (userid, imdbid, categoryid, createddate) values (%d, %d, %s, now())", $uid, $imdbid, $catid);
		return $db->queryInsert($sql);
	}

	public function getMovies($uid)
	{
		$db = new Settings();
		$sql = sprintf("select usermovies.*, movieinfo.year, movieinfo.plot, movieinfo.cover, movieinfo.title from usermovies left outer join movieinfo on movieinfo.imdbid = usermovies.imdbid where userid = %d order by movieinfo.title asc", $uid);
		return $db->query($sql);
	}

	public function delMovie($uid, $imdbid)
	{
		$db = new Settings();
		$db->queryExec(sprintf("DELETE from usermovies where userid = %d and imdbid = %d ", $uid, $imdbid));
	}

	public function getMovie($uid, $imdbid)
	{
		$db = new Settings();
		$sql = sprintf("select usermovies.*, movieinfo.title from usermovies left outer join movieinfo on movieinfo.imdbid = usermovies.imdbid where usermovies.userid = %d and usermovies.imdbid = %d ", $uid, $imdbid);
		return $db->queryOneRow($sql);
	}

	public function delMovieForUser($uid)
	{
		$db = new Settings();
		$db->queryExec(sprintf("DELETE from usermovies where userid = %d", $uid));
	}

	public function updateMovie($uid, $imdbid, $catid=array())
	{
		$db = new Settings();

		$catid = (!empty($catid)) ? $db->escapeString(implode('|', $catid)) : "null";

		$sql = sprintf("update usermovies set categoryid = %s where userid = %d and imdbid = %d", $catid, $uid, $imdbid);
		$db->queryExec($sql);
	}
}