<?php

require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/nzb.php");
require_once(WWW_DIR."/lib/nfo.php");
require_once("functions.php");

/*
 * Gets information contained within the NZB.
 */
Class NZBcontents
{
	function NZBcontents($echooutput=false)
	{
		$this->echooutput = $echooutput;
		$s = new Sites();
		$this->site = $s->get();
	}

	public function getNfoFromNZB($guid, $relID, $groupID, $nntp)
	{
		if($fetchedBinary = $this->NFOfromNZB($guid, $relID, $groupID, $nntp))
			return $fetchedBinary;
		else if ($fetchedBinary = $this->hiddenNFOfromNZB($guid, $relID, $groupID, $nntp))
			return $fetchedBinary;
		else
			return false;
	}

	// Returns a XML of the NZB file.
	public function LoadNZB($guid)
	{
		$nzb = new NZB();
        $functions = new Functions();
		// Fetch the NZB location using the GUID.
		if (!file_exists($nzbpath = $functions->NZBPath($guid)))
		{
			echo "\n".$guid." appears to be missing the nzb file, skipping.\n";
			return false;
		}
		else if(!$nzbpath = 'compress.zlib://'.$nzbpath)
		{
			echo "\nUnable to decompress: ".$nzbpath." - ".fileperms($nzbpath)." - may have bad file permissions, skipping.\n";
			return false;
		}
		else if(!$nzbfile = @simplexml_load_file($nzbpath))
		{
			echo "\nUnable to load NZB: ".$guid." appears to be an invalid NZB, skipping.\n";
			return false;
		}
		else
			return $nzbfile;
	}

	// Attempts to get the releasename from a par2 file
	public function checkPAR2($guid, $relID, $groupID, $echooutput)
	{
		$nzbfile = $this->LoadNZB($guid);
		if ($nzbfile !== false)
		{
			foreach ($nzbfile->file as $nzbcontents)
			{
				if (preg_match('/\.(par[2" ]|\d{2,3}").+\(1\/1\)$/i', $nzbcontents->attributes()->subject))
				{
					$pp = new Functions($echooutput);
					if ($pp->parsePAR2($nzbcontents->segments->segment, $relID, $groupID, null) === true)
				    break;
				}
			}
		}
	}

	// Gets the completion from the NZB, optionally looks if there is an NFO/PAR2 file.
	public function NZBcompletion($guid, $relID, $groupID, $nntp, $db, $nfocheck=false)
	{
		$nzbfile = $this->LoadNZB($guid);
		if ($nzbfile !== false)
		{
            $db = new DB();
            $pp = new Functions ($this->echooutput);
            $messageid = '';
			$actualParts = $artificialParts = 0;
			$foundnfo = $foundpar2 = false;

			foreach ($nzbfile->file as $nzbcontents)
			{
				foreach($nzbcontents->segments->segment as $segment)
					$actualParts++;

				$subject = $nzbcontents->attributes()->subject;
				if(preg_match('/(\d+)\)$/', $subject, $parts))
					$artificialParts = $artificialParts+$parts[1];

				if ($nfocheck !== false && $foundnfo !== true)
				{
					if (preg_match('/\.\b(nfo|inf|ofn)\b(?![ .-])/i', $subject))
					{
						$messageid = $nzbcontents->segments->segment;
						$foundnfo = true;
					}
				}
				if ($foundpar2 === false)
				{
					if (preg_match('/\.(par[2" ]|\d{2,3}").+\(1\/1\)$/i', $subject))
					{
						$pp = new Functions($this->echooutput);
						if ($pp->parsePAR2($nzbcontents->segments->segment, $relID, $groupID, $nntp) === true)
							$foundpar2 = true;
					}
				}
			}


			if($artificialParts <= 0 || $actualParts <= 0)
				$completion = 0;
			else
				$completion = ($actualParts/$artificialParts)*100;

			if ($completion > 100)
				$completion = 100;

			$db->query(sprintf('UPDATE releases SET completion = %d WHERE ID = %d', $completion, $relID));
			if ($nfocheck !== false)
			{
				if ($foundnfo === true)
					return $messageid;
				else
					return false;
			}
			else
				return true;
		}
		return false;
	}

	// Look for an .nfo file in the NZB, return the NFO. Also gets the NZB completion.
	public function NFOfromNZB($guid, $relID, $groupID, $nntp)
	{
		$messageid = $this->NZBcompletion($guid, $relID, $groupID, $nntp, true);
		if ($messageid !== "")
		{
			$nfo = new NFO();
            $functions = new Functions ();
			$functions->addReleaseNfo($relID);
			$groups = new Groups();
			$fetchedBinary = $nntp->getMessage($functions->getByNameByID($groupID), $messageid);
			if ($fetchedBinary === false || PEAR::isError($fetchedBinary))
			{
				$nntp->doQuit();
				$nntp->doConnect();
				$fetchedBinary = $nntp->getMessage($functions->getByNameByID($groupID), $messageid);
				if ($fetchedBinary === false || PEAR::isError($fetchedBinary))
				{
					$nntp->doQuit();
					$fetchedBinary = false;
				}
			}
			if ($functions->isNFO($fetchedBinary) === true)
			{
				if ($this->echooutput)
					echo "+";
				return $fetchedBinary;
			}
			else
				return false;
		}
		else
			return false;
	}

	// Look for an NFO in the nzb which does not end in .nfo, return the nfo.
	public function hiddenNFOfromNZB($guid, $relID, $groupID, $nntp)
	{
		$nzbfile = $this->LoadNZB($guid);
		if ($nzbfile !== false)
		{
			$db = new DB();
			$groups = new Groups();
            $functions = new Functions ();
			$groupName = $functions->getByNameByID($groupID);
			$foundnfo = $failed = false;
			$nfo = new NFO($this->echooutput);
			foreach ($nzbfile->file as $nzbcontents)
			{
				$subject = $nzbcontents->attributes()->subject;
				// Look for a subject with 1 part, ignore common file extensions.
				if (preg_match('/(yEnc\s\(1\/1\)|\(1\/1\)$)/i', $subject) && !preg_match('/\.(apk|bat|bmp|cbr|cbz|cfg|css|csv|cue|db|dll|doc|epub|exe|gif|htm|ico|idx|ini|jpg|lit|log|m3u|mid|mobi|mp3|nib|nzb|odt|opf|otf|par|par2|pdf|psd|pps|png|ppt|r\d{2,4}|rar|sfv|srr|sub|srt|sql|rom|rtf|tif|torrent|ttf|txt|vb|vol\d+\+\d+|wps|xml|zip)/i', $subject))
				{
					$messageid = $nzbcontents->segments->segment;
					if ($messageid !== false)
					{
						$possibleNFO = $nntp->getMessage($groupName, $messageid);
						if ($possibleNFO === false || PEAR::isError($possibleNFO))
						{
							$nntp->doQuit();
							$nntp->doConnect();
							$possibleNFO = $nntp->getMessage($groupName, $messageid);
							if ($possibleNFO === false || PEAR::isError($possibleNFO))
							{
								$nntp->doQuit();
								$possibleNFO = false;
							}
						}
						if ($possibleNFO !== false)
						{
							if ($functions->isNFO($possibleNFO) == true)
							{
								$fetchedBinary = $possibleNFO;
								$foundnfo = true;
							}
						}
						else
						{
							// NFO download failed, increment attempts.
							$db->query(sprintf("UPDATE releases SET nfostatus = nfostatus-1 WHERE ID = %d", $relID));
							$failed = true;
						}
					}
				}
			}
			if ($foundnfo !== false && $failed == false)
			{
				$functions->addReleaseNfo($relID);
				if ($this->echooutput)
					echo "*";
				return $fetchedBinary;
			}
			if ($foundnfo == false && $failed == false)
			{
				// No NFO file in the NZB.
				if ($this->echooutput)
					echo "-";
				$db->query(sprintf("UPDATE releases SET nfostatus = 0 WHERE ID = %d", $relID));
				return false;
			}
			if ($failed == true)
			{
				if ($this->echooutput)
					echo "f";
				return false;
			}
		}
		else
			return false;
	}

	public function nzblist($guid='')
	{
		if (empty($guid))
			return false;

		$nzb = new NZB();
		$nzbpath = $nzb->getNZBPath($guid);
		$nzb = array();

		if (file_exists($nzbpath))
		{
			$nzbpath = 'compress.zlib://'.$nzbpath;
			$xmlObj = @simplexml_load_file($nzbpath);

			if ($xmlObj && strtolower($xmlObj->getName()) == 'nzb')
			{
				foreach($xmlObj->file as $file)
				{
					$nzbfile = array();
					$nzbfile['subject'] = (string) $file->attributes()->subject;
					$nzbfile = array_merge($nzbfile, (array) $file->groups);
					$nzbfile = array_merge($nzbfile, (array) $file->segments);
					$nzb[] = $nzbfile;
					$nzbfile = null;
				}
			}
			else
				$nzb = false;
			unset($xmlObj);
			return $nzb;
		}
		else
			return false;
	}

	//	Update the releases completion.
	function updateCompletion($completion, $relID)
	{
		$db = new DB();
		$db->query(sprintf("UPDATE releases SET completion = %d WHERE ID = %d", $completion, $relID));
	}
}
