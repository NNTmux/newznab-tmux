<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR. "lib/framework/db.php");
require_once(WWW_DIR. "lib/category.php");
require_once(WWW_DIR. "lib/groups.php");
require_once("namecleaner.php");
require_once("functions.php");
require_once("nzbcontents.php");
require_once("ColorCLI.php");
require_once("Info.php");
require_once("Pprocess.php");
require_once("consoletools.php");


//This script is ported from nZEDb and adapted for newznab

CONST PREDB_REGEX = "/([\w\(\)]+[\._]([\w\(\)]+[\._-])+[\w\(\)]+-\w+)/";

/**
 * Class Namefixer
 */
class Namefixer
{

	/**
	 * @param bool $echooutput
	 */
	function __construct($echooutput=true)
	{
		$this->echooutput = $echooutput;
		$this->relid = $this->fixed = $this->checked = 0;
        $this->db = new DB();
		$db = $this->db;
		$this->timeother = " AND rel.adddate > (now() - interval 6 hour) AND rel.categoryID in (2020, 5050, 6070, 8010) GROUP BY rel.ID ORDER BY postdate";
		$this->timeall = " AND rel.adddate > (now() - interval 6 hour) GROUP BY rel.ID ORDER BY postdate";
		$this->fullother = " AND rel.categoryID IN (2020, 5050, 6070, 8010) GROUP BY rel.ID";
		$this->fullall = "";
        $this->done = $this->matched = false;
        $this->c = new ColorCLI();
        $this->consoletools = new ConsoleTools();
	}


	/**
	 * Attempts to fix release names using the NFO.
	 *
	 * @param $time
	 * @param $echo
	 * @param $cats
	 * @param $namestatus
	 * @param $show
	 */
	public function fixNamesWithNfo($time, $echo, $cats, $namestatus, $show)
	{

	   if ($time == 1) {
			echo $this->c->header("Fixing search names in the past 6 hours using .nfo files.");
		} else {
			echo $this->c->header("Fixing search names since the beginning using .nfo files.");
		}

		$db = new DB();
        $functions = new Functions ();
		$type = "NFO, ";
		// Only select releases we haven't checked here before
            $prehashID = false;
			if ($cats === 3) {
			$query = "SELECT rel.ID AS releaseID FROM releases rel "
				. "INNER JOIN releasenfo nfo ON (nfo.releaseID = rel.ID) "
				. "WHERE prehashID = 0";
			$cats = 2;
            $prehashID = true;
		} else {
			$query = "SELECT rel.ID AS releaseID FROM releases rel "
				. "INNER JOIN releasenfo nfo ON (nfo.releaseID = rel.ID) "
				. "WHERE (isrenamed = 0 OR rel.categoryID = 8010) AND proc_nfo = 0";
		}

		//24 hours, other cats
		if ($time == 1 && $cats == 1)
			$relres = $db->queryDirect($query.$this->timeother);
		//24 hours, all cats
		else if ($time == 1 && $cats == 2)
			$relres = $db->queryDirect($query.$this->timeall);
		//other cats
		else if ($time == 2 && $cats == 1)
			$relres = $db->queryDirect($query.$this->fullother);
		//all cats
		if ($time == 2 && $cats == 2)
			$relres = $db->queryDirect($query.$this->fullall);

		$total = $relres->rowCount();
		if ($total > 0) {
			echo $this->c->primary(number_format($total) . " releases to process.");
			sleep(2);
			foreach ($relres as $rel){
			  $relrow = $db->queryOneRow("SELECT nfo.releaseID AS nfoID, rel.groupID, rel.categoryID, rel.searchname, UNCOMPRESS(nfo) AS textstring, "
					. "rel.ID AS releaseID FROM releases rel "
					. "INNER JOIN releasenfo nfo ON (nfo.releaseID = rel.ID) "
					. "WHERE rel.ID = " . $rel['releaseID']);

				//ignore encrypted nfos
				if (preg_match('/^=newz\[NZB\]=\w+/', $relrow['textstring'])) {
					$db->query(sprintf("UPDATE releases SET proc_nfo = 1 WHERE ID = %d", $relrow['rel.ID']));
					$this->checked++;
				} else {
					$this->done = $this->matched = false;
					$this->checkName($relrow, $echo, $type, $namestatus, $show, $prehashID);
					$this->checked++;
					if ($this->checked % 500 === 0 && $show === 1) {
						echo $this->c->alternate(number_format($this->checked) . " NFOs processed.\n");
						sleep(1);
					}
				}
				if ($show === 2) {
					$this->consoletools->overWritePrimary("Renamed Releases: [" . number_format($this->fixed) . "] " . $this->consoletools->percentString($this->checked, $total));
				}
			}
			if ($echo == 1) {
				echo $this->c->header("\n" . $this->fixed . " releases have had their names changed out of: " . $this->checked . " NFO's.");
			} else {
				echo $this->c->header("\n" . $this->fixed . " releases could have their names changed. " . $this->checked . " NFO's were checked.");
			}
		} else {
			echo $this->c->info("Nothing to fix.");
		}
	}

