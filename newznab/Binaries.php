<?php
namespace newznab;

use newznab\db\Settings;

/**
 * This class manages the downloading of binaries and parts from usenet, and the
 * managing of data in the binaries and parts tables.
 *
*/
class Binaries
{
	const OPTYPE_BLACKLIST = 1;
	const OPTYPE_WHITELIST = 2;

	const BLACKLIST_DISABLED = 0;
	const BLACKLIST_ENABLED = 1;

	const BLACKLIST_FIELD_SUBJECT = 1;
	const BLACKLIST_FIELD_FROM = 2;
	const BLACKLIST_FIELD_MESSAGEID = 3;

	/**
	 * Cache of black list regexes.
	 *
	 * @var array
	 */
	public $blackList = [];

	/**
	 * Cache of white list regexes.
	 * @var array
	 */
	public $whiteList = [];

	/**
	 * How many headers do we download per loop?
	 *
	 * @var int
	 */
	public $messageBuffer;

	/**
	 * @var ColorCLI
	 */
	protected $_colorCLI;

	/**
	 * @var Logger
	 */
	protected $_debugging;

	/**
	 * @var Groups
	 */
	protected $_groups;

	/**
	 * @var NNTP
	 */
	protected $_nntp;

	/**
	 * Should we use part repair?
	 *
	 * @var bool
	 */
	protected $_partRepair;

	/**
	 * @var \newznab\db\Settings
	 */
	protected $_pdo;

	/**
	 * How many days to go back on a new group?
	 *
	 * @var bool
	 */
	protected $_newGroupScanByDays;

	/**
	 * How many headers to download on new groups?
	 *
	 * @var int
	 */
	protected $_newGroupMessagesToScan;

	/**
	 * How many days to go back on new groups?
	 *
	 * @var int
	 */
	protected $_newGroupDaysToScan;

	/**
	 * How many headers to download per run of part repair?
	 *
	 * @var int
	 */
	protected $_partRepairLimit;

	/**
	 * Should we use table per group?
	 *
	 * @var bool
	 */
	protected $_tablePerGroup;

	/**
	 * Echo to cli?
	 *
	 * @var bool
	 */
	protected $_echoCLI;

	/**
	 * @var bool
	 */
	protected $_debug = false;

	/**
	 * Max tries to download headers.
	 * @var int
	 */
	protected $_partRepairMaxTries;

	/**
	 * An array of binaryblacklist IDs that should have their activity date updated
	 * @var array(int)
	 */
	protected $_binaryBlacklistIdsToUpdate = [];

	/**
	 * Constructor.
	 *
	 * @param array $options Class instances / echo to CLI?
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Echo'                => true,
			'ColorCLI'            => null,
			'Logger'              => null,
			'Groups'              => null,
			'NNTP'                => null,
			'Settings'            => null,
		];
		$options += $defaults;

		$this->_colorCLI = ($options['ColorCLI'] instanceof \ColorCLI ? $options['ColorCLI'] : new \ColorCLI());
		$this->_echoCLI = ($options['Echo'] && NN_ECHOCLI);
		$this->_pdo = ($options['Settings'] instanceof Settings ? $options['Settings'] : new Settings());
		$this->_nntp = ($options['NNTP'] instanceof \NNTP ? $options['NNTP'] : new \NNTP(['Echo' => $this->_colorCLI, 'Settings' => $this->_pdo, 'ColorCLI' => $this->_colorCLI]));
		$this->_groups = ($options['Groups'] instanceof \Groups ? $options['Groups'] : new \Groups(['Settings' => $this->_pdo]));

		$this->_debug = (NN_DEBUG || NN_LOGGING);
		if ($this->_debug) {
			try {
				$this->_debugging = new \Logger(['ColorCLI' => $this->_colorCLI]);
			} catch (\LoggerException $error) {
				$this->_debug = false;
			}
		}

		$this->n = "\n";

		$this->messageBuffer = ($this->_pdo->getSetting('maxmssgs') != '') ? $this->_pdo->getSetting('maxmssgs') : 20000;
		$this->_compressedHeaders = ($this->_pdo->getSetting('compressedheaders') == "1") ? true : false;
		$this->MaxMsgsPerRun = (!empty($this->_pdo->getSetting('maxmsgsperrun'))) ? $this->_pdo->getSetting('maxmsgsperrun') : 200000;
		$this->_newGroupScanByDays = ($this->_pdo->getSetting('newsgroupscanmethod') == "1") ? true : false;
		$this->_newGroupMessagesToScan = (!empty($this->_pdo->getSetting('newgroupmsgstoscan'))) ? $this->_pdo->getSetting('newgroupmsgstoscan') : 50000;
		$this->_newGroupDaysToScan = (!empty($this->_pdo->getSetting('newgroupdaystoscan'))) ? $this->_pdo->getSetting('newgroupdaystoscan') : 3;
		$this->_tablePerGroup = ($this->_pdo->getSetting('tablepergroup') == 1 ? true : false);
		$this->_partRepair = ($this->_pdo->getSetting('partrepair') == 0 ? false : true);
		$this->_partRepairLimit = ($this->_pdo->getSetting('maxpartrepair') != '') ? (int)$this->_pdo->getSetting('maxpartrepair') : 15000;
		$this->_partRepairMaxTries = ($this->_pdo->getSetting('partrepairmaxtries') != '' ? (int)$this->_pdo->getSetting('partrepairmaxtries') : 3);

		$this->blackList = array(); //cache of our black/white list
		$this->blackList_by_group = array();
		$this->message = array();
		$this->startUpdate = microtime(true);
		$this->startLoop = microtime(true);
		$this->startHeaders = microtime(true);

		$this->onlyProcessRegexBinaries = false;

		$this->blackList = $this->whiteList = [];
	}

	/**
	 * Download new headers for all active groups.
	 *
	 * @param int $maxHeaders (Optional) How many headers to download max.
	 *
	 * @return void
	 */
	public function updateAllGroups($maxHeaders = 0)
	{
		$groups = $this->_groups->getActive();

		$groupCount = count($groups);
		if ($groupCount > 0) {
			$counter = 1;
			$allTime = microtime(true);

			$this->log(
				'Updating: ' . $groupCount . ' group(s) - Using compression? ' . ($this->_compressedHeaders ? 'Yes' : 'No'),
				'updateAllGroups',
				\Logger::LOG_INFO,
				'header'
			);

			// Loop through groups.
			foreach ($groups as $group) {
				$this->log(
					'Starting group ' . $counter . ' of ' . $groupCount,
					'updateAllGroups',
					\Logger::LOG_INFO,
					'header'
				);
				$this->updateGroup($group, $maxHeaders);
				$counter++;
			}

			$this->log(
				'Updating completed in ' . number_format(microtime(true) - $allTime, 2) . ' seconds.',
				'updateAllGroups',
				\Logger::LOG_INFO,
				'primary'
			);
		} else {
			$this->log(
				'No groups specified. Ensure groups are added to newznab\'s database for updating.',
				'updateAllGroups',
				\Logger::LOG_NOTICE,
				'warning'
			);
		}
	}

