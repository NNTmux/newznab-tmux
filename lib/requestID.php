<?php
require_once(dirname(__FILE__) . "/../bin/config.php");
require_once(WWW_DIR . "lib/framework/db.php");
require_once(WWW_DIR . "lib/category.php");
require_once(WWW_DIR . "lib/groups.php");
require_once(WWW_DIR . "lib/site.php");
require_once("functions.php");
require_once("ColorCLI.php");
require_once("consoletools.php");
require_once("namefixer.php");

//This script is adapted from nZEDB requestID.php

/**
 * Attempts to find a PRE name for a release using a request ID from our local pre database,
 * or internet request id database.
 *
 * Class RequestID
 */
class RequestID
{
	// Request ID.
	const REQID_NONE = -3; // The Request ID was not found locally or via web lookup.
	const REQID_ZERO = -2; // The Request ID was 0.
	const REQID_NOLL = -1; // Request ID was not found via local lookup.
	const REQID_UPROC = 0; // Release has not been processed.
	const REQID_FOUND = 1; // Request ID found and release was updated.

	/**
	 * @var bool Echo to CLI?
	 */
	protected $echoOutput;

	/**
	 * @var Category
	 */
	protected $category;

	/**
	 * @var DB
	 */
	protected $db;

	/**
	 * @var ConsoleTools
	 */
	protected $consoleTools;

	/**
	 * @var ColorCLI
	 */
	protected $colorCLI;

	/**
	 * @var int How many request id's did we find?
	 */
	protected $reqIDsFound = 0;

	/**
	 * @var bool Is this a local or web lookup ?
	 */
	protected $local = true;

	/**
	 * @var int What group are we working on ?
	 */
	protected $groupID = 0;

	/**
	 * @var array MySQL results for releases with RequestID's.
	 */
	protected $results = array();

	/**
	 * @var array Single MySQL result.
	 */
	protected $result = array();

	/**
	 * @var int How many releases max to do a web lookup.
	 */
	protected $limit = 100;

	/**
	 * @var bool The title found from a request ID lookup.
	 */
	protected $newTitle = false;

	/**
	 * @var int The found request ID for the release.
	 */
	protected $requestID = self::REQID_ZERO;

	/**
	 * The ID of the PRE entry the found request ID belongs to.
	 *
	 * @var bool|int
	 */
	protected $preDbID = false;

	/**
	 * @var bool|stdClass
	 */
	protected $site;

	/**
	 * Construct.
	 *
	 * @param bool $echoOutput
	 */
	public function __construct($echoOutput = false)
	{
		$this->echoOutput = $echoOutput;
		$this->category = new Categorize();
		$this->db = new DB();
		$this->consoleTools = new ConsoleTools();
		$this->colorCLI = new ColorCLI();
		$this->site = (new Sites)->get();
		$this->functions = new Functions();
	}

	/**
	 * Process RequestID's via Web or Local lookup.
	 *
	 * @param int  $groupID The ID of the group.
	 * @param int  $limit   How many requests to do.
	 * @param bool $local   Do a local or web lookup?
	 *
	 * @return int How many request ID's were found.
	 */
	public function lookupReqIDs($groupID, $limit, $local)
	{
		$this->groupID = $groupID;
		$this->limit = $limit;
		$this->local = $local;
		$this->reqIDsFound = 0;
		$this->getResults();

		if ($this->results !== false && $this->results->rowCount() > 0) {
			$this->findReqIdMatches();
		}

		return $this->reqIDsFound;
	}

	/**
	 * Get all results from the releases table that have request ID's to be processed.
	 */
	protected function getResults()
	{
		// Look for records that potentially have requestID titles and have not been matched to a PreDB title
		$this->results = $this->db->queryDirect(
			sprintf(
				'SELECT r.ID, r.name, r.searchname, g.name AS groupname, r.groupID
				FROM releases r
				LEFT JOIN groups g ON r.groupID = g.ID
				WHERE nzbstatus = 1
				AND prehashID = 0
				AND (isrequestid = 1 AND reqidstatus = %d
					OR (reqidstatus = %d AND adddate > NOW() - INTERVAL %d HOUR)
				)
				%s %s LIMIT %d',
				($this->local === true ? self::REQID_UPROC : self::REQID_NOLL),
				self::REQID_NONE,
				(isset($this->site->request_hours) ? (int)$this->site->request_hours : 1),
				(empty($this->groupID) ? '' : ('AND groupID = ' . $this->groupID)),
				($this->local === true ? '' : 'ORDER BY postdate DESC'),
				$this->limit
			)
		);
	}

