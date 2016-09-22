<?php
namespace nntmux;

use nntmux\db\Settings;

/**
 * This class looks up tv episode data.
 */
class Episode
{
	/**
	 * Get an episodeinfo row by id.
	 *
	 * @param $episodeinfoID
	 *
	 * @return array|bool
	 */
	public function getEpisodeInfoByID($episodeinfoID)
	{
		$db = new Settings();
		return $db->queryOneRow(sprintf('SELECT * FROM episodeinfo WHERE id = %d', $episodeinfoID));
	}

	/**
	 * Get an episodeinfo row by name.
	 *
	 * @param        $showtitle
	 * @param        $fullep
	 * @param string $epabsolute
	 *
	 * @return array|bool
	 */
	public function getEpisodeInfoByName($showtitle, $fullep, $epabsolute='0')
	{
		$db = new Settings();

		if($epabsolute == '0') //as string - not int.
			if(!preg_match('/[21]\d{3}\/\d{2}\/\d{2}/', $fullep))
				$additionalSql = sprintf('AND fullep = %s', $db->escapeString($fullep));
			else	$additionalSql = sprintf('AND airdate LIKE %s', $db->escapeString($fullep.' %'));
		else $additionalSql = sprintf('AND epabsolute = %s', $db->escapeString($epabsolute));

		return $db->queryOneRow(sprintf('SELECT * FROM episodeinfo WHERE showtitle = %s %s', $db->escapeString($showtitle), $additionalSql));
	}
}