	/**
	 * Download new headers for a single group.
	 *
	 * @param array $groupMySQL Array of MySQL results for a single group.
	 * @param int   $maxHeaders (Optional) How many headers to download max.
	 *
	 * @return void
	 */
	public function updateGroup($groupMySQL, $maxHeaders = 0)
	{
		$startGroup = microtime(true);

		// Select the group on the NNTP server, gets the latest info on it.
		$groupNNTP = $this->_nntp->selectGroup($groupMySQL['name']);
		if ($this->_nntp->isError($groupNNTP)) {
			$groupNNTP = $this->_nntp->dataError($this->_nntp, $groupMySQL['name']);
			if ($groupNNTP->code == 411) {
				$this->_groups->disableIfNotExist($groupMySQL['id']);
			}
			if ($this->_nntp->isError($groupNNTP)) {
				return;
			}
		}

		if ($this->_echoCLI) {
			$this->_colorCLI->doEcho($this->_colorCLI->primary('Processing ' . $groupMySQL['name']), true);
		}
		if ($groupMySQL['regexmatchonly'] == 1)
		{
			$this->onlyProcessRegexBinaries = true;
			if ($this->_echoCLI) {
				$this->_colorCLI->doEcho($this->_colorCLI->primary('Note: Discarding parts that do not match a regex', true));
			}
		}
		else
		{
			$this->onlyProcessRegexBinaries = false;
		}

		// Attempt to repair any missing parts before grabbing new ones.
		if ($groupMySQL['last_record'] != 0) {
			if ($this->_partRepair) {
				if ($this->_echoCLI) {
					$this->_colorCLI->doEcho($this->_colorCLI->primary('Part repair enabled. Checking for missing parts.'), true);
				}
				$this->partRepair($groupMySQL);
			} else if ($this->_echoCLI) {
				$this->_colorCLI->doEcho($this->_colorCLI->primary('Part repair disabled by user.'), true);
			}
		}

		// Generate postdate for first record, for those that upgraded.
		if (is_null($groupMySQL['first_record_postdate']) && $groupMySQL['first_record'] != 0) {

			$groupMySQL['first_record_postdate'] = $this->postdate($groupMySQL['first_record'], $groupNNTP);

			$this->_pdo->queryExec(
				sprintf('
					UPDATE groups
					SET first_record_postdate = %s
					WHERE id = %d',
					$this->_pdo->from_unixtime($groupMySQL['first_record_postdate']),
					$groupMySQL['id']
				)
			);
		}

		// Get first article we want aka the oldest.
		if ($groupMySQL['last_record'] == 0) {
			if ($this->_newGroupScanByDays) {
				// For new newsgroups - determine here how far we want to go back using date.
				$first = $this->daytopost($this->_newGroupDaysToScan, $groupNNTP);
			} else if ($groupNNTP['first'] >= ($groupNNTP['last'] - ($this->_newGroupMessagesToScan + $this->messageBuffer))) {
				// If what we want is lower than the groups first article, set the wanted first to the first.
				$first = $groupNNTP['first'];
			} else {
				// Or else, use the newest article minus how much we should get for new groups.
				$first = (string)($groupNNTP['last'] - ($this->_newGroupMessagesToScan + $this->messageBuffer));
			}

			// We will use this to subtract so we leave articles for the next time (in case the server doesn't have them yet)
			$leaveOver = $this->messageBuffer;

			// If this is not a new group, go from our newest to the servers newest.
		} else {
			// Set our oldest wanted to our newest local article.
			$first = $groupMySQL['last_record'];

			// This is how many articles we will grab. (the servers newest minus our newest).
			$totalCount = (string)($groupNNTP['last'] - $first);

			// Check if the server has more articles than our loop limit x 2.
			if ($totalCount > ($this->messageBuffer * 2)) {
				// Get the remainder of $totalCount / $this->message buffer
				$leaveOver = round(($totalCount % $this->messageBuffer), 0, PHP_ROUND_HALF_DOWN) + $this->messageBuffer;
			} else {
				// Else get half of the available.
				$leaveOver = round(($totalCount / 2), 0, PHP_ROUND_HALF_DOWN);
			}
		}

		// The last article we want, aka the newest.
		$last = $groupLast = (string)($groupNNTP['last'] - $leaveOver);

		// If the newest we want is older than the oldest we want somehow.. set them equal.
		if ($last < $first) {
			$last = $groupLast = $first;
		}

		// This is how many articles we are going to get.
		$total = (string)($groupLast - $first);
		// This is how many articles are available (without $leaveOver).
		$realTotal = (string)($groupNNTP['last'] - $first);

		// Check if we should limit the amount of fetched new headers.
		if ($maxHeaders > 0) {
			if ($maxHeaders < ($groupLast - $first)) {
				$groupLast = $last = (string)($maxHeaders + $first);
			}
			$total = (string)($groupLast - $first);
		}

		// If total is bigger than 0 it means we have new parts in the newsgroup.
		if ($total > 0) {

			if ($this->_echoCLI) {
				$this->_colorCLI->doEcho(
					$this->_colorCLI->primary(
						($groupMySQL['last_record'] == 0
							? 'New group ' . $groupNNTP['group'] . ' starting with ' .
							($this->_newGroupScanByDays
								? $this->_newGroupDaysToScan . ' days'
								: number_format($this->_newGroupMessagesToScan) . ' messages'
							) . ' worth.'
							: 'Group ' . $groupNNTP['group'] . ' has ' . number_format($realTotal) . ' new articles.'
						) .
						' Leaving ' . number_format($leaveOver) .
						" for next pass.\nServer oldest: " . number_format($groupNNTP['first']) .
						' Server newest: ' . number_format($groupNNTP['last']) .
						' Local newest: ' . number_format($groupMySQL['last_record'])
					), true
				);
			}

			$done = false;
			// Get all the parts (in portions of $this->messageBuffer to not use too much memory).
			while ($done === false) {

				// Increment last until we reach $groupLast (group newest article).
				if ($total > $this->messageBuffer) {
					$last = (string)($first + $this->messageBuffer) > $groupLast ? $groupLast : (string)($first + $this->messageBuffer);
				}
				// Increment first so we don't get an article we already had.
				$first++;

				if ($this->_echoCLI) {
					$this->_colorCLI->doEcho(
						$this->_colorCLI->header(
							"\nGetting " . number_format($last - $first + 1) . ' articles (' . number_format($first) .
							' to ' . number_format($last) . ') from ' . $groupMySQL['name'] . " - (" .
							number_format($groupLast - $last) . " articles in queue)."
						)
					);
				}

				// Get article headers from newsgroup.
				$scanSummary = $this->scan($groupMySQL, $first, $last);

				// Check if we fetched headers.
				if (!empty($scanSummary)) {

					// If new group, update first record & postdate
					if (is_null($groupMySQL['first_record_postdate']) && $groupMySQL['first_record'] == 0) {
						$groupMySQL['first_record'] = $scanSummary['firstArticleNumber'];

						if (isset($scanSummary['firstArticleDate'])) {
							$groupMySQL['first_record_postdate'] = strtotime($scanSummary['firstArticleDate']);
						} else {
							$groupMySQL['first_record_postdate'] = $this->postdate($groupMySQL['first_record'], $groupNNTP);
						}

						$this->_pdo->queryExec(
							sprintf('
								UPDATE groups
								SET first_record = %s, first_record_postdate = %s
								WHERE id = %d',
								$scanSummary['firstArticleNumber'],
								$this->_pdo->from_unixtime($this->_pdo->escapeString($groupMySQL['first_record_postdate'])),
								$groupMySQL['id']
							)
						);
					}

					if (isset($scanSummary['lastArticleDate'])) {
						$scanSummary['lastArticleDate'] = strtotime($scanSummary['lastArticleDate']);
					} else {
						$scanSummary['lastArticleDate'] = $this->postdate($scanSummary['lastArticleNumber'], $groupNNTP);
					}

					$this->_pdo->queryExec(
						sprintf('
							UPDATE groups
							SET last_record = %s, last_record_postdate = %s, last_updated = NOW()
							WHERE id = %d',
							$this->_pdo->escapeString($scanSummary['lastArticleNumber']),
							$this->_pdo->from_unixtime($scanSummary['lastArticleDate']),
							$groupMySQL['id']
						)
					);
				} else {
					// If we didn't fetch headers, update the record still.
					$this->_pdo->queryExec(
						sprintf('
							UPDATE groups
							SET last_record = %s, last_updated = NOW()
							WHERE id = %d',
							$this->_pdo->escapeString($last),
							$groupMySQL['id']
						)
					);
				}

				if ($last == $groupLast) {
					$done = true;
				} else {
					$first = $last;
				}
			}

			if ($this->_echoCLI) {
				$this->_colorCLI->doEcho(
					$this->_colorCLI->primary(
						PHP_EOL . 'Group ' . $groupMySQL['name'] . ' processed in ' .
						number_format(microtime(true) - $startGroup, 2) . ' seconds.'
					), true
				);
			}
		} else if ($this->_echoCLI) {
			$this->_colorCLI->doEcho(
				$this->_colorCLI->primary(
					'No new articles for ' . $groupMySQL['name'] . ' (first ' . number_format($first) .
					', last ' . number_format($last) . ', grouplast ' . number_format($groupMySQL['last_record']) .
					', total ' . number_format($total) . ")\n" . 'Server oldest: ' . number_format($groupNNTP['first']) .
					' Server newest: ' . number_format($groupNNTP['last']) . ' Local newest: ' . number_format($groupMySQL['last_record'])
				), true
			);
		}
	}

