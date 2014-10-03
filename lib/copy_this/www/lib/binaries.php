<?php
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/nntp.php");
require_once(WWW_DIR . "/lib/groups.php");
require_once(WWW_DIR . "/lib/backfill.php");
require_once(WWW_DIR . "/lib/ColorCLI.php");
require_once(WWW_DIR . "/lib/Logger.php");

/**
 * This class manages the downloading of binaries and parts from usenet, and the
 * managing of data in the binaries and parts tables.
 */
class Binaries
{
	const OPT_BLACKLIST = 1;
	const OPT_WHITELIST = 2;

	const BLACKLIST_DISABLED = 0;
	const BLACKLIST_ENABLED = 1;

	const BLACKLIST_FIELD_SUBJECT = 1;
	const BLACKLIST_FIELD_FROM = 2;
	const BLACKLIST_FIELD_MESSAGEID = 3;

	/**
	 * How many headers do we download per loop?
	 *
	 * @var int
	 */
	public $messageBuffer;

	/**
	 * Does the user have any blacklists enabled?
	 * @var bool
	 */
	protected $_blackListEmpty = false;

	/**
	 * Is the blacklist already cached?
	 *
	 * @var bool
	 */
	protected $_blackListLoaded = false;

	/**
	 * Default constructor
	 */
	function Binaries()
	{
		$this->n = "\n";

		$s = new Sites();
		$site = $s->get();
		$this->messageBuffer = ($site->maxmssgs != '') ? $site->maxmssgs : 20000;
		$this->_partRepair = ($site->partrepair == 0 ? false : true);
		$this->compressedHeaders = ($site->compressedheaders == "1") ? true : false;
		$this->MaxMsgsPerRun = (!empty($site->maxmsgsperrun)) ? $site->maxmsgsperrun : 200000;
		$this->NewGroupScanByDays = ($site->newgroupscanmethod == "1") ? true : false;
		$this->NewGroupMsgsToScan = (!empty($site->newgroupmsgstoscan)) ? $site->newgroupmsgstoscan : 50000;
		$this->NewGroupDaysToScan = (!empty($site->newgroupdaystoscan)) ? $site->newgroupdaystoscan : 3;
		$this->_tablePerGroup = ($site->tablepergroup == 1 ? true : false);
		$this->_showDroppedYEncParts = ($site->showdroppedyencparts == 1 ? true : false);

		$this->blackList = [];
		$this->_blackListLoaded = false;
		$this->blackList_by_group = array();
		$this->message = array();

		$this->onlyProcessRegexBinaries = false;
		$this->_colorCLI =  new \ColorCLI();
		$this->_echoCLI = NN_ECHOCLI;
		$this->_pdo = new DB();
		$this->_nntp = new \NNTP(['Echo' => $this->_colorCLI, 'Settings' => $this->_pdo, 'ColorCLI' => $this->_colorCLI]);
		$this->_groups = new Groups();
		$this->_debug = (NN_DEBUG || NN_LOGGING);
		if ($this->_debug) {
			try {
				$this->_debugging = new \Logger(['ColorCLI' => $this->_colorCLI]);
			} catch (\LoggerException $error) {
				$this->_debug = false;
			}
		}
	}

	/**
	 * Process headers and store in database for all active groups.
	 */
	function updateAllGroups()
	{
		$n = $this->n;
		$groups = new Groups;
		$res = $groups->getActive();

		$s = new Sites();
		echo $s->getLicense();

		if ($res) {
			shuffle($res);
			$alltime = microtime(true);
			echo 'Updating: ' . sizeof($res) . ' groups - Using compression? ' . (($this->compressedHeaders) ? 'Yes' : 'No') . $n;

			$nntp = new Nntp();
			if ($nntp->doConnect()) {

				$pos = 0;
				foreach ($res as $groupArr) {
					$pos++;
					echo 'Group ' . $pos . ' of ' . sizeof($res) . $n;
					$this->message = array();
					$this->updateGroup($nntp, $groupArr);
				}

				$nntp->doQuit();
				echo 'Updating completed in ' . number_format(microtime(true) - $alltime, 2) . ' seconds' . $n;
			} else {
				echo "Failed to get NNTP connection.$n";
			}
		} else {
			echo "No groups specified. Ensure groups are added to newznab's database and activated before updating.$n";
		}
	}

