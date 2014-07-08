<?php
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/category.php");

/**
 * This class handles data access for genre data.
 */
class Genres
{
	const CONSOLE_TYPE = Category::CAT_PARENT_GAME;
	const MUSIC_TYPE = Category::CAT_PARENT_MUSIC;
	const BOOK_TYPE = Category::CAT_BOOK_EBOOK;
	const GAME_TYPE    = Category::CAT_PARENT_PC;

	/**
	 * Get genre rows, filter by type/active.
	 */
	public function getGenres($type='', $activeonly=false, $usecache=false)
	{
		$db = new DB();

		if (!empty($type))
			$typesql = sprintf(" and genres.type = %d", $type);
		else
			$typesql = '';

		if ($activeonly)
		{
			$sql = sprintf("SELECT genres.*  FROM genres INNER JOIN (SELECT DISTINCT genreID FROM musicinfo) X ON X.genreID = genres.ID %s
			UNION
			SELECT genres.*  FROM genres INNER JOIN (SELECT DISTINCT genreID FROM consoleinfo) X ON X.genreID = genres.ID %s
			ORDER BY title", $typesql, $typesql);
		}
		else
			$sql = sprintf("select genres.* from genres where 1 %s order by title", $typesql);

		return $db->query($sql, $usecache);
	}

	/**
	 * Add new genre.
	 */
	public function getOrAddGenreKey($genreName, &$genreassoc)
	{
		if (in_array(strtolower($genreName), $genreassoc))
		{
			$genreKey = array_search(strtolower($genreName), $genreassoc);
		}
		else
		{
			$db = new DB();
			$genreKey = $db->queryInsert(sprintf("INSERT INTO genres (`title`, `type`) VALUES (%s, %d)", $db->escapeString($genreName), Genres::MUSIC_TYPE));
			$genreassoc[$genreKey] = strtolower($genreName);
		}

		return $genreKey;
	}
}