	/**
	 * Download a range of usenet messages. Store binaries with subjects matching a
	 * specific pattern in the database.
	 *
	 * @param        $groupArr
	 * @param        $first
	 * @param        $last
	 * @param string $type
	 *
	 * @return array
	 */
	function scan($groupArr, $first, $last, $type = 'update')
	{
		$db = new Settings();
		$releaseRegex = new ReleaseRegex;
		$n = $this->n;
		// Check if MySQL tables exist, create if they do not, get their names at the same time.
		$tableNames = $this->_groups->getCBPTableNames($this->_tablePerGroup, $groupArr['id']);
		$partRepair = ($type === 'partrepair');
		$returnArray = [];

		// Download the headers.
		if ($partRepair === true) {
			// This is slower but possibly is better with missing headers.
			$msgs = $this->_nntp->getOverview($first . '-' . $last, true, false);
		} else {
			$msgs = $this->_nntp->getXOVER($first . '-' . $last);
		}

		// If there was an error, try to reconnect.
		if ($this->_nntp->isError($msgs)) {

			// Increment if part repair and return false.
			if ($partRepair === true) {
				$this->_pdo->queryExec(
					sprintf(
						'UPDATE %s SET attempts = attempts + 1 WHERE groupid = %d AND numberid %s',
						$tableNames['prname'],
						$groupArr['id'],
						($first == $last ? '= ' . $first : 'IN (' . implode(',', range($first, $last)) . ')')
					)
				);
				return $returnArray;
			}

			// This is usually a compression error, so try disabling compression.
			$this->_nntp->doQuit();
			if ($this->_nntp->doConnect(false) !== true) {
				return $returnArray;
			}

			// Re-select group, download headers again without compression and re-enable compression.
			$this->_nntp->selectGroup($groupArr['name']);
			$msgs = $this->_nntp->getXOVER($first . '-' . $last);
			$this->_nntp->enableCompression();

			// Check if the non-compression headers have an error.
			if ($this->_nntp->isError($msgs)) {
				$this->log(
					"Code {$msgs->code}: {$msgs->message}\nSkipping group: {$groupArr['name']}",
					'scan',
					\Logger::LOG_WARNING,
					'error'
				);
				return $returnArray;
			}
		}

		$rangerequested = range($first, $last);
		$msgsreceived = array();
		$msgsblacklisted = array();
		$msgsignored = array();
		$msgsinserted = array();
		$msgsnotinserted = array();

		$timeHeaders = number_format(microtime(true) - $this->startHeaders, 2);

		if ($this->_nntp->isError($msgs)) {
			echo "Error {$msgs->code}: {$msgs->message}$n";
			echo "Skipping group$n";

			return $returnArray;
		}

		// Check if we got headers.
		$msgCount = count($msgs);

		if ($msgCount < 1) {
			return $returnArray;
		}

		// Get highest and lowest article numbers/dates.
		$iterator1 = 0;
		$iterator2 = $msgCount - 1;
		while (true) {
			if (!isset($returnArray['firstArticleNumber']) && isset($msgs[$iterator1]['Number'])) {
				$returnArray['firstArticleNumber'] = $msgs[$iterator1]['Number'];
				$returnArray['firstArticleDate'] = $msgs[$iterator1]['Date'];
			}

			if (!isset($returnArray['lastArticleNumber']) && isset($msgs[$iterator2]['Number'])) {
				$returnArray['lastArticleNumber'] = $msgs[$iterator2]['Number'];
				$returnArray['lastArticleDate'] = $msgs[$iterator2]['Date'];
			}

			// Break if we found non empty articles.
			if (isset($returnArray['firstArticleNumber']) && isset($returnArray['lastArticleNumber'])) {
				break;
			}

			// Break out if we couldn't find anything.
			if ($iterator1++ >= $msgCount - 1 || $iterator2-- <= 0) {
				break;
			}
		}

		if (is_array($msgs)) {
			//loop headers, figure out parts
			foreach ($msgs AS $msg) {
				if (!isset($msg['Number']))
					continue;

				$msgsreceived[] = $msg['Number'];
				$msgPart = $msgTotalParts = 0;

				$pattern = '|\((\d+)[\/](\d+)\)|i';
				preg_match_all($pattern, $msg['Subject'], $matches, PREG_PATTERN_ORDER);
				$matchcnt = sizeof($matches[0]);
				for ($i = 0; $i < $matchcnt; $i++) {
					$msgPart = $matches[1][$i];
					$msgTotalParts = $matches[2][$i];
				}

				if (!isset($msg['Subject']) || $matchcnt == 0) // not a binary post most likely.. continue
				{
					$msgsignored[] = $msg['Number'];
					continue;
				}

				if ((int)$msgPart > 0 && (int)$msgTotalParts > 0) {
					$subject = utf8_encode(trim(preg_replace('|\(' . $msgPart . '[\/]' . $msgTotalParts . '\)|i', '', $msg['Subject'])));

					if (!isset($this->message[$subject])) {
						$this->message[$subject] = $msg;
						$this->message[$subject]['MaxParts'] = (int)$msgTotalParts;
						$this->message[$subject]['Date'] = strtotime($this->message[$subject]['Date']);
					}
					if ((int)$msgPart > 0) {
						$this->message[$subject]['Parts'][(int)$msgPart] = array('Message-ID' => substr($msg['Message-ID'], 1, -1), 'number' => $msg['Number'], 'part' => (int)$msgPart, 'size' => $msg['Bytes']);
						$this->message[$subject]['PartNumbers'][(int)$msgPart] = $msg['Number'];
					}
				}
			}
			unset($msg);
			unset($msgs);
			$count = 0;
			$updatecount = 0;
			$partcount = 0;
			$rangenotreceived = array_diff($rangerequested, $msgsreceived);

			if ($type != 'partrepair')
				echo "Received " . sizeof($msgsreceived) . " articles of " . ($last - $first + 1) . " requested, " . sizeof($msgsignored) . " not binaries $n";

			if ($type == 'update' && sizeof($msgsreceived) == 0) {
				echo "Error: Server did not return any articles.$n";
				echo "Skipping group$n";

				return $returnArray;
			}

			if (sizeof($rangenotreceived) > 0) {
				switch ($type) {
					case 'backfill':
						//don't add missing articles
						break;
					case 'partrepair':
					case 'update':
					default:
						$this->addMissingParts($rangenotreceived, $tableNames['prname'], $groupArr['id']);
						break;
				}
				echo "Server did not return " . count($rangenotreceived) . " article(s).$n";
			}

			if (isset($this->message) && count($this->message)) {
				$groupRegexes = $releaseRegex->getForGroup($groupArr['name']);

				//insert binaries and parts into database. when binary already exists; only insert new parts
				foreach ($this->message AS $subject => $data) {
					//Filter binaries based on black/white list
					if ($this->isBlackListed($data, $groupArr['name'])) {
						$msgsblacklisted[] = count($data['Parts']);
						if ($type == 'partrepair') {
							$partIds = array();
							foreach ($data['Parts'] as $partdata)
								$partIds[] = $partdata['number'];
							$db->queryExec(sprintf("DELETE FROM %s WHERE numberid IN (%s) AND groupid=%d", $tableNames['prname'], implode(',', $partIds), $groupArr['id']));
						}
						continue;
					}

					if (isset($data['Parts']) && count($data['Parts']) > 0 && $subject != '') {
						//Check for existing binary
						$binaryID = 0;
						$binaryHash = md5($subject . $data['From'] . $groupArr['id']);
						$res = $db->queryOneRow(sprintf("SELECT id FROM %s WHERE binaryhash = %s", $tableNames['bname'], $db->escapeString($binaryHash)));
						if (!$res) {

							//Apply Regexes
							$regexMatches = array();
							foreach ($groupRegexes as $groupRegex) {
								$regexCheck = $releaseRegex->performMatch($groupRegex, $subject);
								if ($regexCheck !== false) {
									$regexMatches = $regexCheck;
									break;
								}
							}

							$sql = '';
							if (!empty($regexMatches)) {
								$relparts = explode("/", $regexMatches['parts']);
								$sql = sprintf('INSERT INTO %s (name, fromname, date, xref, totalparts, groupid, procstat, categoryid, regexid, reqid, relpart, reltotalpart, binaryhash, relname, dateadded) VALUES (%s, %s, FROM_UNIXTIME(%s), %s, %s, %d, %d, %s, %d, %s, %d, %d, %s, %s, now())', $tableNames['bname'], $db->escapeString($subject), $db->escapeString(utf8_encode($data['From'])), $db->escapeString($data['Date']), $db->escapeString($data['Xref']), $db->escapeString($data['MaxParts']), $groupArr['id'], Releases::PROCSTAT_TITLEMATCHED, $regexMatches['regcatid'], $regexMatches['regexid'], $db->escapeString($regexMatches['reqid']), $relparts[0], $relparts[1], $db->escapeString($binaryHash), $db->escapeString(str_replace('_', ' ', $regexMatches['name'])));
							} elseif ($this->onlyProcessRegexBinaries === false) {
								$sql = sprintf('INSERT INTO %s (name, fromname, date, xref, totalparts, groupid, binaryhash, dateadded) VALUES (%s, %s, FROM_UNIXTIME(%s), %s, %s, %d, %s, now())', $tableNames['bname'], $db->escapeString($subject), $db->escapeString(utf8_encode($data['From'])), $db->escapeString($data['Date']), $db->escapeString($data['Xref']), $db->escapeString($data['MaxParts']), $groupArr['id'], $db->escapeString($binaryHash));
							} //onlyProcessRegexBinaries is true, there was no regex match and we are doing part repair so delete them
							elseif ($type == 'partrepair') {
								$partIds = array();
								foreach ($data['Parts'] as $partdata)
									$partIds[] = $partdata['number'];
								$db->queryExec(sprintf('DELETE FROM %s WHERE numberid IN (%s) AND groupid = %d', $tableNames['prname'], implode(',', $partIds), $groupArr['id']));
								continue;
							}
							if ($sql != '') {
								$binaryID = $db->queryInsert($sql);
								$count++;
								//if ($count % 500 == 0) echo "$count bin adds...";
							}
						} else {
							$binaryID = $res["id"];
							$updatecount++;
							//if ($updatecount % 500 == 0) echo "$updatecount bin updates...";
						}

						if ($binaryID != 0) {
							$partParams = array();
							$partNumbers = array();
							foreach ($data['Parts'] AS $partdata) {
								$partcount++;

								$partParams[] = sprintf('(%d, %s, %s, %s, %s)', $binaryID, $db->escapeString($partdata['Message-ID']), $db->escapeString($partdata['number']), $db->escapeString(round($partdata['part'])), $db->escapeString($partdata['size']));
								$partNumbers[] = $partdata['number'];
							}

							$partSql = ('INSERT INTO ' . $tableNames['pname'] . ' (binaryid, messageid, number, partnumber, size) VALUES '.implode(', ', $partParams));
							$pidata = $db->queryInsert($partSql);
							if (!$pidata) {
								$msgsnotinserted = array_merge($msgsnotinserted, $partNumbers);
							} else {
								$msgsinserted = array_merge($msgsinserted, $partNumbers);
							}
						}
					}
				}

				if (!empty($this->_binaryBlacklistIdsToUpdate)) {
					$this->_pdo->queryExec(
						sprintf('UPDATE binaryblacklist SET last_activity = NOW() WHERE id IN (%s)',
							implode(',', $this->_binaryBlacklistIdsToUpdate)
						)
					);
					$this->_binaryBlacklistIdsToUpdate = [];
				}

				//TODO: determine whether to add to missing articles if insert failed
				if (sizeof($msgsnotinserted) > 0) {
					echo 'WARNING: ' . count($msgsnotinserted) . ' Parts failed to insert' . $n;
					$this->addMissingParts($msgsnotinserted, $tableNames['prname'], $groupArr['id']);
				}
				if (($count >= 500) || ($updatecount >= 500)) {
					echo $n;
				} //line break for bin adds output
			}

			$timeUpdate = number_format(microtime(true) - $this->startUpdate, 2);
			$timeLoop = number_format(microtime(true) - $this->startLoop, 2);

			if (sizeof($msgsblacklisted) > 0)
				echo "Blacklisted " . array_sum($msgsblacklisted) . " parts in " . sizeof($msgsblacklisted) . " binaries" . $n;

			if ($type != 'partrepair') {
				if ($this->_echoCLI) {
					$this->_colorCLI->doEcho(
						$this->_colorCLI->alternateOver(number_format($count)).
						$this->_colorCLI->primaryOver(' new, ') .
						$this->_colorCLI->alternateOver(number_format($updatecount)).
						$this->_colorCLI->primaryOver(' updated, ') .
						$this->_colorCLI->alternateOver(number_format($partcount)).
						$this->_colorCLI->primaryOver(' parts, ') .
						$this->_colorCLI->alternateOver($timeHeaders . 's') .
						$this->_colorCLI->primaryOver(' to download articles, ') .
						$this->_colorCLI->alternateOver($timeUpdate . 's') .
						$this->_colorCLI->primaryOver(' to insert binaries/parts, ') .
						$this->_colorCLI->alternateOver($timeLoop . 's') .
						$this->_colorCLI->primary(' total.')
					);
				}
			}
			unset($this->message);
			unset($data);

			return $returnArray;
		} else {
			echo "Error: Can't get parts from server (msgs not array) $n";
			echo "Skipping group$n";

			return $returnArray;
		}
	}

