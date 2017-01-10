<?php

namespace nntmux\processing;

use nntmux\NZBMultiGroup;
use nntmux\utility\Utility;


class ProcessMultiGroupReleases extends ProcessReleases
{
	/**
	 * @var
	 */
	protected $mgrFromNames;

	/**
	 * @var NZBMultiGroup
	 */
	protected $mgrnzb;


	/**
	 * ProcessMultiGroupReleases constructor.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = [])
	{
		parent::__construct($options);
		$this->mgrnzb = new NZBMultiGroup($this->pdo);
	}

	/**
	 * @param $fromName
	 *
	 * @return bool
	 */
	public function isMultiGroup($fromName)
	{
		$array = array_column($this->getAllPosters(), 'poster');
		return in_array($fromName, $array);
	}


	protected function initiateMgrTableNames()
	{
		$group = [
			'cname' => 'mgr_collections',
			'bname' => 'mgr_binaries',
			'pname' => 'mgr_parts'
		];

		return $group;
	}

	/**
	 * Create releases from complete collections.
	 *
	 * @param $groupID
	 *
	 * @return array
	 * @access public
	 */
	public function createMGRReleases($groupID)
	{
		$tableNames = $this->initiateMgrTableNames();
		return $this->createReleasesMain($groupID, $tableNames);
	}

	/**
	 * Create NZB files from complete releases.
	 *
	 *
	 * @param $groupID
	 *
	 * @return int
	 * @access public
	 */
	public function createMGRNZBs($groupID)
	{
		$this->mgrFromNames = Utility::convertMultiArray($this->getAllPosters(), "','");

		$releases = $this->pdo->queryDirect(
			sprintf("
				SELECT SQL_NO_CACHE CONCAT(COALESCE(cp.title,'') , CASE WHEN cp.title IS NULL THEN '' ELSE ' > ' END , c.title) AS title,
					r.name, r.id, r.guid
				FROM releases r
				INNER JOIN categories c ON r.categories_id = c.id
				INNER JOIN categories cp ON cp.id = c.parentid
				WHERE %s r.nzbstatus = 0 AND r.fromname IN ('%s')",
				(!empty($groupID) ? ' r.groups_id = ' . $groupID . ' AND ' : ' '),
				$this->mgrFromNames
			)
		);

		$nzbCount = 0;

		if ($releases && $releases->rowCount()) {
			$total = $releases->rowCount();
			// Init vars for writing the NZB's.
			$this->mgrnzb->initiateForMgrWrite();
			foreach ($releases as $release) {

				if ($this->mgrnzb->writeNZBforReleaseId($release['id'], $release['guid'], $release['name'], $release['title']) === true) {
					$nzbCount++;
					if ($this->echoCLI) {
						echo $this->pdo->log->primaryOver("Creating NZBs and deleting MGR Collections:\t" . $nzbCount . '/' . $total . "\r");
					}
				}
			}
		}

		return $nzbCount;
	}

	/**
	 * Add multi group posters to database
	 *
	 * @param $poster
	 */
	public function addPoster($poster)
	{
		$this->pdo->queryInsert(sprintf('INSERT INTO mgr_posters (poster) VALUE (%s)', $this->pdo->escapeString($poster)));
	}

	/**
	 * Update multi group poster
	 *
	 * @param $id
	 * @param $poster
	 */
	public function updatePoster($id, $poster)
	{
		$this->pdo->queryExec(sprintf('UPDATE mgr_posters SET poster = %s WHERE id = %d', $this->pdo->escapeString($poster), $id));
	}

	/**
	 * Delete multi group posters from database
	 *
	 * @param $id
	 */
	public function deletePoster($id)
	{
		$this->pdo->queryExec(sprintf('DELETE FROM mgr_posters WHERE id = %d', $id));
	}

	/**
	 * Fetch all multi group posters from database
	 *
	 * @return array|bool
	 */
	public function getAllPosters()
	{
		$result = $this->pdo->query(sprintf('SELECT poster AS poster FROM mgr_posters'));
		if (is_array($result) && !empty($result)) {
			return $result;
		}
		return false;
	}
}