	/**
	 * Attempts to fix release names using the File name.
	 * @param $time
	 * @param $echo
	 * @param $cats
	 * @param $namestatus
	 * @param $show
	 */
	public function fixNamesWithFiles($time, $echo, $cats, $namestatus, $show)
	{
		if ($time == 1)
			echo $this->c->header ("Fixing search names in the past 6 hours using the filename.");
		else
			echo $this->c->header ("Fixing search names since the beginning using the filename.");

		$db = new DB();
        $functions = new Functions();
		$type = "Filenames, ";
        $prehashID = false;
	   	if ($cats === 3) {
			$query = "SELECT relfiles.name AS textstring, rel.categoryID, rel.searchname, rel.groupID, relfiles.releaseID AS fileID, "
				. "rel.ID AS releaseID FROM releases rel "
				. "INNER JOIN releasefiles relfiles ON (relfiles.releaseID = rel.ID) "
				. "WHERE prehashID = 0";
			$cats = 2;
            $prehashID = true;
		} else {
			$query = "SELECT relfiles.name AS textstring, rel.categoryID, rel.searchname, rel.groupID, relfiles.releaseID AS fileID, "
				. "rel.ID AS releaseID FROM releases rel "
				. "INNER JOIN releasefiles relfiles ON (relfiles.releaseID = rel.ID) "
				. "WHERE (isrenamed = 0 OR rel.categoryID = 8010) AND proc_files = 0";
		}

		//24 hours, other cats
		if ($time == 1 && $cats == 1)
			$relres = $db->queryDirect($query.$this->timeother);
		//24 hours, all cats
		if ($time == 1 && $cats == 2)
			$relres = $db->queryDirect($query.$this->timeall);
		//other cats
		if ($time == 2 && $cats == 1)
			$relres = $db->queryDirect($query.$this->fullother);
		//all cats
		if ($time == 2 && $cats == 2)
			$relres = $db->queryDirect($query.$this->fullall);

		$total = $relres->rowCount();
		if ($total > 0) {
			echo $this->c->primary(number_format($total) . " releases to process.");
			sleep(2);
			foreach ($relres as $relrow) {
				$this->done = $this->matched = false;
				$this->checkName($relrow, $echo, $type, $namestatus, $show, $prehashID);
				$this->checked++;
				if ($this->checked % 500 == 0 && $show === 1) {
					echo $this->c->alternate($this->checked . " files processed.");
					sleep(1);
				}
				if ($show === 2) {
					$this->consoletools->overWritePrimary("Renamed Releases: [" . number_format($this->fixed) . "] " . $this->consoletools->percentString($this->checked, $total));
				}
			}
			if ($echo == 1) {
				echo $this->c->header("\n" . $this->fixed . " releases have had their names changed out of: " . $this->checked . " files.");
			} else {
				echo $this->c->header("\n" . $this->fixed . " releases could have their names changed. " . $this->checked . " files were checked.");
			}
		} else {
			echo $this->c->info("Nothing to fix.");
		}
	}

	/**
	 * Attempts to fix release names using the Par2 File.
	 * @param $time
	 * @param $echo
	 * @param $cats
	 * @param $namestatus
	 * @param $show
	 * @param $nntp
	 */
	public function fixNamesWithPar2($time, $echo, $cats, $namestatus, $show, $nntp)
	{
	    if (!isset($nntp))
			exit($this->c->error("Not connected to usenet(namefixer->fixNamesWithPar2.\n"));
		if ($time == 1)
			echo $this->c->header ("Fixing search names in the past 6 hours using the par2 files.");
		else
			echo $this->c->header ("Fixing search names since the beginning using the par2 files.");

		$db = $this->db;
        $functions = new Functions();
		$type = "PAR2, ";
		if ($cats === 3) {
			$query = "SELECT rel.ID AS releaseID, rel.guid, rel.groupID FROM releases rel WHERE prehashID = 0";
			$cats = 2;
		} else {
			$query = "SELECT rel.ID AS releaseID, rel.guid, rel.groupID FROM releases rel WHERE (isrenamed = 0 OR rel.categoryID = 8010) AND proc_par2 = 0";
		}

		//24 hours, other cats
		if ($time == 1 && $cats == 1) {
			$relres = $db->queryDirect($query . $this->timeother);
		}
		//24 hours, all cats
		if ($time == 1 && $cats == 2) {
			$relres = $db->queryDirect($query . $this->timeall);
		}
		//other cats
		if ($time == 2 && $cats == 1) {
			$relres = $db->queryDirect($query . $this->fullother);
		}
		//all cats
		if ($time == 2 && $cats == 2) {
			$relres = $db->queryDirect($query . $this->fullall);
		}
        	$total = $relres->rowCount();
		if ($total > 0) {
			echo $this->c->primary(number_format($total) . " releases to process.");
			sleep(2);
		    $db = $this->db;
			$nzbcontents = new NZBcontents(array('echo' => $this->echooutput, 'nntp' => $nntp, 'nfo' => new Info(), 'db' => $this->db, 'pp' => new PProcess(true)));
			foreach ($relres as $relrow)
			{
                $this->done = $this->matched = false;
				if (($nzbcontents->checkPAR2($relrow['guid'], $relrow['releaseID'], $relrow['groupID'], $namestatus, $show)) === true) {
				    $this->fixed++;
                    }
                $this->checked++;
			   if ($this->checked % 500 == 0 && $show === 1) {
					echo $this->c->alternate("\n" . $this->checked . " files processed.\n");
				}
				if ($show === 2) {
					$this->consoletools->overWritePrimary("Renamed Releases: [" . number_format($this->fixed) . "] " . $this->consoletools->percentString($this->checked, $total));
				}
			}
			if ($echo == 1) {
				echo $this->c->header("\n" . $this->fixed . " releases have had their names changed out of: " . $this->checked . " files.");
			} else {
				echo $this->c->header("\n" . $this->fixed . " releases could have their names changed. " . $this->checked . " files were checked.");
			}
		} else {
			echo $this->c->info("Nothing to fix.");
		}
	}

