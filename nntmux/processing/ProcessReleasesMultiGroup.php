<?php

namespace nntmux\processing;

use app\models\MultigroupPosters;
use nntmux\NZBMultiGroup;
use nntmux\utility\Utility;


class ProcessReleasesMultiGroup extends ProcessReleases
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
	 * ProcessReleasesMultiGroup constructor.
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
	public static function isMultiGroup($fromName)
	{
		$poster = MultigroupPosters::find('first', ['conditions' => ['poster' => $fromName]]);
		return (empty($poster) ? false : true);
	}


	protected function initiateMgrTableNames()
	{
		$group = [
			'cname' => 'multigroup_collections',
			'bname' => 'multigroup_binaries',
			'pname' => 'multigroup_parts'
		];

		return $group;
	}

	/**
	 * Process incomplete MultiGroup Releases
	 *
	 * @param $groupID
	 */
	public function processIncompleteMgrCollections($groupID)
	{
		$tableNames = $this->initiateMgrTableNames();
		$this->processIncompleteCollectionsMain($groupID, $tableNames);
	}

	/**
	 * Process MultiGroup collection sizes
	 *
	 * @param $groupID
	 */
	public function processMgrCollectionSizes($groupID)
	{
		$tableNames = $this->initiateMgrTableNames();
		$this->processCollectionSizesMain($groupID, $tableNames);
	}

	/**
	 * Delete unwanted MultiGroup collections
	 *
	 * @param $groupID
	 */
	public function deleteUnwantedMgrCollections($groupID)
	{
		$tableNames = $this->initiateMgrTableNames();
		$this->deleteUnwantedCollectionsMain($groupID, $tableNames);
	}

	public function deleteMgrCollections($groupID)
	{
		$tableNames = $this->initiateMgrTableNames();
		$this->deleteCollectionsMain($groupID, $tableNames);
	}

	/**
	 * Create releases from complete MultiGroup collections.
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
	 * Create NZB files from complete MultiGroup releases.
	 *
	 *
	 * @param $groupID
	 *
	 * @return int
	 * @access public
	 */
	public function createMGRNZBs($groupID)
	{
		$list = [];
		$posters = MultigroupPosters::find('all',
			[
				'fields' => ['poster'],
				'order'  => ['poster' => 'ASC'],
			]
		);

		foreach ($posters as $poster) {
			$list[] = $poster->poster;
		}

		$this->mgrFromNames = implode("','", $list);

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
}
