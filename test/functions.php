<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/framework/cache.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR. "lib/category.php");
require_once(WWW_DIR."/lib/releases.php");
require_once(WWW_DIR."/lib/releaseimage.php");
require_once(WWW_DIR."/lib/nzb.php");
require_once(WWW_DIR."/lib/rarinfo/par2info.php");
require_once(WWW_DIR."/lib/nfo.php");
require_once(WWW_DIR."/lib/site.php");
require_once(WWW_DIR."/lib/util.php");
require_once(WWW_DIR."/lib/groups.php");
require_once(WWW_DIR."/lib/nntp.php");
require_once("consoletools.php");
require_once("ColorCLI.php");



 //*addedd from nZEDb for testing

class Functions

{
  function __construct($echooutput=true)
  {
    $this->echooutput = $echooutput;
    $this->c = new ColorCLI();
    $this->db = new DB();

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

    	// Used for deleting, updating (and inserting without needing the last insert id).
	public function queryExec($query)
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
			$db->exec(sprintf('UPDATE releases SET nfostatus = 1 WHERE ID = %d', $release['ID']));
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
	public function isNFO($possibleNFO)
	{
		$ok = false;
		if ($possibleNFO !== false)
		{
			if (!preg_match('/(<?xml|;\s*Generated\sby.+SF\w|^\s*PAR|\.[a-z0-9]{2,7}\s[a-z0-9]{8}|^\s*RAR|\A.{0,10}(JFIF|matroska|ftyp|ID3))/i', $possibleNFO))
			{
				if (strlen($possibleNFO) < 45 * 1024)
				{
					// exif_imagetype needs a minimum size or else it doesn't work.
					if (strlen($possibleNFO) > 15)
					{
						// Check if it's a picture - EXIF.
						if (@exif_imagetype($possibleNFO) == false)
						{
							// Check if it's a picture - JFIF.
							if ($this->check_JFIF($possibleNFO) == false)
							{
								// Check if it's a par2.
								$par2info = new Par2Info();
								$par2info->setData($possibleNFO);
								if ($par2info->error)
								{
									$ok = true;
								}
							}
						}
					}
				}
			}
		}
		return $ok;
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
		$quer = $db->queryOneRow('SELECT groupID, categoryID, searchname, '.$t.' as postdate, ID as releaseID FROM releases WHERE (bitwise & 4) = 0 AND ID = '.$relID);
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
    //end of testing

   }

?>