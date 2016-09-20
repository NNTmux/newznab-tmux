<?php
namespace nntmux;

use nntmux\db\DB;

/**
 * Class UserMovies
 */
class UserMovies
{
	/**
	 * @var \nntmux\db\Settings
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

		$this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
	}

	/**
	 * Add movie for a user
	 *
	 * @param       $uid
	 * @param       $imdbid
	 * @param array $catID
	 *
	 * @return bool|int
	 */
	public function addMovie($uid, $imdbid, $catID = [])
	{
		return $this->pdo->queryInsert(
			sprintf(
				"INSERT INTO user_movies (users_id, imdbid, categories, createddate)
				VALUES (%d, %d, %s, now())",
				$uid,
				$imdbid,
				(!empty($catID) ? $this->pdo->escapeString(implode('|', $catID)) : "NULL")
			)
		);
	}

	/**
	 * Get movies for a user
	 *
	 * @param $uid
	 *
	 * @return array
	 */
	public function getMovies($uid)
	{
		return $this->pdo->query(
			sprintf(
				"SELECT um.*, mi.year, mi.plot, mi.cover, mi.title
				FROM user_movies um
				LEFT OUTER JOIN movieinfo mi ON mi.imdbid = um.imdbid
				WHERE users_id = %d
				ORDER BY mi.title ASC",
				$uid
			)
		);
	}

	/**
	 * Delete movie for a user
	 *
	 * @param $uid
	 * @param $imdbid
	 *
	 * @return bool|\PDOStatement
	 */
	public function delMovie($uid, $imdbid)
	{
		return $this->pdo->queryExec(sprintf(
			"DELETE FROM user_movies
			WHERE users_id = %d
			AND imdbid = %d ",
			$uid,
			$imdbid
			)
		);
	}

	/**
	 * Get movie for a user
	 *
	 * @param $uid
	 * @param $imdbid
	 *
	 * @return array|bool
	 */
	public function getMovie($uid, $imdbid)
	{
		return $this->pdo->queryOneRow(sprintf(
			"SELECT um.*, mi.title
			FROM user_movies um
			LEFT OUTER JOIN movieinfo mi ON mi.imdbid = um.imdbid
			WHERE um.users_id = %d
			AND um.imdbid = %d ",
			$uid,
			$imdbid
			)
		);
	}

	/**
	 * @param $uid
	 */
	public function delMovieForUser($uid)
	{
		$this->pdo->queryExec(sprintf(
			"DELETE FROM user_movies
			WHERE users_id = %d",
			$uid
			)
		);
	}

	/**
	 * Update movie for a user
	 *
	 * @param       $uid
	 * @param       $imdbid
	 * @param array $catID
	 */
	public function updateMovie($uid, $imdbid, $catID = [])
	{
		$this->pdo->queryExec(
			sprintf(
				"UPDATE user_movies
				SET categories = %s
				WHERE users_id = %d
				AND imdbid = %d",
				(!empty($catID) ? $this->pdo->escapeString(implode('|', $catID)) : "NULL"),
				$uid,
				$imdbid
			)
		);
	}
}
