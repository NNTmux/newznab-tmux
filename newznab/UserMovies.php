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

		return $this->pdo->queryInsert(sprintf("INSERT INTO user_movies (userid, imdbid, categoryid, createddate) VALUES (%d, %d, %s, now())", $uid, $imdbid, $catid));
	}

	public function getMovies($uid)
	{
		return $this->pdo->query(sprintf("SELECT user_movies.*, movieinfo.year, movieinfo.plot, movieinfo.cover, movieinfo.title FROM user_movies LEFT OUTER JOIN movieinfo ON movieinfo.imdbid = user_movies.imdbid WHERE userid = %d ORDER BY movieinfo.title ASC", $uid));
	}

	public function delMovie($uid, $imdbid)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM user_movies WHERE userid = %d AND imdbid = %d ", $uid, $imdbid));
	}

	public function getMovie($uid, $imdbid)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT user_movies.*, movieinfo.title FROM user_movies LEFT OUTER JOIN movieinfo ON movieinfo.imdbid = user_movies.imdbid WHERE user_movies.userid = %d AND user_movies.imdbid = %d ", $uid, $imdbid));
	}

	public function delMovieForUser($uid)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM user_movies WHERE userid = %d", $uid));
	}

	public function updateMovie($uid, $imdbid, $catid= [])
	{

		$catid = (!empty($catid)) ? $this->pdo->escapeString(implode('|', $catid)) : "null";
		$this->pdo->queryExec(sprintf("UPDATE user_movies SET categoryid = %s WHERE userid = %d AND imdbid = %d", $catid, $uid, $imdbid));
	}
}
