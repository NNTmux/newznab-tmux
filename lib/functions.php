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
require_once(WWW_DIR . "/lib/tvrage.php");
require_once(WWW_DIR . "/lib/movie.php");
require_once(WWW_DIR . "/lib/postprocess.php");
require_once(WWW_DIR . "/lib/Tmux.php");
require_once(WWW_DIR . "/lib/amazon.php");
require_once(WWW_DIR . "/lib/genres.php");
require_once("consoletools.php");
require_once("ColorCLI.php");
require_once("nzbcontents.php");
require_once("namefixer.php");
require_once("TraktTv.php");
require_once("Sharing.php");
require_once("Film.php");
require_once("Info.php");


//*addedd from nZEDb for testing

class Functions

{
	/**
	 * Initiate objects used in processAdditional.
	 *
	 * @return void
	 */
	protected function initAdditional()
	{
		// Check if the objects are already initiated.
		if ($this->additionalInitiated) {
			return;
		}

		/**
		 * How many additional to process per run.
		 *
		 * @var int
		 */
		private
		$addqty;

		/**
		 * Have we initiated the objects used for processAdditional?
		 *
		 * @var bool
		 */
		private
		$additionalInitiated;

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
			$this->audiofileregex = '\.(AAC|AIFF|APE|AC3|ASF|DTS|FLAC|MKA|MKS|MP2|MP3|RA|OGG|OGM|W64|WAV|WMA)';
			$this->ignorebookregex = '/\b(epub|lit|mobi|pdf|sipdf|html)\b.*\.rar(?!.{20,})/i';
			$this->supportfiles = '/\.(vol\d{1,3}\+\d{1,3}|par2|srs|sfv|nzb';
			$this->videofileregex = '\.(AVI|F4V|IFO|M1V|M2V|M4V|MKV|MOV|MP4|MPEG|MPG|MPGV|MPV|OGV|QT|RM|RMVB|TS|VOB|WMV)';
			$this->segmentstodownload = (!empty($this->tmux->segmentstodownload)) ? $this->tmux->segmentstodownload : 2;
			$this->passchkattempts = (!empty($this->tmux->passchkattempts)) ? $this->tmux->passchkattempts : 1;
			$this->partsqty = (!empty($this->tmux->maxpartsprocessed)) ? $this->tmux->maxpartsprocessed : 3;
			$this->rageqty = (!empty($his->tmux->maxrageprocessed)) ? $this->tmux->maxrageprocessed : 75;
			$this->pubkey = $this->site->amazonpubkey;
			$this->privkey = $this->site->amazonprivkey;
			$this->asstag = $this->site->amazonassociatetag;
			$this->addqty = (!empty($this->tmux->maxaddprocessed)) ? (int)$this->site->maxaddprocessed : 25;
			$this->gameqty = (!empty($this->tmux->maxgamesprocessed)) ? $this->tmux->maxgamesprocessed : 150;
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
			$this->nfo = new Info();

			//\\ Paths.
			$this->audSavePath = www . DIR . 'covers/audiosample/';
			$this->imgSavePath = WWW_DIR . 'covers/console/';
			$this->jpgSavePath = WWW_DIR . 'covers/sample/';
			$this->mainTmpPath = $this->site->tmpunrarpath;
			//\\
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

		/**
		 * Process comments.
		 *
		 * @param NNTP $nntp
		 */
		public
		function processSharing(&$nntp)
		{
			$sharing = new Sharing($this->db, $nntp);
			$sharing->start();
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


		//
		// Attempt to get a better name from a par2 file and categorize the release.
		//
		public
		function parsePAR2($messageID, $relID, $groupID, $nntp, $show)
		{
			$db = new DB();
			$category = new Category();
			$c = new ColorCLI;

			if (!isset($nntp))
				exit($c->error("Not connected to usenet(functions->parsePAR2).\n"));

			if ($messageID == '')
				return false;
			$t = 'UNIX_TIMESTAMP(postdate)';
			$quer = $db->queryOneRow('SELECT groupID, categoryID, searchname, ' . $t . ' as postdate, ID as releaseID FROM releases WHERE isrenamed = 0 AND ID = ' . $relID);
			if ($quer['categoryID'] != Category::CAT_MISC_OTHER)
				return false;

			$nntp = new Nntp();
			$nntp->doConnect();
			$groups = new Groups();
			$functions = new Functions();
			$par2 = $nntp->getMessage($this->getByNameByID($groupID), $messageID);
			if (PEAR::isError($par2)) {
				$nntp->doQuit();
				$nntp->doConnect();
				$par2 = $nntp->getMessage($this->getByNameByID($groupID), $messageID);
				if (PEAR::isError($par2)) {
					$nntp->doQuit();

					return false;
				}
			}

			$par2info = new Par2Info();
			$par2info->setData($par2);
			if ($par2info->error)
				return false;

			$files = $par2info->getFileList();
			if ($files !== false && count($files) > 0) {
				$db = new DB();
				$namefixer = new Namefixer;
				$rf = new ReleaseFiles();
				$relfiles = 0;
				$foundname = false;
				foreach ($files as $fileID => $file) {
					if (!array_key_exists('name', $file))
						return false;
					// Add to releasefiles.
					if (($relfiles < 11 && $db->queryOneRow(sprintf("SELECT ID FROM releasefiles WHERE releaseID = %d AND name = %s", $relID, $db->escapeString($file["name"])))) === false) {
						if ($rf->add($relID, $file["name"], $file["size"], $quer["postdate"], 0))
							$relfiles++;
					}
					$quer["textstring"] = $file["name"];
					if ($namefixer->checkName($quer, 1, 'PAR2, ', 1, $show) === true) {
						$foundname = true;
						break;
					}
				}
				if ($relfiles > 0) {
					echo $this->c->debug("Added " . $relfiles . " releasefiles from PAR2 for " . $quer["searchname"]);
					$cnt = $db->queryOneRow('SELECT COUNT(releaseID) AS count FROM releasefiles WHERE releaseID = ' . $relID);
					$count = $relfiles;
					if ($cnt !== false && $cnt['count'] > 0)
						$count = $relfiles + $cnt['count'];
					$db->exec(sprintf('UPDATE releases SET rarinnerfilecount = %d where ID = %d', $count, $relID));
				}
				if ($foundname === true)
					return true;
				else
					return false;
			} else
				return false;
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

		/**
		 * Run processAdditional threaded.
		 *
		 * @param string $releaseToWork
		 * @param        $nntp
		 *
		 * @return void
		 */
		public
		function processAdditionalThreaded($releaseToWork = '', $nntp)
		{
			if (!isset($nntp)) {
				exit($this->c->error("Not connected to usenet(functions->processAdditionalThreaded).\n"));
			}

			$this->processAdditional($nntp, $releaseToWork);
		}

		/**
		 * Check for passworded releases, RAR contents and Sample/Media info.
		 *
		 * @note Called externally by tmux/bin/update_per_group and update/postprocess.php
		 *
		 * @param NNTP   $nntp          Class NNTP
		 * @param string $releaseToWork String containing SQL results. Optional.
		 * @param string $groupID       Group ID. Optional
		 *
		 * @return void
		 */
		public
		function processAdditional($nntp, $releaseToWork = '', $groupID = '')
		{
			$groupID = ($groupID === '' ? '' : 'AND groupID = ' . $groupID);

			// Get out all releases which have not been checked more than max attempts for password.
			$totResults = 0;
			$result = [];
			if ($releaseToWork === '') {

				$i = -6;
				$limit = $this->addqty;
				// Get releases starting from -6 password status until we reach our max limit set in site or we reach -1 password status.
				while (($totResults <= $limit) && ($i <= -1)) {

					$qResult = $this->db->query(
						sprintf('
						SELECT r.ID, r.guid, r.name, c.disablepreview, r.size, r.groupID,
							r.nfostatus, r.completion, r.categoryID, r.searchname
						FROM releases r
						LEFT JOIN category c ON c.ID = r.categoryID
						WHERE r.size < %d
						%s
						AND r.passwordstatus = %d
						AND (r.haspreview = -1 AND c.disablepreview = 0)
						ORDER BY postdate
						DESC LIMIT %d',
							$this->maxsize * 1073741824, $groupID, $i, $limit
						)
					);

					// Get the count of rows we got from the query.
					$currentCount = count($qResult);

					if ($currentCount > 0) {

						// Merge the results.
						$result += $qResult;

						// Decrement so we don't get more than the max user specified value.
						$limit -= $currentCount;

						// Update the total results.
						$totResults += $currentCount;

						// Echo how many we got for this query.
						$this->doecho('Passwordstatus = ' . $i . ': Available to process = ' . $currentCount);
					}
					$i++;
				}
			} else {

				$pieces = explode('           =+=            ', $releaseToWork);
				$result = array(
					array(
						'ID'             => $pieces[0],
						'guid'           => $pieces[1],
						'name'           => $pieces[2],
						'disablepreview' => $pieces[3],
						'size'           => $pieces[4],
						'groupID'        => $pieces[5],
						'nfostatus'      => $pieces[6],
						'categoryID'     => $pieces[7],
						'searchname'     => $pieces[8]
					)
				);
				$totResults = 1;
			}

			$resCount = $startCount = $totResults;
			if ($resCount > 0) {
				// Start up the required objects.
				$this->initAdditional();

				if ($this->echooutput && $resCount > 1) {
					$this->doecho('Additional post-processing, started at: ' . date('D M d, Y G:i a'));
					$this->doecho('Downloaded: (xB) = yEnc article, f= failed ;Processing: z = zip file, r = rar file');
					$this->doecho('Added: s = sample image, j = jpeg image, A = audio sample, a = audio mediainfo, v = video sample');
					$this->doecho('Added: m = video mediainfo, n = nfo, ^ = file details from inside the rar/zip');
				}

				$nzb = new NZB($this->echooutput);

				// Loop through the releases.
				foreach ($result as $rel) {
					if ($this->echooutput) {
						echo $this->c->primaryOver("[" .
							($releaseToWork === ''
								? $startCount--
								: $rel['ID']
							) . '][' . $this->readableBytesString($rel['size'])
							. ']'
						);
					}

					$this->debugging->start('processAdditional', 'Processing ' . $rel['searchname'], 5);

					// Per release defaults.
					$this->tmpPath = $this->mainTmpPath . $rel['guid'] ./;
				if (!is_dir($this->tmpPath)) {
					$old = umask(0777);
					@mkdir($this->tmpPath, 0777, true);
					@chmod($this->tmpPath, 0777);
					@umask($old);

					if (!is_dir($this->tmpPath)) {

						$error = "Unable to create directory: {$this->tmpPath}";
						if ($this->echooutput) {
							echo $this->c->error($error);
						}

						// Decrement password status.
						$this->db->exec('UPDATE releases SET passwordstatus = passwordstatus - 1 WHERE ID = ' . $rel['ID']);
						continue;
					}
				}

				$nzbPath = $nzb->getNZBPath($rel['guid']);
				if (!is_file($nzbPath)) {
					// The nzb was not located. decrement the password status.
					$this->db->exec('UPDATE releases SET passwordstatus = passwordstatus - 1 WHERE ID = ' . $rel['ID']);
					continue;
				}

				// Turn on output buffering.
				ob_start();

				// Decompress the NZB.
				@readgzfile($nzbPath);

				// Read the nzb into memory.
				$nzbFile = ob_get_contents();

				// Clean (erase) the output buffer and turn off output buffering.
				ob_end_clean();

				// Get a list of files in the nzb.
				$nzbFiles = $this->nzbFileList($nzbFile);
				if (count($nzbFiles) === 0) {
					// There does not appear to be any files in the nzb, decrement password status.
					$this->db->exec('UPDATE releases SET passwordstatus = passwordstatus - 1 WHERE ID = ' . $rel['ID']);
					continue;
				}

				// Sort the files.
				usort($nzbFiles, 'functions::sortRAR');

				// Only process for samples, previews and images if not disabled.
				$this->blnTookSample = ($this->processSample ? false : true);
				$this->blnTookSample = (($rel['disablepreview'] === '1') ? true : false);
				$this->blnTookVideo = ($this->processVideo ? false : true);
				$this->blnTookMediainfo = ($this->processMediaInfo ? false : true);
				$this->blnTookAudioinfo = ($this->processAudioInfo ? false : true);
				$this->blnTookAudioSample = ($this->processAudioSample ? false : true);
				$this->blnTookJPG = ($this->processJPGSample ? false : true);

				// Reset and set certain variables.
				$passStatus = array(Releases::PASSWD_NONE);
				$sampleMsgID = $jpgMsgID = $audioType = $mID = array();
				$mediaMsgID = $audioMsgID = '';
				$hasRar = $ignoredBooks = $failed = $this->filesAdded = 0;
				$this->password = $this->noNFO = $bookFlood = false;
				$groupName = $this->getByNameByID($rel['groupID']);

				// Make sure we don't already have an nfo.
				if ($rel['nfostatus'] !== '1') {
					$this->noNFO = true;
				}

				// Go through the nzb for this release looking for a rar, a sample etc...
				foreach ($nzbFiles as $nzbContents) {

					// Check if it's not a nfo, nzb, par2 etc...
					if (preg_match($this->supportFiles . "|nfo\b|inf\b|ofn\b)($|[ \")\]-])(?!.{20,})/i", $nzbContents['title'])) {
						continue;
					}

					// Check if it's a rar/zip.
					if (preg_match("
						/\.(part0*1|part0+|r0+|r0*1|rar|0+|0*10?|zip)(\.rar)*($|[ \")\]-])|\"[a-f0-9]{32}\.[1-9]\d{1,2}\".*\(\d+\/\d{2,}\)$/i",
						$nzbContents['title']
					)
					) {

						$hasRar = 1;
					}

					// Look for a video sample, make sure it's not an image.
					if ($this->processSample === true &&
						empty($sampleMsgID) &&
						!preg_match('/\.jpe?g/i', $nzbContents['title']) &&
						preg_match('/sample/i', $nzbContents['title'])
					) {

						if (isset($nzbContents['segments'])) {
							$sampleMsgID[] = (string)$nzbContents['segments'][0];

							// Get the amount of segments for this file.
							$segCount = count($nzbContents['segments']);
							if ($segCount > 1) {

								// If it's more than 1 try to get up to the site specified value of segments.
								for ($i = 1; $i < $this->segmentsToDownload; $i++) {
									if ($segCount > $i) {
										$sampleMsgID[] = (string)$nzbContents['segments'][$i];
									} else {
										break;
									}
								}
							}
						}
					}

					// Look for a video file, make sure it's not a sample.
					if ($this->processMediaInfo === true &&
						empty($mediaMsgID) &&
						!preg_match('/sample/i', $nzbContents['title']) &&
						preg_match('/' . $this->videoFileRegex . '[. ")\]]/i', $nzbContents['title'])
					) {

						if (isset($nzbContents['segments'])) {
							$mediaMsgID = (string)$nzbContents['segments'][0];
						}
					}

					// Look for a audio file.
					if ($this->processAudioInfo === true &&
						empty($audioMsgID) &&
						preg_match('/' . $this->audioFileRegex . '[. ")\]]/i', $nzbContents['title'], $type)
					) {

						if (isset($nzbContents['segments'])) {
							// Get the extension.
							$audioType = $type[1];
							$audioMsgID = (string)$nzbContents['segments'][0];
						}
					}

					// Look for a JPG picture, make sure it's not a CD cover.
					if ($this->processJPGSample === true &&
						empty($jpgMsgID) &&
						!preg_match('/flac|lossless|mp3|music|inner-sanctum|sound/i', $groupName) &&
						preg_match('/\.jpe?g[. ")\]]/i', $nzbContents['title'])
					) {

						if (isset($nzbContents['segments'])) {

							$jpgMsgID[] = (string)$nzbContents['segments'][0];
							// If there's more than 1 part, get 2.
							if (count($nzbContents['segments']) > 1) {
								$jpgMsgID[] = (string)$nzbContents['segments'][1];
							}
						}
					}

					// To see if this is book flood.
					if (preg_match($this->ignoreBookRegex, $nzbContents['title'])) {
						$ignoredBooks++;
					}
				}

				// Ignore massive book NZBs.
				$fileCount = count($nzbFiles);
				if ($fileCount > 40 && ($ignoredBooks * 2) >= $fileCount) {
					if (isset($rel['categoryID']) && substr($rel['categoryID'], 0, 1) === '8') {
						$this->db->exec(sprintf('UPDATE releases SET passwordstatus = 0, haspreview = 0, categoryID = 7900 WHERE ID = %d', $rel['ID']));
					}
					$bookFlood = true;
				}

				// Separate the nzb content into the different parts (support files, archive segments and the first parts).
				if ($bookFlood === false && $hasRar !== 0) {
					if ($this->processPasswords === true ||
						$this->processSample === true ||
						$this->processMediaInfo === true ||
						$this->processAudioInfo === true ||
						$this->processVideo === true
					) {

						$this->sum = $this->size = $this->segsize = $this->adj = $notInfinite = $failed = 0;
						$this->name = '';
						$this->ignoreNumbered = false;

						// Loop through the files, attempt to find if password-ed and files. Starting with what not to process.
						foreach ($nzbFiles as $rarFile) {
							if ($this->passChkAttempts > 1) {
								if ($notInfinite > $this->passChkAttempts) {
									break;
								}
							} else {
								if ($notInfinite > $this->partsQTY) {
									if ($this->echooutput) {
										echo "\n";
										echo $this->c->info("Ran out of tries to download yEnc articles for the RAR files.");
									}
									break;
								}
							}

							if ($this->password === true) {
								$this->debugging->start('processAdditional',
									'Skipping processing of rar ' . $rarFile['title'] . ' it has a password.', 4
								);
								break;
							}

							// Probably not a rar/zip.
							if (!preg_match("/\.\b(part\d+|part00\.rar|part01\.rar|rar|r00|r01|zipr\d{2,3}|zip|zipx)($|[ \")\]-])|\"[a-f0-9]{32}\.[1-9]\d{1,2}\".*\(\d+\/\d{2,}\)$/i", $rarFile['title'])) {
								continue;
							}

							// Process rar contents until 1G or 85% of file size is found (smaller of the two).
							if ($rarFile['size'] === 0 && $rarFile['partsactual'] !== 0 && $rarFile['partstotal'] !== 0) {
								$this->segsize = $rarFile['size'] / ($rarFile['partsactual'] / $rarFile['partstotal']);
							} else {
								$this->segsize = 0;
							}

							$this->sum = $this->sum + $this->adj * $this->segsize;
							if ($this->sum > $this->size || $this->adj === 0) {

								// Get message-id's for the rar file.
								$mID = array_slice((array)$rarFile['segments'], 0, $this->segmentsToDownload);

								// Download the article(s) from usenet.
								$fetchedBinary = $nntp->getMessages($groupName, $mID, $this->alternateNNTP);
								if ($nntp->isError($fetchedBinary)) {
									$fetchedBinary = false;
								}

								if ($fetchedBinary !== false) {

									// Echo we downloaded rar/zip.
									if ($this->echooutput) {
										echo '(rB)';
									}

									$notInfinite++;

									// Process the rar/zip file.
									$relFiles = $this->processReleaseFiles($fetchedBinary, $rel, $rarFile['title'], $nntp);

									if ($this->password === true) {
										$passStatus[] = Releases::PASSWD_RAR;
									}

									if ($relFiles === false) {
										$this->debugging->start('processAdditional', 'Error processing files ' . $rarFile['title'], 4);
										continue;
									}

								} else {

									if ($this->echooutput) {
										echo 'f(' . $notInfinite . ')';
									}

									$notInfinite += 0.2;
									$failed++;
								}
							}
						}
					}

					// Get names of all files in temp dir.
					$files = @scandir($this->tmpPath);
					if ($files !== false) {

						// Loop over them.
						foreach ($files as $file) {

							// Check if the file exists.
							if (is_file($this->tmpPath . $file)) {

								// Check if it's a rar file.
								if (substr($file, -4) === '.rar') {

									// Load the file in archive info.
									$archInfo = new ArchiveInfo();
									$archInfo->open($this->tmpPath . $file, true);
									if ($archInfo->error) {
										continue;
									}

									$tmpFiles = $archInfo->getArchiveFileList();
									if (isset($tmpFiles[0]['name'])) {
										foreach ($tmpFiles as $r) {
											if (isset($r['range'])) {
												$range = $r['range'];
											} else {
												$range = mt_rand(0, 99999);
											}

											$r['range'] = $range;
											if (!isset($r['error'])) {

												if ($rel['categoryID'] !== Category::CAT_MISC) {
													// Check if it's a par2.
													if (preg_match('/\.par2/i', $r['name'])) {
														$par2 = $archInfo->getFileData($r['name'], $r['source']);
														// Try to get a release name.
														$this->siftPAR2($par2, $rel);
													}
												}

												if (preg_match(
													$this->supportFiles .
													'|part\d+|r\d{1,3}|zipr\d{2,3}|\d{2,3}|zipx|zip|rar)(\.rar)?$/i', $r['name']
												)
												) {
													continue;
												}

												$this->addFile($r, $rel, $archInfo, $nntp);
											}
										}
									}
								}
							}
						}
					}
				}

				// Check if we should process these types of files.
				if ($this->blnTookSample === false ||
					$this->blnTookAudioinfo === false ||
					$this->blnTookMediainfo === false ||
					$this->blnTookJPG === false ||
					$this->blnTookVideo === false ||
					$this->blnTookAudioSample === false
				) {

					// Get all the names of the files in the temp dir.
					$files = @scandir($this->tmpPath);
					if ($files !== false) {

						// Loop over them.
						foreach ($files as $file) {

							// Check if it's really a file.
							if (is_file($this->tmpPath . $file)) {
								$name = '';

								// Audio sample.
								if (($this->blnTookAudioinfo === false || $this->blnTookAudioSample === false) &&
									preg_match('/(.*)' . $this->audioFileRegex . '$/i', $file, $name)
								) {

									// Move the file.
									@rename($this->tmpPath . $name[0], $this->tmpPath . 'audiofile.' . $name[2]);
									// Try to get audio sample/audio media info.
									$this->getAudioInfo($rel['guid'], $rel['ID'], $name[2]);
									// Delete the file.
									@unlink($this->tmpPath . 'audiofile.' . $name[2]);
								}

								// JGP file sample.
								if ($this->blnTookJPG === false && preg_match('/\.jpe?g$/', $file)) {

									// Try to resize/move the image.
									$this->blnTookJPG =
										$this->saveImage(
											$rel['guid'] . '_thumb',
											$this->tmpPath . $file, $this->jpgSavePath, 650, 650
										);

									// If it's successful, tell the DB.
									if ($this->blnTookJPG !== false) {
										$this->db->exec(sprintf('UPDATE releases SET jpgstatus = %d WHERE ID = %d', 1, $rel['ID']));
									}

									// Delete the old file.
									@unlink($this->tmpPath . $file);
								}

								// Video sample // video clip // video media info.
								if ($this->blnTookSample === false || $this->blnTookVideo === false || $this->blnTookMediainfo === false) {

									// Check if it's a video.
									if (preg_match('/(.*)' . $this->videoFileRegex . '$/i', $file, $name)) {

										// Move it.
										@rename($this->tmpPath . $name[0], $this->tmpPath . 'sample.avi');

										// Try to get a sample with it.
										if ($this->blnTookSample === false) {
											$this->blnTookSample = $this->getSample($rel['guid']);
										}

										// Try to get a video with it. Don't get it here if $sampleMsgID is empty or has 1 message-id (Saves downloading another part).
										if ($this->blnTookVideo === false && count($sampleMsgID) < 2) {
											$this->blnTookVideo = $this->getVideo($rel['guid']);
										}

										// Try to get media info with it.
										if ($this->blnTookMediainfo === false) {
											$this->blnTookMediainfo = $this->getMediaInfo($rel['ID']);
										}

										// Delete it.
										@unlink($this->tmpPath . 'sample.avi');
									}
								}

								// If we got it all, break out.
								if ($this->blnTookJPG === true &&
									$this->blnTookAudioinfo === true &&
									$this->blnTookAudioSample === true &&
									$this->blnTookMediainfo === true &&
									$this->blnTookVideo === true &&
									$this->blnTookSample === true
								) {

									break;
								}
							}
						}
						unset($files);
					}
				}

				// Download and process sample image.
				if ($this->blnTookSample === false || $this->blnTookVideo === false) {

					if (!empty($sampleMsgID)) {

						// Download it from usenet.
						$sampleBinary = $nntp->getMessages($groupName, $sampleMsgID);
						if ($nntp->isError($sampleBinary)) {
							$sampleBinary = false;
						}

						if ($sampleBinary !== false) {
							if ($this->echooutput) {
								echo '(sB)';
							}

							// Check if it's more than 40 bytes.
							if (strlen($sampleBinary) > 40) {

								// Try to create the file.
								$this->addMediaFile($this->tmpPath . 'sample_' . mt_rand(0, 99999) . '.avi', $sampleBinary);

								// Try to get a sample picture.
								if ($this->blnTookSample === false) {
									$this->blnTookSample = $this->getSample($rel['guid']);
								}

								// Try to get a sample video.
								if ($this->blnTookVideo === false) {
									$this->blnTookVideo = $this->getVideo($rel['guid']);
								}

								// Try to get media info. Don't get it here if $mediaMsgID is not empty.
								if ($this->blnTookMediainfo === false && empty($mediaMsgID)) {
									$this->blnTookMediainfo = $this->getMediaInfo($rel['ID']);
								}

							}
							unset($sampleBinary);
						} else {
							if ($this->echooutput) {
								echo 'f';
							}
						}
					}
				}

				// Download and process mediainfo. Also try to get a sample if we didn't get one yet.
				if ($this->blnTookMediainfo === false || $this->blnTookSample === false || $this->blnTookVideo === false) {

					if (!empty($mediaMsgID)) {

						// Try to download it from usenet.
						$mediaBinary = $nntp->getMessages($groupName, $mediaMsgID);
						if ($nntp->isError($mediaBinary)) {
							// If error set it to false.
							$mediaBinary = false;
						}

						if ($mediaBinary !== false) {

							if ($this->echooutput) {
								echo '(mB)';
							}

							// If it's more than 40 bytes...
							if (strlen($mediaBinary) > 40) {

								// Create a file on the disk with it.
								$this->addMediaFile($this->tmpPath . 'media.avi', $mediaBinary);

								// Try to get media info.
								if ($this->blnTookMediainfo === false) {
									$this->blnTookMediainfo = $this->getMediaInfo($rel['ID']);
								}

								// Try to get a sample picture.
								if ($this->blnTookSample === false) {
									$this->blnTookSample = $this->getSample($rel['guid']);
								}

								// Try to get a sample video.
								if ($this->blnTookVideo === false) {
									$this->blnTookVideo = $this->getVideo($rel['guid']);
								}
							}
							unset($mediaBinary);
						} else {
							if ($this->echooutput) {
								echo 'f';
							}
						}
					}
				}

				// Download audio file, use media info to try to get the artist / album.
				if (($this->blnTookAudioinfo === false || $this->blnTookAudioSample === false) && !empty($audioMsgID)) {

					// Try to download it from usenet.
					$audioBinary = $nntp->getMessages($groupName, $audioMsgID);
					if ($nntp->isError($audioBinary)) {
						$audioBinary = false;
					}

					if ($audioBinary !== false) {
						if ($this->echooutput) {
							echo '(aB)';
						}

						// Create a file with it.
						$this->addMediaFile($this->tmpPath . 'audio.' . $audioType, $audioBinary);

						// Try to get media info / sample of the audio file.
						$this->getAudioInfo($rel['guid'], $rel['id'], $audioType);

						unset($audioBinary);
					} else {
						if ($this->echooutput) {
							echo 'f';
						}
					}
				}

				// Download JPG file.
				if ($this->blnTookJPG === false && !empty($jpgMsgID)) {

					// Try to download it.
					$jpgBinary = $nntp->getMessages($groupName, $jpgMsgID);
					if ($nntp->isError($jpgBinary)) {
						$jpgBinary = false;
					}

					if ($jpgBinary !== false) {

						if ($this->echooutput) {
							echo '(jB)';
						}

						// Try to create a file with it.
						$this->addMediaFile($this->tmpPath . 'samplepicture.jpg', $jpgBinary);

						// Try to resize and move it.
						$this->blnTookJPG = $this->releaseImage->saveImage($rel['guid'] . '_thumb', $this->tmpPath . 'samplepicture.jpg', $this->jpgSavePath, 650, 650);
						if ($this->blnTookJPG !== false) {
							// Update the DB to say we got it.
							$this->db->exec(sprintf('UPDATE releases SET jpgstatus = %d WHERE ID = %d', 1, $rel['ID']));
							if ($this->echooutput) {
								echo 'j';
							}
						}

						@unlink($this->tmpPath . 'samplepicture.jpg');
						unset($jpgBinary);
					} else {
						if ($this->echooutput) {
							echo 'f';
						}
					}
				}

				// Set up release values.
				$hpSQL = $iSQL = $vSQL = $jSQL = '';
				if ($this->processSample === true && $this->blnTookSample !== false) {
					$this->db->exec(sprintf('UPDATE releases SET haspreview = 1 WHERE guid = %s', $this->db->escapeString($rel['guid'])));
				} else {
					$hpSQL = ', haspreview = 0';
				}

				if ($failed > 0) {
					if ($failed / count($nzbFiles) > 0.7 || $notInfinite > $this->passChkAttempts || $notInfinite > $this->partsQTY) {
						$passStatus[] = Releases::BAD_FILE;
					}
				}

				// If samples exist from previous runs, set flags.
				if (file_exists($this->imgSavePath . $rel['guid'] . '_thumb.jpg')) {
					$iSQL = ', haspreview = 1';
				}
				if (file_exists($this->vidSavePath . $rel['guid'] . '.ogv')) {
					$vSQL = ', videostatus = 1';
				}
				if (file_exists($this->jpgSavePath . $rel['guid'] . '_thumb.jpg')) {
					$jSQL = ', jpgstatus = 1';
				}

				$size = $this->db->queryOneRow('SELECT COUNT(releasefiles.releaseID) AS count, SUM(releasefiles.size) AS size FROM releasefiles WHERE releaseID = ' . $rel['ID']);

				$pStatus = max($passStatus);
				if ($this->processPasswords === true && $pStatus > 0) {
					$sql = sprintf('UPDATE releases SET passwordstatus = %d, rarinnerfilecount = %d %s %s %s %s WHERE ID = %d', $pStatus, $size['count'], $iSQL, $vSQL, $jSQL, $hpSQL, $rel['ID']);
				} else if ($hasRar && ((isset($size['size']) && (is_null($size['size']) || $size['size'] === '0')) || !isset($size['size']))) {
					if (!$this->blnTookSample) {
						$hpSQL = '';
					}
					$sql = sprintf('UPDATE releases SET passwordstatus = passwordstatus - 1, rarinnerfilecount = %d %s %s %s %s WHERE ID = %d', $size['count'], $iSQL, $vSQL, $jSQL, $hpSQL, $rel['ID']);
				} else {
					$sql = sprintf('UPDATE releases SET passwordstatus = %s, rarinnerfilecount = %d %s %s %s %s WHERE ID = %d', Releases::PASSWD_NONE, $size['count'], $iSQL, $vSQL, $jSQL, $hpSQL, $rel['ID']);
				}

				$this->db->exec($sql);

				// Erase all files and directory.
				foreach (glob($this->tmpPath . '*') as $v) {
					@unlink($v);
				}
				foreach (glob($this->tmpPath . '.*') as $v) {
					@unlink($v);
				}
				@rmdir($this->tmpPath);
			}
				if ($this->echooutput) {
					echo "\n";
				}
			}

			unset($rar, $nzbContents);
		}


		// Process nfo files.
		public
		function processNfos($releaseToWork = '', $nntp)
		{
			if (!isset($nntp))
				exit($this->c->error("Not connected to usenet(functions->processNfos).\n"));

			if ($this->site->lookupnfo == 1) {
				$nfo = new Info($this->echooutput);
				$nfo->processNfoFiles($releaseToWork, $this->site->lookupimdb, $this->site->lookuptvrage, $groupID = '', $nntp);
			}
		}


		function doecho($str)
		{
			if ($this->echooutput)
				echo $this->c->header($str);
		}

		// Comparison function for usort, for sorting nzb file content.
		public
		function sortrar($a, $b)
		{
			$pos = 0;
			$af = $bf = false;
			$a = preg_replace('/\d+[- ._]?(\/|\||[o0]f)[- ._]?\d+?(?![- ._]\d)/i', ' ', $a['title']);
			$b = preg_replace('/\d+[- ._]?(\/|\||[o0]f)[- ._]?\d+?(?![- ._]\d)/i', ' ', $b['title']);

			if (preg_match("/\.(part\d+|r\d+)(\.rar)*($|[ \")\]-])/i", $a))
				$af = true;
			if (preg_match("/\.(part\d+|r\d+)(\.rar)*($|[ \")\]-])/i", $b))
				$bf = true;

			if (!$af && preg_match("/\.(rar)($|[ \")\]-])/i", $a)) {
				$a = preg_replace('/\.(rar)(?:$|[ \")\]-])/i', '.*rar', $a);
				$af = true;
			}
			if (!$bf && preg_match("/\.(rar)($|[ \")\]-])/i", $b)) {
				$b = preg_replace('/\.(rar)(?:$|[ \")\]-])/i', '.*rar', $b);
				$bf = true;
			}

			if (!$af && !$bf)
				return strnatcasecmp($a, $b);
			else if (!$bf)
				return -1;
			else if (!$af)
				return 1;

			if ($af && $bf)
				$pos = strnatcasecmp($a, $b);
			else if ($af)
				$pos = -1;
			else if ($bf)
				$pos = 1;

			return $pos;
		}

		// Process all TV related releases which will assign their series/episode/rage data.
		public
		function processTv($releaseToWork = '')
		{
			if ($this->site->lookuptvrage == 1) {
				$tvrage = new TvRage($this->echooutput);
				$this->processTvReleases($releaseToWork, $this->site->lookuptvrage == 1);
			}
		}

		public
		function processTvReleases($releaseToWork = '', $lookupTvRage = true, $local = false)
		{
			$ret = 0;
			$trakt = new TraktTv();
			$tvrage = new TvRage();

			// Get all releases without a rageID which are in a tv category.
			if ($releaseToWork == '') {
				$res = $this->db->query(sprintf("SELECT r.searchname, r.ID FROM releases r INNER JOIN category c ON r.categoryID = c.ID WHERE r.rageID = -1 AND c.parentID = %d ORDER BY postdate DESC LIMIT %d", Category::CAT_PARENT_TV, $this->rageqty));
				$tvcount = count($res);
			} else {
				$pieces = explode("           =+=            ", $releaseToWork);
				$res = array(array('searchname' => $pieces[0], 'ID' => $pieces[1]));
				$tvcount = 1;
			}

			if ($this->echooutput && $tvcount > 1) {
				echo $this->c->header("Processing TV for " . $tvcount . " release(s).");
			}

			foreach ($res as $arr) {
				$show = $tvrage->parseNameEpSeason($arr['searchname']);
				if (is_array($show) && $show['name'] != '') {
					// Update release with season, ep, and airdate info (if available) from releasetitle.
					$tvrage->updateEpInfo($show, $arr['ID']);

					// Find the rageID.
					$ID = $tvrage->getByTitle($show['cleanname']);

					// Force local lookup only
					if ($local == true) {
						$lookupTvRage = false;
					}

					if ($ID === false && $lookupTvRage) {
						// If it doesnt exist locally and lookups are allowed lets try to get it.
						if ($this->echooutput) {
							echo $this->c->primaryOver("TVRage ID for ") . $this->c->headerOver($show['cleanname']) . $this->c->primary(" not found in local db, checking web.");
						}

						$tvrShow = $tvrage->getRageMatch($show);
						if ($tvrShow !== false && is_array($tvrShow)) {
							// Get all tv info and add show.
							$tvrage->updateRageInfo($tvrShow['showid'], $show, $tvrShow, $arr['ID']);
						} else if ($tvrShow === false) {
							// If tvrage fails, try trakt.
							$traktArray = $trakt->traktTVSEsummary($show['name'], $show['season'], $show['episode']);
							if ($traktArray !== false) {
								if (isset($traktArray['show']['tvrage_ID']) && $traktArray['show']['tvrage_ID'] !== 0) {
									if ($this->echooutput) {
										echo $this->c->primary('Found TVRage ID on trakt:' . $traktArray['show']['tvrage_ID']);
									}
									$this->updateRageInfoTrakt($traktArray['show']['tvrage_ID'], $show, $traktArray, $arr['ID']);
								} // No match, add to tvrage with rageID = -2 and $show['cleanname'] title only.
								else {
									$this->add(-2, $show['cleanname'], '', '', '', '');
								}
							} // No match, add to tvrage with rageID = -2 and $show['cleanname'] title only.
							else {
								$this->add(-2, $show['cleanname'], '', '', '', '');
							}
						} else {
							// $tvrShow probably equals -1 but we'll do this as a catchall instead of a specific else if.
							// Skip because we couldnt connect to tvrage.com.
						}
					} else if ($ID > 0) {
						//if ($this->echooutput) {
						//    echo $this->c->AlternateOver("TV series: ") . $this->c->header($show['cleanname'] . " " . $show['seriesfull'] . (($show['year'] != '') ? ' ' . $show['year'] : '') . (($show['country'] != '') ? ' [' . $show['country'] . ']' : ''));
						// }
						$tvairdate = (isset($show['airdate']) && !empty($show['airdate'])) ? $this->db->escapeString($this->checkDate($show['airdate'])) : "NULL";
						$tvtitle = "NULL";

						if ($lookupTvRage) {
							$epinfo = $tvrage->getEpisodeInfo($ID, $show['season'], $show['episode']);
							if ($epinfo !== false) {
								if (isset($epinfo['airdate'])) {
									$tvairdate = $this->db->escapeString($this->checkDate($epinfo['airdate']));
								}

								if (!empty($epinfo['title'])) {
									$tvtitle = $this->db->escapeString(trim($epinfo['title']));
								}
							}
						}
						if ($tvairdate == "NULL") {
							$this->db->exec(sprintf('UPDATE releases SET tvtitle = %s, rageID = %d WHERE ID = %d', $tvtitle, $ID, $arr['ID']));
						} else {
							$this->db->exec(sprintf('UPDATE releases SET tvtitle = %s, tvairdate = %s, rageID = %d WHERE ID = %d', $tvtitle, $tvairdate, $ID, $arr['ID']));
						}
						// Cant find rageID, so set rageID to n/a.
					} else {
						$this->db->exec(sprintf('UPDATE releases SET rageID = -2 WHERE ID = %d', $arr['ID']));
					}
					// Not a tv episode, so set rageID to n/a.
				} else {
					$this->db->exec(sprintf('UPDATE releases SET rageID = -2 WHERE ID = %d', $arr['ID']));
				}
				$ret++;
			}

			return $ret;
		}

		public
		function updateRageInfoTrakt($rageid, $show, $traktArray, $relid)
		{

			$tvrage = new TvRage();
			// Try and get the episode specific info from tvrage.
			$epinfo = $tvrage->getEpisodeInfo($rageid, $show['season'], $show['episode']);
			if ($epinfo !== false) {
				$tvairdate = (!empty($epinfo['airdate'])) ? $this->db->escapeString($epinfo['airdate']) : "NULL";
				$tvtitle = (!empty($epinfo['title'])) ? $this->db->escapeString($epinfo['title']) : "NULL";
				$this->db->exec(sprintf("UPDATE releases SET tvtitle = %s, tvairdate = %s, rageID = %d WHERE ID = %d", $this->db->escapeString(trim($tvtitle)), $tvairdate, $traktArray['show']['tvrage_ID'], $relid));
			} else {
				$this->db->exec(sprintf("UPDATE releases SET rageID = %d WHERE ID = %d", $traktArray['show']['tvrage_ID'], $relid));
			}

			$genre = '';
			if (isset($traktArray['show']['genres']) && is_array($traktArray['show']['genres']) && !empty($traktArray['show']['genres'])) {
				$genre = $traktArray['show']['genres']['0'];
			}

			$country = '';
			if (isset($traktArray['show']['country']) && !empty($traktArray['show']['country'])) {
				$country = $this->countryCode($traktArray['show']['country']);
			}

			$rInfo = $tvrage->getRageInfoFromPage($rageid);
			$desc = '';
			if (isset($rInfo['desc']) && !empty($rInfo['desc'])) {
				$desc = $rInfo['desc'];
			}

			$imgbytes = '';
			if (isset($rInfo['imgurl']) && !empty($rInfo['imgurl'])) {
				$img = getUrl($rInfo['imgurl']);
				if ($img !== false) {
					$im = @imagecreatefromstring($img);
					if ($im !== false) {
						$imgbytes = $img;
					}
				}
			}

			$this->add($rageid, $show['cleanname'], $desc, $genre, $country, $imgbytes);
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
		function countryCode($country)
		{
			if (!is_array($country) && strlen($country) > 2) {
				$code = $this->db->queryOneRow('SELECT code FROM country WHERE LOWER(name) = LOWER(' . $this->db->escapeString($country) . ')');
				if (isset($code['code'])) {
					return $code['code'];
				}
			}

			return $country;
		}

		public
		function add($rageid, $releasename, $desc, $genre, $country, $imgbytes)
		{
			$releasename = str_replace(array('.', '_'), array(' ', ' '), $releasename);
			$country = $this->countryCode($country);

			if ($rageid != -2) {
				$ckid = $this->db->queryOneRow('SELECT ID FROM tvrage WHERE rageID = ' . $rageid);
			} else {
				$ckid = $this->db->queryOneRow('SELECT ID FROM tvrage WHERE releasetitle = ' . $this->db->escapeString($releasename));
			}

			if (!isset($ckid['ID']) || $rageid == -2) {
				$this->db->exec(sprintf('INSERT INTO tvrage (rageID, releasetitle, description, genre, country, createddate, imgdata) VALUES (%s, %s, %s, %s, %s, NOW(), %s)', $rageid, $this->db->escapeString($releasename), $this->db->escapeString(substr($desc, 0, 10000)), $this->db->escapeString(substr($genre, 0, 64)), $this->db->escapeString($country), $this->db->escapeString($imgbytes)));
			} else {
				$this->db->exec(sprintf('UPDATE tvrage SET releasetitle = %s, description = %s, genre = %s, country = %s, createddate = NOW(), imgdata = %s WHERE rage = %d', $this->db->escapeString($releasename), $this->db->escapeString(substr($desc, 0, 10000)), $this->db->escapeString(substr($genre, 0, 64)), $this->db->escapeString($country), $this->db->escapeString($imgbytes), $rageid));
			}
		}

		// Open the rar, see if it has a password, attempt to get a file.
		function processReleaseFiles($fetchedBinary, $release, $name, $nntp)
		{
			if (!isset($nntp))
				exit($this->c->error("Not connected to usenet(postprocess->processReleaseFiles).\n"));

			$retval = array();
			$rar = new ArchiveInfo();
			$rf = new ReleaseFiles();
			$this->password = false;

			if (preg_match("/\.(part\d+|rar|r\d{1,3})($|[ \")\]-])|\"[a-f0-9]{32}\.[1-9]\d{1,2}\".*\(\d+\/\d{2,}\)$/i", $name)) {
				$rar->setData($fetchedBinary, true);
				if ($rar->error) {
					$this->debug("\nError: {$rar->error}.");

					return false;
				}

				$tmp = $rar->getSummary(true, false);
				if (preg_match('/par2/i', $tmp['main_info']))
					return false;

				if (isset($tmp['is_encrypted']) && $tmp['is_encrypted'] != 0) {
					$this->debug('Archive is password encrypted.');
					$this->password = true;

					return false;
				}

				if (!empty($rar->isEncrypted)) {
					$this->debug('Archive is password encrypted.');
					$this->password = true;

					return false;
				}

				$files = $rar->getArchiveFileList();
				if (count($files) == 0 || !is_array($files) || !isset($files[0]['compressed']))
					return false;

				if ($files[0]['compressed'] == 0 && $files[0]['name'] != $this->name) {
					$this->name = $files[0]['name'];
					$this->size = $files[0]['size'] * 0.95;
					$this->adj = $this->sum = 0;

					if ($this->echooutput)
						echo 'r';
					// If archive is not stored compressed, process data
					foreach ($files as $file) {
						if (isset($file['name'])) {
							if (isset($file['error'])) {
								$this->debug("Error: {$file['error']} (in: {$file['source']})");
								continue;
							}
							if ($file['pass'] == true) {
								$this->password = true;
								break;
							}

							if (preg_match($this->supportfiles . ')(?!.{20,})/i', $file['name']))
								continue;

							if (preg_match('/\.zip$/i', $file['name'])) {
								$zipdata = $rar->getFileData($file['name'], $file['source']);
								$data = $this->processReleaseZips($zipdata, false, true, $release, $nntp);

								if ($data != false) {
									foreach ($data as $d) {
										if (preg_match('/\.(part\d+|r\d+|rar)(\.rar)?$/i', $d['zip']['name']))
											$tmpfiles = $this->getRar($d['data']);
									}
								}
							}

							if (!isset($file['next_offset']))
								$file['next_offset'] = 0;
							$range = mt_rand(0, 99999);
							if (isset($file['range']))
								$range = $file['range'];
							$retval[] = array('name' => $file['name'], 'source' => $file['source'], 'range' => $range, 'size' => $file['size'], 'date' => $file['date'], 'pass' => $file['pass'], 'next_offset' => $file['next_offset']);
							$this->adj = $file['next_offset'] + $this->adj;
						}
					}

					$this->sum = $this->adj;
					if ($this->segsize != 0)
						$this->adj = $this->adj / $this->segsize;
					else
						$this->adj = 0;

					if ($this->adj < .7)
						$this->adj = 1;
				} else {
					$this->size = $files[0]['size'] * 0.95;
					if ($this->name != $files[0]['name']) {
						$this->name = $files[0]['name'];
						$this->sum = $this->segsize;
						$this->adj = 1;
					}

					// File is compressed, use unrar to get the content
					$rarfile = $this->tmpPath . 'rarfile' . mt_rand(0, 99999) . '.rar';
					if (@file_put_contents($rarfile, $fetchedBinary)) {
						$execstring = '"' . $this->site->unrarpath . '" e -ai -ep -c- -ID -inul -kb -or -p- -r -y "' . $rarfile . '" "' . $this->tmpPath . '"';
						$output = @runCmd($execstring, false, true);
						if (isset($files[0]['name'])) {
							if ($this->echooutput)
								echo 'r';
							foreach ($files as $file) {
								if (isset($file['name'])) {
									if (!isset($file['next_offset']))
										$file['next_offset'] = 0;
									$range = mt_rand(0, 99999);
									if (isset($file['range']))
										$range = $file['range'];

									$retval[] = array('name' => $file['name'], 'source' => $file['source'], 'range' => $range, 'size' => $file['size'], 'date' => $file['date'], 'pass' => $file['pass'], 'next_offset' => $file['next_offset']);
								}
							}
						}
					}
				}
			}


			// Use found content to populate releasefiles, nfo, and create multimedia files.
			foreach ($retval as $k => $v) {
				if (!preg_match($this->supportfiles . '|part\d+|r\d{1,3}|zipr\d{2,3}|\d{2,3}|zipx|zip|rar)(\.rar)?$/i', $v['name']) && count($retval) > 0)
					$this->addfile($v, $release, $rar, $nntp);
				else
					unset($retval[$k]);
			}

			if (count($retval) == 0)
				$retval = false;
			unset($fetchedBinary, $rar, $rf, $nfo);

			return $retval;
		}

		/**
		 * Open the zip, see if it has a password, attempt to get a file.
		 *
		 * @note Called by processReleaseFiles
		 *
		 * @param      $fetchedBinary
		 * @param bool $open
		 * @param bool $data
		 * @param      $release
		 * @param      $nntp
		 *
		 * @return array|bool
		 */
		protected
		function processReleaseZips($fetchedBinary, $open = false, $data = false, $release, $nntp)
		{
			if (!isset($nntp)) {
				exit($this->c->error("Not connected to usenet(Functions->processReleaseZips).\n"));
			}

			// Load the ZIP file or data.
			$zip = new ZipInfo();
			if ($open)
				$zip->open($fetchedBinary, true);
			else
				$zip->setData($fetchedBinary, true);

			if ($zip->error) {
				$this->c->error('processReleaseZips', 'ZIP Error: ' . $zip->error);

				return false;
			}

			if (!empty($zip->isEncrypted)) {
				$this->c->error('processReleaseZips', 'ZIP archive is password encrypted for release ' . $release['ID']);
				$this->password = true;

				return false;
			}

			$files = $zip->getFileList();
			$dataArray = array();
			if ($files !== false) {

				if ($this->echooutput) {
					echo 'z';
				}
				foreach ($files as $file) {
					$thisData = $zip->getFileData($file['name']);
					$dataArray[] = array('zip' => $file, 'data' => $thisData);

					// Process RARs inside the ZIP.
					if (preg_match('/\.(r\d+|part\d+|rar)$/i', $file['name']) || preg_match('/\bRAR\b/i', $thisData)) {

						$tmpFiles = $this->getRar($thisData);
						if ($tmpFiles !== false) {

							$limit = 0;
							foreach ($tmpFiles as $f) {

								if ($limit++ > 11) {
									break;
								}
								$this->addFile($f, $release, $rar = false, $nntp);
								$files[] = $f;
							}
						}
					} //Extract a NFO from the zip.
					else if ($this->nonfo === true && $file['size'] < 100000 && preg_match('/\.(nfo|inf|ofn)$/i', $file['name'])) {
						if ($file['compressed'] !== 1) {
							$nfo = new Info($this->echooutput);
							if ($this->nfo->addAlternateNfo($thisData, $release, $nntp)) {
								$this->c->error('processReleaseZips', 'Added NFO from ZIP file for releaseID ' . $release['ID']);
								if ($this->echooutput) {
									echo 'n';
								}
								$this->nonfo = false;
							}
						} else if ($this->tmux->zippath !== '' && $file['compressed'] === 1) {

							$zip->setExternalClient($this->tmux->zippath);
							$zipData = $zip->extractFile($file['name']);
							if ($zipData !== false && strlen($zipData) > 5) {
								$nfo = new Info($this->echooutput);
								if ($this->nfo->addAlternateNfo($zipData, $release, $nntp)) {

									$this->c->error('processReleaseZips', 'Added compressed NFO from ZIP file for releaseID ' . $release['ID']);
									if ($this->echooutput) {
										echo 'n';
									}

									$this->nonfo = false;
								}
							}
						}
					}
				}
			}

			if ($data) {
				$files = $dataArray;
				unset($dataArray);
			}

			unset($fetchedBinary, $zip);

			return $files;
		}

		/**
		 * Get contents of rar file.
		 *
		 * @note Called by processReleaseFiles and processReleaseZips
		 *
		 * @param $fetchedBinary
		 *
		 * @return array|bool
		 */
		protected
		function getRar($fetchedBinary)
		{
			$rar = new ArchiveInfo();
			$files = $retVal = false;
			if ($rar->setData($fetchedBinary, true)) {
				// Useless?
				$files = $rar->getArchiveFileList();
			}
			if ($rar->error) {
				$this->c->error('getRar', 'RAR Error: ' . $rar->error);

				return $retVal;
			}
			if (!empty($rar->isEncrypted)) {
				$this->c->error('getRar', 'Archive is password encrypted.');
				$this->password = true;

				return $retVal;
			}
			$tmp = $rar->getSummary(true, false);

			if (isset($tmp['is_encrypted']) && $tmp['is_encrypted'] != 0) {
				$this->c->error('getRar', 'Archive is password encrypted.');
				$this->password = true;

				return $retVal;
			}
			$files = $rar->getArchiveFileList();
			if ($files !== false) {
				$retVal = array();
				if ($this->echooutput !== false) {
					echo 'r';
				}
				foreach ($files as $file) {
					if (isset($file['name'])) {
						if (isset($file['error'])) {
							$this->c->error('getRar', "Error: {$file['error']} (in: {$file['source']})");
							continue;
						}
						if (isset($file['pass']) && $file['pass'] == true) {
							$this->password = true;
							break;
						}
						if (preg_match($this->supportFiles . ')(?!.{20,})/i', $file['name'])) {
							continue;
						}
						if (preg_match('/([^\/\\\\]+)(\.[a-z][a-z0-9]{2,3})$/i', $file['name'], $name)) {
							$rarFile = $this->tmpPath . $name[1] . mt_rand(0, 99999) . $name[2];
							$fetchedBinary = $rar->getFileData($file['name'], $file['source']);
							if ($this->site->mediainfopath !== '') {
								$this->addMediaFile($rarFile, $fetchedBinary);
							}
						}
						if (!preg_match('/\.(r\d+|part\d+)$/i', $file['name'])) {
							$retVal[] = $file;
						}
					}
				}
			}

			if (count($retVal) === 0)
				return false;

			return $retVal;
		}

		public
		function updateReleaseHasPreview($guid)
		{
			$this->db->exec(sprintf('UPDATE releases SET haspreview = 1 WHERE guid = %s', $this->db->escapeString($guid)));
		}

		function addfile($v, $release, $rar = false, $nntp)
		{
			if (!isset($nntp))
				exit($this->c->error("Not connected to usenet(postprocess->addfile).\n"));

			if (!isset($v['error']) && isset($v['source'])) {
				if ($rar !== false && preg_match('/\.zip$/', $v['source'])) {
					$zip = new ZipInfo();
					$tmpdata = $zip->getFileData($v['name'], $v['source']);
				} else if ($rar !== false)
					$tmpdata = $rar->getFileData($v['name'], $v['source']);
				else
					$tmpdata = false;

				// Check if we already have the file or not.
				// Also make sure we don't add too many files, some releases have 100's of files, like PS3 releases.
				if ($this->filesadded < 11 && $this->db->queryOneRow(sprintf('SELECT ID FROM releasefiles WHERE releaseID = %d AND name = %s AND size = %d', $release['ID'], $this->db->escapeString($v['name']), $v['size'])) === false) {
					$rf = new ReleaseFiles();
					if ($rf->add($release['ID'], $v['name'], $v['size'], $v['date'], $v['pass'])) {
						$this->filesadded++;
						$this->newfiles = true;
						if ($this->echooutput)
							echo '^';
					}
				}

				if ($tmpdata !== false) {
					// Extract a NFO from the rar.
					if ($this->nonfo === true && $v['size'] > 100 && $v['size'] < 100000 && preg_match('/(\.(nfo|inf|ofn)|info.txt)$/i', $v['name'])) {
						$nfo = new Info($this->echooutput);
						if ($this->nfo->addAlternateNfo($tmpData, $release, $nntp)) {
							$this->debug('added rar nfo');
							if ($this->echooutput)
								echo 'n';
							$this->nonfo = false;
						}
					} // Extract a video file from the compressed file.
					else if ($this->site->mediainfopath != '' && preg_match('/' . $this->videofileregex . '$/i', $v['name']))
						$this->addmediafile($this->tmpPath . 'sample_' . mt_rand(0, 99999) . '.avi', $tmpdata);
					// Extract an audio file from the compressed file.
					else if ($this->site->mediainfopath != '' && preg_match('/' . $this->audiofileregex . '$/i', $v['name'], $ext))
						$this->addmediafile($this->tmpPath . 'audio_' . mt_rand(0, 99999) . $ext[0], $tmpdata);
					else if ($this->site->mediainfopath != '' && preg_match('/([^\/\\\r]+)(\.[a-z][a-z0-9]{2,3})$/i', $v['name'], $name))
						$this->addmediafile($this->tmpPath . $name[1] . mt_rand(0, 99999) . $name[2], $tmpdata);
				}
				unset($tmpdata, $rf);
			}
		}


		public
		function debug($str)
		{
			if ($this->echooutput && $this->DEBUG_ECHO) {
				echo $this->c->debug($str);
			}
		}

		public
		function nzbFileList($nzb)
		{
			$num_pars = $i = 0;
			$result = array();

			$nzb = str_replace("\x0F", '', $nzb);
			$xml = @simplexml_load_string($nzb);
			if (!$xml || strtolower($xml->getName()) != 'nzb') {
				return $result;
			}

			foreach ($xml->file as $file) {
				// Subject.
				$title = $file->attributes()->subject;

				// Amoune of pars.
				if (preg_match('/\.par2/i', $title)) {
					$num_pars++;
				}

				$result[$i]['title'] = $title;

				// Extensions.
				if (preg_match(
					'/\.(\d{2,3}|7z|ace|ai7|srr|srt|sub|aiff|asc|avi|audio|bin|bz2|'
					. 'c|cfc|cfm|chm|class|conf|cpp|cs|css|csv|cue|deb|divx|doc|dot|'
					. 'eml|enc|exe|file|gif|gz|hlp|htm|html|image|iso|jar|java|jpeg|'
					. 'jpg|js|lua|m|m3u|mm|mov|mp3|mpg|nfo|nzb|odc|odf|odg|odi|odp|'
					. 'ods|odt|ogg|par2|parity|pdf|pgp|php|pl|png|ppt|ps|py|r\d{2,3}|'
					. 'ram|rar|rb|rm|rpm|rtf|sfv|sig|sql|srs|swf|sxc|sxd|sxi|sxw|tar|'
					. 'tex|tgz|txt|vcf|video|vsd|wav|wma|wmv|xls|xml|xpi|xvid|zip7|zip)'
					. '[" ](?!(\)|\-))/i', $file->attributes()->subject, $ext
				)
				) {

					if (preg_match('/\.r\d{2,3}/i', $ext[0])) {
						$ext[1] = 'rar';
					}

					$result[$i]['ext'] = strtolower($ext[1]);
				} else {
					$result[$i]['ext'] = '';
				}

				$filesize = $numsegs = 0;

				// File size.
				foreach ($file->segments->segment as $segment) {
					$filesize += $segment->attributes()->bytes;
					$numsegs++;
				}
				$result[$i]['size'] = $filesize;

				// File completion.
				if (preg_match('/(\d+)\)$/', $title, $parts)) {
					$result[$i]['partstotal'] = $parts[1];
				}
				$result[$i]['partsactual'] = $numsegs;

				// Groups.
				if (!isset($result[$i]['groups'])) {
					$result[$i]['groups'] = array();
				}
				foreach ($file->groups->group as $g) {
					array_push($result[$i]['groups'], (string)$g);
				}

				// Parts.
				if (!isset($result[$i]['segments'])) {
					$result[$i]['segments'] = array();
				}
				foreach ($file->segments->segment as $s) {
					array_push($result[$i]['segments'], (string)$s);
				}

				unset($result[$i]['segments']['@attributes']);
				$i++;
			}

			return $result;
		}

		function addmediafile($file, $data)
		{
			if (@file_put_contents($file, $data) !== false) {
				$xmlarray = @runCmd('"' . $this->site->mediainfopath . '" --Output=XML "' . $file . '"');
				if (is_array($xmlarray)) {
					$xmlarray = implode("\n", $xmlarray);
					$xmlObj = @simplexml_load_string($xmlarray);
					$arrXml = objectsIntoArray($xmlObj);
					if (!isset($arrXml['File']['track'][0]))
						@unlink($file);
				}
			}
		}

		public
		function parseImdb($str)
		{
			if (preg_match('/(?:imdb.*?)?(?:tt|Title\?)(\d{5,7})/i', $str, $matches)) {
				return trim($matches[1]);
			}

			return false;
		}

		public
		function processMovies($releaseToWork = '')
		{
			if ($this->site->lookupimdb == 1) {
				$movie = new Film($this->echooutput);
				$movie->processMovieReleases($releaseToWork);
			}
		}

		/**
		 * Lookup games if enabled.
		 *
		 * @return void
		 */
		public
		function processGames()
		{
			if ($this->site->lookupgames == 1) {
				$console = new Console($this->echooutput);
				$this->processConsoleReleases();
			}
		}

		public
		function processConsoleReleases()
		{
			$db = $this->db;
			$res = $db->queryDirect(sprintf('SELECT r.searchname, r.ID FROM releases r INNER JOIN category c ON r.categoryID = c.ID WHERE r.consoleinfoID IS NULL AND c.parentID = %d ORDER BY r.postdate DESC LIMIT %d', Category::CAT_PARENT_GAME, $this->gameqty));

			if ($res->rowCount() > 0) {
				if ($this->echooutput) {
					echo $this->c->header("\nProcessing " . $res->rowCount() . ' console release(s).');
				}

				foreach ($res as $arr) {
					$startTime = microtime(true);
					$usedAmazon = false;
					$gameInfo = $this->parseTitle($arr['searchname']);
					if ($gameInfo !== false) {

						if ($this->echooutput) {
							echo $this->c->headerOver('Looking up: ') . $this->c->primary($gameInfo['title'] . ' (' . $gameInfo['platform'] . ')');
						}

						// Check for existing console entry.
						$gameCheck = $this->getConsoleInfoByName($gameInfo['title'], $gameInfo['platform']);

						if ($gameCheck === false) {
							$gameId = $this->updateConsoleInfo($gameInfo);
							$usedAmazon = true;
							if ($gameId === false) {
								$gameId = -2;
							}
						} else {
							$gameId = $gameCheck['ID'];
						}

						// Update release.
						$db->exec(sprintf('UPDATE releases SET consoleinfoID = %d WHERE ID = %d', $gameId, $arr['ID']));
					} else {
						// Could not parse release title.
						$db->exec(sprintf('UPDATE releases SET consoleinfoID = %d WHERE ID = %d', -2, $arr['ID']));
						echo '.';
					}

					// Sleep to not flood amazon.
					$diff = floor((microtime(true) - $startTime) * 1000000);
					if ($this->sleeptime * 1000 - $diff > 0 && $usedAmazon === true) {
						usleep($this->sleeptime * 1000 - $diff);
					}
				}
			} else
				if ($this->echooutput) {
					echo $this->c->header('No console releases to process.');
				}
		}

		function parseTitle($releasename)
		{
			$matches = '';
			$releasename = preg_replace('/\sMulti\d?\s/i', '', $releasename);
			$result = array();

			// Get name of the game from name of release.
			preg_match('/^(.+((abgx360EFNet|EFNet\sFULL|FULL\sabgxEFNet|abgx\sFULL|abgxbox360EFNet)\s|illuminatenboard\sorg))?(?P<title>.*?)[\.\-_ ](v\.?\d\.\d|PAL|NTSC|EUR|USA|JP|ASIA|JAP|JPN|AUS|MULTI\.?5|MULTI\.?4|MULTI\.?3|PATCHED|FULLDVD|DVD5|DVD9|DVDRIP|PROPER|REPACK|RETAIL|DEMO|DISTRIBUTION|REGIONFREE|READ\.?NFO|NFOFIX|PS2|PS3|PSP|WII|X\-?BOX|XBLA|X360|NDS|N64|NGC)/i', $releasename, $matches);
			if (isset($matches['title'])) {
				$title = $matches['title'];
				// Replace dots or underscores with spaces.
				$result['title'] = preg_replace('/(\.|_|\%20)/', ' ', $title);
				// Needed to add code to handle DLC Properly.
				if (preg_match('/dlc/i', $result['title'])) {
					$result['dlc'] = '1';
					if (preg_match('/Rock Band Network/i', $result['title'])) {
						$result['title'] = 'Rock Band';
					} else if (preg_match('/\-/i', $result['title'])) {
						$dlc = explode("-", $result['title']);
						$result['title'] = $dlc[0];
					} else if (preg_match('/(.*? .*?) /i', $result['title'], $dlc)) {
						$result['title'] = $dlc[0];
					}
				}
			}

			//get the platform of the release
			preg_match('/[\.\-_ ](?P<platform>XBLA|WiiWARE|N64|SNES|NES|PS2|PS3|PS 3|PSP|WII|XBOX360|X\-?BOX|X360|NDS|NGC)/i', $releasename, $matches);
			if (isset($matches['platform'])) {
				$platform = $matches['platform'];
				if (preg_match('/^(XBLA)$/i', $platform)) {
					if (preg_match('/DLC/i', $title)) {
						$platform = str_replace('XBLA', 'XBOX360', $platform); // baseline single quote
					}
				}
				$browseNode = $this->getBrowseNode($platform);
				$result['platform'] = $platform;
				$result['node'] = $browseNode;
			}
			$result['release'] = $releasename;
			array_map("trim", $result);

			// Make sure we got a title and platform otherwise the resulting lookup will probably be shit. Other option is to pass the $release->categoryID here if we don't find a platform but that would require an extra lookup to determine the name. In either case we should have a title at the minimum.
			return (isset($result['title']) && !empty($result['title']) && isset($result['platform'])) ? $result : false;
		}

		function getBrowseNode($platform)
		{
			switch ($platform) {
				case 'PS2':
					$nodeId = '301712';
					break;
				case 'PS3':
					$nodeId = '14210751';
					break;
				case 'PSP':
					$nodeId = '11075221';
					break;
				case 'WII':
				case 'Wii':
					$nodeId = '14218901';
					break;
				case 'XBOX360':
				case 'X360':
					$nodeId = '14220161';
					break;
				case 'XBOX':
				case 'X-BOX':
					$nodeId = '537504';
					break;
				case 'NDS':
					$nodeId = '11075831';
					break;
				case 'N64':
					$nodeId = '229763';
					break;
				case 'SNES':
					$nodeId = '294945';
					break;
				case 'NES':
					$nodeId = '566458';
					break;
				case 'NGC':
					$nodeId = '541022';
					break;
				default:
					$nodeId = '468642';
					break;
			}

			return $nodeId;
		}

		public
		function matchBrowseNode($nodeName)
		{
			$str = '';

			//music nodes above mp3 download nodes
			switch ($nodeName) {
				case 'Action':
				case 'Adventure':
				case 'Arcade':
				case 'Board Games':
				case 'Cards':
				case 'Casino':
				case 'Flying':
				case 'Puzzle':
				case 'Racing':
				case 'Rhythm':
				case 'Role-Playing':
				case 'Simulation':
				case 'Sports':
				case 'Strategy':
				case 'Trivia':
					$str = $nodeName;
					break;
			}

			return ($str != '') ? $str : false;
		}

		public
		function updateConsoleInfo($gameInfo)
		{
			$db = $this->db;
			$gen = new Genres();
			$ri = new ReleaseImage();

			$con = array();
			$amaz = $this->fetchAmazonProperties($gameInfo['title'], $gameInfo['node']);
			if (!$amaz) {
				return false;
			}

			// Load genres.
			$defaultGenres = $gen->getGenres(Genres::CONSOLE_TYPE);
			$genreassoc = array();
			foreach ($defaultGenres as $dg) {
				$genreassoc[$dg['ID']] = strtolower($dg['title']);
			}

			// Get game properties.
			$con['coverurl'] = (string)$amaz->Items->Item->LargeImage->URL;
			if ($con['coverurl'] != "") {
				$con['cover'] = 1;
			} else {
				$con['cover'] = 0;
			}

			$con['title'] = (string)$amaz->Items->Item->ItemAttributes->Title;
			if (empty($con['title'])) {
				$con['title'] = $gameInfo['title'];
			}

			$con['platform'] = (string)$amaz->Items->Item->ItemAttributes->Platform;
			if (empty($con['platform'])) {
				$con['platform'] = $gameInfo['platform'];
			}

			// Beginning of Recheck Code.
			// This is to verify the result back from amazon was at least somewhat related to what was intended.
			// Some of the platforms don't match Amazon's exactly. This code is needed to facilitate rechecking.
			if (preg_match('/^X360$/i', $gameInfo['platform'])) {
				$gameInfo['platform'] = str_replace('X360', 'Xbox 360', $gameInfo['platform']);
			} // baseline single quote
			if (preg_match('/^XBOX360$/i', $gameInfo['platform'])) {
				$gameInfo['platform'] = str_replace('XBOX360', 'Xbox 360', $gameInfo['platform']);
			} // baseline single quote
			if (preg_match('/^NDS$/i', $gameInfo['platform'])) {
				$gameInfo['platform'] = str_replace('NDS', 'Nintendo DS', $gameInfo['platform']);
			} // baseline single quote
			if (preg_match('/^PS3$/i', $gameInfo['platform'])) {
				$gameInfo['platform'] = str_replace('PS3', 'PlayStation 3', $gameInfo['platform']);
			} // baseline single quote
			if (preg_match('/^PSP$/i', $gameInfo['platform'])) {
				$gameInfo['platform'] = str_replace('PSP', 'Sony PSP', $gameInfo['platform']);
			} // baseline single quote
			if (preg_match('/^Wii$/i', $gameInfo['platform'])) {
				$gameInfo['platform'] = str_replace('Wii', 'Nintendo Wii', $gameInfo['platform']); // baseline single quote
				$gameInfo['platform'] = str_replace('WII', 'Nintendo Wii', $gameInfo['platform']); // baseline single quote
			}
			if (preg_match('/^N64$/i', $gameInfo['platform'])) {
				$gameInfo['platform'] = str_replace('N64', 'Nintendo 64', $gameInfo['platform']);
			} // baseline single quote
			if (preg_match('/^NES$/i', $gameInfo['platform'])) {
				$gameInfo['platform'] = str_replace('NES', 'Nintendo NES', $gameInfo['platform']);
			} // baseline single quote
			if (preg_match('/Super/i', $con['platform'])) {
				$con['platform'] = str_replace('Super Nintendo', 'SNES', $con['platform']); // baseline single quote
				$con['platform'] = str_replace('Nintendo Super NES', 'SNES', $con['platform']); // baseline single quote
			}
			// Remove Online Game Code So Titles Match Properly.
			if (preg_match('/\[Online Game Code\]/i', $con['title'])) {
				$con['title'] = str_replace(' [Online Game Code]', '', $con['title']);
			} // baseline single quote
// Basically the XBLA names contain crap, this is to reduce the title down far enough to be usable.
			if (preg_match('/xbla/i', $gameInfo['platform'])) {
				$gameInfo['title'] = substr($gameInfo['title'], 0, 10);
				$con['substr'] = $gameInfo['title'];
			}

			// This actual compares the two strings and outputs a percentage value.
			$titlepercent = $platformpercent = '';
			similar_text(strtolower($gameInfo['title']), strtolower($con['title']), $titlepercent);
			similar_text(strtolower($gameInfo['platform']), strtolower($con['platform']), $platformpercent);

			// Since Wii Ware games and XBLA have inconsistent original platforms, as long as title is 50% its ok.
			if (preg_match('/(wiiware|xbla)/i', $gameInfo['platform'])) {
				if ($titlepercent >= 50) {
					$platformpercent = 100;
				}
			}

			// If the release is DLC matching sucks, so assume anything over 50% is legit.
			if (isset($gameInfo['dlc']) && $gameInfo['dlc'] == 1) {
				if ($titlepercent >= 50) {
					$titlepercent = 100;
					$platformpercent = 100;
				}
			}

			/* Show the percentages.
			  echo("Matched: Title Percentage: $titlepercent%");
			  echo("Matched: Platform Percentage: $platformpercent%"); */

			// If the Title is less than 80% Platform must be 100% unless it is XBLA.
			if ($titlepercent < 70) {
				if ($platformpercent != 100) {
					return false;
				}
			}

			// If title is less than 80% then its most likely not a match.
			if ($titlepercent < 70) {
				return false;
			}

			// Platform must equal 100%.
			if ($platformpercent != 100) {
				return false;
			}

			$con['asin'] = (string)$amaz->Items->Item->ASIN;

			$con['url'] = (string)$amaz->Items->Item->DetailPageURL;
			$con['url'] = str_replace("%26tag%3Dws", "%26tag%3Dopensourceins%2D21", $con['url']);

			$con['salesrank'] = (string)$amaz->Items->Item->SalesRank;
			if ($con['salesrank'] == "") {
				$con['salesrank'] = 'null';
			}

			$con['publisher'] = (string)$amaz->Items->Item->ItemAttributes->Publisher;

			$con['esrb'] = (string)$amaz->Items->Item->ItemAttributes->ESRBAgeRating;

			$con['releasedate'] = $db->escapeString((string)$amaz->Items->Item->ItemAttributes->ReleaseDate);
			if ($con['releasedate'] == "''") {
				$con['releasedate'] = 'null';
			}

			$con['review'] = "";
			if (isset($amaz->Items->Item->EditorialReviews)) {
				$con['review'] = trim(strip_tags((string)$amaz->Items->Item->EditorialReviews->EditorialReview->Content));
			}

			$genreKey = -1;
			$genreName = '';
			if (isset($amaz->Items->Item->BrowseNodes) || isset($amaz->Items->Item->ItemAttributes->Genre)) {
				if (isset($amaz->Items->Item->BrowseNodes)) {
					//had issues getting this out of the browsenodes obj
					//workaround is to get the xml and load that into its own obj
					$amazGenresXml = $amaz->Items->Item->BrowseNodes->asXml();
					$amazGenresObj = simplexml_load_string($amazGenresXml);
					$amazGenres = $amazGenresObj->xpath("//Name");
					foreach ($amazGenres as $amazGenre) {
						$currName = trim($amazGenre[0]);
						if (empty($genreName)) {
							$genreMatch = $this->matchBrowseNode($currName);
							if ($genreMatch !== false) {
								$genreName = $genreMatch;
								break;
							}
						}
					}
				}

				if (empty($genreName) && isset($amaz->Items->Item->ItemAttributes->Genre)) {
					$a = (string)$amaz->Items->Item->ItemAttributes->Genre;
					$b = str_replace('-', ' ', $a);
					$tmpGenre = explode(' ', $b);
					foreach ($tmpGenre as $tg) {
						$genreMatch = $this->matchBrowseNode(ucwords($tg));
						if ($genreMatch !== false) {
							$genreName = $genreMatch;
							break;
						}
					}
				}
			}

			if (empty($genreName)) {
				$genreName = 'Unknown';
			}

			if (in_array(strtolower($genreName), $genreassoc)) {
				$genreKey = array_search(strtolower($genreName), $genreassoc);
			} else {
				$genreKey = $db->queryInsert(sprintf("INSERT INTO genres (`title`, `type`) VALUES (%s, %d)", $db->escapeString($genreName), Genres::CONSOLE_TYPE));
			}

			$con['consolegenre'] = $genreName;
			$con['consolegenreID'] = $genreKey;

			$check = $db->queryOneRow(sprintf('SELECT ID FROM consoleinfo WHERE title = %s AND asin = %s', $db->escapeString($con['title']), $db->escapeString($con['asin'])));
			if ($check === false) {
				$consoleId = $db->queryInsert(sprintf("INSERT INTO consoleinfo (title, asin, url, salesrank, platform, publisher, genreid, esrb, releasedate, review, cover, createddate, updateddate) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, NOW(), NOW())", $db->escapeString($con['title']), $db->escapeString($con['asin']), $db->escapeString($con['url']), $con['salesrank'], $db->escapeString($con['platform']), $db->escapeString($con['publisher']), ($con['consolegenreID'] == -1 ? "null" : $con['consolegenreID']), $db->escapeString($con['esrb']), $con['releasedate'], $db->escapeString($con['review']), $con['cover']));
			} else {
				$consoleId = $check['ID'];
				$db->exec(sprintf('UPDATE consoleinfo SET title = %s, asin = %s, url = %s, salesrank = %s, platform = %s, publisher = %s, genreid = %s, esrb = %s, releasedate = %s, review = %s, cover = %s, updateddate = NOW() WHERE ID = %d', $db->escapeString($con['title']), $db->escapeString($con['asin']), $db->escapeString($con['url']), $con['salesrank'], $db->escapeString($con['platform']), $db->escapeString($con['publisher']), ($con['consolegenreID'] == -1 ? "null" : $con['consolegenreID']), $db->escapeString($con['esrb']), $con['releasedate'], $db->escapeString($con['review']), $con['cover'], $consoleId));
			}

			if ($consoleId) {
				if ($this->echooutput) {
					echo $this->c->header("Added/updated game: ") .
						$this->c->alternateOver("   Title:    ") . $this->c->primary($con['title']) .
						$this->c->alternateOver("   Platform: ") . $this->c->primary($con['platform']);
				}

				$con['cover'] = $ri->saveImage($consoleId, $con['coverurl'], $this->imgSavePath, 250, 250);
			} else {
				if ($this->echooutput) {
					echo $this->c->headerOver("Nothing to update: ") . $this->c->primary($con['title'] . " (" . $con['platform']);
				}
			}

			return $consoleId;
		}

		public
		function fetchAmazonProperties($title, $node)
		{
			$obj = new AmazonProductAPI($this->pubkey, $this->privkey, $this->asstag);
			try {
				$result = $obj->searchProducts($title, AmazonProductAPI::GAMES, "NODE", $node);
			} catch (Exception $e) {
				$result = false;
			}

			return $result;
		}

		public
		function getConsoleInfoByName($title, $platform)
		{
			$db = $this->db;
			$like = 'LIKE';

			return $db->queryOneRow(sprintf("SELECT * FROM consoleinfo WHERE title LIKE %s AND platform %s %s", $db->escapeString("%" . $title . "%"), $like, $db->escapeString("%" . $platform . "%")));
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

		//end of testing

	}