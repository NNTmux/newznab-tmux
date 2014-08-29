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
	const GAME_TYPE = Category::CAT_PARENT_PC;


	public function getGenres($type = '', $activeonly = false)
	{
		$db = new DB();

		return $db->query($this->getListQuery($type, $activeonly));
	}

	private function getListQuery($type = '', $activeonly = false)
	{
		if (!empty($type))
			$typesql = sprintf(" AND g.type = %d", $type);
		else
			$typesql = '';

		if ($activeonly) {
			$sql = sprintf("
						SELECT g.*
						FROM genres g
						INNER JOIN
							(SELECT DISTINCT genreID FROM musicinfo) x
							ON x.genreID = g.ID %1\$s
						UNION
						SELECT g.*
						FROM genres g
						INNER JOIN
							(SELECT DISTINCT genreID FROM consoleinfo) x
							ON x.genreID = g.ID %1\$s
						UNION
						SELECT g.*
						FROM genres g
						INNER JOIN
							(SELECT DISTINCT genre_id FROM gamesinfo) x
							ON x.genre_id = g.ID %1\$s
							ORDER BY title",
				$typesql
			);
		} else {
			$sql = sprintf("SELECT g.* FROM genres g WHERE 1 %s ORDER BY g.title", $typesql);
		}

		return $sql;
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