	/**
	 * Process headers and store in database for a group.
	 */
	function updateGroup($nntp = null, $groupArr)
	{
		$blnDoDisconnect = false;
		if ($nntp == null) {
			$nntp = new Nntp();
			if (!$nntp->doConnect()) {
				echo "Failed to get NNTP connection.";

				return;
			}
			$this->message = array();
			$blnDoDisconnect = true;
		}

		$db = new DB();
		$backfill = new Backfill();

		$n = $this->n;
		$this->startGroup = microtime(true);
		$this->startLoop = microtime(true);

		echo 'Processing ' . $groupArr['name'] . $n;

		// Connect to server
		$data = $nntp->selectGroup($groupArr['name']);
		if (NNTP::isError($data)) {
			echo "Could not select group (bad name?): {$groupArr['name']}$n $n";

			return;
		}

		if ($groupArr['regexmatchonly'] == 1) {
			$this->onlyProcessRegexBinaries = true;
			echo "Note: Discarding parts that do not match a regex" . $n;
		} else {
			$this->onlyProcessRegexBinaries = false;
		}

		//Attempt to repair any missing parts before grabbing new ones
		$this->partRepair($nntp, $groupArr);

		//Get first and last part numbers from newsgroup
		$last = $grouplast = $data['last'];

		// For new newsgroups - determine here how far you want to go back.
		if ($groupArr['last_record'] == 0) {
			if ($this->NewGroupScanByDays) {
				$first = $backfill->daytopost($nntp, $groupArr['name'], $this->NewGroupDaysToScan, true);
				if ($first == '') {
					echo "Skipping group: {$groupArr['name']}$n";

					return;
				}
			} else {
				if ($data['first'] > ($data['last'] - $this->NewGroupMsgsToScan))
					$first = $data['first'];
				else
					$first = $data['last'] - $this->NewGroupMsgsToScan;
			}
			$first_record_postdate = $this->postdate($first, $data);
			if ($first_record_postdate != "")
				$db->queryExec(sprintf("update groups SET first_record = %s, first_record_postdate = FROM_UNIXTIME(" . $first_record_postdate . ") WHERE ID = %d", $db->escapeString($first), $groupArr['ID']));
		} else {
			if ($data['last'] < $groupArr['last_record']) {
				echo "Warning: Server's last num {$data['last']} is lower than the local last num {$groupArr['last_record']}" . $n;

				return;
			}
			$first = $groupArr['last_record'] + 1;
		}

		// Generate postdates for first and last records, for those that upgraded
		if ((is_null($groupArr['first_record_postdate']) || is_null($groupArr['last_record_postdate'])) && ($groupArr['last_record'] != "0" && $groupArr['first_record'] != "0"))
			$db->queryExec(sprintf("update groups SET first_record_postdate = FROM_UNIXTIME(" . $this->postdate($groupArr['first_record'], $data) . "), last_record_postdate = FROM_UNIXTIME(" . $backfill->postdate($nntp, $groupArr['last_record'], false) . ") WHERE ID = %d", $groupArr['ID']));

		// Deactivate empty groups
		if (($data['last'] - $data['first']) <= 5)
			$db->queryExec(sprintf("update groups SET active = %s, last_updated = now() WHERE ID = %d", $db->escapeString('0'), $groupArr['ID']));

		// Calculate total number of parts
		$total = $grouplast - $first + 1;

		// If total is bigger than 0 it means we have new parts in the newsgroup
		if ($total > 0) {
			echo "Group " . $data["group"] . " has " . number_format($total) . " new parts." . $n;
			if ($total > $this->MaxMsgsPerRun) {
				$grouplast = $first + $this->MaxMsgsPerRun;
				echo "NOTICE: Only processing first " . number_format($this->MaxMsgsPerRun) . " parts." . $n;
			}
			echo "First: " . $data['first'] . " Last: " . $data['last'] . " Local last: " . $groupArr['last_record'] . $n;
			if ($groupArr['last_record'] == 0)
				echo "New group starting with " . (($this->NewGroupScanByDays) ? $this->NewGroupDaysToScan . " days" : $this->NewGroupMsgsToScan . " messages") . " worth." . $n;

			$done = false;

			// Get all the parts (in portions of $this->messageBuffer to not use too much memory)
			while ($done === false) {
				$this->startLoop = microtime(true);

				if ($total > $this->messageBuffer) {
					if ($first + $this->messageBuffer > $grouplast)
						$last = $grouplast;
					else
						$last = $first + $this->messageBuffer;
				}

				echo "Getting " . number_format($last - $first + 1) . " parts (" . $first . " to " . $last . ") - " . number_format($grouplast - $last) . " in queue" . $n;
				flush();

				//get headers from newsgroup
				$lastId = $this->scan($nntp, $groupArr, $first, $last);

				if ($lastId === false) {
					//scan failed - skip group
					return;
				}
				$db->queryExec(sprintf("update groups SET last_record = %s, last_updated = now() WHERE ID = %d", $db->escapeString($lastId), $groupArr['ID']));

				if ($last == $grouplast)
					$done = true;
				else {
					$last = $lastId;
					$first = $last + 1;
				}
			}

			$last_record_postdate = $this->postdate($last, $data);
			if ($last_record_postdate != "") {
				$db->queryExec(sprintf("update groups SET last_record_postdate = FROM_UNIXTIME(" . $last_record_postdate . "), last_updated = now() WHERE ID = %d", $groupArr['ID'])); //Set group's last postdate
			}
			$timeGroup = number_format(microtime(true) - $this->startGroup, 2);
			echo "Group processed in $timeGroup seconds $n $n";
		} else {
			echo "No new records for " . $data["group"] . " (first $first last $last total $total) grouplast " . $groupArr['last_record'] . $n . $n;

		}

		if ($blnDoDisconnect) {
			$nntp->doQuit();
		}
	}

