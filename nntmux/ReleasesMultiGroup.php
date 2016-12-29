<?php

namespace nntmux;

use app\models\ReleasesGroups;
use app\models\Settings;
use nntmux\processing\ProcessReleases;


class ReleasesMultiGroup extends ProcessReleases
{

	/**
	 * @var array of MGR groups
	 */
	public static $mgrGroups = [
		 'alt.binaries.amazing',
		 'alt.binaries.ath',
		 'alt.binaries.bloaf',
		 'alt.binaries.british.drama',
		 'alt.binaries.chello',
		 'alt.binaries.etc',
		 'alt.binaries.font',
		 'alt.binaries.misc',
		 'alt.binaries.tatu'
	];

	/**
	 * @var array of MGR posters
	 */
	public static $mgrPosterNames = [
		'mmmq@meh.com',
		'buymore@suprnova.com',
		'pfc@p0rnFuscated.com',
		'mq@meh.com'
	];

	/**
	 * @var
	 */
	protected $mgrFromNames;

	/**
	 * @var NZBMultiGroup
	 */
	protected $mgrnzb;


	/**
	 * ReleasesMultiGroup constructor.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = [])
	{
		parent::__construct();

		$this->mgrFromNames = implode(",", self::$mgrPosterNames);
		$this->mgrnzb = new NZBMultiGroup();
	}

	/**
	 * @param $fromName
	 *
	 * @return bool
	 */
	public static function isMultiGroup($fromName)
	{
		return in_array($fromName, self::$mgrPosterNames);
	}

