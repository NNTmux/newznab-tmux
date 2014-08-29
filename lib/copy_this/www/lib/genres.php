<?php

require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/category.php");

class Genres
{
	const CONSOLE_TYPE = Category::CAT_PARENT_GAME;
	const MUSIC_TYPE = Category::CAT_PARENT_MUSIC;
	const GAME_TYPE = Category::CAT_PARENT_PC;

	const STATUS_ENABLED = 0;
	const STATUS_DISABLED = 1;

	/**
	 * @var DB;
	 */
	public $pdo;

	/**
	 * @param array $options Class instances.
	 */
	public function __construct(array $options = array())
	{
		$defaults = [
			'Settings' => null
		];
		$options += $defaults;

		$this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
	}

	public function getGenres($type = '', $activeonly = false)
	{
		return $this->pdo->query($this->getListQuery($type, $activeonly));
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

	public function getRange($type = '', $activeonly = false, $start, $num)
	{
		$sql = $this->getListQuery($type, $activeonly);
		$sql .= " LIMIT " . $num . " OFFSET " . $start;

		return $this->pdo->query($sql);
	}

	public function getCount($type = '', $activeonly = false)
	{
		if (!empty($type))
			$typesql = sprintf(" AND g.type = %d", $type);
		else
			$typesql = '';

		if ($activeonly)
			$sql = sprintf("
						SELECT COUNT(*) AS num
						FROM genres g
						INNER JOIN
							(SELECT DISTINCT genreID FROM musicinfo) x
							ON x.genreID = g.ID %1\$s
						+
						SELECT COUNT(*) AS num
						FROM genres g
						INNER JOIN
							(SELECT DISTINCT genreID FROM consoleinfo) y
							ON y.genreID = g.ID %1\$s
						+
						SELECT COUNT(*) AS num
						FROM genres g
						INNER JOIN
							(SELECT DISTINCT genre_id FROM gamesinfo) x
							ON x.genre_id = g.ID %1\$s",
				$typesql
			);
		else
			$sql = sprintf("SELECT COUNT(g.ID) AS num FROM genres g WHERE 1 %s ORDER BY g.title", $typesql);

		$res = $this->pdo->queryOneRow($sql);

		return $res["num"];
	}

	public function getById($id)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT * FROM genres WHERE ID = %d", $id));
	}

	public function update($id, $disabled)
	{
		return $this->pdo->queryExec(sprintf("UPDATE genres SET disabled = %d WHERE ID = %d", $disabled, $id));
	}

	public function getDisabledIDs()
	{
		return $this->pdo->query("SELECT ID FROM genres WHERE disabled = 1");
	}
}