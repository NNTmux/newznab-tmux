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
require_once("consoletools.php");



 //*addedd from nZEDb for testing

class Functions

{
    //
        // the element relstatus of table releases is used to hold the status of the release
        // The variable is a bitwise AND of status
        // List of processed constants - used in releases table. Constants need to be powers of 2: 1, 2, 4, 8, 16 etc...
        const NFO_PROCESSED_NAMEFIXER     = 1;  // We have processed the release against its .nfo file in the namefixer
        const PREHASH_PROCESSED_NAMEFIXER   = 2;  // We have processed the release against a predb name

  // database function
    public function fetchArray($result)
	{
		return (is_null($result) ? null : $result->fetch_array());
	}
 //  gets name of category from category.php
    public function getNameByID($ID)
	{
		$db = new DB();
		$arr1 = $db->queryOneRow(sprintf("SELECT title from category where ID = %d", substr($ID, 0, 1)."000"));
		$parent = array_shift($arr1);
		$arr2 = $db->queryOneRow(sprintf("SELECT title from category where ID = %d", $ID));
		$cat = array_shift($arr2);
		return $parent." ".$cat;
	}
    //deletes from releases
    public function fastDelete($id, $guid, $site)
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

		$db->query(sprintf("delete releases, releasenfo, releasecomment, usercart, releasefiles, releaseaudio, releasesubs, releasevideo, releaseextrafull
							from releases
								LEFT OUTER JOIN releasenfo on releasenfo.releaseID = releases.ID
								LEFT OUTER JOIN releasecomment on releasecomment.releaseID = releases.ID
								LEFT OUTER JOIN usercart on usercart.releaseID = releases.ID
								LEFT OUTER JOIN releasefiles on releasefiles.releaseID = releases.ID
								LEFT OUTER JOIN releaseaudio on releaseaudio.releaseID = releases.ID
								LEFT OUTER JOIN releasesubs on releasesubs.releaseID = releases.ID
								LEFT OUTER JOIN releasevideo on releasevideo.releaseID = releases.ID
								LEFT OUTER JOIN releaseextrafull on releaseextrafull.releaseID = releases.ID
							where releases.ID = %d", $id));

		$ri->delete($guid); // This deletes a file so not in the query
	}
    //reads name of group
     public function getByNameByID($id)
	{
		$db = new DB();
		$res = $db->queryOneRow(sprintf("select name from groups where ID = %d ", $id));
		return $res["name"];
	}
     //Add release nfo, imported from nZEDb
    	public function addReleaseNfo($relid)
	{
		$db = new DB();
		return $db->queryInsert(sprintf("INSERT IGNORE INTO releasenfo (releaseID) VALUE (%d)", $relid));
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
	public function parsePAR2($messageID, $relID, $groupID)
	{
		$db = new DB();
		$category = new Category();
        $functions = new Functions();

		$quer = $db->queryOneRow("SELECT groupID, categoryID, relnamestatus, searchname, UNIX_TIMESTAMP(postdate) as postdate, ID as releaseID  FROM releases WHERE ID = {$relID}");
		if ($quer["relnamestatus"] !== 1 && $quer["categoryID"] != Category::CAT_MISC_OTHER)
			return false;

		$nntp = new NNTP();
		$nntp->doConnect();
		$groups = new Groups();
        $functions = new Functions();
		$par2 = $nntp->getMessage($functions->getByNameByID($groupID), $messageID);
		if ($par2 === false || PEAR::isError($par2))
		{
			$nntp->doQuit();
			$nntp->doConnect();
			$par2 = $nntp->getMessage($functions->getByNameByID($groupID), $messageID);
			if ($par2 === false || PEAR::isError($par2))
			{
				$nntp->doQuit();
				return false;
			}
		}
		$nntp->doQuit();

		$par2info = new Par2Info();
		$par2info->setData($par2);
		if ($par2info->error)
			return false;

		$files = $par2info->getFileList();
		if (count($files) > 0)
		{
            $db = new DB();
            $namefixer = new Namefixer;
			$rf = new ReleaseFiles();
			$relfiles = 0;
			$foundname = false;
			foreach ($files as $fileID => $file)
			{
				// Add to releasefiles.
				if ($db->queryOneRow(sprintf("SELECT ID FROM releasefiles WHERE releaseID = %d AND name = %s", $relID, $db->escapeString($file["name"]))) === false)
				{
					if ($rf->add($relID, $file["name"], $file["size"], $quer["postdate"], 0))
						$relfiles++;
				}
				$quer["textstring"] = $file["name"];
				$namefixer->checkName($quer, 1, "PAR2, ", 1);
				$stat = $db->queryOneRow("SELECT relnamestatus AS a FROM releases WHERE ID = {$relID}");
				if ($stat["a"] != 1)
				{
					$foundname = true;
					break;
				}
			}
			if ($relfiles > 0)
			{

				$cnt = $db->queryOneRow("SELECT COUNT(releaseID) AS count FROM releasefiles WHERE releaseID = {$relID}");
				$count = $relfiles;
				if ($cnt !== false && $cnt["count"] > 0)
					$count = $relfiles + $cnt["count"];
				$db->query(sprintf("UPDATE releases SET rarinnerfilecount = %d where ID = %d", $count, $relID));
			}
			if ($foundname === true)
				return true;
			else
				return false;
		}
		else
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
		$resrel = $db->query("SELECT ID, ".$type.", groupID FROM releases ".$where);
		$total = count($resrel);
		if (count($resrel) > 0)
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
    //end of testing

   }

?>