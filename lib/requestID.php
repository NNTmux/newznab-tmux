<?php
require_once(dirname(__FILE__) . "/../bin/config.php");
require_once(WWW_DIR . "lib/framework/db.php");
require_once(WWW_DIR . "lib/category.php");
require_once(WWW_DIR . "lib/groups.php");
require_once("functions.php");
require_once("ColorCLI.php");
require_once("consoletools.php");
require_once("namefixer.php");

//This script is adapted from nZEDB requestID.php

$c = new ColorCLI();
if (!isset($argv[1]) || ( $argv[1] != "all" && $argv[1] != "full" && !is_numeric($argv[1]))) {
	exit($c->error("\nThis script tries to match a release request ID by group to a PreDB request ID by group doing local lookup only.\n"
			. "In addition an optional final argument is time, in minutes, to check releases that have previously been checked.\n\n"
			. "php requestID.php 1000 show		...: to limit to 1000 sorted by newest postdate and show renaming.\n"
			. "php requestID.php full show		...: to run on full database and show renaming.\n"
			. "php requestID.php all show		...: to run on all hashed releases (including previously renamed) and show renaming.\n"));
}

$db = new DB();
$functions = new Functions();
$category = new Category();
$groups = new Groups();
$consoletools = new ConsoleTools();
$namefixer = new Namefixer();
$timestart = TIME();
$counter = $counted = 0;

if (isset($argv[2]) && is_numeric($argv[2])) {
	$time = ' OR r.postdate > NOW() - INTERVAL ' . $argv[2] . ' MINUTE)';
} else if (isset($argv[3]) && is_numeric($argv[3])) {
	$time = ' OR r.postdate > NOW() - INTERVAL ' . $argv[3] . ' MINUTE)';
} else {
	$time = ')';
}

//runs on every release not already PreDB Matched
if (isset($argv[1]) && $argv[1] === "all") {
	$qry = $db->queryDirect("SELECT r.ID, r.name, r.categoryID, g.name AS groupname, g.ID as gid FROM releases r LEFT JOIN groups g ON r.groupID = g.ID WHERE nzbstatus = 1 AND prehashID = 0 AND isrequestid = 1");
//runs on all releases not already renamed not already PreDB matched
} else if (isset($argv[1]) && $argv[1] === "full") {
	$qry = $db->queryDirect("SELECT r.ID, r.name, r.categoryID, g.name AS groupname, g.ID as gid FROM releases r LEFT JOIN groups g ON r.groupID = g.ID WHERE nzbstatus = 1 AND prehashID = 0 AND (isrenamed = 0 AND isrequestid = 1 " . $time . " AND reqidstatus in (0, -1, -3)");
//runs on all releases not already renamed limited by user not already PreDB matched
} else if (isset($argv[1]) && is_numeric($argv[1])) {
	$qry = $db->queryDirect("SELECT r.ID, r.name, r.categoryID, g.name AS groupname, g.ID as gid FROM releases r LEFT JOIN groups g ON r.groupID = g.ID WHERE nzbstatus = 1 AND prehashID = 0 AND (isrenamed = 0 AND isrequestid = 1 " . $time . " AND reqidstatus in (0, -1, -3) ORDER BY postdate DESC LIMIT " . $argv[1]);
}