	/**
	 * Update the release with the new information.
	 * @param        $release
	 * @param        $name
	 * @param        $method
	 * @param        $echo
	 * @param        $type
	 * @param        $namestatus
	 * @param        $show
	 * @param string $prehashID
	 */
	public function updateRelease($release, $name, $method, $echo, $type, $namestatus, $show, $prehashID = '0')
	{
        if ($this->relid !== $release["releaseID"])
		{
			$namecleaning = new nameCleaning();
			$newname = $namecleaning->fixerCleaner($name);
			if (strtolower($newname) != strtolower($release["searchname"]))
			{
				$n = "\n";
				$this->matched = true;
				$this->relid = $release["releaseID"];

				$category = new Category();
                $functions = new Functions ();
                $groupname = $functions->getByNameByID($release["groupID"]);
				$determinedcat = $category->determineCategory($groupname, $newname);

				if ($type === "PAR2, ")
                    {
					$newname = ucwords($newname);
                    }

				$this->fixed++;

                $checkedname = explode("\\", $newname);
                $newname = $checkedname[0];
                $newname = preg_replace(array('/^[-=_\.:\s]+/', '/[-=_\.:\s]+$/'), '', $newname);

				if ($this->echooutput === true)
				{
					$groups = new Groups();
                    $functions = new Functions ();
					$groupname = $functions->getByNameByID($release["groupID"]);
					$oldcatname = $functions->getNameByID($release["categoryID"]);
					$newcatname = $functions->getNameByID($determinedcat);
                    $n = "\n";

					if ($type === "PAR2, ")
                        {
						echo $n;
                        }
					 echo $n . $this->c->headerOver("New name:  ") . $this->c->primary($newname) .
                    $this->c->headerOver("Old name:  ") . $this->c->primary($release["searchname"]) .
                    $this->c->headerOver("New cat:   ") . $this->c->primary($newcatname) .
                    $this->c->headerOver("Old cat:   ") . $this->c->primary($oldcatname) .
                    $this->c->headerOver("Group:     ") . $this->c->primary($groupname) .
                    $this->c->headerOver("Method:    ") . $this->c->primary($type . $method) .
                    $this->c->headerOver("ReleaseID: ") . $this->c->primary($release["releaseID"]);
					if ($type !== "PAR2, ")
                    {
						echo $n;
                    }
				}

                if ($echo == 1) {
                    $db = new DB();
                    if ($namestatus == 1) {
                        if ($type == "NFO, ") {
                            $status = "isrenamed = 1, iscategorized = 1, proc_nfo = 1,";
                        } else if ($type == "PAR2, ") {
                            $status = "isrenamed = 1, iscategorized = 1, proc_par2 = 1,";
                        } else if ($type == "Filenames, ") {
                            $status = "isrenamed = 1, iscategorized = 1, proc_files = 1,";
                        }
                                $run = $db->exec(sprintf("UPDATE releases SET rageID = NULL, seriesfull = NULL, season = NULL, episode = NULL, tvtitle = NULL, tvairdate = NULL, imdbID = NULL, musicinfoID = NULL, consoleinfoID = NULL, bookinfoID = NULL, "
								. "anidbID = NULL, prehashID = %s, searchname = %s, isrenamed = 1,"
								. " %s categoryID = %d WHERE ID = %d", $prehashID, $db->escapeString(substr($newname, 0, 255)), $status, $determinedcat, $release["releaseID"]));
					} else {
						$run = $db->exec(sprintf("UPDATE releases SET rageID = NULL, seriesfull = NULL, season = NULL, episode = NULL, tvtitle = NULL, tvairdate = NULL, imdbID = NULL, musicinfoID = NULL, consoleinfoID = NULL, bookinfoID = NULL, "
								. "anidbID = NULL, prehashID = %s, searchname = %s, iscategorized = 1, "
								. "categoryID = %d WHERE ID = %d", $prehashID, $db->escapeString(substr($newname, 0, 255)), $determinedcat, $release["releaseID"]));
                                }
                }
			}
		}
        $this->done = true;
	}


	/**
	 * @param $hash
	 * @param $release
	 * @param $echo
	 * @param $namestatus
	 * @param $echooutput
	 * @param $show
	 *
	 * @return int
	 */
	public function matchPredbHash($hash, $release, $echo, $namestatus, $echooutput, $show)
	{
		$db = $this->db;
		$matching = 0;
		$hashtype = "";
		$this->category = new Category();
		$this->matched = false;

		// Determine MD5 or SHA1
		if (strlen($hash) === 40) {
			$hashtype = "SHA1, ";
			$hashcheck = "sha1";
		} else {
			$hashtype = "MD5, ";
			$hashcheck = "md5";
		}

		$res = $db->queryDirect(sprintf("SELECT title, source FROM prehash WHERE %s = %s", $hashcheck, $db->escapeString(strtolower($hash))));
		$total = $res->rowCount();
		if ($total > 0) {
			foreach ($res as $row) {
				if ($row["title"] !== $release["searchname"]) {
					$determinedcat = $this->category->determineCategory($release["groupID"], $row["title"]);

					if ($echo == 1) {
						$this->matched = true;
						if ($namestatus == 1) {
							$db->exec(sprintf("UPDATE releases SET rageID = -1, seriesfull = NULL, season = NULL, episode = NULL, tvtitle = NULL, tvairdate = NULL, imdbID = NULL, musicinfoID = NULL, consoleinfoID = NULL, bookinfoID = NULL, anidbID = NULL, "
									. "searchname = %s, categoryID = %d, isrenamed = 1, iscategorized = 1, dehashstatus = 1 WHERE ID = %d", $db->escapeString($row["title"]), $determinedcat, $release["releaseID"]
								)
							);
						} else {
							$db->exec(sprintf("UPDATE releases SET rageID = -1, seriesfull = NULL, season = NULL, episode = NULL, tvtitle = NULL, tvairdate = NULL, imdbID = NULL, musicinfoID = NULL, consoleinfoID = NULL, bookinfoID = NULL, anidbID = NULL, "
									. "searchname = %s, categoryID = %d, dehashstatus = 1 WHERE ID = %d", $db->escapeString($row["title"]), $determinedcat, $release["releaseID"]
								)
							);
						}
					}

					if ($echooutput && $show === 1) {
						$this->updateRelease($release, $row["title"], $method = "prehash hash release name: " . $row["source"], $echo, $hashtype, $namestatus, $show);
					}
					$matching++;
				}
			}
		} else {
			$db->exec(sprintf("UPDATE releases SET dehashstatus = %d - 1 WHERE ID = %d", $release['dehashstatus'], $release['releaseID']));
			echo ".";
		}
		return $matching;
	}