	/**
	 * Loop over range of wanted headers, insert headers into DB.
	 *
	 * @param array      $groupMySQL   The group info from mysql.
	 * @param int        $first        The oldest wanted header.
	 * @param int        $last         The newest wanted header.
	 * @param string     $type         Is this partrepair or update or backfill?
	 * @param null|array $missingParts If we are running in partrepair, the list of missing article numbers.
	 *
	 * @return array Empty on failure.
	 */
	public function scan($groupMySQL, $first, $last, $type = 'update', $missingParts = null)
	{
		// Start time of scan method and of fetching headers.
		$startLoop = microtime(true);

		// Check if MySQL tables exist, create if they do not, get their names at the same time.
		$tableNames = $this->_groups->getCBPTableNames($this->_tablePerGroup, $groupMySQL['ID']);

		$returnArray = [];

		$partRepair = ($type === 'partrepair');
		$addToPartRepair = ($type === 'update' && $this->_partRepair);

		// Download the headers.
		if ($partRepair === true) {
			// This is slower but possibly is better with missing headers.
			$headers = $this->_nntp->getOverview($first . '-' . $last, true, false);
		} else {
			$headers = $this->_nntp->getXOVER($first . '-' . $last);
		}

		// If there was an error, try to reconnect.
		if ($this->_nntp->isError($headers)) {

			// Increment if part repair and return false.
			if ($partRepair === true) {
				$this->_pdo->queryExec(
					sprintf(
						'UPDATE partrepair SET attempts = attempts + 1 WHERE groupID = %d AND numberid %s',
						$groupMySQL['ID'],
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
			$this->_nntp->selectGroup($groupMySQL['name']);
			$headers = $this->_nntp->getXOVER($first . '-' . $last);
			$this->_nntp->enableCompression();

			// Check if the non-compression headers have an error.
			if ($this->_nntp->isError($headers)) {
				$this->log(
					"Code {$headers->code}: {$headers->message}\nSkipping group: ${$groupMySQL['name']}",
					'scan',
					\Logger::LOG_WARNING,
					'error'
				);
				return $returnArray;
			}
		}

		// Start of processing headers.
		$startCleaning = microtime(true);

		// End of the getting data from usenet.
		$timeHeaders = number_format($startCleaning - $startLoop, 2);

		// Check if we got headers.
		$msgCount = count($headers);

		if ($msgCount < 1) {
			return $returnArray;
		}

		// Get highest and lowest article numbers/dates.
		$iterator1 = 0;
		$iterator2 = $msgCount - 1;
		while (true) {
			if (!isset($returnArray['firstArticleNumber']) && isset($headers[$iterator1]['Number'])) {
				$returnArray['firstArticleNumber'] = $headers[$iterator1]['Number'];
				$returnArray['firstArticleDate'] = $headers[$iterator1]['Date'];
			}

			if (!isset($returnArray['lastArticleNumber']) && isset($headers[$iterator2]['Number'])) {
				$returnArray['lastArticleNumber'] = $headers[$iterator2]['Number'];
				$returnArray['lastArticleDate'] = $headers[$iterator2]['Date'];
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

		$headersRepaired = $articles = $rangeNotReceived = $binariesUpdate = $headersReceived = $headersNotInserted = [];
		$notYEnc = $headersBlackListed = 0;

		$partsQuery = $partsCheck = sprintf('INSERT INTO %s (binaryID, number, messageID, partnumber, size) VALUES ', $tableNames['pname']);

		$this->_pdo->beginTransaction();
		// Loop articles, figure out files/parts.
		foreach ($headers as $header) {

			// Check if we got the article or not.
			if (isset($header['Number'])) {
				$headersReceived[] = $header['Number'];
			} else {
				if ($addToPartRepair) {
					$rangeNotReceived[] = $header['Number'];
				}
				continue;
			}

			// If set we are running in partRepair mode.
			if ($partRepair === true && !is_null($missingParts)) {
				if (!in_array($header['Number'], $missingParts)) {
					// If article isn't one that is missing skip it.
					continue;
				} else {
					// We got the part this time. Remove article from part repair.
					$headersRepaired[] = $header['Number'];
				}
			}

			/*
			 * Find part / total parts. Ignore if no part count found.
			 *
			 * \s* Trims the leading space.
			 * (?!"Usenet Index Post) ignores these types of articles, they are useless.
			 * (.+) Fetches the subject.
			 * \s+ Trims trailing space after the subject.
			 * \((\d+)\/(\d+)\) Gets the part count.
			 * No ending ($) as there are cases of subjects with extra data after the part count.
			 */
			if (preg_match('/^\s*(?!"Usenet Index Post)(.+)\s+\((\d+)\/(\d+)\)/', $header['Subject'], $matches)) {
				// Add yEnc to subjects that do not have them, but have the part number at the end of the header.
				if (!stristr($header['Subject'], 'yEnc')) {
					$matches[1] .= ' yEnc';
				}
			} else {
				if ($this->_showDroppedYEncParts === true && strpos($header['Subject'], '"Usenet Index Post') !== 0) {
					file_put_contents(
						NN_LOGS . 'not_yenc' . $groupMySQL['name'] . '.dropped.log',
						$header['Subject'] . PHP_EOL, FILE_APPEND
					);
				}
				$notYEnc++;
				continue;
			}

			// Filter subject based on black/white list.
			if ($this->_blackListEmpty === false && $this->isBlackListed($header, $groupMySQL['name'])) {
				$headersBlackListed++;
				continue;
			}

			if (!isset($header['Bytes'])) {
				$header['Bytes'] = (isset($header[':bytes']) ? $header[':bytes'] : 0);
			}
			$header['Bytes'] = (int) $header['Bytes'];

			// Set up the info for inserting into parts/binaries/collections tables.
			if (!isset($articles[$matches[1]])) {

				// Attempt to find the file count. If it is not found, set it to 0.
				if (!preg_match('/[[(\s](\d{1,5})(\/|[\s_]of[\s_]|-)(\d{1,5})[])\s$:]/i', $matches[1], $fileCount)) {
					$fileCount[1] = $fileCount[3] = 0;

					if ($this->_showDroppedYEncParts === true) {
						file_put_contents(
							NN_LOGS . 'no_files' . $groupMySQL['name'] . '.log',
							$header['Subject'] . PHP_EOL, FILE_APPEND
						);
					}
				}

				$binaryID = $this->_pdo->queryInsert(
					sprintf("
						INSERT INTO %s (binaryhash, name, totalparts, currentparts, filenumber, partsize)
						VALUES ('%s', %s, %d, %d, 1, %d, %d)
						ON DUPLICATE KEY UPDATE currentparts = currentparts + 1, partsize = partsize + %d",
						$tableNames['bname'],
						md5($matches[1] . $header['From'] . $groupMySQL['ID']),
						$this->_pdo->escapeString(utf8_encode($matches[1])),
						$matches[3],
						$fileCount[1],
						$header['Bytes'],
						$header['Bytes']
					)
				);

				if ($binaryID === false) {
					if ($addToPartRepair) {
						$headersNotInserted[] = $header['Number'];
					}
					$this->_pdo->Rollback();
					$this->_pdo->beginTransaction();
					continue;
				}

				$binariesUpdate[$binaryID]['Size'] = 0;
				$binariesUpdate[$binaryID]['Parts'] = 0;

				$articles[$matches[1]]['BinaryID'] = $binaryID;

			} else {
				$binaryID = $articles[$matches[1]]['BinaryID'];
				$binariesUpdate[$binaryID]['Size'] += $header['Bytes'];
				$binariesUpdate[$binaryID]['Parts']++;
			}

			// Strip the < and >, saves space in DB.
			$header['Message-ID'][0] = "'";

			$partsQuery .=
				'(' . $binaryID . ',' . $header['Number'] . ',' . rtrim($header['Message-ID'], '>') . "'," .
				$matches[2] . ',' . $header['Bytes'] . ',' ;

		}
		unset($headers); // Reclaim memory.

		// Start of inserting into SQL.
		$startUpdate = microtime(true);

		// End of processing headers.
		$timeCleaning = number_format($startUpdate - $startCleaning, 2);

		$binariesQuery = $binariesCheck = sprintf('INSERT INTO %s (id, partsize, currentparts) VALUES ', $tableNames['bname']);
		foreach ($binariesUpdate as $binaryID => $binary) {
			$binariesQuery .= '(' . $binaryID . ',' . $binary['Size'] . ',' . $binary['Parts'] . '),';
		}
		$binariesEnd = ' ON DUPLICATE KEY UPDATE partsize = VALUES(partsize) + partsize, currentparts = VALUES(currentparts) + currentparts';
		$binariesQuery = rtrim($binariesQuery, ',') . $binariesEnd;

		// Check if we got any binaries. If we did, try to insert them.
		if (((strlen($binariesCheck . $binariesEnd) === strlen($binariesQuery)) ? true : $this->_pdo->queryExec($binariesQuery))) {
			if ($this->_debug) {
				$this->_colorCLI->doEcho(
					$this->_colorCLI->debug(
						'Sending ' . round(strlen($partsQuery) / 1024, 2) . ' KB of parts to MySQL'
					)
				);
			}

			if (((strlen($partsQuery) === strlen($partsCheck)) ? true  : $this->_pdo->queryExec(rtrim($partsQuery, ',')))) {
				$this->_pdo->Commit();
			} else {
				if ($addToPartRepair) {
					$headersNotInserted += $headersReceived;
				}
				$this->_pdo->Rollback();
			}
		} else {
			if ($addToPartRepair) {
				$headersNotInserted += $headersReceived;
			}
			$this->_pdo->Rollback();
		}

		if ($this->_echoCLI && $partRepair === false) {
			$this->_colorCLI->doEcho(
				$this->_colorCLI->primary(
					'Received ' . count($headersReceived) .
					' articles of ' . (number_format($last - $first + 1)) . ' requested, ' .
					$headersBlackListed . ' blacklisted, ' . $notYEnc . ' not yEnc.'
				)
			);
		}

		// Start of part repair.
		$startPR = microtime(true);

		// End of inserting.
		$timeInsert = number_format($startPR - $startUpdate, 2);

		if ($partRepair && count($headersRepaired) > 0) {
			$this->removeRepairedParts($headersRepaired, $tableNames['prname'], $groupMySQL['ID']);
		}

		if ($addToPartRepair) {

			$notInsertedCount = count($headersNotInserted);
			if ($notInsertedCount > 0) {
				$this->addMissingParts($headersNotInserted, $tableNames['prname'], $groupMySQL['ID']);

				$this->log(
					$notInsertedCount . ' articles failed to insert!',
					'scan',
					\Logger::LOG_WARNING,
					'warning'
				);
			}

			// Check if we have any missing headers.
			if (($last - $first - $notYEnc - $headersBlackListed + 1) > count($headersReceived)) {
				$rangeNotReceived = array_merge($rangeNotReceived, array_diff(range($first, $last), $headersReceived));
			}
			$notReceivedCount = count($rangeNotReceived);

			if ($notReceivedCount > 0) {
				$this->addMissingParts($rangeNotReceived, $tableNames['prname'], $groupMySQL['id']);

				if ($this->_echoCLI) {
					$this->_colorCLI->doEcho(
						$this->_colorCLI->alternate(
							'Server did not return ' . $notReceivedCount .
							' articles from ' . $groupMySQL['name'] . '.'
						), true
					);
				}
			}
		}

		$currentMicroTime = microtime(true);
		if ($this->_echoCLI) {
			$this->_colorCLI->doEcho(
				$this->_colorCLI->alternateOver($timeHeaders . 's') .
				$this->_colorCLI->primaryOver(' to download articles, ') .
				$this->_colorCLI->alternateOver($timeCleaning . 's') .
				$this->_colorCLI->primaryOver(' to process collections, ') .
				$this->_colorCLI->alternateOver($timeInsert . 's') .
				$this->_colorCLI->primaryOver(' to insert binaries/parts, ') .
				$this->_colorCLI->alternateOver(number_format($currentMicroTime - $startPR, 2) . 's') .
				$this->_colorCLI->primaryOver(' for part repair, ') .
				$this->_colorCLI->alternateOver(number_format($currentMicroTime - $startLoop, 2) . 's') .
				$this->_colorCLI->primary(' total.')
			);
		}
		return $returnArray;
	}

	/**
	 * Go through all rows in partrepair table and see if theyve arrived on usenet yet.
	 *
	 * @param Nntp  $nntp
	 * @param array $group
	 *
	 * @return bool
	 */
	public function partRepair($nntp, $group)
	{
		$db = new DB();

		$parts = array();
		$chunks = array();
		$result = array();

		$query = sprintf
		(
			"SELECT numberID FROM partrepair WHERE groupID = %d AND attempts < 5 ORDER BY numberID ASC LIMIT 40000",
			$group['ID']
		);

		$result = $db->query($query);
		if (!count($result))
			return false;

		foreach ($result as $item)
			$parts[] = $item['numberID'];

		if (count($parts)) {
			$matched = array();
			printf("Repair: supposed to repair %s parts.%s", count($parts), $this->n);
			foreach ($parts as $key => $item) {
				if (in_array(substr($item, 0, -2), $matched))
					continue;

				# when moving to php >= 5.3
				#preg_filter(sprintf("~%s~", substr($item, 0, -3)), '$0', $parts);

				$result = preg_grep(sprintf("~%s~", substr($item, 0, -2)), $parts);
				if (count($result)) {
					$matched[] = substr($item, 0, -2);
					array_push($chunks, $result);

					foreach ($result as $key => $val)
						unset($parts[$key]);
				}
			}

			$chunks = $this->getSuperUniqueArray($chunks);

			if (!count($chunks)) {
				printf("Repair: unable to extract parts for repair! Please report this is a bug, together with a dump of your partrepair table.\n", $this->n);

				return false;
			}

			$repaired = 0;
			foreach ($chunks as $chunk) {
				$start = current($chunk);
				$end = end($chunk);

				# TODO: if less than 3 chunks do 3 single calls to scan()
				if (count($chunk) < 3) {
				}

				$range = ($end - $start);

				printf("Repair: + %s-%s (%s missing, %s articles overhead)%s", $start, $end, count($chunk), $range, $this->n);
				$this->scan($nntp, $group, $start, $end, 'partrepair');

				$query = sprintf
				(
					"SELECT pr.ID, pr.numberID, p.number from partrepair pr LEFT JOIN parts p ON p.number = pr.numberID WHERE pr.groupID=%d AND pr.numberID IN (%s) ORDER BY pr.numberID ASC",
					$group['ID'], implode(',', $chunk)
				);

				$result = $db->query($query);
				foreach ($result as $item) {
					# TODO: rewrite.. stupid
					if ($item['number'] == $item['numberID']) {
						#printf("Repair: %s repaired.%s", $item['ID'], $this->n);
						$db->queryExec(sprintf("DELETE FROM partrepair WHERE ID=%d LIMIT 1", $item['ID']));
						$repaired++;
						continue;
					} else {
						#printf("Repair: %s has not arrived yet or deleted.%s", $item['numberID'], $this->n);
						$db->queryExec(sprintf("update partrepair SET attempts=attempts+1 WHERE ID=%d LIMIT 1", $item['ID']));
					}
				}
			}

			$delret = $db->queryExec(sprintf('DELETE FROM partrepair WHERE attempts >= 5 AND groupID = %d', $group['ID']));
			$delcnt = $delret->rowCount();
			$db->log->doEcho($db->log->primary(sprintf('Repair: repaired %s', $repaired)));
			$db->log->doEcho($db->log->primary(sprintf('Repair: cleaned %s parts.', $delcnt)));

			return true;
		}

		return false;
	}

	/**
	 * Insert a missing part to the database.
	 */
	private function addMissingParts($numbers, $groupID)
	{
		$db = new DB();
		$added = false;
		$insertStr = "INSERT INTO partrepair (numberID, groupID) VALUES ";
		foreach ($numbers as $number) {
			if ($number > 0) {
				$checksql = sprintf("select numberID from partrepair where numberID = %u and groupID = %d", $number, $groupID);
				$chkrow = $db->queryOneRow($checksql);
				if ($chkrow) {
					$updsql = sprintf("update partrepair set attempts = attempts + 1 where numberID = %u and groupID = %d", $number, $groupID);
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
	 * Get blacklist and cache it. Return if already cached.
	 *
	 * @return void
	 */
	protected function retrieveBlackList()
	{
		if ($this->_blackListLoaded) {
			return;
		}
		$this->blackList = $this->getBlacklist(true);
		$this->_blackListLoaded = true;
		if (count($this->blackList) === 0) {
			$this->_blackListEmpty = true;
		}
	}

	/**
	 * Test if a message subject is blacklisted.
	 */
	public function isBlackListed($msg, $groupName)
	{
		if (empty($this->blackList_by_group[$groupName])) {
			$main_blackList = $this->retrieveBlackList();
			$this->blackList_by_group[$groupName] = array();
			foreach ($main_blackList as $blist) {
				if (preg_match('/^' . $blist['groupname'] . '$/i', $groupName)) {
					$this->blackList_by_group[$groupName][] = $blist;
				}
			}
		}

		$blackList = $this->blackList_by_group[$groupName];
		$field = array();
		if (isset($msg["Subject"]))
			$field[Binaries::BLACKLIST_FIELD_SUBJECT] = $msg["Subject"];

		if (isset($msg["From"]))
			$field[Binaries::BLACKLIST_FIELD_FROM] = $msg["From"];

		if (isset($msg["Message-ID"]))
			$field[Binaries::BLACKLIST_FIELD_MESSAGEID] = $msg["Message-ID"];

		// if a white list is detected we now are required to
		// only accept the entry if it matches at least 1 whitelist
		// while a blacklist will over-ride all
		$whitelist = array();
		$matches_whitelist = false;

		foreach ($blackList as $blist) {
			//blacklist
			if ($blist['optype'] == Binaries::OPT_BLACKLIST) {
				if (preg_match('/' . $blist['regex'] . '/i', $field[$blist['msgcol']])) {
					return true;
				}
			} else if ($blist['optype'] == Binaries::OPT_WHITELIST) {
				$whitelist[] = $blist['regex'];
				if (preg_match('/' . $blist['regex'] . '/i', $field[$blist['msgcol']])) {
					// Flag that we matched the white list
					$matches_whitelist = true;
				}
			}
		}

		# We parsed entire matching list entries at this point.. now we need
		# to handle the whitelist (if it was enabled)
		if (count($whitelist) > 0 && !$matches_whitelist) {
			# We failed to match white list
			return true;
		}

		return false;
	}

	/**
	 * Rawsearch. Perform a simple like match on binary subjects matching a pattern.
	 */
	public function search($search, $limit = 1000, $excludedcats = array())
	{
		$db = new DB();

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
			$exccatlist = " and b.categoryID not in (" . implode(",", $excludedcats) . ") ";

		$res = $db->query(sprintf("
					SELECT b.*,
					g.name AS group_name,
					r.guid,
					(SELECT COUNT(ID) FROM parts p where p.binaryID = b.ID) as 'binnum'
					FROM binaries b
					INNER JOIN groups g ON g.ID = b.groupID
					LEFT OUTER JOIN releases r ON r.ID = b.releaseID
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
		$db = new DB();

		return $db->query(sprintf("select binaries.* from binaries where releaseID = %d order by relpart", $id));
	}

	/**
	 * Get a binary row.
	 */
	public function getById($id)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("select binaries.*, groups.name as groupname from binaries left outer join groups on binaries.groupID = groups.ID where binaries.ID = %d ", $id));
	}

	/**
	 * Get list of blacklists from database.
	 */
	public function getBlacklist($activeonly = true)
	{
		$db = new DB();

		$where = "";
		if ($activeonly)
			$where = " where binaryblacklist.status = 1 ";

		return $db->query("SELECT binaryblacklist.ID, binaryblacklist.optype, binaryblacklist.status, binaryblacklist.description, binaryblacklist.groupname AS groupname, binaryblacklist.regex,
												groups.ID AS groupID, binaryblacklist.msgcol FROM binaryblacklist
												left outer JOIN groups ON groups.name = binaryblacklist.groupname
												" . $where . "
												ORDER BY coalesce(groupname,'zzz')"
		);
	}

	/**
	 * Get a blacklist row from database.
	 */
	public function getBlacklistByID($id)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("select * from binaryblacklist where ID = %d ", $id));
	}

	/**
	 * Delete a blacklist row from database.
	 */
	public function deleteBlacklist($id)
	{
		$db = new DB();

		return $db->queryExec(sprintf("DELETE from binaryblacklist where ID = %d", $id));
	}

	/**
	 * Update a blacklist row.
	 */
	public function updateBlacklist($regex)
	{
		$db = new DB();

		$groupname = $regex["groupname"];
		if ($groupname == "")
			$groupname = "null";
		else {
			$groupname = preg_replace("/a\.b\./i", "alt.binaries.", $groupname);
			$groupname = sprintf("%s", $db->escapeString($groupname));
		}

		$db->queryExec(sprintf("update binaryblacklist set groupname=%s, regex=%s, status=%d, description=%s, optype=%d, msgcol=%d where ID = %d ", $groupname, $db->escapeString($regex["regex"]), $regex["status"], $db->escapeString($regex["description"]), $regex["optype"], $regex["msgcol"], $regex["id"]));
	}

	/**
	 * Add a new blacklist row.
	 */
	public function addBlacklist($regex)
	{
		$db = new DB();

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
		$db = new DB();
		$db->queryExec(sprintf("DELETE from parts where binaryID = %d", $id));
		$db->queryExec(sprintf("DELETE from binaries where ID = %d", $id));
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
		$goalTime =          // The time we want =
			time()           // current unix time (ex. 1395699114)
			-                // minus
			(86400 * $days); // 86400 (seconds in a day) times days wanted. (ie 1395699114 - 2592000 (30days)) = 1393107114

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

		$totalArticles = (int)($lastDate - $firstDate);

		if ($this->_echoCLI) {
			$this->_colorCLI->doEcho(
				$this->_colorCLI->primary(
					'Searching for an approximate article number for group ' . $data['group'] . ' ' . $days . ' days back.'
				)
			);
		}

		switch (true) {
			case $totalArticles < 1000000:
				$matchPercentage = 1.0100;
				break;
			case $totalArticles < 10000000:
				$matchPercentage = 1.0070;

				break;
			case $totalArticles < 100000000:
				$matchPercentage = 1.0030;
				break;
			case $totalArticles < 500000000:
				$matchPercentage = 1.0010;
				break;
			case $totalArticles < 1000000000:
				$matchPercentage = 1.0008;
				break;
			default:
				$matchPercentage = 1.0005;
				break;
		}

		$wantedArticle = ($data['last'] * (($goalTime - $firstDate) / ($totalArticles)));
		$articleTime = 0;
		$percent = 1.01;
		for ($i = 0; $i < 100; $i++) {
			$wantedArticle = (int)$wantedArticle;

			if ($wantedArticle <= $data['first'] || $wantedArticle >= $data['last']) {
				break;
			}

			$articleTime = $this->postdate($wantedArticle, $data);
			if ($articleTime >= ($goalTime / $matchPercentage) && $articleTime <= ($goalTime * $matchPercentage)) {
				break;
			}

			if ($articleTime > $goalTime) {
				$wantedArticle /= $percent;
			} else if ($articleTime < $goalTime) {
				$wantedArticle *= $percent;
			}
			$percent -= 0.001;
		}

		$wantedArticle = (int)$wantedArticle;
		if ($this->_echoCLI) {
			$this->_colorCLI->doEcho(
				$this->_colorCLI->primary(
					'Found article #' . $wantedArticle . ' which has a date of ' . date('r', $articleTime) .
					', vs wanted date of ' . date('r', $goalTime) . '.'
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
						WHERE b.ID = p.binaryID
						AND b.groupID = %s
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
}