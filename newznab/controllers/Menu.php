<?php

use newznab\db\Settings;

/**
 * This class looks up site menu data.
 */
class Menu
{
	/**
	 * Get all menu rows for a role.
	 */
	public function get($role, $serverurl)
	{
		$db = new Settings();

		$guest = "";
		if ($role != Users::ROLE_GUEST)
			$guest = sprintf(" and role != %d ", Users::ROLE_GUEST);

		if ($role != Users::ROLE_ADMIN)
			$guest .= sprintf(" and role != %d ", Users::ROLE_ADMIN);

		$sql = sprintf("select * from menu where role <= %d %s order by ordinal", $role, $guest);

		$data = $db->query($sql);

		$ret = array();
		foreach ($data as $d)
		{
			if (!preg_match("/http/i", $d["href"]))
			{
				$d["href"] = $serverurl.$d["href"];
				$ret[] = $d;
			}
			else
			{
				$ret[] = $d;
			}
		}
		return $ret;
	}

	/**
	 * Get all menu rows.
	 */
	public function getAll()
	{
		$db = new Settings();
		return $db->query(sprintf("select * from menu order by role, ordinal"));
	}

	/**
	 * Get a menu row by its id.
	 */
	public function getById($id)
	{
		$db = new Settings();
		return $db->queryOneRow(sprintf("select * from menu where id = %d", $id));
	}

	/**
	 * Delete a menu row.
	 */
	public function delete($id)
	{
		$db = new Settings();
		return $db->queryExec(sprintf("DELETE from menu where id = %d", $id));
	}

	/**
	 * Add a menu row.
	 */
	public function add($menu)
	{
		$db = new Settings();
		return $db->queryInsert(sprintf("INSERT INTO menu (href, title, tooltip, role, ordinal, menueval, newwindow )
			VALUES (%s, %s, %s, %d, %d, %s, %d) ", $db->escapeString($menu["href"]), $db->escapeString($menu["title"]), $db->escapeString($menu["tooltip"]), $menu["role"] , $menu["ordinal"], $db->escapeString($menu["menueval"]), $menu["newwindow"] ));
	}

	/**
	 * Update a menu row.
	 */
	public function update($menu)
	{
		$db = new Settings();
		return $db->queryExec(sprintf("update menu set href = %s, title = %s, tooltip = %s, role = %d, ordinal = %d, menueval = %s, newwindow=%d where id = %d	", $db->escapeString($menu["href"]), $db->escapeString($menu["title"]), $db->escapeString($menu["tooltip"]), $menu["role"] , $menu["ordinal"], $db->escapeString($menu["menueval"]), $menu["newwindow"], $menu["id"] ));
	}
}