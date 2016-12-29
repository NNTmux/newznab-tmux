<?php

namespace nntmux;

use app\models\ReleasesGroups;
use app\models\Settings;
use nntmux\db\DB;
use nntmux\processing\ProcessReleases;


class ReleasesMultiGroup
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
	 * ReleasesMultiGroup constructor.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'ColorCLI'            => null,
			'Groups'              => null,
			'Settings'            => null,
		];
		$options += $defaults;

		$this->_pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
		$this->_groups = ($options['Groups'] instanceof Groups ? $options['Groups'] : new Groups(['Settings' => $this->_pdo]));
		$this->_colorCLI = ($options['ColorCLI'] instanceof ColorCLI ? $options['ColorCLI'] : new ColorCLI());
		$this->echoCLI = NN_ECHOCLI;
		$this->releases = new Releases(['Settings' => $this->_pdo, 'Groups' => $this->_groups]);
		$this->consoleTools = new ConsoleTools(['ColorCLI' => $this->_pdo->log]);
		$this->nzb = new NZB($this->_pdo);
		$this->releaseCleaning = new ReleaseCleaning($this->_pdo);
		$this->tablePerGroup = (Settings::value('..tablepergroup') == 0 ? false : true);
		$this->releaseCreationLimit = (Settings::value('..maxnzbsprocessed') != '' ? (int)Settings::value('..maxnzbsprocessed') : 1000);
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
		$group = $this->_groups->getCBPTableNames($this->tablePerGroup, '', true);

		$categorize = new Categorize(['Settings' => $this->_pdo]);
		$returnCount = $duplicate = 0;

		if ($this->echoCLI) {
			$this->_pdo->log->doEcho($this->_pdo->log->header("Process Releases -> Create releases from complete collections."));
		}

		$this->_pdo->ping(true);

		$collections = $this->_pdo->queryDirect(
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
			echo $this->_pdo->log->primary($collections->rowCount() . " Collections ready to be converted to releases.");
		}

		if ($collections instanceof \Traversable) {
			$preDB = new PreDb(['Echo' => $this->echoCLI, 'Settings' => $this->_pdo]);

			foreach ($collections as $collection) {

				$cleanRelName = $this->_pdo->escapeString(
					utf8_encode(
						str_replace(['#', '@', '$', '%', '^', '§', '¨', '©', 'Ö'], '', $collection['subject'])
					)
				);
				$fromName = $this->_pdo->escapeString(
					utf8_encode(trim($collection['fromname'], "'"))
				);

				// Look for duplicates, duplicates match on releases.name, releases.fromname and releases.size
				// A 1% variance in size is considered the same size when the subject and poster are the same
				$dupeCheck = $this->_pdo->queryOneRow(
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
							'searchname' => $this->_pdo->escapeString(utf8_encode($cleanedName)),
							'totalpart' => $collection['totalfiles'],
							'groups_id' => $collection['group_id'],
							'guid' => $this->_pdo->escapeString($this->releases->createGUID()),
							'postdate' => $this->_pdo->escapeString($collection['date']),
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
						$this->_pdo->queryExec(
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
								$grpTmp = $this->_groups->isValidGroup($grp);
								if ($grpTmp !== false) {
									//check if the group already exists in database
									$xrefGrpID = $this->_groups->getIDByName($grpTmp);
									if ($xrefGrpID === '') {
										$xrefGrpID = $this->_groups->add(
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
					$this->_pdo->queryExec(
						sprintf('
							DELETE c, b, p FROM %s c
							INNER JOIN %s b ON(c.id=b.collection_id)
							STRAIGHT_JOIN %s p ON(b.id=p.binaryid)
							WHERE c.collectionhash = %s',
							$group['mgrcname'], $group['mgrbname'], $group['mgrpname'],
							$this->_pdo->escapeString($collection['collectionhash'])
						)
					);
					$duplicate++;
				}
			}
		}

		if ($this->echoCLI) {
			$this->_pdo->log->doEcho(
				$this->_pdo->log->primary(
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
	public function createNZBs()
	{

		$releases = $this->_pdo->queryDirect(
			sprintf("
				SELECT SQL_NO_CACHE CONCAT(COALESCE(cp.title,'') , CASE WHEN cp.title IS NULL THEN '' ELSE ' > ' END , c.title) AS title,
					r.name, r.id, r.guid
				FROM releases r
				INNER JOIN categories c ON r.categories_id = c.id
				INNER JOIN categories cp ON cp.id = c.parentid
				WHERE nzbstatus = 0"
			)
		);

		$nzbCount = 0;

		if ($releases && $releases->rowCount()) {
			$total = $releases->rowCount();
			// Init vars for writing the NZB's.
			$this->nzb->initiateForWrite('', true);
			foreach ($releases as $release) {

				if ($this->nzb->writeNZBforReleaseId($release['id'], $release['guid'], $release['name'], $release['title'], true) === true) {
					$nzbCount++;
					if ($this->echoCLI) {
						echo $this->_pdo->log->primaryOver("Creating NZBs and deleting Collections:\t" . $nzbCount . '/' . $total . "\r");
					}
				}
			}
		}

		return $nzbCount;
	}
}
