<?php
require_once(dirname(__FILE__) . "/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(dirname(__FILE__) . '/../lib/ColorCLI.php');
require_once(dirname(__FILE__) . '/../lib/NameFixer.php');
require_once(dirname(__FILE__) . '/../lib/Functions.php');
require_once(dirname(__FILE__) . '/../lib/Info.php');
require_once(dirname(__FILE__) . '/../lib/NZBContents.php');
require_once(dirname(__FILE__) . '/../lib/MiscSorter.php');

$c = new ColorCLI();
if (!isset($argv[1])) {
	exit($c->error("This script is not intended to be run manually, it is called from groupfixrelnames_threaded.py."));
} else if (isset($argv[1])) {
	$pdo = new DB();
	$namefixer = new NameFixer(true);
	$functions = new Functions();
	$pieces = explode(' ', $argv[1]);
	$guidChar = $pieces[1];
	$maxperrun = $pieces[2];
	$thread = $pieces[3];

	switch (true) {
		case $pieces[0] === 'nfo' && isset($guidChar) && isset($maxperrun) && is_numeric($maxperrun):
			$releases = $pdo->queryDirect(
				sprintf('
								SELECT r.ID AS releaseID, r.guid, r.groupID, r.categoryID, r.name, r.searchname,
									uncompress(nfo) AS textstring
								FROM releases r USE INDEX (ix_releases_guid)
								INNER JOIN releasenfo rn ON r.ID = rn.releaseID
								WHERE r.guid %s
								AND r.nzbstatus = 1
								AND r.proc_nfo = 0
								AND r.nfostatus = 1
								AND r.prehashID = 0
								ORDER BY r.postdate DESC
								LIMIT %s',
					$functions->likeString($guidChar, false, true),
					$maxperrun
				)
			);
			if ($releases !== false) {
				foreach ($releases as $release) {
					if (preg_match('/^=newz\[NZB\]=\w+/', $release['textstring'])) {
						$namefixer->done = $namefixer->matched = false;
						$pdo->queryDirect(sprintf('UPDATE releases SET proc_nfo = 1 WHERE ID = %d', $release['releaseID']));
						$namefixer->checked++;
						echo '.';
					} else {
						$namefixer->done = $namefixer->matched = false;
						if ($namefixer->checkName($release, true, 'NFO, ', 1, 1) !== true) {
							echo '.';
						}
						$namefixer->checked++;
					}
				}
			}
			break;
		case $pieces[0] === 'filename' && isset($guidChar) && isset($maxperrun) && is_numeric($maxperrun):
			$releases = $pdo->queryDirect(
				sprintf('
								SELECT rf.name AS textstring, rf.releaseID AS fileid,
									r.ID AS releaseID, r.name, r.searchname, r.categoryID, r.groupID
								FROM releases r USE INDEX (ix_releases_guid)
								INNER JOIN releasefiles rf ON r.ID = rf.releaseID
								WHERE r.guid %s
								AND r.nzbstatus = 1 AND r.proc_files = 0
								AND r.prehashID = 0
								ORDER BY r.postdate ASC
								LIMIT %s',
					$functions->likeString($guidChar, false, true),
					$maxperrun
				)
			);
			if ($releases !== false) {
				foreach ($releases as $release) {
					$namefixer->done = $namefixer->matched = false;
					if ($namefixer->checkName($release, true, 'Filenames, ', 1, 1) !== true) {
						echo '.';
					}
					$namefixer->checked++;
				}
			}
			break;
		case $pieces[0] === 'md5' && isset($guidChar) && isset($maxperrun) && is_numeric($maxperrun):
			$releases = $pdo->queryDirect(
				sprintf('
								SELECT DISTINCT r.ID AS releaseID, r.name, r.searchname, r.categoryID, r.groupID, r.dehashstatus,
									rf.name AS filename
								FROM releases r USE INDEX (ix_releases_guid)
								LEFT OUTER JOIN releasefiles rf ON r.ID = rf.releaseID AND rf.ishashed = 1
								WHERE r.guid %s
								AND nzbstatus = 1 AND r.ishashed = 1
								AND r.dehashstatus BETWEEN -6 AND 0
								AND r.prehashID = 0
								ORDER BY r.dehashstatus DESC, r.postdate ASC
								LIMIT %s',
					$functions->likeString($guidChar, false, true),
					$maxperrun
				)
			);
			if ($releases !== false) {
				foreach ($releases as $release) {
					if (preg_match('/[a-fA-F0-9]{32,40}/i', $release['name'], $matches)) {
						$namefixer->matchPredbHash($matches[0], $release, 1, 1, true, 1);
					} else if (preg_match('/[a-fA-F0-9]{32,40}/i', $release['filename'], $matches)) {
						$namefixer->matchPredbHash($matches[0], $release, 1, 1, true, 1);
					} else {
						$pdo->exec(sprintf("UPDATE releases SET dehashstatus = %d - 1 WHERE ID = %d", $release['dehashstatus'], $release['releaseID']));
						echo '.';
					}
				}
			}
			break;
		case $pieces[0] === 'par2' && isset($guidChar) && isset($maxperrun) && is_numeric($maxperrun):
			$releases = $pdo->queryDirect(
				sprintf('
								SELECT r.ID AS releaseID, r.guid, r.groupID
								FROM releases r USE INDEX (ix_releases_guid)
								WHERE r.guid %s
								AND r.nzbstatus = 1
								AND r.proc_par2 = 0
								AND r.prehashID = 0
								ORDER BY r.postdate ASC
								LIMIT %s',
					$functions->likeString($guidChar, false, true),
					$maxperrun
				)
			);
			if ($releases !== false) {
				$nntp = new NNTP(['Settings' => $pdo, 'ColorCLI' => $c]);
				$Nfo = new Info();
				$nzbcontents = new NZBContents(
					array(
						'Echo'        => true, 'NNTP' => $nntp, 'Nfo' => $Nfo, 'Settings' => $pdo,
						'PostProcess' => new PProcess(['Settings' => $pdo, 'Nfo' => $Nfo, 'NameFixer' => $namefixer])
					)
				);
				foreach ($releases as $release) {
					$res = $nzbcontents->checkPAR2($release['guid'], $release['releaseID'], $release['groupID'], 1, 1);
					if ($res === false) {
						echo '.';
					}
				}
			}
			break;
		case $pieces[0] === 'miscsorter' && isset($guidChar) && isset($maxperrun) && is_numeric($maxperrun):
			$releases = $pdo->queryDirect(
				sprintf('
								SELECT r.ID AS releaseID
								FROM releases r USE INDEX (ix_releases_guid)
								WHERE r.guid %s
								AND r.nzbstatus = 1 AND r.nfostatus = 1
								AND r.proc_sorter = 0 AND r.isrenamed = 0
								AND r.prehashID = 0
								ORDER BY r.postdate DESC
								LIMIT %s',
					$pdo->likeString($guidChar, false, true),
					$maxperrun
				)
			);
			if ($releases !== false) {
				$nntp = new NNTP(['Settings' => $pdo, 'ColorCLI' => $c]);
				$sorter = new MiscSorter(true);
				foreach ($releases as $release) {
					$res = $sorter->nfosorter(null, $release['releaseID'], $nntp);
					if ($res != true) {
						$pdo->exec(sprintf('UPDATE releases SET proc_sorter = 1 WHERE ID = %d', $release['releaseID']));
						echo '.';
					}
				}
			}
			break;
		case $pieces[0] === 'predbft' && isset($maxperrun) && is_numeric($maxperrun) && isset($thread) && is_numeric($thread):
			$pres = $pdo->queryDirect(
				sprintf('
							SELECT p.ID AS prehashID, p.title, p.source, p.searched
							FROM prehash p
							WHERE LENGTH(title) >= 15 AND title NOT REGEXP "[\"\<\> ]"
							AND searched = 0
							AND DATEDIFF(NOW(), predate) > 1
							ORDER BY predate ASC
							LIMIT %s
							OFFSET %s',
					$maxperrun,
					$thread * $maxperrun
				)
			);
			if ($pres !== false) {
				foreach ($pres as $pre) {
					$namefixer->done = $namefixer->matched = false;
					$ftmatched = $searched = 0;
					$ftmatched = $namefixer->matchPredbFT($pre, 1, 1, true, 1);
					if ($ftmatched > 0) {
						$searched = 1;
					} elseif ($ftmatched < 0) {
						$searched = -6;
						echo "*";
					} else {
						$searched = $pre['searched'] - 1;
						echo ".";
					}
					$pdo->exec(sprintf("UPDATE prehash SET searched = %d WHERE ID = %d", $searched, $pre['prehashID']));
					$namefixer->checked++;
				}
			}
	}
}