	/**
	 * Attempt to get missing article headers.
	 *
	 * @param array $groupArr The info for this group from mysql.
	 *
	 * @return void
	 */
	public function partRepair($groupArr)
	{
		$tableNames = $this->_groups->getCBPTableNames($this->_tablePerGroup, $groupArr['id']);
		// Get all parts in partrepair table.
		$missingParts = $this->_pdo->query(
			sprintf('
				SELECT * FROM %s
				WHERE groupid = %d AND attempts < %d
				ORDER BY numberid ASC LIMIT %d',
				$tableNames['prname'],
				$groupArr['id'],
				$this->_partRepairMaxTries,
				$this->_partRepairLimit
			)
		);

		$missingCount = count($missingParts);
		if ($missingCount > 0) {
			if ($this->_echoCLI) {
				$this->_colorCLI->doEcho(
					$this->_colorCLI->primary(
						'Attempting to repair ' .
						number_format($missingCount) .
						' parts.'
					), true
				);
			}

			// Loop through each part to group into continuous ranges with a maximum range of messagebuffer/4.
			$ranges = $partList = [];
			$firstPart = $lastNum = $missingParts[0]['numberid'];

			foreach ($missingParts as $part) {
				if (($part['numberid'] - $firstPart) > ($this->messageBuffer / 4)) {

					$ranges[] = [
						'partfrom' => $firstPart,
						'partto'   => $lastNum,
						'partlist' => $partList
					];

					$firstPart = $part['numberid'];
					$partList = [];
				}
				$partList[] = $part['numberid'];
				$lastNum = $part['numberid'];
			}

			$ranges[] = [
				'partfrom' => $firstPart,
				'partto'   => $lastNum,
				'partlist' => $partList
			];

			// Download missing parts in ranges.
			foreach ($ranges as $range) {

				$partFrom = $range['partfrom'];
				$partTo   = $range['partto'];
				$partList = $range['partlist'];

				if ($this->_echoCLI) {
					echo chr(rand(45,46)) . "\r";
				}

				// Get article headers from newsgroup.
				$this->scan($groupArr, $partFrom, $partTo, 'partrepair', $partList);
			}

			// Calculate parts repaired
			$result = $this->_pdo->queryOneRow(
				sprintf('
					SELECT COUNT(id) AS num
					FROM %s
					WHERE groupid = %d
					AND numberid <= %d',
					$tableNames['prname'],
					$groupArr['id'],
					$missingParts[$missingCount - 1]['numberid']
				)
			);

			$partsRepaired = 0;
			if ($result !== false) {
				$partsRepaired = ($missingCount - $result['num']);
			}

			// Update attempts on remaining parts for active group
			if (isset($missingParts[$missingCount - 1]['id'])) {
				$this->_pdo->queryExec(
					sprintf('
						UPDATE %s
						SET attempts = attempts + 1
						WHERE groupid = %d
						AND numberid <= %d',
						$tableNames['prname'],
						$groupArr['id'],
						$missingParts[$missingCount - 1]['numberid']
					)
				);
			}

			if ($this->_echoCLI) {
				$this->_colorCLI->doEcho(
					$this->_colorCLI->primary(
						PHP_EOL .
						number_format($partsRepaired) .
						' parts repaired.'
					), true
				);
			}
		}

		// Remove articles that we cant fetch after x attempts.
		$this->_pdo->queryExec(
			sprintf(
				'DELETE FROM %s WHERE attempts >= %d AND groupid = %d',
				$tableNames['prname'],
				$this->_partRepairMaxTries,
				$groupArr['id']
			)
		);
	}