	/**
	 * Create releases from complete collections.
	 *
	 *
	 * @return array
	 * @access public
	 */
	public function createMGRReleases()
	{
		$startTime = time();
		$group = $this->groups->getCBPTableNames($this->tablePerGroup, '', true);

		$categorize = new Categorize(['Settings' => $this->pdo]);
		$returnCount = $duplicate = 0;

		if ($this->echoCLI) {
			$this->pdo->log->doEcho($this->pdo->log->header("Process Releases -> Create releases from complete collections."));
		}

		$this->pdo->ping(true);

		$collections = $this->pdo->queryDirect(
			sprintf('
				SELECT SQL_NO_CACHE %s.*, groups.name AS gname
				FROM %s
				INNER JOIN groups ON %s.group_id = groups.id
				WHERE %s.filecheck = %d
				AND filesize > 0 LIMIT %d',
				$group['mgrcname'],
				$group['mgrcname'],
				$group['mgrcname'],
				$group['mgrcname'],
				ProcessReleases::COLLFC_SIZED,
				$this->releaseCreationLimit
			)
		);

		if ($this->echoCLI && $collections !== false) {
			echo $this->pdo->log->primary($collections->rowCount() . " Collections ready to be converted to releases.");
		}

		if ($collections instanceof \Traversable) {
			$preDB = new PreDb(['Echo' => $this->echoCLI, 'Settings' => $this->pdo]);

			foreach ($collections as $collection) {

				$cleanRelName = $this->pdo->escapeString(
					utf8_encode(
						str_replace(['#', '@', '$', '%', '^', '§', '¨', '©', 'Ö'], '', $collection['subject'])
					)
				);
				$fromName = $this->pdo->escapeString(
					utf8_encode(trim($collection['fromname'], "'"))
				);

				// Look for duplicates, duplicates match on releases.name, releases.fromname and releases.size
				// A 1% variance in size is considered the same size when the subject and poster are the same
				$dupeCheck = $this->pdo->queryOneRow(
					sprintf("
						SELECT SQL_NO_CACHE id
						FROM releases
						WHERE name = %s
						AND fromname = %s
						AND size BETWEEN '%s'
						AND '%s'",
						$cleanRelName,
						$fromName,
						($collection['filesize'] * .99),
						($collection['filesize'] * 1.01)
					)
				);

				if ($dupeCheck === false) {

					$cleanedName = $this->releaseCleaning->releaseCleaner(
						$collection['subject'], $collection['fromname'], $collection['filesize'], $collection['gname']
					);

					if (is_array($cleanedName)) {
						$properName = $cleanedName['properlynamed'];
						$preID = (isset($cleanerName['predb']) ? $cleanerName['predb'] : false);
						$isReqID = (isset($cleanerName['requestid']) ? $cleanerName['requestid'] : false);
						$cleanedName = $cleanedName['cleansubject'];
					} else {
						$properName = true;
						$isReqID = $preID = false;
					}

					if ($preID === false && $cleanedName !== '') {
						// try to match the cleaned searchname to predb title or filename here
						$preMatch = $preDB->matchPre($cleanedName);
						if ($preMatch !== false) {
							$cleanedName = $preMatch['title'];
							$preID = $preMatch['predb_id'];
							$properName = true;
						}
					}

					$releaseID = $this->releases->insertRelease(
						[
							'name' => $cleanRelName,
							'searchname' => $this->pdo->escapeString(utf8_encode($cleanedName)),
							'totalpart' => $collection['totalfiles'],
							'groups_id' => $collection['group_id'],
							'guid' => $this->pdo->escapeString($this->releases->createGUID()),
							'postdate' => $this->pdo->escapeString($collection['date']),
							'fromname' => $fromName,
							'size' => $collection['filesize'],
							'categories_id' => $categorize->determineCategory($collection['group_id'], $cleanedName, $fromName),
							'isrenamed' => ($properName === true ? 1 : 0),
							'reqidstatus' => ($isReqID === true ? 1 : 0),
							'predb_id' => ($preID === false ? 0 : $preID),
							'nzbstatus' => NZB::NZB_NONE
						]
					);

					if ($releaseID !== false) {
						// Update collections table to say we inserted the release.
						$this->pdo->queryExec(
							sprintf('
								UPDATE %s
								SET filecheck = %d, releaseid = %d
								WHERE id = %d',
								$group['mgrcname'],
								ProcessReleases::COLLFC_INSERTED,
								$releaseID,
								$collection['id']
							)
						);

						if (preg_match_all('#(\S+):\S+#', $collection['xref'], $matches)) {
							foreach ($matches[1] as $grp) {
								//check if the group name is in a valid format
								$grpTmp = $this->groups->isValidGroup($grp);
								if ($grpTmp !== false) {
									//check if the group already exists in database
									$xrefGrpID = $this->groups->getIDByName($grpTmp);
									if ($xrefGrpID === '') {
										$xrefGrpID = $this->groups->add(
											[
												'name'                  => $grpTmp,
												'description'           => 'Added by Release processing',
												'backfill_target'       => 1,
												'first_record'          => 0,
												'last_record'           => 0,
												'active'                => 0,
												'backfill'              => 0,
												'minfilestoformrelease' => '',
												'minsizetoformrelease'  => ''
											]
										);
									}

									$relGroups = ReleasesGroups::create(
										[
											'releases_id' => $releaseID,
											'groups_id'   => $xrefGrpID,
										]
									);
									$relGroups->save();
								}
							}
						}

						$returnCount++;

						if ($this->echoCLI) {
							echo "Added $returnCount releases.\r";
						}
					}
				} else {
					// The release was already in the DB, so delete the collection.
					$this->pdo->queryExec(
						sprintf('
							DELETE c, b, p FROM %s c
							INNER JOIN %s b ON(c.id=b.collection_id)
							STRAIGHT_JOIN %s p ON(b.id=p.binaryid)
							WHERE c.collectionhash = %s',
							$group['mgrcname'], $group['mgrbname'], $group['mgrpname'],
							$this->pdo->escapeString($collection['collectionhash'])
						)
					);
					$duplicate++;
				}
			}
		}

		if ($this->echoCLI) {
			$this->pdo->log->doEcho(
				$this->pdo->log->primary(
					PHP_EOL .
					number_format($returnCount) .
					' Releases added and ' .
					number_format($duplicate) .
					' duplicate collections deleted in ' .
					$this->consoleTools->convertTime(time() - $startTime)
				), true
			);
		}

		return ['added' => $returnCount, 'dupes' => $duplicate];
	}

