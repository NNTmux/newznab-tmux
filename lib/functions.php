<?php
require_once(dirname(__FILE__) . "/../bin/config.php");
require_once(WWW_DIR . "/lib/framework/cache.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/category.php");
require_once(WWW_DIR . "/lib/releases.php");
require_once(WWW_DIR . "/lib/releaseimage.php");
require_once(WWW_DIR . "/lib/nzb.php");
require_once(WWW_DIR . "/lib/rarinfo/par2info.php");
require_once(WWW_DIR . "/lib/rarinfo/archiveinfo.php");
require_once(WWW_DIR . "/lib/rarinfo/zipinfo.php");
require_once(WWW_DIR . "/lib/nfo.php");
require_once(WWW_DIR . "/lib/site.php");
require_once(WWW_DIR . "/lib/util.php");
require_once(WWW_DIR . "/lib/groups.php");
require_once(WWW_DIR . '/lib/nntp.php');
require_once(WWW_DIR . "/lib/postprocess.php");
require_once(WWW_DIR . "/lib/Tmux.php");
require_once("consoletools.php");
require_once("ColorCLI.php");
require_once("nzbcontents.php");
require_once("namefixer.php");
require_once("TraktTv.php");


//*addedd from nZEDb for testing

class Functions

{
	function __construct($echooutput = true)
	{
		$s = new Sites();
		$this->site = $s->get();
		$t = new Tmux();
		$this->tmux = $t->get();
		$this->p = new Postprocess();
		$this->echooutput = $echooutput;
		$this->c = new ColorCLI();
		$this->db = new DB();
		$this->m = new Movie();
		$this->consoleTools = new ConsoleTools();
		$this->tmpPath = $this->site->tmpunrarpath;
		$this->sleeptime = (!empty($this->tmux->amazonsleep)) ? $this->tmux->amazonsleep : 1000;
		$this->DEBUG_ECHO = ($this->tmux->debuginfo == '0') ? false : true;
		if (defined('DEBUG_ECHO') && DEBUG_ECHO == true) {
			$this->DEBUG_ECHO = true;
		}
		$this->debug = ($this->tmux->debuginfo == "0") ? false : true;

		$this->compressedHeaders = ($this->site->compressedheaders == '1') ? true : false;
		$this->safepartrepair = (!empty($this->tmux->safepartrepair)) ? $this->tmux->safepartrepair : 0;
		$this->safebdate = (!empty($this->tmux->safebackfilldate)) ? $this->tmux->safebackfilldate : '2012 - 06 - 24';
		$this->DoPartRepair = ($this->tmux->partrepair == '0') ? false : true;
		$this->messagebuffer = (!empty($this->site->maxmssgs)) ? (int)$this->site->maxmssgs : 20000;
		$this->NewGroupScanByDays = ($this->site->newgroupscanmethod == '1') ? true : false;
		$this->NewGroupMsgsToScan = (!empty($this->site->newgroupmsgstoscan)) ? (int)$this->site->newgroupmsgstoscan : 50000;
		$this->NewGroupDaysToScan = (!empty($this->site->newgroupdaystoscan)) ? (int)$this->site->newgroupdaystoscan : 3;
		$this->partrepairlimit = (!empty($this->tmux->maxpartrepair)) ? (int)$this->tmux->maxpartrepair : 15000;
	}

	/**
	 * @var object Instance of PDO class.
	 */
	private
	static $pdo = null;

	/**
	 * Should we use part repair?
	 *
	 * @var bool
	 */
	private
	$DoPartRepair;

	/**
	 * How many headers do we download per loop?
	 *
	 * @var int
	 */
	public
	$messagebuffer;

	/**
	 * How many days to go back on a new group?
	 *
	 * @var bool
	 */
	private
	$NewGroupScanByDays;

	/**
	 * Path to save large jpg pictures(xxx).
	 *
	 * @var string
	 */
	public
	$jpgSavePath;


	// database function
	public
	function queryArray($query)

	{
		$db = new DB();
		if ($query == '') return false;

		$result = $db->queryDirect($query);
		$rows = array();
		foreach ($result as $row) {
			$rows[] = $row;
		}

		return (!isset($rows)) ? false : $rows;
	}