	/**
	 * Check the array using regex for a clean name.
	 *
	 * @param      $release
	 * @param      $echo
	 * @param      $type
	 * @param      $namestatus
	 * @param      $show
	 * @param bool $prehashID
	 *
	 * @return bool
	 */
	public function checkName($release, $echo, $type, $namestatus, $show, $prehashID = false)
	{
	  // Get pre style name from releases.name
        $matches = '';
		if (preg_match_all('/([\w\(\)]+[\s\._-]([\w\(\)]+[\s\._-])+[\w\(\)]+-\w+)/', $release['textstring'], $matches)) {
			foreach ($matches as $match) {
				foreach ($match as $val) {
					$title = $this->db->queryOneRow("SELECT title, ID from prehash WHERE title = " . $this->db->escapeString(trim($val)));
					if (isset($title['title'])) {
						$this->cleanerName = $title['title'];
						if (!empty($this->cleanerName)) {
							$this->updateRelease($release, $title['title'], $method = "prehash: Match", $echo, $type, $namestatus, $show, $title['ID']);
							continue;
					}
				}
			}
		}
    }
        	// if processing prehashID on filename, do not continue
		if ($prehashID === true) {
			return false;
		}
		if ($type == "PAR2, ") {
			$this->fileCheck($release, $echo, $type, $namestatus, $show);
		} else {
			// Just for NFOs.
			if ($type == "NFO, ") {
				$this->nfoCheckTV($release, $echo, $type, $namestatus, $show);
				$this->nfoCheckMov($release, $echo, $type, $namestatus, $show);
				$this->nfoCheckMus($release, $echo, $type, $namestatus, $show);
				$this->nfoCheckTY($release, $echo, $type, $namestatus, $show);
				$this->nfoCheckG($release, $echo, $type, $namestatus, $show);
			}
			// Just for filenames.
			if ($type == "Filenames, ") {
				$this->fileCheck($release, $echo, $type, $namestatus, $show);
			}
			$this->tvCheck($release, $echo, $type, $namestatus, $show);
			$this->movieCheck($release, $echo, $type, $namestatus, $show);
			$this->gameCheck($release, $echo, $type, $namestatus, $show);
			$this->appCheck($release, $echo, $type, $namestatus, $show);
		}
    // The release didn't match so set proc_nfo = 1 so it doesn't get rechecked. Also allows removeCrapReleases to run extra things on the release.
	   if ($namestatus == 1 && $this->matched === false && $type == "NFO, ")
		{
			$db = new Db;
			$db->exec(sprintf("UPDATE releases SET proc_nfo = 1 WHERE ID = %d", $release['releaseID']));
		}
        // The release didn't match so set proc_files = 1 so it doesn't get rechecked. Also allows removeCrapReleases to run extra things on the release.
		elseif ($namestatus == 1 && $this->matched === false && $type == "Filenames, ")
		{
			$db = new DB();
			$db->exec(sprintf("UPDATE releases SET proc_files = 1 WHERE ID = %d", $release["releaseID"]));
		}
        // The release didn't match so set proc_par2 = 1 so it doesn't get rechecked. Also allows removeCrapReleases to run extra things on the release.
        elseif ($namestatus == 1 && $this->matched === false && $type == "PAR2, ")
		{
			$db = new DB();
			$db->exec(sprintf("UPDATE releases SET proc_par2 = 1 WHERE ID = %d", $release["releaseID"]));
		}

		return $this->matched;
	}