	/**
	 * Insert a missing part to the database.
	 */
	private function addMissingParts($numbers, $tablename, $groupID)
	{
		$db = new Settings();
		$added = false;
		$insertStr = "INSERT INTO $tablename (numberid, groupid) VALUES ";
		foreach ($numbers as $number) {
			if ($number > 0) {
				$checksql = sprintf("select numberid from $tablename where numberid = %u and groupid = %d", $number, $groupID);
				$chkrow = $db->queryOneRow($checksql);
				if ($chkrow) {
					$updsql = sprintf('update ' . $tablename . ' set attempts = attempts + 1 where numberid = %u and groupid = %d', $number, $groupID);
					$db->queryExec($updsql);
				} else {
					$added = true;
					$insertStr .= sprintf("(%u, %d), ", $number, $groupID);
				}
			}
		}
		if ($added) {
			$insertStr = substr($insertStr, 0, -2);

			return $db->queryInsert($insertStr, false);
		}

		return -1;
	}

	/**
	 * Are white or black lists loaded for a group name?
	 * @var array
	 */
	protected $_listsFound = [];

	/**
	 * Get blacklist and cache it. Return if already cached.
	 *
	 * @param string $groupName
	 *
	 * @return void
	 */
	protected function _retrieveBlackList($groupName)
	{
		if (!isset($this->blackList[$groupName])) {
			$this->blackList[$groupName] = $this->getBlacklist(true, self::OPTYPE_BLACKLIST, $groupName, true);
		}
		if (!isset($this->whiteList[$groupName])) {
			$this->whiteList[$groupName] = $this->getBlacklist(true, self::OPTYPE_WHITELIST, $groupName, true);
		}
		$this->_listsFound[$groupName] = ($this->blackList[$groupName] || $this->whiteList[$groupName]);
	}

