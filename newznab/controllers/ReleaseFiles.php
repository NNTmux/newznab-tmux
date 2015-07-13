<?php

use newznab\db\Settings;

/**
 * This class handles storage and retrieval of releasefiles.
 */
class ReleaseFiles
{
	/**
	 * @var \newznab\db\Settings
	 */
	protected $pdo;

	/**
	 * @param \newznab\db\Settings $settings
	 */
	public function __construct($settings = null)
	{
		$this->pdo = ($settings instanceof Settings ? $settings : new Settings());
	}


	/**
	 * Get releasefiles row by id.
	 */
	public function get($id)
	{
		return $this->pdo->query(sprintf("SELECT * FROM releasefiles WHERE releaseid = %d ORDER BY releasefiles.name ", $id));
	}

	/**
	 * Get releasefiles row by release.GUID.
	 */
	public function getByGuid($guid)
	{
		return $this->pdo->query(sprintf("SELECT releasefiles.* FROM releasefiles INNER JOIN releases r ON r.id = releasefiles.releaseid WHERE r.guid = %s ORDER BY releasefiles.name ", $this->pdo->escapeString($guid)));
	}

	/**
	 * Delete a releasefiles row.
	 */
	public function delete($id)
	{
		return $this->pdo->queryExec(sprintf("DELETE FROM releasefiles WHERE releaseid = %d", $id));
	}

	/**
	 * Add a releasefiles row.
	 */
	public function add($id, $name, $size, $createddate, $passworded)
	{
		return $this->pdo->queryInsert(sprintf("INSERT INTO releasefiles  (releaseid, name, size, createddate, passworded) VALUES
			(%d, %s, %s, from_unixtime(%d), %d)",
				$id, $this->pdo->escapeString($name), $this->pdo->escapeString($size),
				$createddate, $passworded
			)
		);
	}
}