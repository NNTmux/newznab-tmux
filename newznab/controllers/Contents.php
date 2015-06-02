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
		return $this->pdo->queryExec(sprintf("DELETE from content where id=%d", $id));
	}

	/**
	 * Update a content row.
	 */
	public function update($content)
	{
		$content = $this->validate($content);
		$this->pdo->queryExec(sprintf("update content set	role=%d, title = %s , 	url = %s , 	body = %s , 	metadescription = %s , 	metakeywords = %s , 	contenttype = %d , 	showinmenu = %d , 	status = %d , 	ordinal = %d	where	id = %d ", $content["role"], $this->pdo->escapeString($content["title"]), $this->pdo->escapeString($content["url"]), $this->pdo->escapeString($content["body"]), $this->pdo->escapeString($content["metadescription"]), $this->pdo->escapeString($content["metakeywords"]), $content["contenttype"], $content["showinmenu"], $content["status"], $content["ordinal"], $content["id"] ));
	}

	/**
	 * Add a content row.
	 */
	public function add($content)
	{
		$content = $this->validate($content);
		return $this->pdo->queryInsert(sprintf("insert into content (role, title, url, body, metadescription, metakeywords, 	contenttype, 	showinmenu, 	status, 	ordinal	)	values	(%d, %s, 	%s, 	%s, 	%s, 	%s, 	%d, 	%d, 	%d, 	%d 	)", $content["role"], $this->pdo->escapeString($content["title"]),  $this->pdo->escapeString($content["url"]),  $this->pdo->escapeString($content["body"]),  $this->pdo->escapeString($content["metadescription"]),  $this->pdo->escapeString($content["metakeywords"]), $content["contenttype"], $content["showinmenu"], $content["status"], $content["ordinal"] ));
	}

	/**
	 * Get all active content rows.
	 */
	public function get()
	{
		return $this->pdo->query(sprintf("select * from content where status = 1 order by contenttype, coalesce(ordinal, 1000000)"));
	}

	/**
	 * Get all content rows.
	 */
	public function getAll()
	{
		return $this->pdo->query(sprintf("select * from content order by contenttype, coalesce(ordinal, 1000000)"));
	}

	/**
	 * Get a content row by its id.
	 */
	public function getByID($id, $role)
	{
		if ($role == Users::ROLE_ADMIN)
			$role = "";
		else
			$role = sprintf("and (role=%d or role=0)", $role);

		return $this->pdo->queryOneRow(sprintf("select * from content where id = %d %s", $id, $role));
	}

	/**
	 * Get the index page.
	 */
	public function getIndex()
	{
		return $this->pdo->queryOneRow(sprintf("select * from content where status=1 and contenttype = %d ", Contents::TYPEINDEX), true);
	}

	/**
	 * Get all content rows for a role and menu type, ie useful links.
	 */
	public function getForMenuByTypeAndRole($id, $role)
	{
		if ($role == Users::ROLE_ADMIN)
			$role = "";
		else
			$role = sprintf("and (role=%d or role=0)", $role);
		return $this->pdo->query(sprintf("select * from content where showinmenu=1 and status=1 and contenttype = %d %s ", $id, $role));
	}
}