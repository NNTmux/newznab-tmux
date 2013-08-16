<?php
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


 //*addedd from nZEDb for testing

class Functions

{
    public function fetchArray($result)
	{
		return (is_null($result) ? null : $result->fetch_array());
	}

    public function getNameByID($ID)
	{
		$db = new DB();
		$arr1 = $db->queryOneRow(sprintf("SELECT title from category where ID = %d", substr($ID, 0, 1)."000"));
		$parent = array_shift($arr1);
		$arr2 = $db->queryOneRow(sprintf("SELECT title from category where ID = %d", $ID));
		$cat = array_shift($arr2);
		return $parent." ".$cat;
	}

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
    //end of testing

   }

?>