	/**
	 * Check if an article is blacklisted.
	 *
	 * @param array  $msg       The article header (OVER format).
	 * @param string $groupName The group name.
	 *
	 * @return bool
	 */
	public function isBlackListed($msg, $groupName)
	{
		if (!isset($this->_listsFound[$groupName])) {
			$this->_retrieveBlackList($groupName);
		}
		if (!$this->_listsFound[$groupName]) {
			return false;
		}

		$blackListed = false;

		$field = [
			self::BLACKLIST_FIELD_SUBJECT   => $msg['Subject'],
			self::BLACKLIST_FIELD_FROM      => $msg['From'],
			self::BLACKLIST_FIELD_MESSAGEID => $msg['Message-ID']
		];

		// Try white lists first.
		if ($this->whiteList[$groupName]) {
			// There are white lists for this group, so anything that doesn't match a white list should be considered black listed.
			$blackListed = true;
			foreach ($this->whiteList[$groupName] as $whiteList) {
				if (preg_match('/' . $whiteList['regex'] . '/i', $field[$whiteList['msgcol']])) {
					// This field matched a white list, so it might not be black listed.
					$blackListed = false;
					$this->_binaryBlacklistIdsToUpdate[$whiteList['id']] = $whiteList['id'];
					break;
				}
			}
		}

		// Check if the field is black listed.
		if (!$blackListed && $this->blackList[$groupName]) {
			foreach ($this->blackList[$groupName] as $blackList) {
				if (preg_match('/' . $blackList['regex'] . '/i', $field[$blackList['msgcol']])) {
					$blackListed = true;
					$this->_binaryBlacklistIdsToUpdate[$blackList['id']] = $blackList['id'];
					break;
				}
			}
		}
		return $blackListed;
	}

	/**
	 * Rawsearch. Perform a simple like match on binary subjects matching a pattern.
	 */
	public function search($search, $limit = 1000, $excludedcats = array())
	{
		$db = new Settings();

		//
		// if the query starts with a ^ it indicates the search is looking for items which start with the term
		// still do the like match, but mandate that all items returned must start with the provided word
		//
		$words = explode(" ", $search);
		$searchsql = "";
		$intwordcount = 0;
		if (count($words) > 0) {
			foreach ($words as $word) {
				//
				// see if the first word had a caret, which indicates search must start with term
				//
				if ($intwordcount == 0 && (strpos($word, "^") === 0))
					$searchsql .= sprintf(" and b.name like %s", $db->escapeString(substr($word, 1) . "%"));
				else
					$searchsql .= sprintf(" and b.name like %s", $db->escapeString("%" . $word . "%"));

				$intwordcount++;
			}
		}

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and b.categoryid not in (" . implode(",", $excludedcats) . ") ";

		$res = $db->query(sprintf("
					SELECT b.*,
					g.name AS group_name,
					r.guid,
					(SELECT COUNT(id) FROM parts p where p.binaryid = b.id) as 'binnum'
					FROM binaries b
					INNER JOIN groups g ON g.id = b.groupid
					LEFT OUTER JOIN releases r ON r.id = b.releaseid
					WHERE 1=1 %s %s order by DATE DESC LIMIT %d ",
				$searchsql, $exccatlist, $limit
			)
		);

		return $res;
	}

