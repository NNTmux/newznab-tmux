<?php
require_once(WWW_DIR."/lib/framework/db.php");

/**
 * This class handles storage and retrieval of releasefiles.
 */
class ReleaseFiles
{
	/**
	 * Get releasefiles row by ID.
	 */
	public function get($id)
	{
		$db = new DB();
		return $db->query(sprintf("select * from releasefiles where releaseID = %d  order by releasefiles.name ", $id));
	}

	/**
	 * Get releasefiles row by release.GUID.
	 */
	public function getByGuid($guid)
	{
		$db = new DB();
		return $db->query(sprintf("select releasefiles.* from releasefiles inner join releases r on r.ID = releasefiles.releaseID where r.guid = %s order by releasefiles.name ", $db->escapeString($guid)));
	}

	/**
	 * Delete a releasefiles row.
	 */
	public function delete($id)
	{
		$db = new DB();
		return $db->queryExec(sprintf("DELETE from releasefiles where releaseID = %d", $id));
	}

	/**
	 * Add a releasefiles row.
	 */
	public function add($id, $name, $size, $createddate, $passworded)
	{
		$db = new DB();
		$sql = sprintf("INSERT INTO releasefiles  (releaseID,   name,   size,   createddate,   passworded) VALUES (%d, %s, %s, from_unixtime(%d), %d)", $id, $db->escapeString($name), $db->escapeString($size), $createddate, $passworded );
		return $db->queryInsert($sql);
	}
}