	// Used for deleting, updating (and inserting without needing the last insert ID).
	public
	function exec($query)
	{
		if ($query == '')
			return false;

		try {
			$run = self::$pdo->prepare($query);
			$run->execute();

			return $run;
		} catch (PDOException $e) {
			// Deadlock or lock wait timeout, try 10 times.
			$i = 1;
			while (($e->errorInfo[1] == 1213 || $e->errorInfo[0] == 40001 || $e->errorInfo[1] == 1205 || $e->getMessage() == 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction') && $i <= 10) {
				echo $this->c->error("A Deadlock or lock wait timeout has occurred, sleeping.\n");
				$this->consoletools->showsleep($i * $i);
				$run = self::$pdo->prepare($query);
				$run->execute();

				return $run;
				$i++;
			}
			if ($e->errorInfo[1] == 1213 || $e->errorInfo[0] == 40001 || $e->errorInfo[1] == 1205) {
				//echo "Error: Deadlock or lock wait timeout.";
				return false;
			} else if ($e->errorInfo[1] == 1062 || $e->errorInfo[0] == 23000) {
				//echo "\nError: Update would create duplicate row, skipping\n";
				return false;
			} else if ($e->errorInfo[1] == 1406 || $e->errorInfo[0] == 22001) {
				//echo "\nError: Too large to fit column length\n";
				return false;
			} else
				echo $this->c->error($e->getMessage());

			return false;
		}
	}

	public
	function Prepare($query, $options = array())
	{
		try {
			$PDOstatement = self::$pdo->prepare($query, $options);
		} catch (PDOException $e) {
			//echo $this->c->error($e->getMessage());
			$PDOstatement = false;
		}

		return $PDOstatement;
	}

	public
	function from_unixtime($utime, $escape = true)
	{
		if ($escape === true) {
			return 'FROM_UNIXTIME(' . $utime . ')';
		} else
			return date('Y-m-d h:i:s', $utime);
	}

	// Date to unix time.
	// (substitute for mysql's UNIX_TIMESTAMP() function)
	public
	function unix_timestamp($date)
	{
		return strtotime($date);
	}

	public
	function unix_timestamp_column($column, $outputName = 'unix_time')
	{
		return 'UNIX_TIMESTAMP(' . $column . ') AS ' . $outputName;

	}

	/**
	 * Interpretation of mysql's UUID method.
	 * Return uuid v4 string. http://www.php.net/manual/en/function.uniqid.php#94959
	 *
	 * @return string
	 */
	public
	function uuid()
	{
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff)
		);
	}

	//  gets name of category from category.php
	public
	function getNameByID($ID)
	{
		$db = new DB();
		$parent = $db->queryOneRow(sprintf("SELECT title FROM category WHERE ID = %d", substr($ID, 0, 1) . "000"));
		$cat = $db->queryOneRow(sprintf("SELECT title FROM category WHERE ID = %d", $ID));

		return $parent["title"] . " " . $cat["title"];
	}

	public
	function getIDByName($name)
	{
		$db = new DB();
		$res = $db->queryOneRow(sprintf("SELECT ID FROM groups WHERE name = %s", $db->escapeString($name)));

		return $res["ID"];
	}

	//deletes from releases
	public
	function fastDelete($ID, $guid)
	{
		$nzb = new NZB();
		$ri = new ReleaseImage();
		$ri->delete($guid);


		//
		// delete from disk.
		//
		$nzbpath = $nzb->getNZBPath($guid);

		if (file_exists($nzbpath))
			unlink($nzbpath);

		$this->db->exec(sprintf("delete releases, releasenfo, releasecomment, usercart, releasefiles, releaseaudio, releasesubs, releasevideo, releaseextrafull
							from releases
								LEFT OUTER JOIN releasenfo on releasenfo.releaseID = releases.ID
								LEFT OUTER JOIN releasecomment on releasecomment.releaseID = releases.ID
								LEFT OUTER JOIN usercart on usercart.releaseID = releases.ID
								LEFT OUTER JOIN releasefiles on releasefiles.releaseID = releases.ID
								LEFT OUTER JOIN releaseaudio on releaseaudio.releaseID = releases.ID
								LEFT OUTER JOIN releasesubs on releasesubs.releaseID = releases.ID
								LEFT OUTER JOIN releasevideo on releasevideo.releaseID = releases.ID
								LEFT OUTER JOIN releaseextrafull on releaseextrafull.releaseID = releases.ID
							where releases.ID = %d", $ID
			)
		);
	}

	//reads name of group
	public
	function getByNameByID($ID)
	{
		$db = new DB();
		$res = $db->queryOneRow(sprintf("select name from groups where ID = %d ", $ID));

		return $res["name"];
	}

	// Check if the NZB is there, returns path, else false.
	function NZBPath($releaseGuid, $sitenzbpath = "")
	{
		$nzb = new NZB();
		$nzbfile = $nzb->getNZBPath($releaseGuid, $sitenzbpath, false);

		return !file_exists($nzbfile) ? false : $nzbfile;
	}

	// Sends releases back to other->misc.
	public
	function resetCategorize($where = '')
	{
		$this->db->exec('UPDATE releases SET categoryID = 8010, iscategorized = 0 ' . $where);
	}

	// Categorizes releases.
	// $type = name or searchname
	// Returns the quantity of categorized releases.
	public
	function categorizeRelease($type, $where = '', $echooutput = false)
	{
		$cat = new Category();
		$relcount = 0;
		$resrel = $this->db->queryDirect('SELECT ID, ' . $type . ', groupID FROM releases ' . $where);
		$total = $resrel->rowCount();
		if (count($resrel) > 0) {
			foreach ($resrel as $rowrel) {
				$catId = $cat->determineCategory($rowrel['groupID'], $rowrel[$type]);
				$this->db->exec(sprintf('UPDATE releases SET categoryID = %d, iscategorized = 1 WHERE ID = %d', $catId, $rowrel['ID']));
				$relcount++;
				if ($this->echooutput) {
					$this->consoleTools->overWritePrimary('Categorizing: ' . $this->consoleTools->percentString($relcount, $total));
				}
			}
		}
		if ($this->echooutput !== false && $relcount > 0) {
			echo "\n";
		}

		return $relcount;
	}

	// Optimises/repairs tables on mysql.
	public
	function optimise($admin = false, $type = '')
	{
		$db = new DB();
		$c = new ColorCLI();
		$tablecnt = 0;
		if ($type === 'true' || $type === 'full' || $type === 'analyze') {
			$alltables = $db->query('SHOW TABLE STATUS');
		} else {
			$alltables = $db->query('SHOW TABLE STATUS WHERE Data_free / Data_length > 0.005');
		}
		$tablecnt = count($alltables);
		if ($type === 'all' || $type === 'full') {
			$tbls = '';
			foreach ($alltables as $table) {
				$tbls .= $table['Name'] . ', ';
			}
			$tbls = rtrim(trim($tbls), ',');
			if ($admin === false) {
				echo $this->c->primary('Optimizing tables: ' . $tbls);
			}
			$db->queryDirect("OPTIMIZE LOCAL TABLE ${tbls}");
		} else {
			foreach ($alltables as $table) {
				if ($type === 'analyze') {
					if ($admin === false) {
						echo $this->c->primary('Analyzing table: ' . $table['Name']);
					}
					$db->queryDirect('ANALYZE LOCAL TABLE `' . $table['Name'] . '`');
				} else {
					if ($admin === false) {
						echo $this->c->primary('Optimizing table: ' . $table['Name']);
					}
					if (strtolower($table['engine']) == 'myisam') {
						$db->queryDirect('REPAIR TABLE `' . $table['Name'] . '`');
					}
					$db->queryDirect('OPTIMIZE LOCAL TABLE `' . $table['Name'] . '`');
				}
			}
		}
		if ($type !== 'analyze') {
			$db->queryDirect('FLUSH TABLES');
		}

		return $tablecnt;
	}

	function doecho($str)
	{
		if ($this->echooutput)
			echo $this->c->header($str);
	}

	// Convert 2012-24-07 to 2012-07-24, there is probably a better way
	public
	function checkDate($date)
	{
		if (!empty($date) && $date != null) {
			$chk = explode(" ", $date);
			$chkd = explode("-", $chk[0]);
			if ($chkd[1] > 12) {
				$date = date('Y-m-d H:i:s', strtotime($chkd[1] . " " . $chkd[2] . " " . $chkd[0]));
			}

			return $date;
		}

		return null;
	}

	public
	function updateReleaseHasPreview($guid)
	{
		$this->db->exec(sprintf('UPDATE releases SET haspreview = 1 WHERE guid = %s', $this->db->escapeString($guid)));
	}

	public
	function debug($str)
	{
		if ($this->echooutput && $this->DEBUG_ECHO) {
			echo $this->c->debug($str);
		}
	}

	/**
	 * Retrieve various information on a NZB file (the subject, # of pars,
	 * file extensions, file sizes, file completion, group names, # of parts).
	 *
	 * @param string $nzb The NZB contents in a string.
	 *
	 * @return array $result Empty if not an NZB or the contents of the NZB.
	 *
	 * @access public
	 */
	public function nzbFileList($nzb)
	{
		$num_pars = $i = 0;
		$result = array();

		$nzb = str_replace("\x0F", '', $nzb);
		$xml = @simplexml_load_string($nzb);
		if (!$xml || strtolower($xml->getName()) !== 'nzb') {
			return $result;
		}

		foreach ($xml->file as $file) {
			// Subject.
			$title = $file->attributes()->subject;

			// Amount of pars.
			if (stripos($title, '.par2')) {
				$num_pars++;
			}

			$result[$i]['title'] = $title;

			// Extensions.
			if (preg_match(
					'/\.(\d{2,3}|7z|ace|ai7|srr|srt|sub|aiff|asc|avi|audio|bin|bz2|'
					. 'c|cfc|cfm|chm|class|conf|cpp|cs|css|csv|cue|deb|divx|doc|dot|'
					. 'eml|enc|exe|file|gif|gz|hlp|htm|html|image|iso|jar|java|jpeg|'
					. 'jpg|js|lua|m|m3u|mkv|mm|mov|mp3|mp4|mpg|nfo|nzb|odc|odf|odg|odi|odp|'
					. 'ods|odt|ogg|par2|parity|pdf|pgp|php|pl|png|ppt|ps|py|r\d{2,3}|'
					. 'ram|rar|rb|rm|rpm|rtf|sfv|sig|sql|srs|swf|sxc|sxd|sxi|sxw|tar|'
					. 'tex|tgz|txt|vcf|video|vsd|wav|wma|wmv|xls|xml|xpi|xvid|zip7|zip)'
					. '[" ](?!(\)|\-))/i', $file->attributes()->subject, $ext)) {

				if (preg_match('/\.r\d{2,3}/i', $ext[0])) {
					$ext[1] = 'rar';
				}
				$result[$i]['ext'] = strtolower($ext[1]);
			} else {
				$result[$i]['ext'] = '';
			}

			$fileSize = $numSegments = 0;

			// Parts.
			if (!isset($result[$i]['segments'])) {
				$result[$i]['segments'] = array();
			}

			// File size.
			foreach ($file->segments->segment as $segment) {
				array_push($result[$i]['segments'], (string) $segment);
				$fileSize += $segment->attributes()->bytes;
				$numSegments++;
			}
			$result[$i]['size'] = $fileSize;

			// File completion.
			if (preg_match('/(\d+)\)$/', $title, $parts)) {
				$result[$i]['partstotal'] = $parts[1];
			}
			$result[$i]['partsactual'] = $numSegments;

			// Groups.
			if (!isset($result[$i]['groups'])) {
				$result[$i]['groups'] = array();
			}
			foreach ($file->groups->group as $g) {
				array_push($result[$i]['groups'], (string) $g);
			}

			unset($result[$i]['segments']['@attributes']);
			$i++;
		}
		return $result;
	}

	/**
	 * @param string $group
	 * @param int    $first
	 * @param int    $type
	 * @param object $nntp
	 *
	 * @return void
	 */
	function getFinal($group, $first, $type, $nntp)
	{
		if (!isset($nntp)) {
			exit($this->c->error("Not connected to usenet(backfill->getFinal).\n"));
		}

		$db = $this->db;
		$groups = new Groups();
		$groupArr = $groups->getByName($group);

		// Select group, here, only once
		$data = $nntp->selectGroup($groupArr['name']);
		if ($nntp->isError($data)) {
			$data = $nntp->dataError($nntp, $groupArr['name']);
			if ($nntp->isError($data)) {
				return;
			}
		}

		if ($type == 'Backfill') {
			$postsdate = $this->postdate($nntp, $first, false, $group, true, 'oldest');
		} else {
			$postsdate = $this->postdate($nntp, $first, false, $group, true, 'newest');
		}
		$postsdate = $this->from_unixtime($postsdate);

		if ($type == 'Backfill') {
			$db->exec(sprintf('UPDATE groups SET first_record_postdate = %s, first_record = %s, last_updated = NOW() WHERE ID = %d', $postsdate, $db->escapeString($first), $groupArr['ID']));
		} else {
			$db->exec(sprintf('UPDATE groups SET last_record_postdate = %s, last_record = %s, last_updated = NOW() WHERE ID = %d', $postsdate, $db->escapeString($first), $groupArr['ID']));
		}

		$this->doecho(
			$type .
			' Safe Threaded for ' .
			$group .
			" completed." .
			$this->c->rsetColor()
		);
	}

	/**
	 * Returns a single timestamp from a local article number.
	 * If the article is missing, you can pass $old as true to return false (then use the last known date).
	 *
	 * @param object $nntp
	 * @param int    $post
	 * @param bool   $debug
	 * @param string $group
	 * @param bool   $old
	 * @param string $type
	 *
	 * @return bool|int
	 */
	public
	function postdate($nntp, $post, $debug = true, $group, $old = false, $type)
	{
		if (!isset($nntp)) {
			$dmessage = "Not connected to usenet(backfill->postdate).";


			$this->c->error($dmessage);

			return false;
		}

		$db = $this->db;
		$keeppost = $post;

		$attempts = 0;
		$success = $record = false;
		do {
			$msgs = $nntp->getOverview($post . "-" . $post, true, false);
			$attempts++;
			if (!$nntp->isError($msgs)) {
				// Set table names
				$groups = new Groups();
				$groupID = $this->getIDByName($group);
				$groupa = array();
				$groupa['bname'] = 'binaries';
				$groupa['pname'] = 'parts';
				if ((!isset($msgs[0]['Date']) || $msgs[0]['Date'] == '' || is_null($msgs[0]['Date'])) && $attempts == 0) {
					$old_post = $post;
					if ($type == 'newest') {
						$res = $db->queryOneRow('SELECT p.number AS number FROM' . $groupa['bname'] . ' b, ' . $groupa['pname'] . ' p WHERE b.ID = b.releaseID AND b.ID = p.binaryID AND b.groupID = ' . $groupID . ' ORDER BY p.number DESC LIMIT 1');
						if (isset($res['number']) && is_numeric($res['number'])) {
							$post = $res['number'];
							$dmessage =
								"Unable to fetch article $old_post from " .
								str_replace('alt.binaries', 'a.b', $group) .
								". Retrying with newest article, from parts table, [$post] from ${groupa['pname']}";

							$this->c->info($dmessage);

						}
					} else {
						$res = $db->queryOneRow('SELECT p.number FROM ' . $groupa['bname'] . ' b, ' . $groupa['pname'] . ' p WHERE b.ID = p.binaryID AND b.groupID = ' . $groupID . ' ORDER BY p.number ASC LIMIT 1');
						if (isset($res['number']) && is_numeric($res['number'])) {
							$post = $res['number'];
							$dmessage =
								"Unable to fetch article $old_post from " .
								str_replace('alt.binaries', 'a.b', $group) .
								". Retrying with oldest article, from parts table, [$post] from ${groupa['pname']}.";

							$this->c->info($dmessage);

						}
					}
					$success = false;
				}
				if ((!isset($msgs[0]['Date']) || $msgs[0]['Date'] == '' || is_null($msgs[0]['Date'])) && $attempts != 0) {
					if ($type == 'newest') {
						$res = $db->queryOneRow('SELECT date FROM ' . $groupa['bname'] . ' ORDER BY date DESC LIMIT 1');
						if (isset($res['date'])) {
							$date = $res['date'];
							$dmessage =
								"Unable to fetch article $post from " .
								str_replace('alt.binaries', 'a.b', $group) .
								". Using newest date from ${groupa['bname']}.";

							$this->c->info($dmessage);

							if (strlen($date) > 0) {
								$success = true;
							}
						}
					} else {
						$res = $db->queryOneRow('SELECT date FROM ' . $groupa['bname'] . ' ORDER BY date ASC LIMIT 1');
						if (isset($res['date'])) {
							$date = $res['date'];
							$dmessage =
								"Unable to fetch article $post from " .
								str_replace('alt.binaries', 'a.b', $group) .
								". Using oldest date from ${groupa['bname']}.";

							$this->c->info($dmessage);

							if (strlen($date) > 0) {
								$success = true;
							}
						}
					}
				}

				if (isset($msgs[0]['Date']) && $msgs[0]['Date'] != '' && $success === false) {
					$date = $msgs[0]['Date'];
					if (strlen($date) > 0) {
						$success = true;
					}
				}

				if ($attempts > 0) {
					$this->c->debug('Retried ' . $attempts . " time(s).");
				}
			}
		} while ($attempts <= 20 && $success === false);

		if ($success === false && $old === true) {
			if ($type == 'oldest') {
				$res = $db->queryOneRow(sprintf("SELECT first_record_postdate from groups where name = '%s'", $group));
				if (array_key_exists('first_record_postdate', $res)) {
					$dmessage =
						'Unable to fetch article ' .
						$keeppost . ' from ' .
						str_replace('alt.binaries', 'a.b', $group) .
						'. Using current first_record_postdate[' .
						$res['first_record_postdate'] .
						"], instead.";

					$this->c->info($dmessage);

					return strtotime($res['first_record_postdate']);
				} else {
					return false;
				}
			} else {
				$res = $db->queryOneRow(sprintf("SELECT last_record_postdate from groups where name = '%s'", $group));
				if (array_key_exists('last_record_postdate', $res)) {
					$dmessage =
						'Unable to fetch article ' .
						$keeppost . ' from ' .
						str_replace('alt.binaries', 'a.b', $group) .
						'. Using current last_record_postdate[' .
						$res['last_record_postdate'] .
						"], instead.";

					$this->c->info($dmessage);

					return strtotime($res['last_record_postdate']);
				} else {
					return false;
				}
			}
		} else if ($success === false) {
			return false;
		}


		$date = strtotime($date);

		return $date;
	}

	/**
	 * Backfill groups using user specified article count.
	 *
	 * @param object $nntp
	 * @param string $groupName
	 * @param string $articles
	 * @param string $type
	 *
	 * @return void
	 */
	public
	function backfillPostAllGroups($nntp, $groupName = '', $articles = '', $type = '')
	{
		if (!isset($nntp)) {
			$dmessage = "Not connected to usenet(backfill->backfillPostAllGroups).\n";
			exit($this->c->error($dmessage));
		}

		$res = false;
		$groups = new Groups();
		if ($groupName != '') {
			$grp = $groups->getByName($groupName);
			if ($grp) {
				$res = array($grp);
			}
		} else {
			if ($type == 'normal') {
				$res = $this->getActiveBackfill();
			} else if ($type == 'date') {
				$res = $this->getActiveByDateBackfill();
			}
		}

		if ($res) {
			$counter = 1;
			$binaries = new Binaries();
			foreach ($res as $groupArr) {
				if ($groupName === '') {
					$dmessage = "\nStarting group " . $counter . ' of ' . sizeof($res);

					$this->c->header . $dmessage . $this->c->rsetColor();
				}
				$this->backfillPostGroup($nntp, $this->db, $binaries, $groupArr, sizeof($res) - $counter, $articles);
				$counter++;
			}
		} else {
			$dmessage = "No groups specified. Ensure groups are added to newznab's database for updating.";

			$this->c->warning($dmessage);
		}
	}

	/**
	 * @param string $group
	 * @param int    $first
	 * @param int    $last
	 * @param int    $threads
	 * @param object $nntp
	 *
	 * @return void
	 */
	public
	function getRange($group, $first, $last, $threads, $nntp)
	{
		if (!isset($nntp)) {
			exit($this->c->error("Not connected to usenet(backfill->getRange).\n"));
		}

		$groups = new Groups();
		$this->startGroup = microtime(true);
		$binaries = new Binaries();
		$groupArr = $groups->getByName($group);
		$process = $this->safepartrepair ? 'update' : 'backfill';

		$this->c->header(
			'Processing ' .
			str_replace('alt.binaries', 'a.b', $groupArr['name']) .
			(($this->compressedHeaders) ? ' Using Compression' : ' Not Using Compression') .
			' ==> T-' .
			$threads .
			' ==> ' .
			number_format($first) .
			' to ' .
			number_format($last) .
			$this->c->rsetColor()
		);
		$this->startLoop = microtime(true);

		// Select group, here, only once
		$data = $nntp->selectGroup($groupArr['name']);
		if ($nntp->isError($data)) {
			$data = $nntp->dataError($nntp, $groupArr['name']);
			if ($nntp->isError($data)) {
				return;
			}
		}

		$binaries->scan($nntp, $groupArr, $last, $first, $process);
	}

	/**
	 * Backfill all the groups up to user specified time/date.
	 *
	 * @param object $nntp
	 * @param string $groupName
	 *
	 * @return void
	 */
	public
	function backfillAllGroups($nntp, $groupName = '')
	{
		if (!isset($nntp)) {
			$dmessage = "Not connected to usenet(backfill->backfillAllGroups).\n";
			exit($this->c->error($dmessage));
		}

		$groups = new Groups();

		if ($groupName != '') {
			$grp = $groups->getByName($groupName);
			if ($grp) {
				$res = array($grp);
			}
		} else {
			$res = $this->getActiveBackfill();
		}


		if ($res) {
			$counter = 1;
			$db = $this->db;
			$binaries = new Binaries();
			foreach ($res as $groupArr) {
				if ($groupName === '') {
					$dmessage = "Starting group " . $counter . ' of ' . sizeof($res);

					$this->c->header . $dmessage . $this->c->rsetColor();
				}
				$this->backfillGroup($nntp, $db, $binaries, $groupArr, sizeof($res) - $counter);
				$counter++;
			}
		} else {
			$dmessage = "No groups specified. Ensure groups are added to newznab's database for updating.";
			$this->c->primary . $dmessage . $this->c->rsetColor();
		}
	}

	public
	function backfillGroup($nntp, $db, $binaries, $groupArr, $left)
	{
		if (!isset($nntp)) {
			$dmessage = "Not connected to usenet(backfill->backfillGroup).";
			exit($this->c->error($dmessage));
		}

		$this->startGroup = microtime(true);

		// Select group, here, only once
		$data = $nntp->selectGroup($groupArr['name']);
		if ($nntp->isError($data)) {
			$data = $nntp->dataError($nntp, $groupArr['name']);
			if ($nntp->isError($data)) {
				return;
			}
		}

		// Get targetpost based on days target.
		$targetpost = $this->daytopost($nntp, $groupArr['name'], $groupArr['backfill_target'], $data, true);
		if ($targetpost < 0) {
			$targetpost = round($data['first']);
		}

		if ($groupArr['first_record'] == 0 || $groupArr['backfill_target'] == 0) {
			$dmessage = "Group ${groupArr['name']} has invalid numbers. Have you run update on it? Have you set the backfill days amount?";

			$this->c->warning($dmessage);

			return;
		}

		// Check if we are grabbing further than the server has.
		if ($groupArr['first_record'] <= ($data['first'] + 50000)) {
			$dmessage = "We have hit the maximum we can backfill for " . str_replace('alt.binaries', 'a.b', $groupArr['name']) . ", skipping it.";


			$this->c->notice($dmessage);
			//$groups = new Groups();
			//$groups->disableForPost($groupArr['name']);
			return;
		}

		// If our estimate comes back with stuff we already have, finish.
		if ($targetpost >= $groupArr['first_record']) {
			$dmessage = "Nothing to do, we already have the target post.";

			$this->c->notice($dmessage);

			return;
		}

		$this->c->doecho(
			'Group ' .
			$data['group'] .
			': server has ' .
			number_format($data['first']) .
			' - ' .
			number_format($data['last']) .
			', or ~' .
			((int)
			((
					$this->postdate($nntp, $data['last'], false, $groupArr['name'], false, 'oldest') -
					$this->postdate($nntp, $data['first'], false, $groupArr['name'], false, 'oldest')) /
				86400
			)) .
			" days.\nLocal first = " .
			number_format($groupArr['first_record']) .
			' (' .
			((int)
			((
					date('U') -
					$this->postdate($nntp, $groupArr['first_record'], false, $groupArr['name'], false, 'oldest')) /
				86400
			)) .
			' days).  Backfill target of ' .
			$groupArr['backfill_target'] .
			' days is post ' .
			$targetpost, true
		);

		$done = false;
		// Set first and last, moving the window by maxxMssgs.
		$last = $groupArr['first_record'] - 1;
		// Set the initial "chunk".
		$first = $last - $this->messagebuffer + 1;

		// Just in case this is the last chunk we needed.
		if ($targetpost > $first) {
			$first = $targetpost;
		}

		// In case postdate doesn't get a date.
		if (is_null($groupArr['first_record_postdate']) || $groupArr['first_record_postdate'] == 'NULL') {
			$firstr_date = time();
		} else {
			$firstr_date = strtotime($groupArr['first_record_postdate']);
		}

		while ($done === false) {
			$this->startLoop = microtime(true);

			$this->c->header(
				'Getting ' .
				(number_format($last - $first + 1)) .
				" articles from " .
				str_replace('alt.binaries', 'a.b', $data['group']) .
				", " . $left .
				" group(s) left. (" .
				(number_format($first - $targetpost)) .
				" articles in queue)." .
				$this->c->rsetColor()
			);

			flush();
			$process = $this->safepartrepair ? 'update' : 'backfill';
			$binaries->scan($nntp, $groupArr, $first, $last, $process);
			$newdate = $this->postdate($nntp, $first, false, $groupArr['name'], true, 'oldest');

			if ($newdate !== false) {
				$firstr_date = $newdate;
			}

			$db->exec(sprintf('UPDATE groups SET first_record_postdate = %s, first_record = %s, last_updated = NOW() WHERE ID = %d', $this->from_unixtime($firstr_date), $db->escapeString($first), $groupArr['ID']));
			if ($first == $targetpost) {
				$done = true;
			} else {
				// Keep going: set new last, new first, check for last chunk.
				$last = $first - 1;
				$first = $last - $this->messagebuffer + 1;
				if ($targetpost > $first) {
					$first = $targetpost;
				}
			}
		}
		// Set group's first postdate.
		$db->exec(sprintf('UPDATE groups SET first_record_postdate = %s, last_updated = NOW() WHERE ID = %d', $this->from_unixtime($firstr_date), $groupArr['ID']));

		$timeGroup = number_format(microtime(true) - $this->startGroup, 2);

		$this->c->primary(
			'Group processed in ' .
			$timeGroup .
			" seconds." .
			$this->c->rsetColor()
		);

	}

	/**
	 * Returns article number based on # of days.
	 *
	 * @param object $nntp
	 * @param string $group
	 * @param int    $days
	 * @param array  $data
	 * @param bool   $debug
	 *
	 * @return string
	 */
	public
	function daytopost($nntp, $group, $days, $data, $debug = true)
	{
		if (!isset($nntp)) {
			$dmessage = "Not connected to usenet(backfill->daytopost).\n";
			exit($this->c->error($dmessage));
		}
		$pddebug = false;
		// Goal timestamp.
		$goaldate = date('U') - (86400 * $days);
		$totalnumberofarticles = $data['last'] - $data['first'];
		$upperbound = $data['last'];
		$lowerbound = $data['first'];

		if ($data['last'] == PHP_INT_MAX) {
			$dmessage = "Group data is coming back as php's max value. You should not see this since we use a patched Net_NNTP that fixes this bug.\n";
			exit($this->c->info($dmessage));
		}

		$firstDate = $this->postdate($nntp, $data['first'], $pddebug, $group, false, 'oldest');
		$lastDate = $this->postdate($nntp, $data['last'], $pddebug, $group, false, 'oldest');

		if ($goaldate < $firstDate) {
			$dmessage =
				"Backfill target of $days day(s) is older than the first article stored on your news server.\nStarting from the first available article (" .
				date('r', $firstDate) . ' or ' .
				$this->daysOld($firstDate) . " days).";

			$this->c->warning($dmessage);

			return $data['first'];
		} else if ($goaldate > $lastDate) {
			$dmessage =
				'Backfill target of ' .
				$days .
				" day(s) is newer than the last article stored on your news server.\nTo backfill this group you need to set Backfill Days to at least " .
				ceil($this->daysOld($lastDate) + 1) .
				' days (' .
				date('r', $lastDate - 86400) .
				").";

			$this->c->error($dmessage);

			return '';
		}


		$interval = floor(($upperbound - $lowerbound) * 0.5);
		$templowered = '';
		$dateofnextone = $lastDate;

		// Match on days not timestamp to speed things up.
		while ($this->daysOld($dateofnextone) < $days) {
			while (($tmpDate = $this->postdate($nntp, ($upperbound - $interval), $pddebug, $group, false, 'oldest')) > $goaldate) {
				$upperbound = $upperbound - $interval;
			}

			if (!$templowered) {
				$interval = ceil(($interval / 2));
			}
			$dateofnextone = $this->postdate($nntp, ($upperbound - 1), $pddebug, $group, false, 'oldest');
			while (!$dateofnextone) {
				$dateofnextone = $this->postdate($nntp, ($upperbound - 1), $pddebug, $group, false, 'oldest');
			}
		}

		$dmessage =
			'Determined to be article: ' .
			number_format($upperbound) .
			' which is ' .
			$this->daysOld($dateofnextone) .
			' days old (' .
			date('r', $dateofnextone) .
			')';


		$this->c->doecho($dmessage, true);

		return $upperbound;
	}

	/**
	 * Convert unix time to days ago.
	 *
	 * @param int $timestamp unix time
	 *
	 * @return float
	 */
	private
	function daysOld($timestamp)
	{
		return round((time() - (!is_numeric($timestamp) ? strtotime($timestamp) : $timestamp)) / 86400, 1);
	}

	/**
	 * Safe backfill using posts. Going back to a date specified by the user on the site settings.
	 * This does 1 group for x amount of parts until it reaches the date.
	 *
	 * @param object $nntp
	 * @param string $articles
	 *
	 * @return void
	 */
	public
	function safeBackfill($nntp, $articles = '')
	{
		if (!isset($nntp)) {
			$dmessage = "Not connected to usenet(backfill->safeBackfill).\n";
			exit($this->c->error($dmessage));
		}

		$db = $this->db;
		$groupname = $db->queryOneRow(sprintf('SELECT name FROM groups WHERE first_record_postdate BETWEEN %s AND NOW() AND backfill = 1 ORDER BY name ASC', $db->escapeString($this->safebdate)));

		if (!$groupname) {
			$dmessage =
				'No groups to backfill, they are all at the target date ' .
				$this->safebdate .
				", or you have not enabled them to be backfilled in the groups page.\n";
			exit($dmessage);
		} else {
			$this->backfillPostAllGroups($nntp, $groupname['name'], $articles, $type = '');
		}
	}

	/**
	 * @param object $nntp
	 * @param object $db
	 * @param object $binaries
	 * @param array  $groupArr
	 * @param int    $left
	 * @param string $articles
	 *
	 * @return void
	 */
	public
	function backfillPostGroup($nntp, $db, $binaries, $groupArr, $left, $articles = '')
	{
		if (!isset($nntp)) {
			$dmessage = "Not connected to usenet(backfill->backfillPostGroup).\n";
			exit($this->c->error($dmessage));
		}

		$this->startGroup = microtime(true);

		$this->c->header(
			'Processing ' .
			$groupArr['name'] .
			$this->c->rsetColor()
		);

		// Select group, here, only once
		$data = $nntp->selectGroup($groupArr['name']);
		if ($nntp->isError($data)) {
			$data = $nntp->dataError($nntp, $groupArr['name']);
			if ($nntp->isError($data)) {
				return;
			}
		}

		// Get targetpost based on days target.
		$targetpost = round($groupArr['first_record'] - $articles);
		if ($targetpost < 0) {
			$targetpost = round($data['first']);
		}

		if ($groupArr['first_record'] <= 0 || $targetpost <= 0) {
			$dmessage =
				"You need to run update_binaries on " .
				str_replace('alt.binaries', 'a.b', $data['group']) .
				". Otherwise the group is dead, you must disable it.";

			$this->c->error($dmessage);

			return;
		}

		// Check if we are grabbing further than the server has.
		if ($groupArr['first_record'] <= $data['first'] + $articles) {
			$dmessage =
				"We have hit the maximum we can backfill for " .
				str_replace('alt.binaries', 'a.b', $groupArr['name']) .
				", skipping it.";

			$this->c->notice($dmessage);

			//$groups = new Groups();
			//$groups->disableForPost($groupArr['name']);
			return;
		}

		// If our estimate comes back with stuff we already have, finish.
		if ($targetpost >= $groupArr['first_record']) {
			$dmessage = "Nothing to do, we already have the target post.";

			$this->c->notice($dmessage);

			return;
		}

		$this->c->primary(
			'Group ' . $data['group'] .
			"'s oldest article is " .
			number_format($data['first']) .
			', newest is ' .
			number_format($data['last']) .
			'. The groups retention is: ' .
			((int)
			((
					$this->postdate($nntp, $data['last'], false, $groupArr['name'], false, 'oldest') -
					$this->postdate($nntp, $data['first'], false, $groupArr['name'], false, 'oldest')) /
				86400
			)) .
			" days.\nOur oldest article is: " .
			number_format($groupArr['first_record']) .
			' which is (' .
			((int)
			((
					date('U') -
					$this->postdate($nntp, $groupArr['first_record'], false, $groupArr['name'], false, 'oldest')) /
				86400
			)) .
			' days old). Our backfill target is article ' .
			number_format($targetpost) .
			' which is (' .
			((int)
			((
					date('U') -
					$this->postdate($nntp, $targetpost, false, $groupArr['name'], false, 'oldest')) /
				86400
			)) .
			"\n days old)." .
			$this->c->rsetColor()
		);


		// Calculate total number of parts.
		$done = false;
		// Set first and last, moving the window by maxxMssgs.
		$last = $groupArr['first_record'] - 1;
		// Set the initial "chunk".
		$first = $last - $this->messagebuffer + 1;
		// Just in case this is the last chunk we needed.
		if ($targetpost > $first) {
			$first = $targetpost;
		}

		// In case postdate doesn't get a date.
		if (is_null($groupArr['first_record_postdate']) || $groupArr['first_record_postdate'] == 'NULL') {
			$firstr_date = time();
		} else {
			$firstr_date = strtotime($groupArr['first_record_postdate']);
		}

		while ($done === false) {
			$this->startLoop = microtime(true);

			$this->c->header(
				"\nGetting " .
				($last - $first + 1) .
				" articles from " .
				str_replace('alt.binaries', 'a.b', str_replace('alt.binaries', 'a.b', $data['group'])) .
				", " .
				$left .
				" group(s) left. (" .
				(number_format($first - $targetpost)) .
				" articles in queue)" .
				$this->c->rsetColor()
			);

			flush();
			$process = $this->safepartrepair ? 'update' : 'backfill';
			$binaries->scan($nntp, $groupArr, $first, $last, $process);
			$newdate = $this->postdate($nntp, $first, false, $groupArr['name'], true, 'oldest');
			if ($newdate !== false) {
				$firstr_date = $newdate;
			}

			$db->exec(sprintf('UPDATE groups SET first_record_postdate = %s, first_record = %s, last_updated = NOW() WHERE ID = %d', $this->from_unixtime($firstr_date), $db->escapeString($first), $groupArr['ID']));
			if ($first == $targetpost) {
				$done = true;
			} else {
				// Keep going: set new last, new first, check for last chunk.
				$last = $first - 1;
				$first = $last - $this->messagebuffer + 1;
				if ($targetpost > $first) {
					$first = $targetpost;
				}
			}
		}
		// Set group's first postdate.
		$db->exec(sprintf('UPDATE groups SET first_record_postdate = %s, last_updated = NOW() WHERE ID = %d', $this->from_unixtime($firstr_date), $groupArr['ID']));

		$timeGroup = number_format(microtime(true) - $this->startGroup, 2);


		$this->c->header(
			$data['group'] .
			' processed in ' .
			$timeGroup .
			" seconds." .
			$this->c->rsetColor()
		);
	}

	/**
	 * Download new headers for a single group.
	 *
	 * @param array  $groupArr Array of MySQL results for a single group.
	 * @param object $nntp     Instance of class NNTP
	 *
	 * @return void
	 */
	public
	function updateGroup($groupArr, $nntp)
	{
		if (!isset($nntp)) {
			$message = "Not connected to usenet(binaries->updateGroup).";
			exit($this->c->error($message));
		}


		$this->startGroup = microtime(true);
		$this->c->primary('Processing ' . str_replace('alt.binaries', 'a.b', $groupArr['name']));
		$binaries = new Binaries();


		// Select the group, here, needed for processing the group
		$data = $nntp->selectGroup($groupArr['name']);
		if ($nntp->isError($data)) {
			$data = $nntp->dataError($nntp, $groupArr['name']);
			if ($nntp->isError($data)) {
				return;
			}
		}


		// Attempt to repair any missing parts before grabbing new ones.
		if ($groupArr['last_record'] != 0) {
			if ($this->DoPartRepair) {
				$this->c->primary("Part repair enabled. Checking for missing parts.");
				$this->partRepair($nntp, $groupArr);
			} else {
				$this->c->primary("Part repair disabled by user.");
			}
		}

		// Get first and last part numbers from newsgroup.
		$db = $this->db;

		if ($groupArr['last_record'] == 0) {
			// For new newsgroups - determine here how far you want to go back.
			if ($this->NewGroupScanByDays) {
				$first = $this->daytopost($this->NewGroupDaysToScan, $data);
				if ($first == '') {
					$this->c->warning("Skipping group: {$groupArr['name']}");

					return;
				}
			} else {
				if ($data['first'] > ($data['last'] - ($this->NewGroupMsgsToScan + $this->messagebuffer))) {
					$first = $data['first'];
				} else {
					$first = $data['last'] - ($this->NewGroupMsgsToScan + $this->messagebuffer);
				}
			}

			$left = $this->messagebuffer;
			$last = $grouplast = $data['last'] - $left;
		} else {
			$first = $groupArr['last_record'];

			// Leave 50%+ of the new articles on the server for next run (allow server enough time to actually make parts available).
			$newcount = $data['last'] - $first;
			$left = 0;
			if ($newcount > $this->messagebuffer) {
				// Drop the remaining plus $this->messagebuffer, pick them up on next run
				if ($newcount < (2 * $this->messagebuffer)) {
					$left = ((int)($newcount / 2));
					$last = $grouplast = ($data['last'] - $left);
				} else {
					$remainingcount = $newcount % $this->messagebuffer;
					$left = $remainingcount + $this->messagebuffer;
					$last = $grouplast = ($data['last'] - $left);
				}
			} else {
				$left = ((int)($newcount / 2));
				$last = $grouplast = ($data['last'] - $left);
			}
		}

		// Generate postdate for first record, for those that upgraded.
		if (is_null($groupArr['first_record_postdate']) && $groupArr['first_record'] != '0') {
			$newdate = $this->postdate($groupArr['first_record'], $data);
			if ($newdate !== false) {
				$first_record_postdate = $newdate;
			} else {
				$first_record_postdate = time();
			}

			$groupArr['first_record_postdate'] = $first_record_postdate;

			$db->exec(sprintf('UPDATE groups SET first_record_postdate = %s WHERE ID = %d', $this->from_unixtime($first_record_postdate), $groupArr['ID']));
		}

		// Defaults for post record first/last postdate
		if (is_null($groupArr['first_record_postdate'])) {
			$first_record_postdate = time();
		} else {
			$first_record_postdate = strtotime($groupArr['first_record_postdate']);
		}

		if (is_null($groupArr['last_record_postdate'])) {
			$last_record_postdate = time();
		} else {
			$last_record_postdate = strtotime($groupArr['last_record_postdate']);
		}


		// Calculate total number of parts.
		$total = $grouplast - $first;
		$realtotal = $data['last'] - $first;

		// If total is bigger than 0 it means we have new parts in the newsgroup.
		if ($total > 0) {
			if ($groupArr['last_record'] == 0) {
				$this->c->primary(
					'New group ' .
					$data['group'] .
					' starting with ' .
					(($this->NewGroupScanByDays) ? $this->NewGroupDaysToScan
						. ' days' : number_format($this->NewGroupMsgsToScan) .
						' messages'
					) .
					" worth. Leaving " .
					number_format($left) .
					" for next pass.\nServer oldest: " .
					number_format($data['first']) .
					' Server newest: ' .
					number_format($data['last']) .
					' Local newest: ' .
					number_format($groupArr['last_record'])
				);
			} else {
				$this->c->primary(
					'Group ' .
					$data['group'] .
					' has ' .
					number_format($realtotal) .
					" new articles. Leaving " .
					number_format($left) .
					" for next pass.\nServer oldest: " .
					number_format($data['first']) . ' Server newest: ' .
					number_format($data['last']) .
					' Local newest: ' .
					number_format($groupArr['last_record'])
				);
			}

			$done = false;
			// Get all the parts (in portions of $this->messagebuffer to not use too much memory).
			while ($done === false) {
				$this->startLoop = microtime(true);

				if ($total > $this->messagebuffer) {
					if ($first + $this->messagebuffer > $grouplast) {
						$last = $grouplast;
					} else {
						$last = $first + $this->messagebuffer;
					}
				}
				$first++;
				$this->c->header(
					"Getting " .
					number_format($last - $first + 1) .
					' articles (' . number_format($first) .
					' to ' .
					number_format($last) .
					') from ' .
					str_replace('alt.binaries', 'a.b', $data['group']) .
					" - (" .
					number_format($grouplast - $last) .
					" articles in queue)."
				);
				flush();

				// Get article headers from newsgroup. Let scan deal with nntp connection, else compression fails after first grab
				$scanSummary = $binaries->scan($nntp, $groupArr, $first, $last);

				// Scan failed - skip group.
				if ($scanSummary == false) {
					return;
				}

				// If new group, update first record & postdate
				if (is_null($groupArr['first_record_postdate']) && $groupArr['first_record'] == '0') {
					$groupArr['first_record'] = $scanSummary['firstArticleNumber'];

					if (isset($scanSummary['firstArticleDate'])) {
						$first_record_postdate = strtotime($scanSummary['firstArticleDate']);
					}

					$groupArr['first_record_postdate'] = $first_record_postdate;

					$db->exec(sprintf('UPDATE groups SET first_record = %s, first_record_postdate = %s WHERE ID = %d', $scanSummary['firstArticleNumber'], $this->from_unixtime($db->escapeString($first_record_postdate)), $groupArr['ID']));
				}

				if (isset($scanSummary['lastArticleDate'])) {
					$last_record_postdate = strtotime($scanSummary['lastArticleDate']);
				}

				$db->exec(sprintf('UPDATE groups SET last_record = %s, last_record_postdate = %s, last_updated = NOW() WHERE ID = %d', $db->escapeString($scanSummary['lastArticleNumber']), $this->from_unixtime($last_record_postdate), $groupArr['ID']));

				if ($last == $grouplast) {
					$done = true;
				} else {
					$first = $last;
				}
			}
			$timeGroup = number_format(microtime(true) - $this->startGroup, 2);
			$this->c->primary($data['group'] . ' processed in ' . $timeGroup . " seconds.");
		} else {
			$this->c->primary(
				'No new articles for ' .
				$data['group'] .
				' (first ' .
				number_format($first) .
				' last ' .
				number_format($last) .
				' grouplast ' .
				number_format($groupArr['last_record']) .
				' total ' . number_format($total) .
				")\n" .
				"Server oldest: " .
				number_format($data['first']) .
				' Server newest: ' .
				number_format($data['last']) .
				' Local newest: ' .
				number_format($groupArr['last_record'])
			);
		}
	}

	/**
	 * Attempt to get missing headers.
	 *
	 * @param $nntp     Instance of class NNTP.
	 * @param $groupArr The info for this group from mysql.
	 *
	 * @return void
	 */
	public
	function partRepair($nntp, $groupArr)
	{
		if (!isset($nntp)) {
			$dmessage = "Not connected to usenet(functions->partRepair).";
			exit($this->c->error("Not connected to usenet(functions->partRepair)."));
		}

		// Get all parts in partrepair table.
		$db = $this->db;

		// Check that tables exist, create if they do not
		$group['prname'] = 'partrepair';

		$missingParts = $db->query(sprintf('SELECT * FROM ' . $group['prname'] . ' WHERE groupID = %d AND attempts < 5 ORDER BY numberID ASC LIMIT %d', $groupArr['ID'], $this->partrepairlimit));
		$partsRepaired = $partsFailed = 0;

		if (sizeof($missingParts) > 0) {
			$this->consoleTools->overWritePrimary(
				'Attempting to repair ' .
				number_format(sizeof($missingParts)) .
				" parts."
			);

			// Loop through each part to group into continuous ranges with a maximum range of messagebuffer/4.
			$ranges = array();
			$partlist = array();
			$firstpart = $lastnum = $missingParts[0]['numberID'];
			foreach ($missingParts as $part) {
				if (($part['numberID'] - $firstpart) > ($this->messagebuffer / 4)) {
					$ranges[] = array('partfrom' => $firstpart, 'partto' => $lastnum, 'partlist' => $partlist);
					$firstpart = $part['numberID'];
					$partlist = array();
				}
				$partlist[] = $part['numberID'];
				$lastnum = $part['numberID'];
			}
			$ranges[] = array('partfrom' => $firstpart, 'partto' => $lastnum, 'partlist' => $partlist);

			$num_attempted = 0;

			// Download missing parts in ranges.
			foreach ($ranges as $range) {
				$this->startLoop = microtime(true);

				$partfrom = $range['partfrom'];
				$partto = $range['partto'];
				$partlist = $range['partlist'];
				$count = sizeof($range['partlist']);

				$num_attempted += $count;
				$this->consoleTools->overWritePrimary("Attempting repair: " . $this->consoleTools->percentString2($num_attempted - $count + 1, $num_attempted, sizeof($missingParts)) . ': ' . $partfrom . ' to ' . $partto);

				// Get article from newsgroup.
				$binaries->scan($nntp, $groupArr, $partfrom, $partto, 'update');
			}

			// Calculate parts repaired
			$sql = sprintf('SELECT COUNT(ID) AS num FROM ' . $group['prname'] . ' WHERE groupID=%d AND numberID <= %d', $groupArr['ID'], $missingParts[sizeof($missingParts) - 1]['numberID']);
			$result = $db->queryOneRow($sql);
			if (isset($result['num'])) {
				$partsRepaired = (sizeof($missingParts)) - $result['num'];
			}

			// Update attempts on remaining parts for active group
			if (isset($missingParts[sizeof($missingParts) - 1]['ID'])) {
				$sql = sprintf("UPDATE ${group['prname']} SET attempts=attempts+1 WHERE groupID=%d AND numberID <= %d", $groupArr['ID'], $missingParts[sizeof($missingParts) - 1]['numberID']);
				$result = $db->exec($sql);
				if ($result) {
					$partsFailed = $result->rowCount();
				}
			}

			$this->c->primary(
				"\n" .
				number_format($partsRepaired) .
				" parts repaired."
			);
		}

		// Remove articles that we cant fetch after 5 attempts.
		$db->exec(sprintf('DELETE FROM ' . $group['prname'] . ' WHERE attempts >= 5 AND groupID = %d', $groupArr['ID']));
	}

	/**
	 * Use cURL To download a web page into a string.
	 *
	 * @param string $url       The URL to download.
	 * @param string $method    get/post
	 * @param string $postdata  If using POST, post your POST data here.
	 * @param string $language  Use alternate langauge in header.
	 * @param bool   $debug     Show debug info.
	 * @param string $userAgent User agent.
	 * @param string $cookie    Cookie.
	 *
	 * @return bool|mixed
	 */
	function getUrl($url, $method = 'get', $postdata = '', $language = "", $debug = false, $userAgent = '', $cookie = '')
	{
		switch ($language) {
			case 'fr':
			case 'fr-fr':
				$language = "fr-fr";
				break;
			case 'de':
			case 'de-de':
				$language = "de-de";
				break;
			case 'en':
				$language = 'en';
				break;
			case '':
			case 'en-us':
			default:
				$language = "en-us";
		}
		$header[] = "Accept-Language: " . $language;

		$ch = curl_init();
		$options = array(
			CURLOPT_URL            => $url,
			CURLOPT_HTTPHEADER     => $header,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FOLLOWLOCATION => 1,
			CURLOPT_TIMEOUT        => 15,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
		);
		curl_setopt_array($ch, $options);

		if ($userAgent !== '') {
			curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
		}

		if ($cookie !== '') {
			curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		}

		if ($method === 'post') {
			$options = array(
				CURLOPT_POST       => 1,
				CURLOPT_POSTFIELDS => $postdata
			);
			curl_setopt_array($ch, $options);
		}

		if ($debug) {
			$options =
				array(
					CURLOPT_HEADER      => true,
					CURLINFO_HEADER_OUT => true,
					CURLOPT_NOPROGRESS  => false,
					CURLOPT_VERBOSE     => true
				);
			curl_setopt_array($ch, $options);
		}

		$buffer = curl_exec($ch);
		$err = curl_errno($ch);
		curl_close($ch);

		if ($err !== 0) {
			return false;
		} else {
			return $buffer;
		}
	}

	/**
	 * Formats a 'like' string. ex.(LIKE '%chocolate%')
	 *
	 * @param string $str   The string.
	 * @param bool   $left  Add a % to the left.
	 * @param bool   $right Add a % to the right.
	 *
	 * @return string
	 */
	public
	function likeString($str, $left = true, $right = true)
	{
		return (
			(DB_TYPE === 'mysql' ? 'LIKE ' : 'ILIKE ') .
			$this->db->escapeString(
				($left ? '%' : '') .
				$str .
				($right ? '%' : '')
			)
		);
	}

	// Check if O/S is windows.
	function isWindows()
	{
		return (strtolower(substr(php_uname('s'), 0, 3)) === 'win');
	}

	/**
	 * Run CLI command.
	 *
	 * @param string $command
	 * @param bool   $debug
	 *
	 * @return array
	 */
	function runCmd($command, $debug = false)
	{
		$nl = PHP_EOL;
		if (isWindows() && strpos(phpversion(), "5.2") !== false) {
			$command = "\"" . $command . "\"";
		}

		if ($debug) {
			echo '-Running Command: ' . $nl . '   ' . $command . $nl;
		}

		$output = array();
		$status = 1;
		@exec($command, $output, $status);

		if ($debug) {
			echo '-Command Output: ' . $nl . '   ' . implode($nl . '  ', $output) . $nl;
		}

		return $output;
	}

	// Convert obj to array.
	function objectsIntoArray($arrObjData, $arrSkipIndices = array())
	{
		$arrData = array();

		// If input is object, convert into array.
		if (is_object($arrObjData)) {
			$arrObjData = get_object_vars($arrObjData);
		}

		if (is_array($arrObjData)) {
			foreach ($arrObjData as $index => $value) {
				// Recursive call.
				if (is_object($value) || is_array($value)) {
					$value = objectsIntoArray($value, $arrSkipIndices);
				}
				if (in_array($index, $arrSkipIndices)) {
					continue;
				}
				$arrData[$index] = $value;
			}
		}

		return $arrData;
	}

	/**
	 * Convert bytes to kb/mb/gb/tb and return in human readable format.
	 *
	 * @param int $bytes
	 *
	 * @return string
	 */
	protected
	function readableBytesString($bytes)
	{
		$kb = 1024;
		$mb = $kb * $kb;
		$gb = $kb * $mb;
		$tb = $kb * $gb;
		if ($bytes < $kb) {
			return $bytes . 'B';
		} else if ($bytes < ($mb)) {
			return round($bytes / $kb, 1) . 'KB';
		} else if ($bytes < $gb) {
			return round($bytes / $mb, 1) . 'MB';
		} else if ($bytes < $tb) {
			return round($bytes / $gb, 1) . 'GB';
		} else {
			return round($bytes / $tb, 1) . 'TB';
		}
	}

	/**
 	* Get human readable size string from bytes.
 	*
 	* @param int $bytes     Bytes number to convert.
 	* @param int $precision How many floating point units to add.
 	*
 	* @return string
 	*/
	function bytesToSizeString($bytes, $precision = 0)
	{
    if ($bytes == 0) {
		return '0B';
	}

	$unit = array('B','KB','MB','GB','TB','PB','EB');
	return round($bytes / pow(1024, ($i = floor(log($bytes, 1024)))), $precision) . $unit[(int)$i];
}



	//end of testing

}