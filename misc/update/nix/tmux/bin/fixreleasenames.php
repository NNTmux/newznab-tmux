<?php
require_once realpath(dirname(dirname(dirname(dirname(dirname(__DIR__))))) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use nntmux\db\DB;
use nntmux\processing\PostProcess;
use nntmux\ColorCLI;
use nntmux\NameFixer;
use nntmux\NNTP;
use nntmux\NZBContents;
use nntmux\Nfo;

$c = new ColorCLI();
if (!isset($argv[1])) {
	exit($c->error("This script is not intended to be run manually, it is called from fixreleasenames.php."));
} else if (isset($argv[1])) {
	$db = new DB();
	$namefixer = new NameFixer(['Settings' => $pdo]);
	$pieces = explode(' ', $argv[1]);
	if (isset($pieces[1]) && $pieces[0] == 'nfo') {
		$release = $pieces[1];
		if ($res = $db->queryOneRow(sprintf('SELECT rel.guid AS guid, nfo.releases_id AS nfoid, rel.groups_id, rel.categories_id, rel.name, rel.searchname, uncompress(nfo) AS textstring, rel.id AS releases_id FROM releases rel INNER JOIN release_nfos nfo ON (nfo.releases_id = rel.id) WHERE rel.id = %d', $release))) {
			//ignore encrypted nfos
			if (preg_match('/^=newz\[NZB\]=\w+/', $res['textstring'])) {
				$namefixer->done = $namefixer->matched = false;
				$db->queryDirect(sprintf('UPDATE releases SET proc_nfo = 1 WHERE id = %d', $res['releases_id']));
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
		if ($res = $db->queryOneRow(sprintf('SELECT relfiles.name AS textstring, rel.categories_id, rel.searchname, '
				. 'rel.groups_id, relfiles.releases_id AS fileid, rel.id AS releases_id, rel.name FROM releases rel '
				. 'INNER JOIN release_files relfiles ON (relfiles.releases_id = rel.id) WHERE rel.id = %d', $release))) {
			$namefixer->done = $namefixer->matched = false;
			if ($namefixer->checkName($res, true, 'Filenames, ', 1, 1) !== true) {
				echo '.';
			}
			$namefixer->checked++;
		}
	} else if (isset($pieces[1]) && $pieces[0] == 'srr') {
		$release = $pieces[1];
		if ($res = $db->queryOneRow(sprintf('SELECT relfiles.name AS textstring, rel.categories_id, rel.searchname, '
			. 'rel.groups_id, relfiles.releases_id AS fileid, rel.id AS releases_id, rel.name FROM releases rel '
			. 'INNER JOIN release_files relfiles ON (relfiles.releases_id = rel.id) WHERE rel.id = %d', $release))) {
			$namefixer->done = $namefixer->matched = false;
			if ($namefixer->checkName($res, true, 'Srr, ', 1, 1) !== true) {
				echo '.';
			}
			$namefixer->checked++;
		}
	}else if (isset($pieces[1]) && $pieces[0] == 'md5') {
		$release = $pieces[1];
		if ($res = $db->queryOneRow(sprintf('SELECT r.id AS releases_id, r.name, r.searchname, r.categories_id, r.groups_id, dehashstatus, rf.name AS filename FROM releases r LEFT JOIN release_files rf ON r.id = rf.releases_id WHERE r.id = %d', $release))) {
			if (preg_match('/[a-fA-F0-9]{32,40}/i', $res['name'], $matches)) {
				$namefixer->matchPredbHash($matches[0], $res, 1, 1, true, 1);
			} else if (preg_match('/[a-fA-F0-9]{32,40}/i', $res['filename'], $matches)) {
				$namefixer->matchPredbHash($matches[0], $res, 1, 1, true, 1);
			} else {
				$db->queryExec(sprintf("UPDATE releases SET dehashstatus = %d - 1 WHERE id = %d", $res['dehashstatus'], $res['releases_id']));
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
		$nzbcontents = new NZBContents(array('echo' => true, 'nntp' => $nntp, 'nfo' => new Nfo(), 'db' => $db, 'pp' => new PostProcess(['Settings' => $pdo, 'Nfo' => $Nfo, 'NameFixer' => $namefixer])));
		$res = $nzbcontents->checkPAR2($guid, $relID, $groupID, 1, 1);
		if ($res === false) {
			echo '.';
		}

        $nntp->doQuit();

	} else if (isset($pieces[1]) && $pieces[0] == 'predbft') {
		$pre = $pieces[1];
		if ($res = $db->queryOneRow(sprintf('SELECT id AS predb_id, title, source, searched FROM predb '
				. 'WHERE id = %d', $pre
			)
		)
		) {
			$namefixer->done = $namefixer->matched = false;
			$ftmatched = $searched = 0;
			$ftmatched = $namefixer->matchPredbFT($res, 1, 1, true, 1);
			if ($ftmatched > 0) {
				$searched = 1;
			} elseif ($ftmatched < 0) {
				$searched = -6;
				echo "*";
			} else {
				$searched = $res['searched'] - 1;
				echo ".";
			}
			$db->queryExec(sprintf("UPDATE predb SET searched = %d WHERE id = %d", $searched, $res['predb_id']));
			$namefixer->checked++;
		}

	}
}
