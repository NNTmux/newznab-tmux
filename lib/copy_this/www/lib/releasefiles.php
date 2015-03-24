<?php
require_once(WWW_DIR."/lib/framework/db.php");

/**
 * This class handles storage and retrieval of releasefiles.
 */
class ReleaseFiles
{
	/**
	 * Get releasefiles row by id.
	 */
	public function get($id)
	{
		$db = new DB();
		return $db->query(sprintf("select * from releasefiles where releaseid = %d  order by releasefiles.name ", $id));
	}

	/**
	 * Get releasefiles row by release.GUID.
	 */
	public function getByGuid($guid)
	{
		$db = new DB();
		return $db->query(sprintf("select releasefiles.* from releasefiles inner join releases r on r.id = releasefiles.releaseid where r.guid = %s order by releasefiles.name ", $db->escapeString($guid)));
	}

	/**
	 * Delete a releasefiles row.
	 */
	public function delete($id)
	{
		$db = new DB();
		return $db->queryExec(sprintf("DELETE from releasefiles where releaseid = %d", $id));
	}

	/**
	 * Add a releasefiles row.
	 */
	public function add($id, $name, $size, $createddate, $passworded)
	{
		$db = new DB();
		$sql = sprintf("INSERT INTO releasefiles  (releaseid,   name,   size,   createddate,   passworded) VALUES (%d, %s, %s, from_unixtime(%d), %d)", $id, $db->escapeString($name), $db->escapeString($size), $createddate, $passworded );
		return $db->queryInsert($sql);
	}
}