	/**
	 * Get all binaries for a release.
	 */
	public function getForReleaseId($id)
	{
		$db = new Settings();

		return $db->query(sprintf("select binaries.* from binaries where releaseid = %d order by relpart", $id));
	}

	/**
	 * Get a binary row.
	 */
	public function getById($id)
	{
		$db = new Settings();

		return $db->queryOneRow(sprintf("select binaries.*, groups.name as groupname from binaries left outer join groups on binaries.groupid = groups.id where binaries.id = %d ", $id));
	}

	/**
	 * Return all blacklists.
	 *
	 * @param bool   $activeOnly Only display active blacklists ?
	 * @param int    $opType     Optional, get white or black lists (use Binaries constants).
	 * @param string $groupName  Optional, group.
	 * @param bool   $groupRegex Optional Join groups / binaryblacklist using regexp for equals.
	 *
	 * @return array
	 */
	public function getBlacklist($activeOnly = true, $opType = -1, $groupName = '', $groupRegex = false)
	{
		switch ($opType) {
			case self::OPTYPE_BLACKLIST:
				$opType = 'AND binaryblacklist.optype = ' . self::OPTYPE_BLACKLIST;
				break;
			case self::OPTYPE_WHITELIST:
				$opType = 'AND binaryblacklist.optype = ' . self::OPTYPE_WHITELIST;
				break;
			default:
				$opType = '';
				break;
		}
		return $this->_pdo->query(
			sprintf('
				SELECT
					binaryblacklist.id, binaryblacklist.optype, binaryblacklist.status, binaryblacklist.description,
					binaryblacklist.groupname AS groupname, binaryblacklist.regex, groups.id AS group_id, binaryblacklist.msgcol,
					binaryblacklist.last_activity as last_activity
				FROM binaryblacklist
				LEFT OUTER JOIN groups ON groups.name %s binaryblacklist.groupname
				WHERE 1=1 %s %s %s
				ORDER BY coalesce(groupname,\'zzz\')',
				($groupRegex ? 'REGEXP' : '='),
				($activeOnly ? 'AND binaryblacklist.status = 1' : ''),
				$opType,
				($groupName ? ('AND groups.name REGEXP ' . $this->_pdo->escapeString($groupName)) : '')
			)
		);
	}

	/**
	 * Get a blacklist row from database.
	 */
	public function getBlacklistByID($id)
	{
		$db = new Settings();

		return $db->queryOneRow(sprintf("select * from binaryblacklist where id = %d ", $id));
	}

	/**
	 * Delete a blacklist row from database.
	 */
	public function deleteBlacklist($id)
	{
		$db = new Settings();

		return $db->queryExec(sprintf("DELETE from binaryblacklist where id = %d", $id));
	}

	/**
	 * Update a blacklist row.
	 */
	public function updateBlacklist($regex)
	{
		$db = new Settings();

		$groupname = $regex["groupname"];
		if ($groupname == "")
			$groupname = "null";
		else {
			$groupname = preg_replace("/a\.b\./i", "alt.binaries.", $groupname);
			$groupname = sprintf("%s", $db->escapeString($groupname));
		}

		$db->queryExec(sprintf("update binaryblacklist set groupname=%s, regex=%s, status=%d, description=%s, optype=%d, msgcol=%d where id = %d ", $groupname, $db->escapeString($regex["regex"]), $regex["status"], $db->escapeString($regex["description"]), $regex["optype"], $regex["msgcol"], $regex["id"]));
	}

	/**
	 * Add a new blacklist row.
	 */
	public function addBlacklist($regex)
	{
		$db = new Settings();

		$groupname = $regex["groupname"];
		if ($groupname == "")
			$groupname = "null";
		else {
			$groupname = preg_replace("/a\.b\./i", "alt.binaries.", $groupname);
			$groupname = sprintf("%s", $db->escapeString($groupname));
		}

		return $db->queryInsert(sprintf("insert into binaryblacklist (groupname, regex, status, description, optype, msgcol) values (%s, %s, %d, %s, %d, %d) ",
				$groupname, $db->escapeString($regex["regex"]), $regex["status"], $db->escapeString($regex["description"]), $regex["optype"], $regex["msgcol"]
			)
		);
	}

	/**
	 * Add a new binary row and its associated parts.
	 */
	public function delete($id)
	{
		$db = new Settings();
		$db->queryExec(sprintf("DELETE from parts where binaryid = %d", $id));
		$db->queryExec(sprintf("DELETE from binaries where id = %d", $id));
	}

	# http://php.net/manual/en/function.array-unique.php#97285
	public function getSuperUniqueArray($array)
	{
		$result = array_map("unserialize", array_unique(array_map("serialize", $array)));
		foreach ($result as $key => $value) {
			if (is_array($value))
				$result[$key] = $this->getSuperUniqueArray($value);
		}

		return $result;
	}

	/**
	 * Returns article number based on # of days.
	 *
	 * @param int   $days      How many days back we want to go.
	 * @param array $data      Group data from usenet.
	 *
	 * @return string
	 */
	public function daytopost($days, $data)
	{
		$goalTime = time() - (86400 * $days);
		// The time we want = current unix time (ex. 1395699114) - minus 86400 (seconds in a day)
		// times days wanted. (ie 1395699114 - 2592000 (30days)) = 1393107114

		// The servers oldest date.
		$firstDate = $this->postdate($data['first'], $data);
		if ($goalTime < $firstDate) {
			// If the date we want is older than the oldest date in the group return the groups oldest article.
			return $data['first'];
		}

		// The servers newest date.
		$lastDate = $this->postdate($data['last'], $data);
		if ($goalTime > $lastDate) {
			// If the date we want is newer than the groups newest date, return the groups newest article.
			return $data['last'];
		}

		if ($this->_echoCLI) {
			$this->_colorCLI->doEcho(
				$this->_colorCLI->primary(
					'Searching for an approximate article number for group ' . $data['group'] . ' ' . $days . ' days back.'
				)
			);
		}

		// Pick the middle to start with
		$wantedArticle = round(($data['last'] + $data['first']) / 2);
		$aMax = $data['last'];
		$aMin = $data['first'];
		$reallyOldArticle = $oldArticle = $articleTime = null;

		while(true) {
			// Article exists outside of available range, this shouldn't happen
			if ($wantedArticle <= $data['first'] || $wantedArticle >= $data['last']) {
				break;
			}

			// Keep a note of the last articles we checked
			$reallyOldArticle = $oldArticle;
			$oldArticle = $wantedArticle;

			// Get the date of this article
			$articleTime = $this->postdate($wantedArticle, $data);

			// Article doesn't exist, start again with something random
			if (!$articleTime) {
				$wantedArticle = mt_rand($aMin, $aMax);
				$articleTime = $this->postdate($wantedArticle, $data);
			}

			if ($articleTime < $goalTime) {
				// Article is older than we want
				$aMin = $oldArticle;
				$wantedArticle = round(($aMax + $oldArticle) / 2);
				if ($this->_echoCLI) {
					echo '-';
				}
			} else if ($articleTime > $goalTime) {
				// Article is newer than we want
				$aMax = $oldArticle;
				$wantedArticle = round(($aMin + $oldArticle) / 2);
				if ($this->_echoCLI) {
					echo '+';
				}
			} else if ($articleTime == $goalTime) {
				// Exact match. We did it! (this will likely never happen though)
				break;
			}

			// We seem to be flip-flopping between 2 articles, assume we're out of articles to check.
			// End on an article more recent than our oldest so that we don't miss any releases.
			if ($reallyOldArticle == $wantedArticle && ($goalTime - $articleTime) <= 0) {
				break;
			}
		}

		$wantedArticle = (int)$wantedArticle;
		if ($this->_echoCLI) {
			$this->_colorCLI->doEcho(
				$this->_colorCLI->primary(
					PHP_EOL . 'Found article #' . $wantedArticle . ' which has a date of ' . date('r', $articleTime) .
					', vs wanted date of ' . date('r', $goalTime) . '. Difference from goal is ' . round(($goalTime - $articleTime) / 60 / 60 / 24, 1) . ' days.'
				)
			);
		}

		return $wantedArticle;
	}

	/**
	 * Convert unix time to days ago.
	 *
	 * @param int $timestamp unix time
	 *
	 * @return float
	 */
	private function daysOld($timestamp)
	{
		return round((time() - (!is_numeric($timestamp) ? strtotime($timestamp) : $timestamp)) / 86400, 1);
	}

	/**
	 * Returns unix time for an article number.
	 *
	 * @param int    $post      The article number to get the time from.
	 * @param array  $groupData Usenet group info from NNTP selectGroup method.
	 *
	 * @return bool|int
	 */
	public function postdate($post, array $groupData)
	{
		// Set table names
		$groupID = $this->_groups->getIDByName($groupData['group']);
		$group = [];
		if ($groupID !== '') {
			$group = $this->_groups->getCBPTableNames($this->_tablePerGroup, $groupID);
		}

		$currentPost = $post;

		$attempts = $date = 0;
		do {
			// Try to get the article date locally first.
			if ($groupID !== '') {
				// Try to get locally.
				$local = $this->_pdo->queryOneRow(
					sprintf('
						SELECT b.date AS date
						FROM %s b, %s p
						WHERE b.id = p.binaryid
						AND b.groupid = %s
						AND p.number = %s LIMIT 1',
						$group['bname'],
						$group['pname'],
						$groupID,
						$currentPost
					)
				);
				if ($local !== false) {
					$date = $local['date'];
					break;
				}
			}

			// If we could not find it locally, try usenet.
			$header = $this->_nntp->getXOVER($currentPost);
			if (!$this->_nntp->isError($header)) {
				// Check if the date is set.
				if (isset($header[0]['Date']) && strlen($header[0]['Date']) > 0) {
					$date = $header[0]['Date'];
					break;
				}
			}

			// Try to get a different article number.
			if (abs($currentPost - $groupData['first']) > abs($groupData['last'] - $currentPost)) {
				$tempPost = round($currentPost / (mt_rand(1005, 1012) / 1000), 0, PHP_ROUND_HALF_UP);
				if ($tempPost < $groupData['first']) {
					$tempPost = $groupData['first'];
				}
			} else {
				$tempPost = round((mt_rand(1005, 1012) / 1000) * $currentPost, 0, PHP_ROUND_HALF_UP);
				if ($tempPost > $groupData['last']) {
					$tempPost = $groupData['last'];
				}
			}
			// If we got the same article number as last time, give up.
			if ($tempPost === $currentPost) {
				break;
			}
			$currentPost = $tempPost;

			if ($this->_debug) {
				$this->_colorCLI->doEcho($this->_colorCLI->debug('Postdate retried ' . $attempts . " time(s)."));
			}
		} while ($attempts++ <= 20);

		// If we didn't get a date, set it to now.
		if (!$date) {
			$date = time();
		} else {
			$date = strtotime($date);
		}

		if ($this->_debug) {
			$this->_debugging->log(
				'Binaries',
				"postdate",
				'Article (' .
				$post .
				"'s) date is (" .
				$date .
				') (' .
				$this->daysOld($date) .
				" days old)",
				\Logger::LOG_INFO
			);
		}

		return $date;
	}
	/**
	 * Log / Echo message.
	 *
	 * @param string $message Message to log.
	 * @param string $method  Method that called this.
	 * @param int    $level   Logger severity level constant.
	 * @param string $color   ColorCLI method name.
	 */
	private function log($message, $method, $level, $color)
	{
		if ($this->_echoCLI) {
			$this->_colorCLI->doEcho(
				$this->_colorCLI->$color($message), true
			);
		}

		if ($this->_debug) {
			$this->_debugging->log('Binaries', $method, $message, $level);
		}
	}

	/**
	 * Delete all Binaries/Parts for a group id.
	 *
	 * @param int $groupID The id of the group.
	 *
	 * @note A trigger automatically deletes the parts.
	 *
	 * @return void
	 */
	public function purgeGroup($groupID)
	{
		$this->_pdo->queryExec(sprintf('DELETE b FROM binaries b WHERE b.groupid = %d', $groupID));
	}
}
