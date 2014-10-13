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
	 * Default constructor
	 */
	function Binaries()
	{
		$this->n = "\n";

		$s = new Sites();
		$site = $s->get();
		$this->messageBuffer = ($site->maxmssgs != '') ? $site->maxmssgs : 20000;
		$this->compressedHeaders = ($site->compressedheaders == "1") ? true : false;
		$this->MaxMsgsPerRun = (!empty($site->maxmsgsperrun)) ? $site->maxmsgsperrun : 200000;
		$this->NewGroupScanByDays = ($site->newgroupscanmethod == "1") ? true : false;
		$this->NewGroupMsgsToScan = (!empty($site->newgroupmsgstoscan)) ? $site->newgroupmsgstoscan : 50000;
		$this->NewGroupDaysToScan = (!empty($site->newgroupdaystoscan)) ? $site->newgroupdaystoscan : 3;
		$this->_tablePerGroup = ($site->tablepergroup == 1 ? true : false);

		$this->blackList = array(); //cache of our black/white list
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
			$last_record_postdate = $this->postdate($last, $data);
			if ($first_record_postdate != '')
				$db->queryExec(sprintf('update groups SET first_record = %s, first_record_postdate = FROM_UNIXTIME(' . $first_record_postdate . ') WHERE ID = %d', $db->escapeString($first), $groupArr['ID']));
		} else {
			if ($data['last'] < $groupArr['last_record']) {
				echo "Warning: Server's last num {$data['last']} is lower than the local last num {$groupArr['last_record']}" . $n;

				return;
			}
			$first = $groupArr['last_record'] + 1;
		}

		// Generate postdates for first and last records, for those that upgraded
		if ((is_null($groupArr['first_record_postdate']) || is_null($groupArr['last_record_postdate'])) && ($groupArr['last_record'] != '0' && $groupArr['first_record'] != '0'))
			$db->queryExec(sprintf('update groups SET first_record_postdate = FROM_UNIXTIME('.$backfill->postdate($nntp,$groupArr['first_record'],false).'), last_record_postdate = FROM_UNIXTIME('.$backfill->postdate($nntp,$groupArr['last_record'],false).') WHERE ID = %d', $groupArr['ID']));

		// Deactivate empty groups
		if (($data['last'] - $data['first']) <= 5)
			$db->queryExec(sprintf('update groups SET active = %s, last_updated = now() WHERE ID = %d', $db->escapeString('0'), $groupArr['ID']));

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
	 * Download a range of usenet messages. Store binaries with subjects matching a
	 * specific pattern in the database.
	 */
	function scan($nntp, $groupArr, $first, $last, $type = 'update')
	{
		$db = new Db();
		$releaseRegex = new ReleaseRegex;
		$n = $this->n;
		$this->startHeaders = microtime(true);
		// Check if MySQL tables exist, create if they do not, get their names at the same time.
		$tableNames = $this->_groups->getCBPTableNames($this->_tablePerGroup, $groupArr['ID']);

		if ($this->compressedHeaders) {
			$nntpn = new Nntp();
			$nntpn->doConnect(true);
			$msgs = $nntp->getOverview($first . "-" . $last, true, false);
			$nntpn->doQuit();
		} else
			$msgs = $nntp->getOverview($first . "-" . $last, true, false);

		if (PEAR::isError($msgs) && ($msgs->code == 400 || $msgs->code == 503)) {
			echo "NNTP connection timed out. Reconnecting...$n";
			if (!$nntp->doConnect()) {
				// TODO: What now?
				echo "Failed to get NNTP connection.$n";

				return;
			}
			$nntp->selectGroup($groupArr['name']);
			if ($this->compressedHeaders) {
				$nntpn = new Nntp();
				$nntpn->doConnect(true);
				$msgs = $nntp->getOverview($first . "-" . $last, true, false);
				$nntpn->doQuit();
			} else
				$msgs = $nntp->getOverview($first . "-" . $last, true, false);
		}

		$rangerequested = range($first, $last);
		$msgsreceived = array();
		$msgsblacklisted = array();
		$msgsignored = array();
		$msgsinserted = array();
		$msgsnotinserted = array();

		$timeHeaders = number_format(microtime(true) - $this->startHeaders, 2);

		if (PEAR::isError($msgs)) {
			echo "Error {$msgs->code}: {$msgs->message}$n";
			echo "Skipping group$n";

			return false;
		}

		$this->startUpdate = microtime(true);
		$this->startLoop = microtime(true);
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

				return false;
			}

			if (sizeof($rangenotreceived) > 0) {
				switch ($type) {
					case 'backfill':
						//don't add missing articles
						break;
					case 'partrepair':
					case 'update':
					default:
						$this->addMissingParts($rangenotreceived, $tableNames['prname'], $groupArr['ID']);
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
							$db->queryExec(sprintf("DELETE FROM %s WHERE numberID IN (%s) AND groupID=%d", $tableNames['prname'], implode(',', $partIds), $groupArr['ID']));
						}
						continue;
					}

					if (isset($data['Parts']) && count($data['Parts']) > 0 && $subject != '') {
						//Check for existing binary
						$binaryID = 0;
						$binaryHash = md5($subject . $data['From'] . $groupArr['ID']);
						$res = $db->queryOneRow(sprintf("SELECT ID FROM %s WHERE binaryhash = %s", $tableNames['bname'], $db->escapeString($binaryHash)));
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
								$sql = sprintf('INSERT INTO %s (name, fromname, date, xref, totalparts, groupID, procstat, categoryID, regexID, reqID, relpart, reltotalpart, binaryhash, relname, dateadded) VALUES (%s, %s, FROM_UNIXTIME(%s), %s, %s, %d, %d, %s, %d, %s, %d, %d, %s, %s, now())', $tableNames['bname'], $db->escapeString($subject), $db->escapeString(utf8_encode($data['From'])), $db->escapeString($data['Date']), $db->escapeString($data['Xref']), $db->escapeString($data['MaxParts']), $groupArr['ID'], Releases::PROCSTAT_TITLEMATCHED, $regexMatches['regcatid'], $regexMatches['regexID'], $db->escapeString($regexMatches['reqID']), $relparts[0], $relparts[1], $db->escapeString($binaryHash), $db->escapeString(str_replace('_', ' ', $regexMatches['name'])));
							} elseif ($this->onlyProcessRegexBinaries === false) {
								$sql = sprintf('INSERT INTO %s (name, fromname, date, xref, totalparts, groupID, binaryhash, dateadded) VALUES (%s, %s, FROM_UNIXTIME(%s), %s, %s, %d, %s, now())', $tableNames['bname'], $db->escapeString($subject), $db->escapeString(utf8_encode($data['From'])), $db->escapeString($data['Date']), $db->escapeString($data['Xref']), $db->escapeString($data['MaxParts']), $groupArr['ID'], $db->escapeString($binaryHash));
							} //onlyProcessRegexBinaries is true, there was no regex match and we are doing part repair so delete them
							elseif ($type == 'partrepair') {
								$partIds = array();
								foreach ($data['Parts'] as $partdata)
									$partIds[] = $partdata['number'];
								$db->queryExec(sprintf('DELETE FROM %s WHERE numberID IN (%s) AND groupID=%d', $tableNames['prname'], implode(',', $partIds), $groupArr['ID']));
								continue;
							}
							if ($sql != '') {
								$binaryID = $db->queryInsert($sql);
								$count++;
								if ($count % 500 == 0) echo "$count bin adds...";
							}
						} else {
							$binaryID = $res["ID"];
							$updatecount++;
							if ($updatecount % 500 == 0) echo "$updatecount bin updates...";
						}

						if ($binaryID != 0) {
							$partParams = array();
							$partNumbers = array();
							foreach ($data['Parts'] AS $partdata) {
								$partcount++;

								$partParams[] = sprintf('(%d, %s, %s, %s, %s)', $binaryID, $db->escapeString($partdata['Message-ID']), $db->escapeString($partdata['number']), $db->escapeString(round($partdata['part'])), $db->escapeString($partdata['size']));
								$partNumbers[] = $partdata['number'];
							}

							$partSql = sprintf('INSERT INTO ' . $tableNames['pname'] . ' (binaryID, messageID, number, partnumber, size) VALUES '.implode(', ', $partParams));
							$pidata = $db->queryInsert($partSql);
							if (!$pidata) {
								$msgsnotinserted = array_merge($msgsnotinserted, $partNumbers);
							} else {
								$msgsinserted = array_merge($msgsinserted, $partNumbers);
							}
						}
					}
				}
				//TODO: determine whether to add to missing articles if insert failed
				if (sizeof($msgsnotinserted) > 0) {
					echo 'WARNING: ' . count($msgsnotinserted) . ' Parts failed to insert' . $n;
					$this->addMissingParts($msgsnotinserted, $tableNames['prname'], $groupArr['ID']);
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
				echo number_format($count) . ' new, ' . number_format($updatecount) . ' updated, ' . number_format($partcount) . ' parts.';
				echo " $timeHeaders headers, $timeUpdate update, $timeLoop range.$n";
			}
			unset($this->message);
			unset($data);

			return $last;
		} else {
			echo "Error: Can't get parts from server (msgs not array) $n";
			echo "Skipping group$n";

			return false;
		}
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
		$tableNames = $this->_groups->getCBPTableNames($this->_tablePerGroup, $group['ID']);

		$parts = array();
		$chunks = array();
		$result = array();

		$query = sprintf
		(
			"SELECT numberID FROM %s WHERE groupID = %d AND attempts < 5 ORDER BY numberID ASC LIMIT 40000",
			$tableNames['prname'], $group['ID']
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
					"SELECT pr.ID, pr.numberID, p.number from %s pr LEFT JOIN %s p ON p.number = pr.numberID WHERE pr.groupID=%d AND pr.numberID IN (%s) ORDER BY pr.numberID ASC",
					$tableNames['prname'], $tableNames['pname'], $group['ID'], implode(',', $chunk)
				);

				$result = $db->query($query);
				foreach ($result as $item) {
					# TODO: rewrite.. stupid
					if ($item['number'] == $item['numberID']) {
						#printf("Repair: %s repaired.%s", $item['ID'], $this->n);
						$db->queryExec(sprintf("DELETE FROM %s WHERE ID=%d LIMIT 1", $tableNames['prname'], $item['ID']));
						$repaired++;
						continue;
					} else {
						#printf("Repair: %s has not arrived yet or deleted.%s", $item['numberID'], $this->n);
						$db->queryExec(sprintf("update %s SET attempts=attempts+1 WHERE ID=%d LIMIT 1", $tableNames['prname'], $item['ID']));
					}
				}
			}

			$delret = $db->queryExec(sprintf('DELETE FROM %s WHERE attempts >= 5 AND groupID = %d', $tableNames['prname'], $group['ID']));
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
	private function addMissingParts($numbers, $tablename, $groupID)
	{
		$db = new DB();
		$added = false;
		$insertStr = "INSERT INTO $tablename (numberID, groupID) VALUES ";
		foreach ($numbers as $number) {
			if ($number > 0) {
				$checksql = sprintf("select numberID from $tablename where numberID = %u and groupID = %d", $number, $groupID);
				$chkrow = $db->queryOneRow($checksql);
				if ($chkrow) {
					$updsql = sprintf('update ' . $tablename . ' set attempts = attempts + 1 where numberID = %u and groupID = %d', $number, $groupID);
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
	 * Return internally cached list of binary blacklist patterns.
	 */
	public function retrieveBlackList()
	{
		if (is_array($this->blackList) && !empty($this->blackList)) {
			return $this->blackList;
		}
		$blackList = $this->getBlacklist(true);
		$this->blackList = $blackList;

		return $blackList;
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
}