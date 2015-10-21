<?php
namespace newznab;

use newznab\db\Settings;

/**
 * Class UserMovies
 */
class UserMovies
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

	public function addMovie($uid, $imdbid, $catid= [])
	{

		$catid = (!empty($catid)) ? $this->pdo->escapeString(implode('|', $catid)) : "null";

		return $this->pdo->queryInsert(sprintf("INSERT INTO usermovies (userid, imdbid, categoryid, createddate) VALUES (%d, %d, %s, now())", $uid, $imdbid, $catid));
	}

	public function getMovies($uid)
	{
		return $this->pdo->query(sprintf("SELECT usermovies.*, movieinfo.year, movieinfo.plot, movieinfo.cover, movieinfo.title FROM usermovies LEFT OUTER JOIN movieinfo ON movieinfo.imdbid = usermovies.imdbid WHERE userid = %d ORDER BY movieinfo.title ASC", $uid));
	}

	public function delMovie($uid, $imdbid)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM usermovies WHERE userid = %d AND imdbid = %d ", $uid, $imdbid));
	}

	public function getMovie($uid, $imdbid)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT usermovies.*, movieinfo.title FROM usermovies LEFT OUTER JOIN movieinfo ON movieinfo.imdbid = usermovies.imdbid WHERE usermovies.userid = %d AND usermovies.imdbid = %d ", $uid, $imdbid));
	}

	public function delMovieForUser($uid)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM usermovies WHERE userid = %d", $uid));
	}

	public function updateMovie($uid, $imdbid, $catid= [])
	{

		$catid = (!empty($catid)) ? $this->pdo->escapeString(implode('|', $catid)) : "null";
		$this->pdo->queryExec(sprintf("UPDATE usermovies SET categoryid = %s WHERE userid = %d AND imdbid = %d", $catid, $uid, $imdbid));
	}
}