	/**
	 * Create NZB files from complete releases.
	 *
	 *
	 * @return int
	 * @access public
	 */
	public function createMGRNZBs()
	{

		$releases = $this->pdo->queryDirect(
			sprintf("
				SELECT SQL_NO_CACHE CONCAT(COALESCE(cp.title,'') , CASE WHEN cp.title IS NULL THEN '' ELSE ' > ' END , c.title) AS title,
					r.name, r.id, r.guid
				FROM releases r
				INNER JOIN categories c ON r.categories_id = c.id
				INNER JOIN categories cp ON cp.id = c.parentid
				WHERE r.nzbstatus = 0 AND r.fromname IN (%s)", $this->pdo->escapeString($this->mgrFromNames)
			)
		);

		$nzbCount = 0;

		if ($releases && $releases->rowCount()) {
			$total = $releases->rowCount();
			// Init vars for writing the NZB's.
			$this->mgrnzb->initiateForMgrWrite();
			foreach ($releases as $release) {

				if ($this->mgrnzb->writeMgrNZBforReleaseId($release['id'], $release['guid'], $release['name'], $release['title']) === true) {
					$nzbCount++;
					if ($this->echoCLI) {
						echo $this->pdo->log->primaryOver("Creating NZBs and deleting Collections:\t" . $nzbCount . '/' . $total . "\r");
					}
				}
			}
		}

		return $nzbCount;
	}

