<?php

require(dirname(__FILE__)."/config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/groups.php");
require_once(WWW_DIR.'/lib/nntp.php');
require_once("../test/ColorCLI.php");
require_once("../test/namefixer.php");
require_once("../test/functions.php"); 

$c = new ColorCLI();
if (!isset($argv[1])) {
	exit($c->error("This script is not intended to be run manually, it is called from fixreleasenames_threaded.py."));
} else if (isset($argv[1])) {
	$db = new DB();
	$namefixer = new NameFixer(true);
	$pieces = explode(' ', $argv[1]);
	if (isset($pieces[1]) && $pieces[0] == 'nfo') {
		$release = $pieces[1];
		if ($res = $db->queryOneRow(sprintf('SELECT rel.guid AS guid, nfo.releaseID AS nfoid, rel.groupID, rel.categoryID, rel.searchname, uncompress(nfo) AS textstring, rel.ID AS releaseID FROM releases rel INNER JOIN releasenfo nfo ON (nfo.releaseID = rel.ID) WHERE rel.ID = %d', $release))) {
			//ignore encrypted nfos
			if (preg_match('/^=newz\[NZB\]=\w+/', $res['textstring'])) {
				$namefixer->done = $namefixer->matched = false;
				$db->queryDirect(sprintf('UPDATE releases SET proc_nfo = 1 WHERE ID = %d', $res['releaseID']));
				$namefixer->checked++;
				echo '.';
			} else {
				//echo $res['textstring']."\n";
				$namefixer->done = $namefixer->matched = false;
				if ($namefixer->checkName($res, true, 'NFO, ', 1, 1) !== true) {
					echo '.';
				}
				$namefixer->checked++;
			}
		}
	} else if (isset($pieces[1]) && $pieces[0] == 'filename') {
		$release = $pieces[1];
		if ($res = $db->queryOneRow(sprintf('SELECT relfiles.name AS textstring, rel.categoryID, rel.searchname, '
				. 'rel.groupID, relfiles.releaseID AS fileid, rel.ID AS releaseID FROM releases rel '
				. 'INNER JOIN releasefiles relfiles ON (relfiles.releaseID = rel.ID) WHERE rel.ID = %d', $release))) {
			$namefixer->done = $namefixer->matched = false;
			if ($namefixer->checkName($res, true, 'Filenames, ', 1, 1) !== true) {
				echo '.';
			}
			$namefixer->checked++;
		}
	} else if (isset($pieces[1]) && $pieces[0] == 'md5') {
		$release = $pieces[1];
		if ($res = $db->queryOneRow(sprintf('SELECT r.ID AS releaseID, r.name, r.searchname, r.categoryID, r.groupID, dehashstatus, rf.name AS filename FROM releases r LEFT JOIN releasefiles rf ON r.ID = rf.releaseID WHERE r.ID = %d', $release))) {
			if (preg_match('/[a-f0-9]{32}/i', $res['name'], $matches)) {
				$namefixer->matchPredbMD5($matches[0], $res, 1, 1, true, 1);
			} else if (preg_match('/[a-f0-9]{32}/i', $res['filename'], $matches)) {
				$namefixer->matchPredbMD5($matches[0], $res, 1, 1, true, 1);
			} else {
				echo '.';
			}
		}
	} else if (isset($pieces[1]) && $pieces[0] == 'par2') {
		$nntp = new NNTP();
		if ($nntp->doConnect() === false) {
			exit($c->error("Unable to connect to usenet."));
		}

		$relID = $pieces[1];
		$guid = $pieces[2];
		$groupID = $pieces[3];
		$nzbcontents = new NZBContents(true);
		$pp = new Functions($echooutput = true);
		$res = $nzbcontents->checkPAR2($guid, $relID, $groupID, $db, $pp, 1, $nntp, 1);
		if ($res === false) {
			echo '.';
		}

        $nntp->doQuit();

	}
}
