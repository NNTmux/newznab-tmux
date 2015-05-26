<?php

use newznab\db\Settings;

/**
 * This class looks up site content.
 */
class Contents
{
	const TYPEUSEFUL = 1;
	const TYPEARTICLE = 2;
	const TYPEINDEX = 3;

	/**
	 * Validate a content row before insert/update.
	 */
	public function validate($content)
	{
		if (substr($content["url"],0,1) != '/')
		{
			$content["url"] = "/".$content["url"];
		}

		if (substr($content["url"], strlen($content["url"]) - 1) != '/')
		{
			$content["url"] = $content["url"]."/";
		}

		return $content;
	}

	/**
	 * Delete a content row.
	 */
	public function delete($id)
	{
		$db = new Settings();
		return $db->queryExec(sprintf("DELETE from content where id=%d", $id));
	}

	/**
	 * Update a content row.
	 */
	public function update($content)
	{
		$db = new Settings();
		$content = $this->validate($content);
		$db->queryExec(sprintf("update content set	role=%d, title = %s , 	url = %s , 	body = %s , 	metadescription = %s , 	metakeywords = %s , 	contenttype = %d , 	showinmenu = %d , 	status = %d , 	ordinal = %d	where	id = %d ", $content["role"], $db->escapeString($content["title"]), $db->escapeString($content["url"]), $db->escapeString($content["body"]), $db->escapeString($content["metadescription"]), $db->escapeString($content["metakeywords"]), $content["contenttype"], $content["showinmenu"], $content["status"], $content["ordinal"], $content["id"] ));
	}

	/**
	 * Add a content row.
	 */
	public function add($content)
	{
		$db = new Settings();

		$content = $this->validate($content);

		return $db->queryInsert(sprintf("insert into content (role, title, url, body, metadescription, metakeywords, 	contenttype, 	showinmenu, 	status, 	ordinal	)	values	(%d, %s, 	%s, 	%s, 	%s, 	%s, 	%d, 	%d, 	%d, 	%d 	)", $content["role"], $db->escapeString($content["title"]),  $db->escapeString($content["url"]),  $db->escapeString($content["body"]),  $db->escapeString($content["metadescription"]),  $db->escapeString($content["metakeywords"]), $content["contenttype"], $content["showinmenu"], $content["status"], $content["ordinal"] ));
	}

	/**
	 * Get all active content rows.
	 */
	public function get()
	{
		$db = new Settings();
		return $db->query(sprintf("select * from content where status = 1 order by contenttype, coalesce(ordinal, 1000000)"));
	}

	/**
	 * Get all content rows.
	 */
	public function getAll()
	{
		$db = new Settings();
		return $db->query(sprintf("select * from content order by contenttype, coalesce(ordinal, 1000000)"));
	}

	/**
	 * Get a content row by its id.
	 */
	public function getByID($id, $role)
	{
		$db = new Settings();
		if ($role == Users::ROLE_ADMIN)
			$role = "";
		else
			$role = sprintf("and (role=%d or role=0)", $role);

		return $db->queryOneRow(sprintf("select * from content where id = %d %s", $id, $role));
	}

	/**
	 * Get the index page.
	 */
	public function getIndex()
	{
		$db = new Settings();
		return $db->queryOneRow(sprintf("select * from content where status=1 and contenttype = %d ", Contents::TYPEINDEX), true);
	}

	/**
	 * Get all content rows for a role and menu type, ie useful links.
	 */
	public function getForMenuByTypeAndRole($id, $role)
	{
		$db = new Settings();
		if ($role == Users::ROLE_ADMIN)
			$role = "";
		else
			$role = sprintf("and (role=%d or role=0)", $role);
		return $db->query(sprintf("select * from content where showinmenu=1 and status=1 and contenttype = %d %s ", $id, $role));
	}
}