	/**
	 * Look for a TV name
	 * @param $release
	 * @param $echo
	 * @param $type
	 * @param $namestatus
	 * @param $show
	 */
	public function tvCheck($release, $echo, $type, $namestatus, $show)
	{
		if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2})|\d{1,2}x\d{2}|ep[._ -]?\d{2})[-\w.\',;.()]+(BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -][-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="tvCheck: Title.SxxExx.Text.source.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2})|\d{1,2}x\d{2}|ep[._ -]?\d{2})[-\w.\',;& ]+((19|20)\d\d)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="tvCheck: Title.SxxExx.Text.year.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2})|\d{1,2}x\d{2}|ep[._ -]?\d{2})[-\w.\',;& ]+(480|720|1080)[ip][._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="tvCheck: Title.SxxExx.Text.resolution.source.vcodec.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2})|\d{1,2}x\d{2}|ep[._ -]?\d{2})[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="tvCheck: Title.SxxExx.source.vcodec.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2})|\d{1,2}x\d{2}|ep[._ -]?\d{2})[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?|Dolby( ?TrueHD)?|MP3|TrueHD)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -](480|720|1080)[ip][._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="tvCheck: Title.SxxExx.acodec.source.res.vcodec.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2})|\d{1,2}x\d{2}|ep[._ -]?\d{2})[-\w.\',;& ]+((19|20)\d\d)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="tvCheck: Title.SxxExx.resolution.source.vcodec.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((19|20)\d\d)[._ -]((s\d{1,2}[._ -]?[bde]\d{1,2})|\d{1,2}x\d{2}|ep[._ -]?\d{2})[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="tvCheck: Title.year.###(season/episode).source.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w(19|20)\d\d[._ -]\d{2}[._ -]\d{2}[._ -](IndyCar|NBA|NCW(T|Y)S|NNS|NSCS?)([._ -](19|20)\d\d)?[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="tvCheck: Sports", $echo, $type, $namestatus, $show);
	}


	/**
	 * Look for a movie name
	 *
	 * @param $release
	 * @param $echo
	 * @param $type
	 * @param $namestatus
	 * @param $show
	 */
	public function movieCheck($release, $echo, $type, $namestatus, $show)
	{
		if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((19|20)\d\d)[-\w.\',;& ]+(480|720|1080)[ip][._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="movieCheck: Title.year.Text.res.vcod.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((19|20)\d\d)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[._ -](480|720|1080)[ip][-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="movieCheck: Title.year.source.vcodec.res.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((19|20)\d\d)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?|Dolby( ?TrueHD)?|MP3|TrueHD)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="movieCheck: Title.year.source.vcodec.acodec.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+(Brazilian|Chinese|Croatian|Danish|Deutsch|Dutch|Estonian|English|Finnish|Flemish|Francais|French|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?|Dolby( ?TrueHD)?|MP3|TrueHD)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="movieCheck: Title.year.language.acodec.source.vcodec.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((19|20)\d\d)[._ -](480|720|1080)[ip][._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?|Dolby( ?TrueHD)?|MP3|TrueHD)[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="movieCheck: Title.year.resolution.source.acodec.vcodec.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((19|20)\d\d)[._ -](480|720|1080)[ip][._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="movieCheck: Title.year.resolution.source.vcodec.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((19|20)\d\d)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -](480|720|1080)[ip][._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?|Dolby( ?TrueHD)?|MP3|TrueHD)[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="movieCheck: Title.year.source.resolution.acodec.vcodec.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((19|20)\d\d)[._ -](480|720|1080)[ip][._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?|Dolby( ?TrueHD)?|MP3|TrueHD)[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="movieCheck: Title.year.resolution.acodec.vcodec.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/[-\w.\',;& ]+((19|20)\d\d)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BR(RIP)?|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -](480|720|1080)[ip][._ -][-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="movieCheck: Title.year.source.res.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((19|20)\d\d)[._ -][-\w.\',;& ]+[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BR(RIP)?|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="movieCheck: Title.year.eptitle.source.vcodec.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+(480|720|1080)[ip][._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?|Dolby( ?TrueHD)?|MP3|TrueHD)[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="movieCheck: Title.resolution.source.acodec.vcodec.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+(480|720|1080)[ip][._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?|Dolby( ?TrueHD)?|MP3|TrueHD)[-\w.\',;& ]+(BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -]((19|20)\d\d)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="movieCheck: Title.resolution.acodec.eptitle.source.year.group", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+(Brazilian|Chinese|Croatian|Danish|Deutsch|Dutch|Estonian|English|Finnish|Flemish|Francais|French|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)[._ -]((19|20)\d\d)[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?|Dolby( ?TrueHD)?|MP3|TrueHD)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="movieCheck: Title.language.year.acodec.src", $echo, $type, $namestatus, $show);
	}


	/**
	 * Look for a game name
	 * @param $release
	 * @param $echo
	 * @param $type
	 * @param $namestatus
	 * @param $show
	 */
	public function gameCheck($release, $echo, $type, $namestatus, $show)
	{
		if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+(ASIA|DLC|EUR|GOTY|JPN|KOR|MULTI\d{1}|NTSCU?|PAL|RF|Region[._ -]?Free|USA|XBLA)[._ -](DLC[._ -]Complete|FRENCH|GERMAN|MULTI\d{1}|PROPER|PSN|READ[._ -]?NFO|UMD)?[._ -]?(GC|NDS|NGC|PS3|PSP|WII|XBOX(360)?)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="gameCheck: Videogames 1", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+(GC|NDS|NGC|PS3|WII|XBOX(360)?)[._ -](DUPLEX|iNSOMNi|OneUp|STRANGE|SWAG|SKY)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method = "gameCheck: Videogames 2", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[\w.\',;-].+-OUTLAWS/i', $release["textstring"], $result))
		{
			$result = str_replace("OUTLAWS","PC GAME OUTLAWS",$result['0']);
			$this->updateRelease($release, $result["0"], $method="gameCheck: PC Games -OUTLAWS", $echo, $type, $namestatus, $show);
		}
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[\w.\',;-].+\-ALiAS/i', $release["textstring"], $result))
		{
			$newresult = str_replace("-ALiAS"," PC GAME ALiAS",$result['0']);
			$this->updateRelease($release, $newresult, $method="gameCheck: PC Games -ALiAS", $echo, $type, $namestatus, $show);
		}
        elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[\w.\',;-].+\-RELOADED/i', $release["textstring"], $result))
		{
			$newresult = str_replace("-RELOADED"," PC GAME RELOADED",$result['0']);
			$this->updateRelease($release, $newresult, $method="gameCheck: PC Games -RELOADED", $echo, $type, $namestatus, $show);
		}
        elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[\w.\',;-].+\-SKIDROW/i', $release["textstring"], $result))
		{
			$newresult = str_replace("-SKIDROW"," PC GAME SKIDROW",$result['0']);
			$this->updateRelease($release, $newresult, $method="gameCheck: PC Games -SKIDROW", $echo, $type, $namestatus, $show);
		}
         elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[\w.\',;-].+\-FLT/i', $release["textstring"], $result))
		{
			$newresult = str_replace("-FLT"," PC GAME FLT",$result['0']);
			$this->updateRelease($release, $newresult, $method="gameCheck: PC Games -FAIRLIGHT", $echo, $type, $namestatus, $show);
		}

	}


	/**
	 * Look for an app name
	 * @param $release
	 * @param $echo
	 * @param $type
	 * @param $namestatus
	 * @param $show
	 */
	public function appCheck($release, $echo, $type, $namestatus, $show)
	{
		if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+(\d{1,10}|Linux|UNIX)[._ -](RPM)?[._ -]?(X64)?[._ -]?(Incl)[._ -](Keygen)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="appCheck: Apps 1", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+\d{1,8}[._ -](winall-freeware)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="appCheck: Apps 2", $echo, $type, $namestatus, $show);
	}

	/*
	 * Just for NFOS.
	 */


	/**
	 * TV
	 * @param $release
	 * @param $echo
	 * @param $type
	 * @param $namestatus
	 * @param $show
	 */
	public function nfoCheckTV($release, $echo, $type, $namestatus, $show)
	{
		if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/:\s*.*[\\\\\/]([A-Z0-9].+?S\d+[.-_ ]?[ED]\d+.+?)\.\w{2,}\s+/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["1"], $method="nfoCheck: Generic TV 1", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/(?:(\:\s{1,}))(.+?S\d{1,3}[.-_ ]?[ED]\d{1,3}.+?)(\s{2,}|\r|\n)/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["2"], $method="nfoCheck: Generic TV 2", $echo, $type, $namestatus, $show);
	}


	/**
	 * Movies
	 * @param $release
	 * @param $echo
	 * @param $type
	 * @param $namestatus
	 * @param $show
	 */
	public function nfoCheckMov($release, $echo, $type, $namestatus, $show)
	{
		if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/(?:(\:\s{1,}))(.+?(19|20)\d\d.+?(BDRip|bluray|DVD(R|Rip)?|XVID).+?)(\s{2,}|\r|\n)/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["2"], $method="nfoCheck: Generic Movies 1", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/(?:(\s{2,}))(.+?[\.\-_ ](19|20)\d\d.+?(BDRip|bluray|DVD(R|Rip)?|XVID).+?)(\s{2,}|\r|\n)/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["2"], $method="nfoCheck: Generic Movies 2", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/(?:(\s{2,}))(.+?[\.\-_ ](NTSC|MULTi).+?(MULTi|DVDR)[\.\-_ ].+?)(\s{2,}|\r|\n)/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["2"], $method="nfoCheck: Generic Movies 3", $echo, $type, $namestatus, $show);
	}


	/**
	 * Music
	 * @param $release
	 * @param $echo
	 * @param $type
	 * @param $namestatus
	 * @param $show
	 */
	public function nfoCheckMus($release, $echo, $type, $namestatus, $show)
	{
		if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/(?:\s{2,})(.+?-FM-\d{2}-\d{2})/i', $release["textstring"], $result))
		{
			$newname = str_replace('-FM-', '-FM-Radio-MP3-', $result["1"]);
			$this->updateRelease($release, $newname, $method="nfoCheck: Music FM RADIO", $echo, $type, $namestatus, $show);
		}
	}


	/**
	 * Title, year
	 * @param $release
	 * @param $echo
	 * @param $type
	 * @param $namestatus
	 * @param $show
	 */
	public function nfoCheckTY($release, $echo, $type, $namestatus, $show)
	{
		//Title(year)
		if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/(\w[-\w`~!@#$%^&*()_+={}|"<>?\[\]\\;\',.\/ ]+\s?\((19|20)\d\d\))/i', $release["textstring"], $result) && !preg_match('/\.pdf|Audio ?Book/i', $release["textstring"]))
		{
			$releasename = $result[0];
			if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/(idiomas|lang|language|langue|sprache).*?\b(Brazilian|Chinese|Croatian|Danish|DE|Deutsch|Dutch|Estonian|ES|English|Englisch|Finnish|Flemish|Francais|French|FR|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)\b/i', $release["textstring"], $result))
			{
				if($result[2] == 'DE')          {$result[2] = 'DUTCH';}
				elseif($result[2] == 'Englisch'){$result[2] = 'English';}
				elseif($result[2] == 'FR')      {$result[2] = 'FRENCH';}
				elseif($result[2] == 'ES')      {$result[2] = 'SPANISH';}
				$releasename = $releasename.".".$result[2];
			}
			if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/(frame size|res|resolution|video|video res).*?(272|336|480|494|528|608|640|\(640|688|704|720x480|816|820|1080|1 080|1280 @|1280|1920|1 920|1920x1080)/i', $release["textstring"], $result))
			{
				if($result[2] == '272')         {$result[2] = '272p';}
				elseif($result[2] == '336')     {$result[2] = '480p';}
				elseif($result[2] == '480')     {$result[2] = '480p';}
				elseif($result[2] == '494')     {$result[2] = '480p';}
				elseif($result[2] == '608')     {$result[2] = '480p';}
				elseif($result[2] == '640')     {$result[2] = '480p';}
				elseif($result[2] == '\(640')   {$result[2] = '480p';}
				elseif($result[2] == '688')     {$result[2] = '480p';}
				elseif($result[2] == '704')     {$result[2] = '480p';}
				elseif($result[2] == '720x480') {$result[2] = '480p';}
				elseif($result[2] == '816')     {$result[2] = '1080p';}
				elseif($result[2] == '820')     {$result[2] = '1080p';}
				elseif($result[2] == '1080')    {$result[2] = '1080p';}
				elseif($result[2] == '1280x720'){$result[2] = '720p';}
				elseif($result[2] == '1280 @')  {$result[2] = '720p';}
				elseif($result[2] == '1280')    {$result[2] = '720p';}
				elseif($result[2] == '1920')    {$result[2] = '1080p';}
				elseif($result[2] == '1 920')   {$result[2] = '1080p';}
				elseif($result[2] == '1 080')   {$result[2] = '1080p';}
				elseif($result[2] == '1920x1080'){$result[2] = '1080p';}
				$releasename = $releasename.".".$result[2];
			}
			if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/(largeur|width).*?(640|\(640|688|704|720|1280 @|1280|1920|1 920)/i', $release["textstring"], $result))
			{
				if($result[2] == '640')         {$result[2] = '480p';}
				elseif($result[2] == '\(640')   {$result[2] = '480p';}
				elseif($result[2] == '688')     {$result[2] = '480p';}
				elseif($result[2] == '704')     {$result[2] = '480p';}
				elseif($result[2] == '1280 @')  {$result[2] = '720p';}
				elseif($result[2] == '1280')    {$result[2] = '720p';}
				elseif($result[2] == '1920')    {$result[2] = '1080p';}
				elseif($result[2] == '1 920')   {$result[2] = '1080p';}
				elseif($result[2] == '720')     {$result[2] = '480p';}
				$releasename = $releasename.".".$result[2];
			}
			if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/source.*?\b(BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)\b/i', $release["textstring"], $result))
			{
				if($result[1] == 'BD')          {$result[1] = 'Bluray.x264';}
				elseif($result[1] == 'CAMRIP')  {$result[1] = 'CAM';}
				elseif($result[1] == 'DBrip')   {$result[1] = 'BDRIP';}
				elseif($result[1] == 'DVD R1')  {$result[1] = 'DVD';}
				elseif($result[1] == 'HD')      {$result[1] = 'HDTV';}
				elseif($result[1] == 'NTSC')    {$result[1] = 'DVD';}
				elseif($result[1] == 'PAL')     {$result[1] = 'DVD';}
				elseif($result[1] == 'Ripped ') {$result[1] = 'DVDRIP';}
				elseif($result[1] == 'VOD')     {$result[1] = 'DVD';}
				$releasename = $releasename.".".$result[1];
			}
			if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/(codec|codec name|codec code|format|MPEG-4 Visual|original format|res|resolution|video|video codec|video format|video res|tv system|type|writing library).*?\b(AVC|AVI|DBrip|DIVX|\(Divx|DVD|[HX][._ -]?264|NTSC|PAL|WMV|XVID)\b/i', $release["textstring"], $result))
			{
				if($result[2] == 'AVI')                 {$result[2] = 'DVDRIP';}
				elseif($result[2] == 'DBrip')           {$result[2] = 'BDRIP';}
				elseif($result[2] == '(Divx')           {$result[2] = 'DIVX';}
				elseif($result[2] == 'h.264')           {$result[2] = 'H264';}
				elseif($result[2] == 'MPEG-4 Visual')   {$result[2] = 'x264';}
				elseif($result[1] == 'NTSC')            {$result[1] = 'DVD';}
				elseif($result[1] == 'PAL')             {$result[1] = 'DVD';}
				elseif($result[2] == 'x.264')           {$result[2] = 'x264';}
				$releasename = $releasename.".".$result[2];
			}
			if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/(audio|audio format|codec|codec name|format).*?\b(0x0055 MPEG-1 Layer 3|AAC( LC)?|AC-?3|\(AC3|DD5(.1)?|(A_)?DTS-?(HD)?|Dolby(\s?TrueHD)?|TrueHD|FLAC|MP3)\b/i', $release["textstring"], $result))
			{
				if($result[2] == '0x0055 MPEG-1 Layer 3'){$result[2] = 'MP3';}
				elseif($result[2] == 'AC-3')             {$result[2] = 'AC3';}
				elseif($result[2] == '(AC3')             {$result[2] = 'AC3';}
				elseif($result[2] == 'AAC LC')           {$result[2] = 'AAC';}
				elseif($result[2] == 'A_DTS')            {$result[2] = 'DTS';}
				elseif($result[2] == 'DTS-HD')           {$result[2] = 'DTS';}
				elseif($result[2] == 'DTSHD')            {$result[2] = 'DTS';}
				$releasename = $releasename.".".$result[2];
			}
			$releasename = $releasename."-NoGroup";
			$this->updateRelease($release, $releasename, $method="nfoCheck: Title (Year)", $echo, $type, $namestatus, $show);
		}
	}


	/**
	 * Games
	 * @param $release
	 * @param $echo
	 * @param $type
	 * @param $namestatus
	 * @param $show
	 */
	public function nfoCheckG($release, $echo, $type, $namestatus, $show)
	{
		if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/ALiAS|BAT-TEAM|\FAiRLiGHT|Game Type|Glamoury|HI2U|iTWINS|JAGUAR|LARGEISO|MAZE|MEDIUMISO|nERv|PROPHET|PROFiT|PROCYON|RELOADED|REVOLVER|ROGUE|ViTALiTY/i', $release["textstring"]))
		{
			if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[\w.+&*\/\()\',;: -]+\(c\)[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			{
				$releasename = str_replace(array("(c)", "(C)"),"(GAMES) (c)", $result['0']);
				$this->updateRelease($release, $releasename, $method="nfoCheck: PC Games (c)", $echo, $type, $namestatus, $show);
			}
			elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[\w.+&*\/()\',;: -]+\*ISO\*/i', $release["textstring"], $result))
			{
				$releasename = str_replace("*ISO*","*ISO* (PC GAMES)", $result['0']);
				$this->updateRelease($release, $releasename, $method="nfoCheck: PC Games *ISO*", $echo, $type, $namestatus, $show);
			}
		}
	}


	/**
	 * Misc.
	 * @param $release
	 * @param $echo
	 * @param $type
	 * @param $namestatus
	 * @param $show
	 */
	public function nfoCheckMisc($release, $echo, $type, $namestatus, $show)
	{
		if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/Supplier.+?IGUANA/i', $release["textstring"])) {
			$releasename = '';
			$result = '';
			if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w`~!@#$%^&*()+={}|:"<>?\[\]\\;\',.\/ ]+\s\((19|20)\d\d\)/i', $release["textstring"], $result))
				$releasename = $result[0];
			elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\s\[\*\] (English|Dutch|French|German|Spanish)\b/i', $release["textstring"], $result))
				$releasename = $releasename.".".$result[1];
			elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\s\[\*\] (DTS 6[._ -]1|DS 5[._ -]1|DS 2[._ -]0|DS 2[._ -]0 MONO)\b/i', $release["textstring"], $result))
				$releasename = $releasename.".".$result[2];
			elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/Format.+(DVD(5|9|R)?|[HX][._ -]?264)\b/i', $release["textstring"], $result))
				$releasename = $releasename.".".$result[1];
			elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\[(640x.+|1280x.+|1920x.+)\] Resolution\b/i', $release["textstring"], $result))
			{
				if($result[1] == '640x.+') {$result[1] = '480p';}
				elseif($result[1] == '1280x.+'){$result[1] = '720p';}
				elseif($result[1] == '1920x.+'){$result[1] = '1080p';}
				$releasename = $releasename.".".$result[1];
			}
			$releasename = $releasename.".IGUANA";
			$this->updateRelease($release, $result, $method="nfoCheck: IGUANA", $echo, $type, $namestatus, $show);
		}
	}

	/**
	 * Just for filenames.
	 * @param $release
	 * @param $echo
	 * @param $type
	 * @param $namestatus
	 * @param $show
	 */
	public function fileCheck($release, $echo, $type, $namestatus, $show)
	{
		if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/^(.+?(x264|XviD)\-TVP)\\\\/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["1"], $method="fileCheck: TVP", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/^(\\\\|\/)?(.+(\\\\|\/))*(.+?S\d{1,3}[.-_ ]?[ED]\d{1,3}.+)\.(.+)$/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["4"], $method="fileCheck: Generic TV", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/^(\\\\|\/)?(.+(\\\\|\/))*(.+?([\.\-_ ]\d{4}[\.\-_ ].+?(BDRip|bluray|DVDRip|XVID)).+)\.(.+)$/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["4"], $method="fileCheck: Generic movie 1", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/^([a-z0-9\.\-_]+(19|20)\d\d[a-z0-9\.\-_]+[\.\-_ ](720p|1080p|BDRip|bluray|DVDRip|x264|XviD)[a-z0-9\.\-_]+)\.[a-z]{2,}$/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["1"], $method="fileCheck: Generic movie 2", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/(.+?([\.\-_ ](CD|FM)|[\.\-_ ]\dCD|CDR|FLAC|SAT|WEB).+?(19|20)\d\d.+?)\\\\.+/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["1"], $method="fileCheck: Generic music", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/^(.+?(19|20)\d\d\-([a-z0-9]{3}|[a-z]{2,}|C4))\\\\/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["1"], $method="fileCheck: music groups", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/.+\\\\(.+\((19|20)\d\d\)\.avi)/i', $release["textstring"], $result))
		{
			$newname = str_replace('.avi', ' DVDRip XVID NoGroup', $result["1"]);
			$this->updateRelease($release, $newname, $method="fileCheck: Movie (year) avi", $echo, $type, $namestatus, $show);
		}
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/.+\\\\(.+\((19|20)\d\d\)\.iso)/i', $release["textstring"], $result))
		{
			$newname = str_replace('.iso', ' DVD NoGroup', $result["1"]);
			$this->updateRelease($release, $newname, $method="fileCheck: Movie (year) iso", $echo, $type, $namestatus, $show);
		}
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/^(.+?IMAGESET.+?)\\\\.+/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["1"], $method="fileCheck: XXX Imagesets", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+1080i[._ -]DD5[._ -]1[._ -]MPEG2-R&C(?=\.ts)/i', $release["textstring"], $result))
		{
			$result = str_replace("MPEG2","MPEG2.HDTV",$result["0"]);
			$this->updateRelease($release, $result, $method="fileCheck: R&C", $echo, $type, $namestatus, $show);
		}
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2})|\d{1,2}x\d{2}|ep[._ -]?\d{2})[._ -](480|720|1080)[ip][._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -]nSD[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[._ -]NhaNC3[-\w.\',;& ]+\w/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="fileCheck: NhaNc3", $echo, $type, $namestatus, $show);
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\wtvp-[\w.\-\',;]+((s\d{1,2}[._ -]?[bde]\d{1,2})|\d{1,2}x\d{2}|ep[._ -]?\d{2})[._ -](720p|1080p|xvid)(?=\.(avi|mkv))/i', $release["textstring"], $result))
		{
			$result = str_replace("720p","720p.HDTV.X264",$result['0']);
			$result = str_replace("1080p","1080p.Bluray.X264",$result['0']);
			$result = str_replace("xvid","XVID.DVDrip",$result['0']);
			$this->updateRelease($release, $result, $method="fileCheck: tvp", $echo, $type, $namestatus, $show);
		}
		elseif ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+\d{3,4}\.hdtv-lol\.(avi|mp4|mkv|ts|nfo|nzb)/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="fileCheck: Title.211.hdtv-lol.extension", $echo, $type, $namestatus, $show);
		else if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w[-\w.\',;& ]+-S\d{1,2}[EX]\d{1,2}-XVID-DL.avi/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="fileCheck: Title-SxxExx-XVID-DL.avi", $echo, $type, $namestatus, $show);
		else if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\S.*[\w.\-\',;]+\s\-\ss\d{2}[ex]\d{2}\s\-\s[\w.\-\',;].+\./i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="fileCheck: Title - SxxExx - Eptitle", $echo, $type, $namestatus, $show);
		else if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w.+?\)\.nds/i', $release["textstring"], $result))
			$this->updateRelease($release, $result["0"], $method="fileCheck: ).nds Nintendo DS", $echo, $type, $namestatus, $show);
        else if ($this->done === false && $this->relid !== $release["releaseID"] && preg_match('/\w.+?\.(pdf|html|epub|mobi|azw)/i', $release["textstring"], $result)) {
			$this->updateRelease($release, $result["0"], $method = "fileCheck: EBook", $echo, $type, $namestatus, $show);
		}
        }
}