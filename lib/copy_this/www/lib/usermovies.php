<?php
require_once(WWW_DIR."/lib/framework/db.php");

class UserMovies
{
	public function addMovie($uid, $imdbid, $catid=array())
	{
		$db = new DB();

		$catid = (!empty($catid)) ? $db->escapeString(implode('|', $catid)) : "null";

		$sql = sprintf("insert into usermovies (userID, imdbid, categoryid, createddate) values (%d, %d, %s, now())", $uid, $imdbid, $catid);
		return $db->queryInsert($sql);
	}

	public function getMovies($uid)
	{
		$db = new DB();
		$sql = sprintf("select usermovies.*, movieinfo.year, movieinfo.plot, movieinfo.cover, movieinfo.title from usermovies left outer join movieinfo on movieinfo.imdbid = usermovies.imdbid where userID = %d order by movieinfo.title asc", $uid);
		return $db->query($sql);
	}

	public function delMovie($uid, $imdbid)
	{
		$db = new DB();
		$db->queryExec(sprintf("DELETE from usermovies where userID = %d and imdbid = %d ", $uid, $imdbid));
	}

	public function getMovie($uid, $imdbid)
	{
		$db = new DB();
		$sql = sprintf("select usermovies.*, movieinfo.title from usermovies left outer join movieinfo on movieinfo.imdbid = usermovies.imdbid where usermovies.userID = %d and usermovies.imdbid = %d ", $uid, $imdbid);
		return $db->queryOneRow($sql);
	}

	public function delMovieForUser($uid)
	{
		$db = new DB();
		$db->queryExec(sprintf("DELETE from usermovies where userID = %d", $uid));
	}

	public function updateMovie($uid, $imdbid, $catid=array())
	{
		$db = new DB();

		$catid = (!empty($catid)) ? $db->escapeString(implode('|', $catid)) : "null";

		$sql = sprintf("update usermovies set categoryid = %s where userID = %d and imdbid = %d", $catid, $uid, $imdbid);
		$db->queryExec($sql);
	}
}