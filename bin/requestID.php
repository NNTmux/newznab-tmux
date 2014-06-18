<?php

require(dirname(__FILE__) . "/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/groups.php");
require_once(WWW_DIR . "/lib/category.php");
require_once(WWW_DIR . "/lib/Tmux.php");
require_once(dirname(__FILE__) . '/../lib/functions.php');
require_once(dirname(__FILE__) . '/../lib/ColorCLI.php');

$c = new ColorCLI();
if (!isset($argv[1])) {
	exit($c->error("This script is not intended to be run manually, it is called from requestid_threaded.py."));
}
$pieces = explode('                       ', $argv[1]);
$db = new DB();
$n = "\n";
$category = new Category();
$groups = new Groups();
$t = new Tmux ();
$tmux = $t->get();
$f = new Functions();
$requestID = 0;
if (preg_match('/^\[ ?(\d{4,6}) ?\]/', $pieces[1], $match) ||
	preg_match('/^REQ\s*(\d{4,6})/i', $pieces[1], $match) ||
	preg_match('/^(\d{4,6})-\d{1}\[/', $pieces[1], $match) ||
	preg_match('/(\d{4,6}) -/', $pieces[1], $match)
) {
	$requestID = (int)$match[1];
} else {

	$db->exec('UPDATE releases SET reqidstatus = -2 WHERE ID = ' . $pieces[0]);
	exit('.');
}
$bFound = false;
$newTitle = '';
$updated = 0;

if ($requestID != 0 and $requestID != '') {
	// Do a local lookup first
	$newTitle = localLookup($requestID, $pieces[2], $pieces[1]);
	if (is_array($newTitle) && $newTitle['title'] != '') {
		$bFound = true;
		$local = true;
	} else if ($web == "True") {
		$newTitle = getReleaseNameFromRequestID($site, $requestID, $pieces[2]);
		if (is_array($newTitle) && $newTitle['title'] != '') {
			$bFound = true;
			$local = false;
		}
	}
}
if ($bFound === true) {
	$title = $newTitle['title'];
	$prehashID = $newTitle['ID'];
	$groupname = $f->getByNameByID($pieces[2]);
	$determinedcat = $category->determineCategory($groupname, $title);
	$run = $db->queryDirect(sprintf("UPDATE releases SET rageID = -1, seriesfull = NULL, season = NULL, episode = NULL, tvtitle = NULL, tvairdate = NULL, imdbID = NULL, musicinfoID = NULL, consoleinfoID = NULL, bookinfoID = NULL, anidbID = NULL, "
			. "prehashID = %d, reqidstatus = 1, isrenamed = 1, searchname = %s, categoryID = %d where ID = %d", $prehashID, $db->escapeString($title), $determinedcat, $pieces[0]
		)
	);
	$groupid = $f->getIDByName($pieces[2]);
	if ($groupid !== 0) {
		$dupe = $db->queryOneRow(sprintf('SELECT requestID FROM prehash WHERE title = %s', $db->escapeString($title)));
		if ($dupe === false || ($dupe !== false && $dupe['requestID'] !== $requestID)) {
			$db->queryDirect(
				sprintf("
					INSERT INTO prehash (title, source, requestID, groupID)
					VALUES (%s, %s, %d, %d)",
					$db->escapeString($title),
					$db->escapeString('requestWEB'),
					$requestID, $groupid
				)
			);
		}
	} else if ($groupid === 0) {
		echo $requestID . "\n";
	}
	$newcatname = $f->getNameByID($determinedcat);
	$method = ($local === true) ? 'requestID local' : 'requestID web';

	NameFixer::echoChangedReleaseName(array(
			'new_name'     => $title,
			'old_name'     => $pieces[1],
			'new_category' => $newcatname,
			'old_category' => '',
			'group'        => trim($pieces[2]),
			'release_id'   => $pieces[0],
			'method'       => $method
		)
	);
	$updated++;
} else {
	$db->exec('UPDATE releases SET reqidstatus = -3 WHERE ID = ' . $pieces[0]);
	echo '.';
}

function getReleaseNameFromRequestID($tmux, $requestID, $groupName)
{
	$t = new Tmux();
	$tmux = $t->get();
	if ($tmux->request_url == '') {
		return false;
	}
	// Build Request URL
	$req_url1 = str_ireplace('[GROUP_NM]', urlencode($groupName), $tmux->request_url);
	$req_url = str_ireplace('[REQUEST_ID]', urlencode($requestID), $req_url1);
	$xml = @simplexml_load_file($req_url);
	if (($xml == false) || (count($xml) == 0)) {
		return false;
	}
	$request = $xml->request[0];
	if (isset($request)) {
		return array('title' => $request['name'], 'ID' => 'NULL');
	}

	return true;
}

function localLookup($requestID, $groupName, $oldname)
{
	$db = new DB();
	$groups = new Groups();
	$f = new Functions();
	$groupID = $f->getIDByName($groupName);
	$run = $db->queryOneRow(sprintf("SELECT ID, title FROM prehash WHERE requestID = %d AND groupID = %d", $requestID, $groupID));
	if (isset($run['title']) && preg_match('/s\d+/i', $run['title']) && !preg_match('/s\d+e\d+/i', $run['title'])) {
		return false;
	}
	if (isset($run['title'])) {
		return array('title' => $run['title'], 'ID' => $run['ID']);
	}
	if (preg_match('/\[#?a\.b\.teevee\]/', $oldname)) {
		$groupID = $f->getIDByName('alt.binaries.teevee');
	} else if (preg_match('/\[#?a\.b\.moovee\]/', $oldname)) {
		$groupID = $f->getIDByName('alt.binaries.moovee');
	} else if (preg_match('/\[#?a\.b\.erotica\]/', $oldname)) {
		$groupID = $f->getIDByName('alt.binaries.erotica');
	} else if (preg_match('/\[#?a\.b\.foreign\]/', $oldname)) {
		$groupID = $f->getIDByName('alt.binaries.mom');
	} else if ($groupName == 'alt.binaries.etc') {
		$groupID = $f->getIDByName('alt.binaries.teevee');
	}
	$run1 = $db->queryOneRow(sprintf("SELECT ID, title FROM prehash WHERE requestID = %d AND groupID = %d", $requestID, $groupID));
	if (isset($run1['title'])) {
		return array('title' => $run['title'], 'ID' => $run['ID']);
	}

	return true;
}