$total = $qry->rowCount();
if ($total > 0) {
	$precount = $db->queryOneRow('SELECT COUNT(*) AS count FROM prehash WHERE requestID > 0');
	echo $c->header("\nComparing " . number_format($total) . ' releases against ' . number_format($precount['count']) . " Local requestID's.");
	sleep(2);

	foreach ($qry as $row) {
		$requestID = 0;
		if (preg_match('/^\[ ?(\d{4,6}) ?\]/', $row['name'], $match) ||
			preg_match('/^REQ\s*(\d{4,6})/i', $row['name'], $match) ||
			preg_match('/^(\d{4,6})-\d{1}\[/', $row['name'], $match) ||
			preg_match('/(\d{4,6}) -/', $row['name'], $match)
		) {
			$requestID = (int)$match[1];
		} else {
			echo "requestID = " . $requestID . " name =" . $row['name'] . PHP_EOL;
			$db->exec('UPDATE releases SET reqidstatus = -2 WHERE ID = ' . $row['ID']);
			$counter++;
			continue;
		}

		$bFound = false;
		$newTitle = '';

		if ($requestID != 0 and $requestID != '') {
			// Do a local lookup first
			$newTitle = localLookup($requestID, $row['groupname'], $row['name']);
			if (is_array($newTitle) && $newTitle['title'] != '') {
				$bFound = true;
			}
		}

		if ($bFound === true) {
			$title = $newTitle['title'];
			$preid = $newTitle['ID'];
			$determinedcat = $category->determineCategory($row['gid'], $title);
			$run = $db->queryDirect(sprintf('UPDATE releases set rageID = -1, seriesfull = NULL, season = NULL, episode = NULL, tvtitle = NULL, tvairdate = NULL, imdbID = NULL, musicinfoID = NULL, consoleinfoID = NULL, bookinfoID = NULL, anidbID = NULL, '
					. 'prehashID = %d, reqidstatus = 1, isrenamed = 1, iscategorized = 1, searchname = %s, categoryID = %d WHERE ID = %d', $preid, $db->escapeString($title), $determinedcat, $row['ID']));
			if ($row['name'] !== $newTitle) {
				$counted++;
				if (isset($argv[2]) && $argv[2] === 'show') {
					$newcatname = $category->getNameByID($determinedcat);
					$oldcatname = $category->getNameByID($row['categoryID']);

					echo $c->headerOver("\nNew name:  ") . $c->primary($title) .
					$c->headerOver('Old name:  ') . $c->primary($row['name']) .
					$c->headerOver('New cat:   ') . $c->primary($newcatname) .
					$c->headerOver('Old cat:   ') . $c->primary($oldcatname) .
					$c->headerOver('Group:     ') . $c->primary($row['groupname']) .
					$c->headerOver('Method:    ') . $c->primary('requestID local') .
					$c->headerOver('ReleaseID: ') . $c->primary($row['ID']);
				}
			}
		} else {
			$db->exec('UPDATE releases SET reqidstatus = -3 WHERE ID = ' . $row['ID']);
		}
		if (!isset($argv[2]) || $argv[2] !== 'show') {
			$consoletools->overWritePrimary("Renamed Releases: [" . number_format($counted) . "] " . $consoletools->percentString(++$counter, $total));
		}
	}
	if ($total > 0) {
		echo $c->header("\nRenamed " . number_format($counted) . " releases in " . $consoletools->convertTime(TIME() - $timestart) . ".");
	} else {
		echo $c->info("\nNothing to do.");
	}
} else {
	echo $c->info("No work to process\n");
}


function localLookup($requestID, $groupName, $oldname)
{
	$db = new DB();
	$groups = new Groups();
	$functions = new Functions();
	$groupID = $functions->getIDByName($groupName);
	$run = $db->queryOneRow(sprintf("SELECT ID, title FROM prehash WHERE requestID = %d AND groupID = %d", $requestID, $groupID));
	if (isset($run["title"]) && preg_match('/s\d+/i', $run["title"]) && !preg_match('/s\d+e\d+/i', $run["title"])) {
		return false;
	}
	if (isset($run["title"]))
		return array('title' => $run['title'], 'ID' => $run['ID']);
	if (preg_match('/\[#?a\.b\.teevee\]/', $oldname))
		$groupID = $functions->getIDByName('alt.binaries.teevee');
	else if (preg_match('/\[#?a\.b\.moovee\]/', $oldname))
		$groupID = $functions->getIDByName('alt.binaries.moovee');
	else if (preg_match('/\[#?a\.b\.erotica\]/', $oldname))
		$groupID = $functions->getIDByName('alt.binaries.erotica');
	else if (preg_match('/\[#?a\.b\.foreign\]/', $oldname))
		$groupID = $functions->getIDByName('alt.binaries.mom');
	else if ($groupName == 'alt.binaries.etc')
		$groupID = $functions->getIDByName('alt.binaries.teevee');


	$run = $db->queryOneRow(sprintf("SELECT ID, title FROM prehash WHERE requestID = %d AND groupID = %d", $requestID, $groupID));
	if (isset($run['title']))
		return array('title' => $run['title'], 'ID' => $run['ID']);
}