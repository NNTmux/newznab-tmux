<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/framework/cache.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR. "lib/category.php");
require_once(WWW_DIR."/lib/releases.php");
require_once(WWW_DIR."/lib/releaseimage.php");
require_once(WWW_DIR."/lib/nzb.php");
require_once(WWW_DIR."/lib/rarinfo/par2info.php");
require_once(WWW_DIR."/lib/rarinfo/archiveinfo.php");
require_once(WWW_DIR."/lib/rarinfo/zipinfo.php");
require_once(WWW_DIR."/lib/nfo.php");
require_once(WWW_DIR."/lib/site.php");
require_once(WWW_DIR."/lib/util.php");
require_once(WWW_DIR."/lib/groups.php");
require_once(WWW_DIR."/lib/nntp.php");
require_once(WWW_DIR."/lib/tvrage.php");
require_once(WWW_DIR."/lib/movie.php");
require_once(WWW_DIR."/lib/nntp.php");
require_once(WWW_DIR."/lib/postprocess.php");
require_once(WWW_DIR."/lib/Tmux.php");
require_once("consoletools.php");
require_once("ColorCLI.php");
require_once("nzbcontents.php");
require_once("namefixer.php");
require_once("TraktTv.php");



 //*addedd from nZEDb for testing

class Functions