	/**
	 * See if the release has a valid request ID, try to a PRE name locally or from the internet.
	 */
	protected function findReqIdMatches()
	{
		$this->newTitle = false;

		foreach ($this->results as $result) {
			$this->result = $result;

			$this->newTitle = false;

			// Try to get request id.
			if (preg_match('/\[\s*(\d+)\s*\]/', $this->result['name'], $requestID) ||
				preg_match('/^REQ\s*(\d{4,6})/i', $this->result['name'], $requestID) ||
				preg_match('/^(\d{4,6})-\d{1}\[/', $this->result['name'], $requestID) ||
				preg_match('/(\d{4,6}) -/', $this->result['name'], $requestID)
			) {
				$this->requestID = (int)$requestID[1];
			} else {
				$this->requestID = self::REQID_ZERO;
			}

			if ($this->requestID === self::REQID_ZERO) {
				$this->db->exec(
					sprintf('
						UPDATE releases
						SET reqidstatus = %d
						WHERE ID = %d',
						self::REQID_ZERO,
						$this->result['ID']
					)
				);
				if ($this->local === false && $this->echoOutput) {
					echo '-';
				}
			} else {

				if ($this->local === true) {
					$this->localCheck();
				} else {
					$this->remoteCheck();
				}

				if ($this->newTitle !== false) {
					if ($this->preDbID === false) {
						$this->insertIntoPreDB();
					}

					$this->updateRelease();
				} else {
					$this->db->exec(
						sprintf(
							'UPDATE releases SET reqidstatus = %d WHERE ID = %d',
							self::REQID_NONE,
							$this->result['ID']
						)
					);
					if ($this->local === false && $this->echoOutput) {
						echo '-';
					}
				}
			}
		}
	}

	/**
	 * Try to find a PRE name using the request ID in our local PRE database.
	 */
	protected function localCheck()
	{
		$localCheck = $this->db->queryOneRow(
			sprintf("
				SELECT ID, title
				FROM prehash
				WHERE requestID = %d
				AND groupID = %d",
				$this->requestID,
				$this->result['groupID']
			)
		);

		if ($localCheck !== false) {
			$this->newTitle = $localCheck['title'];
			$this->preDbID = $localCheck['ID'];
			$this->reqIDsFound++;
		}
	}

	/**
	 * Try to find a PRE name on the internet using the found request ID.
	 */
	protected function remoteCheck()
	{
		// Do a web lookup.
		$xml = $this->functions->getUrl(
			str_ireplace(
				'[REQUEST_ID]',
				$this->requestID,
				str_ireplace(
					'[GROUP_NM]',
					urlencode($this->result['groupname']),
					$this->site->request_url
				)
			)
		);

		if ($xml === false && preg_match('/alt\.binaries\.(etc|mom|\.hdtv.x264)/', $this->result['groupname'])) {
			$reqGname = 'alt.binaries.moovee';
			if ($this->result['groupname'] === 'alt.binaries.etc') {
				$reqGname = 'alt.binaries.teevee';
			}
			$xml = $this->functions->getUrl(
				str_ireplace(
					'[REQUEST_ID]',
					$this->requestID,
					str_ireplace(
						'[GROUP_NM]',
						urlencode($reqGname),
						$this->site->request_url
					)
				)
			);
		}

		if ($xml !== false) {
			$xml = simplexml_load_string($xml);
			if ($xml !== false &&
				isset($xml->request[0]['name']) && !empty($xml->request[0]['name']) &&
				strtolower($xml->request[0]['name']) !== strtolower($this->result['searchname'])
			) {

				$this->newTitle = $xml->request[0]['name'];
				$this->reqIDsFound++;
			}
		}
	}

	/**
	 * If we found a PRE name, update the releases name and reset post processing.
	 */
	protected function updateRelease()
	{
		$determinedCategory = $this->category->determineCategory($this->result['groupID'], $this->newTitle);
		$this->db->exec(
			sprintf('
				UPDATE releases
				SET rageid = -1, seriesfull = NULL, season = NULL, episode = NULL, tvtitle = NULL,
				tvairdate = NULL, imdbID = NULL, musicinfoID = NULL, consoleinfoID = NULL, bookinfoID = NULL, anidbID = NULL,
				reqidstatus = %d, isrenamed = 1, proc_files = 1, searchname = %s, categoryiID = %d,
				prehashID = %d
				WHERE ID = %d',
				self::REQID_FOUND,
				$this->db->escapeString($this->newTitle),
				$determinedCategory,
				$this->preDbID,
				$this->result['ID']
			)
		);

		if ($this->echoOutput) {
			NameFixer::echoChangedReleaseName(array(
					'new_name'     => $this->newTitle,
					'old_name'     => $this->result['searchname'],
					'new_category' => $this->functions->getNameByID($determinedCategory),
					'old_category' => '',
					'group'        => $this->result['groupname'],
					'release_id'   => $this->result['ID'],
					'method'       => 'RequestID->updateRelease<' . ($this->local ? 'local' : 'web') . '>'
				)
			);
		}
	}

	/**
	 * If we found a request ID on the internet, check if our PRE database has it, insert it if not.
	 */
	protected function insertIntoPreDB()
	{
		$dupeCheck = $this->db->queryOneRow(
			sprintf('
				SELECT ID AS prehashID, requestID, groupID
				FROM prehash
				WHERE title = %s',
				$this->db->escapeString($this->newTitle)
			)
		);

		if ($dupeCheck === false) {
			$this->preDbID = $this->db->queryInsert(
				sprintf("
					INSERT INTO prehash (title, source, requestID, groupID, predate)
					VALUES (%s, %s, %d, %d, NOW())",
					$this->db->escapeString($this->newTitle),
					$this->db->escapeString('requestWEB'),
					$this->requestID,
					$this->result['groupID']
				)
			);
		} else {
			$this->preDbID = $dupeCheck['prehashID'];
			$this->db->exec(
				sprintf('
					UPDATE prehash
					SET requestID = %d, groupID = %d
					WHERE ID = %d',
					$this->requestID,
					$this->result['groupID'],
					$this->preDbID
				)
			);
		}
	}
}