	/**
	 * Delete MGR collections (complete/incomplete/old/etc).
	 *
	 * @void
	 * @access public
	 */
	public function deleteMGRCollections()
	{
		$startTime = time();
		$group = $this->groups->getCBPTableNames($this->tablePerGroup, '', true);

		$deletedCount = 0;

		// CBP older than retention.
		if ($this->echoCLI) {
			echo (
				$this->pdo->log->header("Process Releases -> Delete finished MGR collections." . PHP_EOL) .
				$this->pdo->log->primary(sprintf(
					'Deleting collections/binaries/parts older than %d hours.',
					Settings::value('..partretentionhours')
				))
			);
		}

		$deleted = 0;
		$deleteQuery = $this->pdo->queryExec(
			sprintf(
				'DELETE c, b, p FROM %s c
				LEFT JOIN %s b ON (c.id=b.collection_id)
				LEFT JOIN %s p ON (b.id=p.binaryid)
				WHERE (c.dateadded < NOW() - INTERVAL %d HOUR)',
				$group['mgrcname'],
				$group['mgrbname'],
				$group['mgrpname'],
				Settings::value('..partretentionhours')
			)
		);

		if ($deleteQuery !== false) {
			$deleted = $deleteQuery->rowCount();
			$deletedCount += $deleted;
		}

		$firstQuery = $fourthQuery = time();

		if ($this->echoCLI) {
			echo $this->pdo->log->primary(
				'Finished deleting ' . $deleted . ' old collections/binaries/parts in ' .
				($firstQuery - $startTime) . ' seconds.' . PHP_EOL
			);
		}

		// Cleanup orphaned collections, binaries and parts
		// this really shouldn't happen, but just incase - so we only run 1/200 of the time
		if (mt_rand(0, 200) <= 1 ) {
			// CBP collection orphaned with no binaries or parts.
			if ($this->echoCLI) {
				echo (
					$this->pdo->log->header("Process Releases -> Remove CBP orphans." . PHP_EOL) .
					$this->pdo->log->primary('Deleting orphaned MGR collections.')
				);
			}

			$deleted = 0;
			$deleteQuery = $this->pdo->queryExec(
				sprintf(
					'DELETE c, b, p FROM %s c
					LEFT JOIN %s b ON (c.id=b.collection_id)
					LEFT JOIN %s p ON (b.id=p.binaryid)
					WHERE (b.id IS NULL OR p.binaryid IS NULL)',
					$group['mgrcname'],
					$group['mgrbname'],
					$group['mgrpname']
				)
			);

			if ($deleteQuery !== false) {
				$deleted = $deleteQuery->rowCount();
				$deletedCount += $deleted;
			}

			$secondQuery = time();

			if ($this->echoCLI) {
				echo $this->pdo->log->primary(
					'Finished deleting ' . $deleted . ' orphaned MGR collections in ' .
					($secondQuery - $firstQuery) . ' seconds.' . PHP_EOL
				);
			}

			// orphaned binaries - binaries with no parts or binaries with no collection
			// Don't delete currently inserting binaries by checking the max id.
			if ($this->echoCLI) {
				echo $this->pdo->log->primary('Deleting orphaned binaries/parts with no collection.');
			}

			$deleted = 0;
			$deleteQuery = $this->pdo->queryExec(
				sprintf(
					'DELETE b, p FROM %s b
									LEFT JOIN %s p ON(b.id=p.binaryid)
									LEFT JOIN %s c ON(b.collection_id=c.id)
									WHERE (p.binaryid IS NULL OR c.id IS NULL) AND b.id < %d ',
					$group['mgrbname'], $group['mgrpname'], $group['mgrcname'], $this->maxQueryFormulator($group['mgrbname'], 20000)
				)
			);

			if ($deleteQuery !== false) {
				$deleted = $deleteQuery->rowCount();
				$deletedCount += $deleted;
			}

			$thirdQuery = time();

			if ($this->echoCLI) {
				echo $this->pdo->log->primary(
					'Finished deleting ' . $deleted . ' binaries with no collections or parts in ' .
					($thirdQuery - $secondQuery) . ' seconds.'
				);
			}

			// orphaned parts - parts with no binary
			// Don't delete currently inserting parts by checking the max id.
			if ($this->echoCLI) {
				echo $this->pdo->log->primary('Deleting orphaned parts with no binaries.');
			}
			$deleted = 0;
			$deleteQuery = $this->pdo->queryExec(
				sprintf(
					'DELETE p FROM %s p LEFT JOIN %s b ON (p.binaryid=b.id) WHERE b.id IS NULL AND p.binaryid < %d',
					$group['mgrpname'], $group['mgrbname'], $this->maxQueryFormulator($group['mgrbname'], 20000)
				)
			);
			if ($deleteQuery !== false) {
				$deleted = $deleteQuery->rowCount();
				$deletedCount += $deleted;
			}

			$fourthQuery = time();

			if ($this->echoCLI) {
				echo $this->pdo->log->primary(
					'Finished deleting ' . $deleted . ' parts with no binaries in ' .
					($fourthQuery - $thirdQuery) . ' seconds.' . PHP_EOL
				);
			}
		} // done cleaning up Binaries/Parts orphans

		if ($this->echoCLI) {
			echo $this->pdo->log->primary(
				'Deleting collections that were missed after NZB creation.'
			);
		}

		$deleted = 0;
		// Collections that were missing on NZB creation.
		$collections = $this->pdo->queryDirect(
			sprintf('
				SELECT SQL_NO_CACHE c.id
				FROM %s c
				INNER JOIN releases r ON r.id = c.releaseid
				WHERE r.nzbstatus = 1',
				$group['mgrcname']
			)
		);

		if ($collections instanceof \Traversable) {
			foreach ($collections as $collection) {
				$deleted++;
				$this->pdo->queryExec(
					sprintf('
						DELETE c, b, p
						FROM %s c
						LEFT JOIN %s b ON(c.id=b.collection_id)
						LEFT JOIN %s p ON(b.id=p.binaryid)
						WHERE c.id = %d',
						$group['mgrcname'],
						$group['mgrbname'],
						$group['mgrpname'],
						$collection['id']
					)
				);
			}
			$deletedCount += $deleted;
		}

		if ($this->echoCLI) {
			$this->pdo->log->doEcho(
				$this->pdo->log->primary(
					'Finished deleting ' . $deleted . ' MGR collections missed after NZB creation in ' .
					(time() - $fourthQuery) . ' seconds.' . PHP_EOL .
					'Removed ' .
					number_format($deletedCount) .
					' parts/binaries/collection rows in ' .
					$this->consoleTools->convertTime(($fourthQuery - $startTime)) . PHP_EOL
				)
			);
		}
	}
}