{
  function __construct($echooutput=true)
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
    $this->tmpPath = $this->site->tmpunrarpath;
    $this->audiofileregex = '\.(AAC|AIFF|APE|AC3|ASF|DTS|FLAC|MKA|MKS|MP2|MP3|RA|OGG|OGM|W64|WAV|WMA)';
	$this->ignorebookregex = '/\b(epub|lit|mobi|pdf|sipdf|html)\b.*\.rar(?!.{20,})/i';
	$this->supportfiles = '/\.(vol\d{1,3}\+\d{1,3}|par2|srs|sfv|nzb';
	$this->videofileregex = '\.(AVI|F4V|IFO|M1V|M2V|M4V|MKV|MOV|MP4|MPEG|MPG|MPGV|MPV|OGV|QT|RM|RMVB|TS|VOB|WMV)';
    $this->segmentstodownload = (!empty($this->tmux->segmentstodownload)) ? $this->tmux->segmentstodownload : 2;
    $this->passchkattempts = (!empty($this->tmux->passchkattempts)) ? $this->tmux->passchkattempts : 1;
    $this->partsqty = (!empty($this->tmux->maxpartsprocessed)) ? $this->tmux->maxpartsprocessed : 3;
    $this->movieqty = (!empty($this->tmux->maximdbprocessed)) ? $this->tmux->maximdbprocessed : 100;
    $this->rageqty = (!empty($his->tmux->maxrageprocessed)) ? $this->tmux->maxrageprocessed : 75;
    $this->DEBUG_ECHO = ($this->tmux->debuginfo == '0') ? false : true;
		if (defined('DEBUG_ECHO') && DEBUG_ECHO == true) {
			$this->DEBUG_ECHO = true;
		}
    $this->nzbs = (!empty($this->tmux->maxnfoprocessed)) ? $this->tmux->maxnfoprocessed : 100;
    $this->service = '';
    $this->debug = ($this->tmux->debuginfo == "0") ? false : true;

  }
    /**
	 * @var object Instance of PDO class.
	 */
	private static $pdo = null;
  // database function
    public function queryArray($query)

	{
	    $db = new DB();
		if ($query == '') return false;

		$result = $db->queryDirect($query);
		$rows = array();
		foreach ($result as $row)
		{
			$rows[] = $row;
		}

		return (!isset($rows)) ? false : $rows;
	}

    	// Used for deleting, updating (and inserting without needing the last insert ID).
	public function exec($query)
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
			while (($e->errorInfo[1] == 1213 || $e->errorInfo[0] == 40001 || $e->errorInfo[1] == 1205 || $e->getMessage()=='SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction') && $i <= 10)
			{
				echo $this->c->error("A Deadlock or lock wait timeout has occurred, sleeping.\n");
				$this->consoletools->showsleep($i * $i);
				$run = self::$pdo->prepare($query);
				$run->execute();
				return $run;
				$i++;
			}
			if ($e->errorInfo[1] == 1213 || $e->errorInfo[0] == 40001 || $e->errorInfo[1] == 1205)
			{
				//echo "Error: Deadlock or lock wait timeout.";
				return false;
			}
			else if ($e->errorInfo[1]==1062 || $e->errorInfo[0]==23000)
			{
				//echo "\nError: Update would create duplicate row, skipping\n";
				return false;
			}
			else if ($e->errorInfo[1]==1406 || $e->errorInfo[0]==22001)
			{
				//echo "\nError: Too large to fit column length\n";
				return false;
			}
			else
				echo $this->c->error($e->getMessage());
			return false;
		}
	}

    public function Prepare($query, $options = array())
	{
		try {
			$PDOstatement = self::$pdo->prepare($query, $options);
		} catch (PDOException $e) {
			//echo $this->c->error($e->getMessage());
			$PDOstatement = false;
		}
		return $PDOstatement;
	}
    public function from_unixtime($utime, $escape=true)
	{
		if ($escape === true)
		{
		    return 'FROM_UNIXTIME('.$utime.')';
		}
		else
			return date('Y-m-d h:i:s', $utime);
	}

	// Date to unix time.
	// (substitute for mysql's UNIX_TIMESTAMP() function)
	public function unix_timestamp($date)
	{
		return strtotime($date);
	}
 //  gets name of category from category.php
    public function getNameByID($ID)
	{
		$db = new DB();
		$parent = $db->queryOneRow(sprintf("SELECT title FROM category WHERE ID = %d", substr($ID, 0, 1)."000"));
		$cat = $db->queryOneRow(sprintf("SELECT title FROM category WHERE ID = %d", $ID));
		return $parent["title"]." ".$cat["title"];
	}

    public function getIDByName($name)
	{
		$db = new DB();
		$res = $db->queryOneRow(sprintf("SELECT ID FROM groups WHERE name = %s", $db->escapeString($name)));
		return $res["ID"];
	}

    //deletes from releases
    public function fastDelete($ID, $guid, $site)
	{
		$db = new DB();
		$nzb = new NZB();
		$ri = new ReleaseImage();


		//
		// delete from disk.
		//
		$nzbpath = $nzb->getNZBPath($guid, $site->nzbpath, false);

		if (file_exists($nzbpath))
			unlink($nzbpath);

		$db->exec(sprintf("delete releases, releasenfo, releasecomment, usercart, releasefiles, releaseaudio, releasesubs, releasevideo, releaseextrafull
							from releases
								LEFT OUTER JOIN releasenfo on releasenfo.releaseID = releases.ID
								LEFT OUTER JOIN releasecomment on releasecomment.releaseID = releases.ID
								LEFT OUTER JOIN usercart on usercart.releaseID = releases.ID
								LEFT OUTER JOIN releasefiles on releasefiles.releaseID = releases.ID
								LEFT OUTER JOIN releaseaudio on releaseaudio.releaseID = releases.ID
								LEFT OUTER JOIN releasesubs on releasesubs.releaseID = releases.ID
								LEFT OUTER JOIN releasevideo on releasevideo.releaseID = releases.ID
								LEFT OUTER JOIN releaseextrafull on releaseextrafull.releaseID = releases.ID
							where releases.ID = %d", $ID));

		$ri->delete($guid); // This deletes a file so not in the query
	}
    //reads name of group
     public function getByNameByID($ID)
	{
		$db = new DB();
		$res = $db->queryOneRow(sprintf("select name from groups where ID = %d ", $ID));
		return $res["name"];
	}
     //Add release nfo, imported from nZEDb
    	public function addReleaseNfo($relid)
	{
		$db = new DB();
		return $db->queryInsert(sprintf("INSERT IGNORE INTO releasenfo (releaseID) VALUE (%d)", $relid));
	}
     // Adds an NFO found from predb, rar, zip etc...
	public function addAlternateNfo($db, $nfo, $release, $nntp)
	{
		if (!isset($nntp))
			exit($this->c->error("Unable to connect to usenet.\n"));

		if ($release['ID'] > 0)
		{
				$compress = 'compress(%s)';
				$nc = $db->escapeString($nfo);

			$ckreleaseid = $db->queryOneRow(sprintf('SELECT ID FROM releasenfo WHERE releaseID = %d', $release['ID']));
			if (!isset($ckreleaseid['ID']))
				$db->exec(sprintf('INSERT INTO releasenfo (nfo, releaseID) VALUES ('.$compress.', %d)', $nc, $release['ID']));
			$db->exec(sprintf('UPDATE releases SET releasenfoID = %d, nfostatus = 1 WHERE ID = %d', $ckreleaseid['ID'], $release['ID']));
			if (!isset($release['completion']))
				$release['completion'] = 0;
			if ($release['completion'] == 0)
			{
				$nzbcontents = new NZBcontents($this->echooutput);
				$nzbcontents->NZBcompletion($release['guid'], $release['ID'], $release['groupID'], $nntp, $db);
			} 
			return true;
		}
		else
			return false;
	}
    // Confirm that the .nfo file is not something else.
	public function isNFO($possibleNFO, $guid) {
		$r = false;
		if ($possibleNFO === false) {
			return $r;
		}
			// Make sure it's not too big or small, size needs to be at least 12 bytes for header checking.
		$size = strlen($possibleNFO);
		if ($size < 100 * 1024 && $size > 12) {
			// Ignore common file types.
			if (preg_match(
				'/(^RIFF|)<\?xml|;\s*Generated\s*by.*SF\w|\A\s*PAR|\.[a-z0-9]{2,7}\s*[a-z0-9]{8}|\A\s*RAR|\A.{0,10}(JFIF|matroska|ftyp|ID3)|\A=newz\[NZB\]=/i'
				, $possibleNFO)) {
				return $r;
			}// file workswith files, so save to disk
			$tmpPath = $this->tmpPath.$guid.'.nfo';
			file_put_contents($tmpPath, $possibleNFO);

			// Linux boxes have 'file' (so should Macs)
			if (strtolower(substr(PHP_OS, 0, 3)) != 'win') {
				exec("file -b $tmpPath", $result);
				if (is_array($result)) {
					if (count($result) > 1) {
						$result = implode(',', $result[0]);
					} else {
						$result = $result[0];
					}
				}
				$test = preg_match('#^.*(ISO-8859|UTF-(?:8|16|32) Unicode(?: \(with BOM\)|)|ASCII)(?: English| C++ Program|) text.*$#i', $result);
				// if the result is false, something went wrong, continue with getID3 tests.
				if ($test !== false) {
					if ($test == 1) {
						@unlink($tmpPath);
						return true;
					}

					// non-printable characters should never appear in text, so rule them out.
					$test = preg_match('#\x00|\x01|\x02|\x03|\x04|\x05|\x06|\x07|\x08|\x0B|\x0E|\x0F|\x12|\x13|\x14|\x15|\x16|\x17|\x18|\x19|\x1A|\x1B|\x1C|\x1D|\x1E|\x1F#', $possibleNFO);
					if ($test) {
						@unlink($tmpPath);
						return false;
					}
				}
			}
			    require_once(WWW_DIR."/lib/rarinfo/par2info.php");
				$par2info = new Par2Info();
				$par2info->setData($possibleNFO);
				if ($par2info->error) {
					// Check if it's an SFV.
					require_once(WWW_DIR."/lib/rarinfo/sfvinfo.php");
					$sfv = new SfvInfo;
					$sfv->setData($possibleNFO);
					if ($sfv->error) {
						return true;
					}
				}
				   	}
		return $r;
	}

	//	Check if the possible NFO is a JFIF.
	function check_JFIF($filename)
	{
		$fp = @fopen($filename, 'r');
		if ($fp)
		{
			// JFIF often (but not always) starts at offset 6.
			if (fseek($fp, 6) == 0)
			{
				// JFIF header is 16 bytes.
				if (($bytes = fread($fp, 16)) !== false)
				{
					// Make sure it is JFIF header.
					if (substr($bytes, 0, 4) == "JFIF")
						return true;
					else
						return false;
				}
			}
		}
	}

    //
	// Attempt to get a better name from a par2 file and categorize the release.
	//
    public function parsePAR2($messageID, $relID, $groupID, $nntp, $show)
	{
		$db = new DB();
		$category = new Category();
        $functions = new Functions();
        $c = new ColorCLI;

        if (!isset($nntp))
			exit($c->error("Not connected to usenet(functions->parsePAR2).\n"));

        if ($messageID == '')
			return false;
        $t = 'UNIX_TIMESTAMP(postdate)';
		$quer = $db->queryOneRow('SELECT groupID, categoryID, searchname, '.$t.' as postdate, ID as releaseID FROM releases WHERE isrenamed = 0 AND ID = '.$relID);
  		if ($quer['categoryID'] != Category::CAT_MISC_OTHER)
            return false;
            
        $nntp = new Nntp();
        $nntp->doConnect();
		$groups = new Groups();
        $functions = new Functions();
		$par2 = $nntp->getMessage($functions->getByNameByID($groupID), $messageID);
		if (PEAR::isError($par2))
		{
			$nntp->doQuit();
			$nntp->doConnect();
			$par2 = $nntp->getMessage($functions->getByNameByID($groupID), $messageID);
			if (PEAR::isError($par2))
			{
				$nntp->doQuit();
				return false;
			}
		}

		$par2info = new Par2Info();
		$par2info->setData($par2);
		if ($par2info->error)
			return false;

		$files = $par2info->getFileList();
		if ($files !== false && count($files) > 0)
		{
            $db = new DB();
            $namefixer = new Namefixer;
			$rf = new ReleaseFiles();
			$relfiles = 0;
			$foundname = false;
			foreach ($files as $fileID => $file)
			{
			   if (!array_key_exists('name', $file))
					return false;// Add to releasefiles.
				if (($relfiles < 11 && $db->queryOneRow(sprintf("SELECT ID FROM releasefiles WHERE releaseID = %d AND name = %s", $relID, $db->escapeString($file["name"])))) === false)
				{
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

    //Categorize releases
    public function categorizeRelease($type, $where="", $echooutput=false)
	{
		$db = new DB();
		$cat = new Category();
		$consoletools = new consoleTools();
		$relcount = 0;
		$resrel = $db->prepare("SELECT ID, ".$type.", groupID FROM releases ".$where);
        $resrel->execute();
		$total = $resrel->rowCount();
		if ($total > 0)
		{
			foreach ($resrel as $rowrel)
			{
				$catId = $cat->determineCategory($rowrel[$type], $rowrel['groupID']);
				$db->queryDirect(sprintf("UPDATE releases SET categoryID = %d, relnamestatus = 1 WHERE ID = %d", $catId, $rowrel['ID']));
				$relcount ++;
				if ($echooutput)
					$consoletools->overWrite("Categorizing:".$consoletools->percentString($relcount,$total));
			}
		}
		if ($echooutput !== false && $relcount > 0)
			echo "\n";
		return $relcount;
	}

    // Optimises/repairs tables on mysql.
	public function optimise($admin = false, $type = '')
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
				$tbls = rtrim(trim($tbls),',');
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
       //taken from nZEDb's PostProcess.php
    	public function processAdditionalThreaded($releaseToWork = '', $nntp)
	{
		if (!isset($nntp)) {
			exit($this->c->error("Not connected to usenet(postprocess->processAdditionalThreaded).\n"));
		}

		$this->processAdditional($releaseToWork, $ID = '', $gui = false, $groupID = '', $nntp);
	}

    // Check for passworded releases, RAR contents and Sample/Media info.
	public function processAdditional($releaseToWork = '', $ID = '', $gui = false, $groupID = '', $nntp)
	{
		if (!isset($nntp))
			exit($this->c->error("Not connected to usenet(postprocess->processAdditional).\n"));

		$like = 'LIKE';

		// not sure if ugo ever implemented this in the ui, other that his own
		if ($gui) {
			$ok = false;
			while (!$ok) {
				usleep(mt_rand(10, 300));
				$this->db->setAutoCommit(false);
				$ticket = $this->db->queryOneRow('SELECT value  FROM tmux WHERE setting ' . $like . " 'nextppticket'");
				$ticket = $ticket['value'];
				$upcnt = $this->db->exec(sprintf("UPDATE tmux SET value = %d WHERE setting %s 'nextppticket' AND value = %d", $ticket + 1, $like, $ticket));
				if (count($upcnt) == 1) {
					$ok = true;
					$this->db->Commit();
				} else
					$this->db->Rollback();
			}
			$this->db->setAutoCommit(true);
			$sleep = 1;
			$delay = 100;

			do {
				sleep($sleep);
				$serving = $this->db->queryOneRow('SELECT * FROM tmux WHERE setting ' . $like . " 'currentppticket1'");
				$time = strtotime($serving['updateddate']);
				$serving = $serving['value'];
				$sleep = min(max(($time + $delay - time()) / 5, 2), 15);
			} while ($serving > $ticket && ($time + $delay + 5 * ($ticket - $serving)) > time());
		}

		$groupID = $groupID == '' ? '' : 'AND groupID = ' . $groupID;
		// Get out all releases which have not been checked more than max attempts for password.
		if ($ID != '')
			$result = $this->db->queryDirect('SELECT r.ID, r.guid, r.name, c.disablepreview, r.size, r.groupID, r.nfostatus, r.completion, r.categoryID FROM releases r LEFT JOIN category c ON c.ID = r.categoryID WHERE r.ID = ' . $ID);
		else {
			$result = $totresults = 0;
			if ($releaseToWork == '') {
				$i = -1;
				$tries = (5 * -1) - 1;
				while (($totresults != $this->addqty) && ($i >= $tries)) {
					$result = $this->db->queryDirect(sprintf('SELECT r.ID, r.guid, r.name, c.disablepreview, r.size, r.groupID, r.nfostatus, r.completion, r.categoryID FROM releases r LEFT JOIN category c ON c.ID = r.categoryID WHERE r.size < %d ' . $groupID . ' AND r.passwordstatus BETWEEN %d AND -1 AND (r.haspreview = -1 AND c.disablepreview = 0) ORDER BY postdate DESC LIMIT %d', $this->maxsize * 1073741824, $i, $this->addqty));
					$totresults = $result->rowCount();
					if ($totresults > 0)
						$this->doecho('Passwordstatus = ' . $i . ': Available to process = ' . $totresults);
					$i--;
				}
			} else {
				$pieces = explode('           =+=            ', $releaseToWork);
				$result = array(array('ID' => $pieces[0], 'guid' => $pieces[1], 'name' => $pieces[2], 'disablepreview' => $pieces[3], 'size' => $pieces[4], 'groupID' => $pieces[5], 'nfostatus' => $pieces[6], 'categoryID' => $pieces[7]));
				$totresults = 1;
			}
		}


		$rescount = $startCount = $totresults;
		if ($rescount > 0) {
			if ($this->echooutput && $rescount > 1) {
				$this->doecho('Additional post-processing, started at: ' . date('D M d, Y G:i a'));
				$this->doecho('Downloaded: b = yEnc article, f= failed ;Processing: z = zip file, r = rar file');
				$this->doecho('Added: s = sample image, j = jpeg image, A = audio sample, a = audio mediainfo, v = video sample');
				$this->doecho('Added: m = video mediainfo, n = nfo, ^ = file details from inside the rar/zip');
				// Get count of releases per passwordstatus
				$pw1 = $this->db->query('SELECT count(*) as count FROM releases WHERE haspreview = -1 and passwordstatus = -1');
				$pw2 = $this->db->query('SELECT count(*) as count FROM releases WHERE haspreview = -1 and passwordstatus = -2');
				$pw3 = $this->db->query('SELECT count(*) as count FROM releases WHERE haspreview = -1 and passwordstatus = -3');
				$pw4 = $this->db->query('SELECT count(*) as count FROM releases WHERE haspreview = -1 and passwordstatus = -4');
				$pw5 = $this->db->query('SELECT count(*) as count FROM releases WHERE haspreview = -1 and passwordstatus = -5');
				$pw6 = $this->db->query('SELECT count(*) as count FROM releases WHERE haspreview = -1 and passwordstatus = -6');
				$this->doecho('Available to process: -6 = ' . number_format($pw6[0]['count']) . ', -5 = ' . number_format($pw5[0]['count']) . ', -4 = ' . number_format($pw4[0]['count']) . ', -3 = ' . number_format($pw3[0]['count']) . ', -2 = ' . number_format($pw2[0]['count']) . ', -1 = ' . number_format($pw1[0]['count']));
			}

			$ri = new ReleaseImage();
			$nzbcontents = new NZBContents($this->echooutput);
			$nzb = new NZB($this->echooutput);
			$groups = new Groups();
			$processSample = ($this->site->ffmpegpath != '') ? true : false;
			$processMediainfo = ($this->site->mediainfopath != '') ? true : false;
			$processAudioinfo = ($this->site->mediainfopath != '') ? true : false;
			$processPasswords = ($this->site->unrarpath != '') ? true : false;
			$tmpPath = $this->tmpPath;

			// Loop through the releases.
			foreach ($result as $rel) {
				if ($this->echooutput && $releaseToWork == '') {
					echo "\n[" . $this->c->primaryOver($startCount--) . ']';
				} else if ($this->echooutput) {
					echo '[' . $this->c->primaryOver($rel['ID']) . ']';
				}

				// Per release defaults.
				$this->tmpPath = $tmpPath . $rel['guid'] . '/';
				if (!is_dir($this->tmpPath)) {
					$old = umask(0777);
					mkdir($this->tmpPath, 0777, true);
					chmod($this->tmpPath, 0777);
					umask($old);

					if (!is_dir($this->tmpPath)) {
						if ($this->echooutput)
							echo $this->c->error("Unable to create directory: {$this->tmpPath}");
						// Decrement passwordstatus.
						$this->db->exec('UPDATE releases SET passwordstatus = passwordstatus - 1 WHERE ID = ' . $rel['ID']);
						continue;
					}
				}

				$nzbpath = $nzb->getNZBPath($rel['guid'], $this->site->nzbpath, false);
				if (!file_exists($nzbpath)) {
					// The nzb was not located. decrement the passwordstatus.
					$this->db->exec('UPDATE releases SET passwordstatus = passwordstatus - 1 WHERE ID = ' . $rel['ID']);
					continue;
				}

				// turn on output buffering
				ob_start();

				// uncompress the nzb
				@readgzfile($nzbpath);

				// read the nzb into memory
				$nzbfile = ob_get_contents();

				// Clean (erase) the output buffer and turn off output buffering
				ob_end_clean();

				// get a list of files in the nzb
				$nzbfiles = $this->nzbFileList($nzbfile);
				if (count($nzbfiles) == 0) {
					// There does not appear to be any files in the nzb, decrement passwordstatus
					$this->db->exec('UPDATE releases SET passwordstatus = passwordstatus - 1 WHERE ID = ' . $rel['ID']);
					continue;
				}

				// sort the files
				usort($nzbfiles, 'Functions::sortrar');

				// Only process for samples, previews and images if not disabled.
				$blnTookSample = ($rel['disablepreview'] == 1) ? true : false;
				$blnTookMediainfo = $blnTookAudioinfo = $blnTookJPG = $blnTookVideo = false;
				if ($processSample === false)
					$blnTookSample = true;
				if ($processMediainfo === false)
					$blnTookMediainfo = true;
				if ($processAudioinfo === false)
					$blnTookAudioinfo = true;
				$passStatus = array(Releases::PASSWD_NONE);
				$bingroup = $samplegroup = $mediagroup = $jpggroup = $audiogroup = '';
				$samplemsgid = $mediamsgid = $audiomsgid = $jpgmsgid = $audiotype = $mid = $rarpart = array();
				$hasrar = $ignoredbooks = $failed = $this->filesadded = 0;
				$this->password = $this->nonfo = $notmatched = $flood = $foundcontent = false;

				// Make sure we don't already have an nfo.
				if ($rel['nfostatus'] !== 1)
					$this->nonfo = true;

				$groupName = $this->getByNameByID($rel['groupID']);
				// Go through the nzb for this release looking for a rar, a sample etc...
				foreach ($nzbfiles as $nzbcontents) {
					// Check if it's not a nfo, nzb, par2 etc...
					if (preg_match($this->supportfiles . "|nfo\b|inf\b|ofn\b)($|[ \")\]-])(?!.{20,})/i", $nzbcontents['title']))
						continue;

					// Check if it's a rar/zip.
					if (preg_match("/\.(part0*1|part0+|r0+|r0*1|rar|0+|0*10?|zip)(\.rar)*($|[ \")\]-])|\"[a-f0-9]{32}\.[1-9]\d{1,2}\".*\(\d+\/\d{2,}\)$/i", $nzbcontents['title']))
						$hasrar = 1;
					else if (!$hasrar)
						$notmatched = true;

					// Look for a sample.
					if ($processSample === true && !preg_match('/\.(jpg|jpeg)/i', $nzbcontents['title']) && preg_match('/sample/i', $nzbcontents['title'])) {
						if (isset($nzbcontents['segments']) && empty($samplemsgid)) {
							$samplegroup = $groupName;
							$samplemsgid[] = $nzbcontents['segments'][0];

							for ($i = 1; $i < $this->segmentstodownload; $i++) {
								if (count($nzbcontents['segments']) > $i)
									$samplemsgid[] = $nzbcontents['segments'][$i];
							}
						}
					}

					// Look for a media file.
					if ($processMediainfo === true && !preg_match('/sample/i', $nzbcontents['title']) && preg_match('/' . $this->videofileregex . '[. ")\]]/i', $nzbcontents['title'])) {
						if (isset($nzbcontents['segments']) && empty($mediamsgid)) {
							$mediagroup = $groupName;
							$mediamsgid[] = $nzbcontents['segments'][0];
						}
					}

					// Look for a audio file.
					if ($processAudioinfo === true && preg_match('/' . $this->audiofileregex . '[. ")\]]/i', $nzbcontents['title'], $type)) {
						if (isset($nzbcontents['segments']) && empty($audiomsgid)) {
							$audiogroup = $groupName;
							$audiotype = $type[1];
							$audiomsgid[] = $nzbcontents['segments'][0];
						}
					}

					// Look for a JPG picture.
					/*if ($processJPGSample === true && !preg_match('/flac|lossless|mp3|music|inner-sanctum|sound/i', $groupName) && preg_match('/\.(jpg|jpeg)[. ")\]]/i', $nzbcontents['title'])) {
						if (isset($nzbcontents['segments']) && empty($jpgmsgid)) {
							$jpggroup = $groupName;
							$jpgmsgid[] = $nzbcontents['segments'][0];
							if (count($nzbcontents['segments']) > 1)
								$jpgmsgid[] = $nzbcontents['segments'][1];
						}
					} */
					if (preg_match($this->ignorebookregex, $nzbcontents['title']))
						$ignoredbooks++;
				}

				// Ignore massive book NZB's.
				if (count($nzbfiles) > 40 && $ignoredbooks * 2 >= count($nzbfiles)) {
					$this->debug(' skipping book flood');
					if (isset($rel['categoryID']) && substr($rel['categoryID'], 0, 2) == 7) {
						$this->db->exec(sprintf('UPDATE releases SET passwordstatus = 0, haspreview = 0, categoryID = 8010 WHERE ID = %d', $rel['ID']));
					}
					$flood = true;
				}

				// Seperate the nzb content into the different parts (support files, archive segments and the first parts).
				if ($flood === false && $hasrar !== 0) {
					if ($this->site->checkpasswordedrar > 0 || $processSample === true || $processMediainfo === true || $processAudioinfo === true) {
						$this->sum = $this->size = $this->segsize = $this->adj = $notinfinite = $failed = 0;
						$this->name = '';
						$this->ignorenumbered = $foundcontent = false;

						// Loop through the files, attempt to find if passworded and files. Starting with what not to process.
						foreach ($nzbfiles as $rarFile) {
							if ($this->passchkattempts > 1) {
								if ($notinfinite > $this->passchkattempts) {
									break;
								}
							} else {
								if ($notinfinite > $this->partsqty) {
									if ($this->echooutput) {
										echo $this->c->info("\nMax parts to pp reached");
									}
									break;
								}
							}

							if ($this->password === true) {
								$this->debug('Skipping processing of rar ' . $rarFile['title'] . ' it has a password.');
								break;
							}

							// Probably not a rar/zip.
							if (!preg_match("/\.\b(part\d+|part00\.rar|part01\.rar|rar|r00|r01|zipr\d{2,3}|zip|zipx)($|[ \")\]-])|\"[a-f0-9]{32}\.[1-9]\d{1,2}\".*\(\d+\/\d{2,}\)$/i", $rarFile['title'])) {
								continue;
							}

							// Process rar contents until 1G or 85% of file size is found (smaller of the two).
							if ($rarFile['size'] == 0 && $rarFile['partsactual'] != 0 && $rarFile['partstotal'] != 0) {
								$this->segsize = $rarFile['size'] / ($rarFile['partsactual'] / $rarFile['partstotal']);
							} else {
								$this->segsize = 0;
							}
							$this->sum = $this->sum + $this->adj * $this->segsize;
							if ($this->sum > $this->size || $this->adj === 0) {
								$mid = array_slice((array) $rarFile['segments'], 0, $this->segmentstodownload);

								$bingroup = $groupName;
								$fetchedBinary = $nntp->getMessages($bingroup, $mid);
								if ($nntp->isError($fetchedBinary)) {
									$nntp->doQuit();
									$nntp->doConnect();
									$fetchedBinary = $nntp->getMessages($bingroup, $mid);
									if ($nntp->isError($fetchedBinary)) {
										$fetchedBinary = false;
									}
								}

								if ($fetchedBinary !== false) {
									$this->debug("\nProcessing " . $rarFile['title']);
									if ($this->echooutput) {
										echo 'b';
									}
									$notinfinite++;
									$relFiles = $this->processReleaseFiles($fetchedBinary, $rel, $rarFile['title'], $nntp);
									if ($this->password === true) {
										$passStatus[] = Releases::PASSWD_RAR;
									}

									if ($relFiles === false) {
										$this->debug('Error processing files ' . $rarFile['title']);
										continue;
									} else {
										// Flag to indicate the archive has content.
										$foundcontent = true;
									}
								} else {
									if ($this->echooutput) {
										echo $this->c->alternateOver("f(" . $notinfinite . ")");
									}
									$notinfinite = $notinfinite + 0.2;
									$failed++;
								}
							}
						}
					}

					// Starting to look for content.
					if (is_dir($this->tmpPath)) {
						$files = @scandir($this->tmpPath);
						$rar = new ArchiveInfo();
						if (!empty($files) && count($files) > 0) {
							foreach ($files as $file) {
								if (is_file($this->tmpPath . $file)) {
									if (preg_match('/\.rar$/i', $file)) {
										$rar->open($this->tmpPath . $file, true);
										if ($rar->error) {
											continue;
										}

										$tmpfiles = $rar->getArchiveFileList();
										if (isset($tmpfiles[0]['name'])) {
											foreach ($tmpfiles as $r) {
												$range = mt_rand(0, 99999);
												if (isset($r['range'])) {
													$range = $r['range'];
												}

												$r['range'] = $range;
												if (!isset($r['error']) && !preg_match($this->supportfiles . '|part\d+|r\d{1,3}|zipr\d{2,3}|\d{2,3}|zipx|zip|rar)(\.rar)?$/i', $r['name'])) {
													$this->addfile($r, $rel, $rar, $nntp);
												}
											}
										}
									}
								}
							}
						}
						unset($rar);
					}
				}
				/* Not a good indicator of if there is a password or not, the rar could have had an error for example.
				  else if ($hasrar == 1)
				  $passStatus[] = Releases::PASSWD_POTENTIAL;

				  if(!$foundcontent && $hasrar == 1)
				  $passStatus[] = Releases::PASSWD_POTENTIAL; */

				// Try to get image/mediainfo/audioinfo, using extracted files before downloading more data
				if ($blnTookSample === false || $blnTookAudioinfo === false || $blnTookMediainfo === false || $blnTookJPG === false || $blnTookVideo === false) {
					if (is_dir($this->tmpPath)) {
						$files = @scandir($this->tmpPath);
						if (isset($files) && is_array($files) && count($files) > 0) {
							foreach ($files as $file) {
								if (is_file($this->tmpPath . $file)) {
									$name = '';
									if ($processAudioinfo === true && $blnTookAudioinfo === false && preg_match('/(.*)' . $this->audiofileregex . '$/i', $file, $name)) {
										rename($this->tmpPath . $name[0], $this->tmpPath . 'audiofile.' . $name[2]);
										$blnTookAudioinfo = $this->p->getAudioSample($this->tmpPath, $rel['guid']);
										@unlink($this->tmpPath . 'sample.' . $name[2]);
									}
									/*if ($processJPGSample === true && $blnTookJPG === false && preg_match('/\.(jpg|jpeg)$/', $file)) {
										if (filesize($this->tmpPath . $file) < 15) {
											continue;
										}
										if (exif_imagetype($this->tmpPath . $file) === false) {
											continue;
										}
										$blnTookJPG = $ri->saveImage($rel['guid'] . '_thumb', $this->tmpPath . $file, $ri->jpgSavePath, 650, 650);
										if ($blnTookJPG !== false) {
											$this->db->exec(sprintf('UPDATE releases SET jpgstatus = %d WHERE ID = %d', 1, $rel['ID']));
										}
									}*/
									if ($processSample === true || $processVideo === true || $processMediainfo === true) {
										if (preg_match('/(.*)' . $this->videofileregex . '$/i', $file, $name)) {
											rename($this->tmpPath . $name[0], $this->tmpPath . 'sample.avi');
											if ($processSample && $blnTookSample === false) {
												$blnTookSample = $this->p->getSample($this->tmpPath, $this->site->ffmpegpath, $rel['guid']);
											}
											if ($processMediainfo && $blnTookMediainfo === false) {
												$blnTookMediainfo = $this->p->getMediainfo($this->tmpPath, $this->site->mediainfopath, $rel['ID']);
											}
											@unlink($this->tmpPath . 'sample.avi');
										}
									}
									if ($blnTookAudioinfo === true && $blnTookMediainfo === true && $blnTookVideo === true && $blnTookSample === true) {
										break;
									}
								}
							}
							unset($files);
						}
					}
				}

				// Download and process sample image.
				if ($processSample === true || $processVideo === true) {
					if ($blnTookSample === false || $blnTookVideo === false) {
						if (!empty($samplemsgid)) {
							$sampleBinary = $nntp->getMessages($samplegroup, $samplemsgid);
							if ($nntp->isError($sampleBinary)) {
								$nntp->doQuit();
								$nntp->doConnect();
								$sampleBinary = $nntp->getMessages($samplegroup, $samplemsgid);
								if ($nntp->isError($sampleBinary))
									$sampleBinary = false;
							}

							if ($sampleBinary !== false) {
								if ($this->echooutput)
									echo 'b';
								if (strlen($sampleBinary) > 100) {
									$this->addmediafile($this->tmpPath . 'sample_' . mt_rand(0, 99999) . '.avi', $sampleBinary);
									if ($processSample === true && $blnTookSample === false)
										$blnTookSample = $this->p->getSample($this->tmpPath, $this->site->ffmpegpath, $rel['guid']);
                                        }
								unset($sampleBinary);
							}
							else {
								if ($this->echooutput)
									echo 'f';
							}
						}
					}
				}

				// Download and process mediainfo. Also try to get a sample if we didn't get one yet.
				if ($processMediainfo === true || $processSample === true || $processVideo === true) {
					if ($blnTookMediainfo === false || $blnTookSample === false || $blnTookVideo === false) {
						if (!empty($mediamsgid)) {
							$mediaBinary = $nntp->getMessages($mediagroup, $mediamsgid);
							if ($nntp->isError($mediaBinary)) {
								$nntp->doQuit();
								$nntp->doConnect();
								$mediaBinary = $nntp->getMessages($mediagroup, $mediamsgid);
								if ($nntp->isError($mediaBinary))
									$mediaBinary = false;
							}
							if ($mediaBinary !== false) {
								if ($this->echooutput)
									echo 'b';
								if (strlen($mediaBinary) > 100) {
									$this->addmediafile($this->tmpPath . 'media.avi', $mediaBinary);
									if ($processMediainfo === true && $blnTookMediainfo === false)
										$blnTookMediainfo = $this->p->getMediainfo($this->tmpPath, $this->site->mediainfopath, $rel['ID']);
									if ($processSample === true && $blnTookSample === false)
										$blnTookSample = $this->p->getSample($this->tmpPath, $this->site->ffmpegpath, $rel['guid']);
								}
								unset($mediaBinary);
							}
							else {
								if ($this->echooutput)
									echo 'f';
							}
						}
					}
				}

				// Download audio file, use mediainfo to try to get the artist / album.
				if ($processAudioinfo === true && !empty($audiomsgid) && $blnTookAudioinfo === false) {
					$audioBinary = $nntp->getMessages($audiogroup, $audiomsgid);
					if ($nntp->isError($audioBinary)) {
						$nntp->doQuit();
						$nntp->doConnect();
						$audioBinary = $nntp->getMessages($audiogroup, $audiomsgid);
						if ($nntp->isError($audioBinary))
							$audioBinary = false;
					}
					if ($audioBinary !== false) {
						if ($this->echooutput)
							echo 'b';
						if (strlen($audioBinary) > 100) {
							$this->addmediafile($this->tmpPath . 'audio.' . $audiotype, $audioBinary);
							$blnTookAudioinfo = $this->p->getAudioSample($this->tmpPath, $rel['guid']);
						}
						unset($audioBinary);
					} else {
						if ($this->echooutput)
							echo 'f';
					}
				}

				// Download JPG file.
				/*if ($processJPGSample === true && !empty($jpgmsgid) && $blnTookJPG === false) {
					$jpgBinary = $nntp->getMessages($jpggroup, $jpgmsgid);
					if ($nntp->isError($jpgBinary)) {
						$nntp->doQuit();
						$nntp->doConnect();
						$jpgBinary = $nntp->getMessages($jpggroup, $jpgmsgid);
						if ($nntp->isError($jpgBinary))
							$jpgBinary = false;
					}
					if ($jpgBinary !== false) {
						if ($this->echooutput)
							echo 'b';
						$this->addmediafile($this->tmpPath . 'samplepicture.jpg', $jpgBinary);
						if (is_dir($this->tmpPath) && is_file($this->tmpPath . 'samplepicture.jpg')) {
							if (filesize($this->tmpPath . 'samplepicture.jpg') > 15 && exif_imagetype($this->tmpPath . 'samplepicture.jpg') !== false && $blnTookJPG === false) {
								$blnTookJPG = $ri->saveImage($rel['guid'] . '_thumb', $this->tmpPath . 'samplepicture.jpg', $ri->jpgSavePath, 650, 650);
								if ($blnTookJPG !== false)
									$this->db->exec(sprintf('UPDATE releases SET jpgstatus = %d WHERE ID = %d', 1, $rel['ID']));
							}

							foreach (glob($this->tmpPath . 'samplepicture.jpg') as $v) {
								@unlink($v);
							}
						}
						unset($jpgBinary);
					} else {
						if ($this->echooutput)
							echo 'f';
					}
				} */

				// Set up release values.
				$hpsql = $isql = $vsql = $jsql = '';
				if ($processSample === true && $blnTookSample !== false)
					$this->updateReleaseHasPreview($rel['guid']);
				else
					$hpsql = ', haspreview = 0';

				if ($failed > 0) {
					if ($failed / count($nzbfiles) > 0.7 || $notinfinite > $this->passchkattempts || $notinfinite > $this->partsqty)
						$passStatus[] = Releases::PASSWD_POTENTIAL;
				}

				// If samples exist from previous runs, set flags.
				if (file_exists($ri->imgSavePath . $rel['guid'] . '_thumb.jpg'))
					$isql = ', haspreview = 1';

				$size = $this->db->queryOneRow('SELECT COUNT(releasefiles.releaseID) AS count, SUM(releasefiles.size) AS size FROM releasefiles WHERE releaseID = ' . $rel['ID']);
				if (max($passStatus) > 0)
					$sql = sprintf('UPDATE releases SET passwordstatus = %d, rarinnerfilecount = %d %s %s %s  WHERE ID = %d', max($passStatus), $size['count'], $isql, $vsql, $hpsql, $rel['ID']);
				else if ($hasrar && ((isset($size['size']) && (is_null($size['size']) || $size['size'] == 0)) || !isset($size['size']))) {
					if (!$blnTookSample)
						$hpsql = '';
					$sql = sprintf('UPDATE releases SET passwordstatus = passwordstatus - 1, rarinnerfilecount = %d %s %s %s WHERE ID = %d', $size['count'], $isql, $vsql, $hpsql, $rel['ID']);
				} else
					$sql = sprintf('UPDATE releases SET passwordstatus = %s, rarinnerfilecount = %d %s %s %s  WHERE ID = %d', Releases::PASSWD_NONE, $size['count'], $isql, $vsql, $hpsql, $rel['ID']);

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
			if ($this->echooutput)
				echo "\n";
		}
		if ($gui)
			$this->db->exec(sprintf("UPDATE site SET value = %d WHERE setting %s 'currentppticket1'", $ticket + 1, $like));

		unset($this->consoleTools, $rar, $nzbcontents, $groups, $ri);
	}

    // Process nfo files.
	public function processNfos($releaseToWork = '', $nntp)
	{
		if (!isset($nntp))
			exit($this->c->error("Not connected to usenet(functions->processNfos).\n"));

		if ($this->site->lookupnfo == 1) {
			$nfo = new Nfo($this->echooutput);
			$this->processNfoFiles($releaseToWork, $this->site->lookupimdb, $this->site->lookuptvrage, $groupID = '', $nntp);
		}
	}

    //Process nfo files
    public function processNfoFiles($releaseToWork = '', $processImdb = 1, $processTvrage = 1, $groupID = '', $nntp) {
		if (!isset($nntp)) {
			exit($this->c->error("Unable to connect to usenet.\n"));
		}

		$db = $this->db;
		$nfocount = $ret = 0;
		$groupID = $groupID == '' ? '' : 'AND groupID = ' . $groupID;

		if ($releaseToWork == '') {
			$i = -1;
			while (($nfocount != $this->nzbs) && ($i >= -6)) {
				$res = $db->query(sprintf('SELECT ID, guid, groupID, name FROM releases WHERE releasenfoID = 0 AND nfostatus between %d AND -1 AND size < %s ' . $groupID . ' LIMIT %d', $i, $this->maxsize * 1073741824, $this->nzbs));
				$nfocount = count($res);
				$i--;
			}
		} else {
			$pieces = explode('           =+=            ', $releaseToWork);
			$res = array(array('ID' => $pieces[0], 'guid' => $pieces[1], 'groupID' => $pieces[2], 'name' => $pieces[3]));
			$nfocount = 1;
		}

		if ($nfocount > 0) {
			if ($this->echooutput && $releaseToWork == '') {
				echo $this->c->primary('Processing ' . $nfocount . ' NFO(s), starting at ' . $this->nzbs . " * = hidden NFO, + = NFO, - = no NFO, f = download failed.");
				// Get count of releases per passwordstatus
				$pw1 = $this->db->query('SELECT count(*) as count FROM releases WHERE releasenfoID = 0 AND nfostatus = -1');
				$pw2 = $this->db->query('SELECT count(*) as count FROM releases WHERE releasenfoID = 0 AND nfostatus = -2');
				$pw3 = $this->db->query('SELECT count(*) as count FROM releases WHERE releasenfoID = 0 AND nfostatus = -3');
				$pw4 = $this->db->query('SELECT count(*) as count FROM releases WHERE releasenfoID = 0 AND nfostatus = -4');
				$pw5 = $this->db->query('SELECT count(*) as count FROM releases WHERE releasenfoID = 0 AND nfostatus = -5');
				$pw6 = $this->db->query('SELECT count(*) as count FROM releases WHERE releasenfoID = 0 AND nfostatus = -6');
				echo $this->c->header('Available to process: -6 = ' . number_format($pw6[0]['count']) . ', -5 = ' . number_format($pw5[0]['count']) . ', -4 = ' . number_format($pw4[0]['count']) . ', -3 = ' . number_format($pw3[0]['count']) . ', -2 = ' . number_format($pw2[0]['count']) . ', -1 = ' . number_format($pw1[0]['count']));
			}
			$groups = new Groups();
			$nzbcontents = new NZBContents($this->echooutput);
			$movie = new Movie($this->echooutput);
			$tvrage = new TvRage();

			foreach ($res as $arr) {
				$fetchedBinary = $nzbcontents->getNFOfromNZB($arr['guid'], $arr['ID'], $arr['groupID'], $nntp, $this->getByNameByID($arr['groupID']), $db, $this);
				if ($fetchedBinary !== false) {
					// Insert nfo into database.
					$cp = 'COMPRESS(%s)';
					$nc = $db->escapeString($fetchedBinary);
					$ckreleaseid = $db->queryOneRow(sprintf('SELECT ID FROM releasenfo WHERE releaseID = %d', $arr['ID']));
					if (!isset($ckreleaseid['ID'])) {
						$db->queryInsert(sprintf('INSERT INTO releasenfo (nfo, releaseID) VALUES (' . $cp . ', %d)', $nc, $arr['ID']));
					}
					$db->exec(sprintf('UPDATE releases SET releasenfoID = %d, nfostatus = 1 WHERE ID = %d', $ckreleaseid['ID'], $arr['ID']));
					$ret++;
					$this->domovieupdate($fetchedBinary, 'nfo', $arr['ID'], $processImdb);

					// If set scan for tvrage info.
					if ($processTvrage == 1) {
						$rageId = $this->parseRageId($fetchedBinary);
						if ($rageId !== false) {
							$show = $tvrage->parseNameEpSeason($arr['name']);
							if (is_array($show) && $show['name'] != '') {
								// Update release with season, ep, and airdate info (if available) from releasetitle.
								$tvrage->updateEpInfo($show, $arr['ID']);

								$rid = $tvrage->getByRageID($rageId);
								if (!$rid) {
									$tvrShow = $tvrage->getRageInfoFromService($rageId);
									$tvrage->updateRageInfo($rageId, $show, $tvrShow, $arr['ID']);
								}
							}
						}
					}
				}
                else {
                  $db->exec(sprintf('UPDATE releases SET releasenfoID = -1, nfostatus = -7 WHERE ID = %d', $arr['ID']));
                  $this->freeNfo();
                  $this->removeNfo();
                }
			}
		}
		// Remove nfo that we cant fetch after 5 attempts.
		if ($releaseToWork == '') {
			if ($this->echooutput) {
				if ($this->echooutput && $nfocount > 0 && $releaseToWork == '') {
					echo "\n";
				}
				if ($this->echooutput && $ret > 0 && $releaseToWork == '') {
					echo $ret . " NFO file(s) found/processed.\n";
				}
			}
			return $ret;
		}
	}

    //set releasenfoID in releases table to -1 where releasenfo nfo IS NULL
    function freeNfo()
    {
            $db = $this->db;
			$relres = $db->query('SELECT ID FROM releasenfo WHERE nfo IS NULL');
			foreach ($relres as $relrow) {
				$db->exec(sprintf('UPDATE releases SET releasenfoID = -1 WHERE releasenfoID = %d', $relrow['ID']));
			}

    }

    function removeNfo()
    {
        $db = $this->db;
        $relres = $db->query('SELECT ID FROM releases WHERE releasenfoID = -1');
			foreach ($relres as $relrow) {
				$db->exec(sprintf('DELETE FROM releasenfo WHERE nfo IS NULL and releaseID = %d', $relrow['ID']));
			}
    }

    function doecho($str)
	{
		if ($this->echooutput)
			echo $this->c->header($str);
	}

    // Comparison function for usort, for sorting nzb file content.
    public function sortrar($a, $b)
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
	public function processTv($releaseToWork = '')
	{
		if ($this->site->lookuptvrage == 1) {
			$tvrage = new TvRage($this->echooutput);
			$this->processTvReleases($releaseToWork, $this->site->lookuptvrage == 1);
		}
	}

    public function processTvReleases($releaseToWork = '', $lookupTvRage = true, $local = false)
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
							if (isset($traktArray['show']['tvrage_id']) && $traktArray['show']['tvrage_id'] !== 0) {
								if ($this->echooutput) {
									echo $this->c->primary('Found TVRage ID on trakt:' . $traktArray['show']['tvrage_id']);
								}
								$this->updateRageInfoTrakt($traktArray['show']['tvrage_id'], $show, $traktArray, $arr['ID']);
							}
							// No match, add to tvrage with rageID = -2 and $show['cleanname'] title only.
							else {
								$this->add(-2, $show['cleanname'], '', '', '', '');
							}
						}
						// No match, add to tvrage with rageID = -2 and $show['cleanname'] title only.
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

    public function updateRageInfoTrakt($rageid, $show, $traktArray, $relid)
	{

        $tvrage = new TvRage();
		// Try and get the episode specific info from tvrage.
		$epinfo = $tvrage->getEpisodeInfo($rageid, $show['season'], $show['episode']);
		if ($epinfo !== false) {
			$tvairdate = (!empty($epinfo['airdate'])) ? $this->db->escapeString($epinfo['airdate']) : "NULL";
			$tvtitle = (!empty($epinfo['title'])) ? $this->db->escapeString($epinfo['title']) : "NULL";
			$this->db->exec(sprintf("UPDATE releases SET tvtitle = %s, tvairdate = %s, rageID = %d WHERE ID = %d", $this->db->escapeString(trim($tvtitle)), $tvairdate, $traktArray['show']['tvrage_id'], $relid));
		} else {
			$this->db->exec(sprintf("UPDATE releases SET rageID = %d WHERE ID = %d", $traktArray['show']['tvrage_id'], $relid));
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
	public function checkDate($date)
	{
		if (!empty($date) && $date != NULL) {
			$chk = explode(" ", $date);
			$chkd = explode("-", $chk[0]);
			if ($chkd[1] > 12) {
				$date = date('Y-m-d H:i:s', strtotime($chkd[1] . " " . $chkd[2] . " " . $chkd[0]));
			}
			return $date;
		}
		return NULL;
	}

    public function countryCode($country)
	{
		if (!is_array($country) && strlen($country) > 2) {
			$code = $this->db->queryOneRow('SELECT code FROM country WHERE LOWER(name) = LOWER(' . $this->db->escapeString($country) . ')');
			if (isset($code['code'])) {
				return $code['code'];
			}
		}
		return $country;
	}

    public function add($rageid, $releasename, $desc, $genre, $country, $imgbytes)
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

						/*if (preg_match('/\.zip$/i', $file['name'])) {
							$zipdata = $rar->getFileData($file['name'], $file['source']);
							$data = $this->processReleaseZips($zipdata, false, true, $release, $nntp);

							if ($data != false) {
								foreach ($data as $d) {
									if (preg_match('/\.(part\d+|r\d+|rar)(\.rar)?$/i', $d['zip']['name']))
										$tmpfiles = $this->getRar($d['data']);
								}
							}
						}*/

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
			}
			else {
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

    public function updateReleaseHasPreview($guid)
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
					$nfo = new Nfo($this->echooutput);
					if ($this->addAlternateNfo($this->db, $tmpdata, $release, $nntp)) {
						$this->debug('added rar nfo');
						if ($this->echooutput)
							echo 'n';
						$this->nonfo = false;
					}
				}
				// Extract a video file from the compressed file.
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

    public function parseRageId($str) {
		if (preg_match('/tvrage\.com\/shows\/ID-(\d{1,6})/i', $str, $matches)) {
			return trim($matches[1]);
		}
		return false;
	}

    public function debug($str)
	{
		if ($this->echooutput && $this->DEBUG_ECHO) {
			echo $this->c->debug($str);
		}
	}

    public function nzbFileList($nzb)
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
					. '[" ](?!(\)|\-))/i', $file->attributes()->subject, $ext)) {

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
				array_push($result[$i]['groups'], (string) $g);
			}

			// Parts.
			if (!isset($result[$i]['segments'])) {
				$result[$i]['segments'] = array();
			}
			foreach ($file->segments->segment as $s) {
				array_push($result[$i]['segments'], (string) $s);
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

    public function parseImdb($str)
	{
		if (preg_match('/(?:imdb.*?)?(?:tt|Title\?)(\d{5,7})/i', $str, $matches)) {
			return trim($matches[1]);
		}

		return false;
	}

	public function getMovieInfo($imdbId)
	{
		return $this->db->queryOneRow(sprintf("SELECT * FROM movieinfo WHERE imdbID = %d", $imdbId));
	}

	public function getMovieInfoMultiImdb($imdbIds)
	{
		$allids = str_replace(',,', ',', str_replace(array('(,', ' ,', ', )', ',)'), '', implode(',', $imdbIds)));
		$sql = sprintf("SELECT DISTINCT movieinfo.*, releases.imdbID AS relimdb FROM movieinfo "
			. "LEFT OUTER JOIN releases ON releases.imdbID = movieinfo.imdbID WHERE movieinfo.imdbID IN (%s)", $allids);
		return $this->db->query($sql);
	}
    public function domovieupdate($buffer, $service, $id, $processImdb = 1)
	{
		$imdbId = $this->parseImdb($buffer);
		if ($imdbId !== false) {
			if ($service == 'nfo') {
				$this->service = 'nfo';
			}
			if ($this->echooutput && $this->service != '') {
				echo $this->c->headerOver("\n" . $service . ' found IMDBid: ') . $this->c->primary('tt' . $imdbId);
			}

			$this->db->exec(sprintf('UPDATE releases SET imdbID = %s WHERE ID = %d', $this->db->escapeString($imdbId), $id));

			// If set, scan for imdb info.
			if ($processImdb == 1) {
				$movCheck = $this->getMovieInfo($imdbId);
				if ($movCheck === false || (isset($movCheck['updateddate']) && (time() - strtotime($movCheck['updateddate'])) > 2592000)) {
					$this->m->updateMovieInfo($imdbId);
				}
			}
		}
		return $imdbId;
	}

    public function parseMovieSearchName($releasename)
	{
		$matches = '';
		if (preg_match('/\b[Ss]\d+[-._Ee]|\bE\d+\b/', $releasename)) {
			return false;
		}
		$cat = new Category();
		if (!$cat->isMovieForeign($releasename)) {
			preg_match('/(?P<name>[\w. -]+)[-._( ](?P<year>(19|20)\d\d)/i', $releasename, $matches);
			if (!isset($matches['year'])) {
				preg_match('/^(?P<name>[\w. -]+[-._ ]((bd|br|dvd)rip|bluray|hdtv|divx|xvid|proper|repack|real\.proper|sub\.?(fix|pack)|ac3d|unrated|1080[ip]|720p))/i', $releasename, $matches);
			}

			if (isset($matches['name'])) {
				$name = preg_replace('/\(.*?\)|[._]/i', ' ', $matches['name']);
				$year = (isset($matches['year'])) ? $matches['year'] : '';
				if (strlen($name) > 4 && !preg_match('/^\d+$/', $name)) {
					if ($this->debug && $this->echooutput) {
						echo "DB name: {$releasename}\n";
					}
					return array('title' => trim($name), 'year' => $year);
				}
			}
		}
		return false;
	}

    public function processMovies($releaseToWork = '')
	{
		if ($this->site->lookupimdb == 1) {
			$movie = new Movie($this->echooutput);
			$this->processMovieReleases($releaseToWork);
		}
	}

    public function processMovieReleases($releaseToWork = '')
	{
		$trakt = new TraktTv();
		$googleban = false;
		$googlelimit = 0;
		$result = '';

		if ($releaseToWork == '') {
			$res = $this->db->query(sprintf("SELECT r.searchname AS name, r.ID FROM releases r "
					. "INNER JOIN category c ON r.categoryID = c.ID "
					. "WHERE r.imdbID IS NULL AND c.parentID = %d LIMIT %d", Category::CAT_PARENT_MOVIE, $this->movieqty));
			$moviecount = count($res);
		} else {
			$pieces = explode("           =+=            ", $releaseToWork);
			$res = array(array('name' => $pieces[0], 'ID' => $pieces[1]));
			$moviecount = 1;
		}

		if ($moviecount > 0) {
			if ($this->echooutput && $moviecount > 1) {
				echo $this->c->header("Processing " . $moviecount . " movie release(s).");
			}

				$like = 'LIKE';
				$inyear = 'year';

			foreach ($res as $arr) {
				$parsed = $this->parseMovieSearchName($arr['name']);
				if ($parsed !== false) {
					$year = false;
					$moviename = $parsed['title'];
					$movienameonly = $moviename;
					if ($parsed['year'] != '') {
						$year = true;
						$moviename .= ' (' . $parsed['year'] . ')';
					}

					// Check locally first.
					if ($year === true) {
						$start = (int) $parsed['year'] - 2;
						$end = (int) $parsed['year'] + 2;
						$ystr = '(';
						while ($start < $end) {
							$ystr .= $start . ',';
							$start ++;
						}
						$ystr .= $end . ')';
						$ckimdbid = $this->db->queryOneRow(sprintf('SELECT imdbID FROM movieinfo '
								. 'WHERE title %s %s AND %s IN %s', $like, "'%" . $parsed['title'] . "%'", $inyear, $ystr));
					} else {
						$ckimdbid = $this->db->queryOneRow(sprintf('SELECT imdbID FROM movieinfo '
								. 'WHERE title %s %s', $like, "'%" . $parsed['title'] . "%'"));
					}

					// Try lookup by %name%
					if (!isset($ckimdbid['imdbID'])) {
						$title = str_replace('er', 're', $parsed['title']);
						if ($title != $parsed['title']) {
							$ckimdbid = $this->db->queryOneRow(sprintf('SELECT imdbID FROM movieinfo WHERE title %s %s', $like, "'%" . $title . "%'"));
						}
						if (!isset($ckimdbid['imdbID'])) {
							$pieces = explode(' ', $parsed['title']);
							$title1 = '%';
							foreach ($pieces as $piece) {
								$title1 .= str_replace(array("'", "!", '"'), "", $piece) . '%';
							}
							$ckimdbid = $this->db->queryOneRow(sprintf("SELECT imdbID FROM movieinfo WHERE replace(replace(title, \"'\", ''), '!', '') %s %s", $like, $this->db->escapeString($title1)));
						}
						if (!isset($ckimdbid['imdbID'])) {
							$pieces = explode(' ', $title);
							$title2 = '%';
							foreach ($pieces as $piece) {
								$title2 .= str_replace(array("'", "!", '"'), "", $piece) . '%';
							}
							$ckimdbid = $this->db->queryOneRow(sprintf("SELECT imdbID FROM movieinfo WHERE replace(replace(replace(title, \"'\", ''), '!', ''), '\"', '') %s %s", $like, $this->db->escapeString($title2)));
						}
					}


					if (isset($ckimdbid['imdbID'])) {
						$imdbID = $this->domovieupdate('tt' . $ckimdbid['imdbID'], 'Local DB', $arr['ID']);
						if ($imdbID === false) {
							$this->db->exec(sprintf("UPDATE releases SET imdbID = 0000000 WHERE ID = %d", $arr["ID"]));
						}
						echo $this->c->alternateOver("\nFound Local: ") . $this->c->headerOver($moviename);
						continue;
					}

					if ($this->echooutput) {
						echo $this->c->primaryOver("\nLooking up: ") . $this->c->headerOver($moviename);
					}

					// Check OMDbapi first
					if ($year === true && preg_match('/\d{4}/', $year)) {
						$url = 'http://www.omdbapi.com/?t=' . str_replace(' ', '%20', $movienameonly) . '&y=' . $year . '&r=json';
					} else {
						$url = 'http://www.omdbapi.com/?t=' . str_replace(' ', '%20', $movienameonly) . '&r=json';
					}
					$omdbid = json_decode(file_get_contents($url));
					if (isset($omdbid->imdbID)) {
						$imdbID = $this->domovieupdate($omdbid->imdbID, 'OMDbAPI', $arr['ID']);
						if ($imdbID !== false) {
							continue;
						}
					}

					// Check on trakt.
					$traktimdbid = $trakt->traktMoviesummary($moviename, 'imdbID');
					if ($traktimdbid !== false) {
						$imdbID = $this->domovieupdate($traktimdbid, 'Trakt', $arr['ID']);
						if ($imdbID === false) {
							// No imdb ID found, set to all zeros so we don't process again.
							$this->db->exec(sprintf("UPDATE releases SET imdbID = 0000000 WHERE ID = %d", $arr["ID"]));
						} else {
							continue;
						}
					}
					// Check on search engines.
					else if ($googleban == false && $googlelimit <= 40) {
						$moviename1 = str_replace(' ', '+', $moviename);
						$buffer = getUrl("https://www.google.com/search?hl=en&as_q=" . urlencode($moviename1) . "&as_epq=&as_oq=&as_eq=&as_nlo=&as_nhi=&lr=&cr=&as_qdr=all&as_sitesearch=imdb.com&as_occt=any&safe=images&tbs=&as_filetype=&as_rights=");

						// Make sure we got some data.
						if ($buffer !== false && strlen($buffer)) {
							$googlelimit++;
							if (!preg_match('/To continue, please type the characters below/i', $buffer)) {
								$imdbID = $this->domovieupdate($buffer, 'Google1', $arr['ID']);
								if ($imdbID === false) {
									if (preg_match('/(?P<name>[\w+].+)(\+\(\d{4}\))/i', $moviename1, $result)) {
										$buffer = getUrl("https://www.google.com/search?hl=en&as_q=" . urlencode($result["name"]) . "&as_epq=&as_oq=&as_eq=&as_nlo=&as_nhi=&lr=&cr=&as_qdr=all&as_sitesearch=imdb.com&as_occt=any&safe=images&tbs=&as_filetype=&as_rights=");

										if ($buffer !== false && strlen($buffer)) {
											$googlelimit++;
											$imdbID = $this->domovieupdate($buffer, 'Google2', $arr["ID"]);
											if ($imdbID === false) {
												//no imdb ID found, set to all zeros so we don't process again
												$this->db->exec(sprintf("UPDATE releases SET imdbID = 0000000 WHERE ID = %d", $arr["ID"]));
											} else {
												continue;
											}
										} else {
											$googleban = true;
											if ($this->bingSearch($moviename, $arr["ID"]) === true) {
												continue;
											} else if ($this->yahooSearch($moviename, $arr["ID"]) === true) {
												continue;
											}
										}
									} else {
										$googleban = true;
										if ($this->bingSearch($moviename, $arr["ID"]) === true) {
											continue;
										} else if ($this->yahooSearch($moviename, $arr["ID"]) === true) {
											continue;
										}
									}
								} else {
									continue;
								}
							} else {
								$googleban = true;
								if ($this->bingSearch($moviename, $arr["ID"]) === true) {
									continue;
								} else if ($this->yahooSearch($moviename, $arr["ID"]) === true) {
									continue;
								}
							}
						} else {
							if ($this->bingSearch($moviename, $arr["ID"]) === true) {
								continue;
							} else if ($this->yahooSearch($moviename, $arr["ID"]) === true) {
								continue;
							}
						}
					} else if ($this->bingSearch($moviename, $arr["ID"]) === true) {
						continue;
					} else if ($this->yahooSearch($moviename, $arr["ID"]) === true) {
						continue;
					} else if (!isset($ckimdbid['imdbID']) && $year === true) {
						$ckimdbid = $this->db->queryOneRow(sprintf('SELECT imdbID FROM movieinfo WHERE title %s %s', $like, "'%" . $parsed['title'] . "%'"));
						if (isset($ckimdbid['imdbID'])) {
							$imdbID = $this->domovieupdate('tt' . $ckimdbid['imdbID'], 'Local DB', $arr['ID']);
							if ($imdbID === false) {
								$this->db->exec(sprintf("UPDATE releases SET imdbID = 0000000 WHERE ID = %d", $arr["ID"]));
							}

							continue;
						}
					} else {
						echo $this->c->error("Exceeded request limits on google.com bing.com and yahoo.com.");
						break;
					}
				} else {
					$this->db->exec(sprintf("UPDATE releases SET imdbID = 0000000 WHERE ID = %d", $arr["ID"]));
					continue;
				}
			}
		}
	}

    public function bingSearch($moviename, $relID)
	{
		$result = '';
		if ($this->binglimit <= 40) {
			$moviename = str_replace(' ', '+', $moviename);
			if (preg_match('/(?P<name>[\w+].+)(\+(?P<year>\(\d{4}\)))?/i', $moviename, $result)) {
				if (isset($result["year"]) && !empty($result["year"])) {
					$buffer = getUrl("http://www.bing.com/search?q=" . $result["name"] . urlencode($result["year"]) . "+" . urlencode("site:imdb.com") . "&qs=n&form=QBRE&pq=" . $result["name"] . urlencode($result["year"]) . "+" . urlencode("site:imdb.com") . "&sc=4-38&sp=-1&sk=");
					if ($buffer !== false && strlen($buffer)) {
						$this->binglimit++;
						$imdbId = $this->domovieupdate($buffer, 'Bing1', $relID);
						if ($imdbId === false) {
							$buffer = getUrl("http://www.bing.com/search?q=" . $result["name"] . "+" . urlencode("site:imdb.com") . "&qs=n&form=QBRE&pq=" . $result["name"] . "+" . urlencode("site:imdb.com") . "&sc=4-38&sp=-1&sk=");
							if ($buffer !== false && strlen($buffer)) {
								$this->binglimit++;
								$imdbId = $this->domovieupdate($buffer, 'Bing2', $relID);
								if ($imdbId === false) {
									$this->db->exec(sprintf("UPDATE releases SET imdbID = 0000000 WHERE ID = %d", $relID));
									return true;
								} else {
									return true;
								}
							} else {
								return false;
							}
						} else {
							return true;
						}
					} else {
						return false;
					}
				} else {
					$buffer = getUrl("http://www.bing.com/search?q=" . $result["name"] . "+" . urlencode("site:imdb.com") . "&qs=n&form=QBRE&pq=" . $result["name"] . "+" . urlencode("site:imdb.com") . "&sc=4-38&sp=-1&sk=");
					if ($buffer !== false && strlen($buffer)) {
						$this->binglimit++;
						$imdbId = $this->domovieupdate($buffer, 'Bing2', $relID);
						if ($imdbId === false) {
							$this->db->exec(sprintf("UPDATE releases SET imdbID = 0000000 WHERE ID = %d", $relID));
							return true;
						} else {
							return true;
						}
					} else {
						return false;
					}
				}
			} else {
				$this->db->exec(sprintf("UPDATE releases SET imdbID = 0000000 WHERE ID = %d", $relID));
				return true;
			}
		} else {
			return false;
		}
	}

	public function yahooSearch($moviename, $relID)
	{
		$result = '';
		if ($this->yahoolimit <= 40) {
			$moviename = str_replace(' ', '+', $moviename);
			if (preg_match('/(?P<name>[\w+].+)(\+(?P<year>\(\d{4}\)))?/i', $moviename, $result)) {
				if (isset($result["year"]) && !empty($result["year"])) {
					$buffer = getUrl("http://search.yahoo.com/search?n=15&ei=UTF-8&va_vt=any&vo_vt=any&ve_vt=any&vp_vt=any&vf=all&vm=p&fl=0&fr=yfp-t-900&p=" . $result["name"] . "+" . urlencode($result["year"]) . "&vs=imdb.com");
					if ($buffer !== false && strlen($buffer)) {
						$this->yahoolimit++;
						$imdbId = $this->domovieupdate($buffer, 'Yahoo1', $relID);
						if ($imdbId === false) {
							$buffer = getUrl("http://search.yahoo.com/search?n=15&ei=UTF-8&va_vt=any&vo_vt=any&ve_vt=any&vp_vt=any&vf=all&vm=p&fl=0&fr=yfp-t-900&p=" . $result["name"] . "&vs=imdb.com");
							if ($buffer !== false && strlen($buffer)) {
								$this->yahoolimit++;
								$imdbId = $this->domovieupdate($buffer, 'Yahoo2', $relID);
								if ($imdbId === false) {
									$this->db->exec(sprintf("UPDATE releases SET imdbID = 0000000 WHERE ID = %d", $relID));
									return true;
								} else {
									return true;
								}
							} else {
								return false;
							}
						} else {
							return true;
						}
					}
					return false;
				} else {
					$buffer = getUrl("http://search.yahoo.com/search?n=15&ei=UTF-8&va_vt=any&vo_vt=any&ve_vt=any&vp_vt=any&vf=all&vm=p&fl=0&fr=yfp-t-900&p=" . $result["name"] . "&vs=imdb.com");
					if ($buffer !== false && strlen($buffer)) {
						$this->yahoolimit++;
						$imdbId = $this->domovieupdate($buffer, 'Yahoo2', $relID);
						if ($imdbId === false) {
							$this->db->exec(sprintf("UPDATE releases SET imdbID = 0000000 WHERE ID = %d", $relID));
							return true;
						} else {
							return true;
						}
					} else {
						return false;
					}
				}
			} else {
				$this->db->exec(sprintf("UPDATE releases SET imdbID = 0000000 WHERE ID = %d", $relID));
				return true;
			}
		} else {
			return false;
		}
	}

    //end of testing

   }

?>