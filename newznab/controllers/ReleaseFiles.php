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
	 * @var SphinxSearch
	 */
	public $sphinxSearch;

	/**
	 * @param \newznab\db\Settings $settings
	 */
	public function __construct($settings = null)
	{
		$this->pdo = ($settings instanceof Settings ? $settings : new Settings());
		$this->sphinxSearch = new SphinxSearch();
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
		$res = $this->pdo->queryExec(sprintf("DELETE FROM releasefiles WHERE releaseid = %d", $id));
		$this->sphinxSearch->updateRelease($id, $this->pdo);
		return $res;
	}

	/**
	 * Add new files for a release ID.
	 *
	 * @param int    $id          The ID of the release.
	 * @param string $name        Name of the file.
	 * @param int    $size        Size of the file.
	 * @param int    $createdTime Unix time the file was created.
	 * @param int    $hasPassword Does it have a password (see Releases class constants)?
	 *
	 * @return mixed
	 */
	public function add($id, $name, $size, $createdTime, $hasPassword)
	{
		$insert = 0;

		$duplicateCheck = $this->pdo->queryOneRow(
			sprintf('
				SELECT id
				FROM releasefiles
				WHERE releaseid = %d AND name = %s',
				$id,
				$this->pdo->escapeString(utf8_encode($name))
			)
		);

		if ($duplicateCheck === false) {
			$insert = $this->pdo->queryInsert(
				sprintf("
						INSERT INTO releasefiles
						(releaseid, name, size, createddate, passworded)
						VALUES
						(%d, %s, %s, %s, %d)",
					$id,
					$this->pdo->escapeString(utf8_encode($name)),
					$this->pdo->escapeString($size),
					$this->pdo->from_unixtime($createdTime),
					$hasPassword
				)
			);
		}
		return $insert;
	}
}