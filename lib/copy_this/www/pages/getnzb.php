<?php
require_once(WWW_DIR . "/lib/releases.php");
require_once(WWW_DIR . "/lib/nzb.php");

$nzb = new NZB;
$rel = new Releases;
$uid = 0;
$role = Users::ROLE_USER;
$rsstoken = "";

//
// page is accessible only by the rss token, or logged in users.
//
if (!$users->isLoggedIn()) {
	if ((!isset($_GET["i"]) || !isset($_GET["r"])))
		$page->show403();

	$res = $users->getByIdAndRssToken($_GET["i"], $_GET["r"]);
	if (!$res)
		$page->show403();

	$uid = $res["ID"];
	$rsstoken = $res["rsstoken"];
	$role = $res["role"];
	$maxdls = $res["downloadrequests"];
} else {
	$uid = $users->currentUserId();
	$role = $page->userdata["role"];
	$maxdls = $page->userdata["downloadrequests"];
}

//
// remove any suffixed id with .nzb which is added to help
// weblogging programs see nzb traffic
//
if (isset($_GET["id"]))
	$_GET["id"] = preg_replace("/\.nzb/i", "", $_GET["id"]);

//
// A hash of the users ip to record against the download
//
$hosthash = "";

//
// check download limit on user role
//
if ($page->site->storeuserips == 1) {
	$hosthash = $users->getHostHash($_SERVER["REMOTE_ADDR"], $page->site->siteseed);

	//
	// users in the user role get tested
	//
	if ($role == Users::ROLE_USER) {
		$dlrequests = $users->getDownloadRequests($uid, $hosthash, $page->site);
	} else {
		$dlrequests = $users->getDownloadRequests($uid);
	}
} else {
	$dlrequests = $users->getDownloadRequests($uid);
}

//
// download limit per role
//
if ($dlrequests['num'] > $maxdls)
	$page->show429($dlrequests['nextdl']);

//
// user requested a zip of guid,guid,guid releases
//
if (isset($_REQUEST["id"]) && isset($_GET["zip"]) && $_GET["zip"] == "1") {
	if (isset($_POST['id']) && is_array($_POST['id']))
		$guids = $_POST['id'];
	else
		$guids = explode(",", $_GET["id"]);

	if ($dlrequests['num'] + sizeof($guids) > $maxdls)
		$page->show429();

	$zip = $rel->getZipped($guids);

	if (strlen($zip) > 0) {
		$users->incrementGrabs($uid, count($guids));
		foreach ($guids as $guid) {
			$rel->updateGrab($guid);
			$users->addDownloadRequest($uid, $hosthash, $guid);

			if (isset($_GET["del"]) && $_GET["del"] == 1)
				$users->delCartByUserAndRelease($guid, $uid);
		}

		$filename = date("Ymdhis") . ".nzb.zip";
		header("Content-type: application/octet-stream");
		header("Content-disposition: attachment; filename=\"" . $filename . "\"");
		echo $zip;
		die();
	} else
		$page->show404();
}

if (isset($_GET["id"])) {
	$reldata = $rel->getByGuid($_GET["id"]);
	$nzbpath = $nzb->getNZBPath($_GET["id"], $page->site->nzbpath);
	$nfo = "";

	if (!file_exists($nzbpath))
		$page->show404();

	if ($reldata) {
		$rel->updateGrab($_GET["id"]);
		$users->addDownloadRequest($uid, $hosthash, $_GET["id"]);
		$users->incrementGrabs($uid);
		if (isset($_GET["del"]) && $_GET["del"] == 1)
			$users->delCartByUserAndRelease($_GET["id"], $uid);

		$nfo = $rel->getReleaseNfo($reldata["ID"]);
		if ($nfo) {
			if (!$users->isLoggedIn()) {
				$nfo = $page->serverurl . "api?t=getnfo&id=" . $reldata["guid"] . "&raw=1&i=" . $uid . "&r=" . $rsstoken;
			} else {
				$nfo = $page->serverurl . "api?t=getnfo&id=" . $reldata["guid"] . "&raw=1";
			}
		}
	} else
		$page->show404();

	header("Content-type: application/x-nzb");
	header("X-DNZB-Name: " . $reldata["searchname"]);
	header("X-DNZB-Category: " . $reldata["category_name"]);
	header("X-DNZB-Details: " . $page->serverurl . "details/" . $reldata["guid"]);
	header("X-DNZB-NFO: " . $nfo);

	// Extra DNZB headers.
	if (!empty($reldata["tvreleasetitle"]))
		header("X-DNZB-ProperName: " . $reldata["tvreleasetitle"]);
	elseif (!empty($reldata["movietitle"]))
		header("X-DNZB-ProperName: " . $reldata["movietitle"]);
	if (!empty($reldata["tvtitle"]))
		header("X-DNZB-EpisodeName: " . $reldata["tvtitle"]);
	if (!empty($reldata["movieyear"]))
		header("X-DNZB-MovieYear: " . $reldata["movieyear"]);
	if ($reldata['rageID'] > 0)
		header("X-DNZB-MoreInfo: http://www.tvrage.com/shows/id-" . $reldata["rageID"]);
	elseif ($reldata['imdbID'] > 0)
		header("X-DNZB-MoreInfo: http://www.imdb.com/title/tt" . $reldata["imdbID"]);

	header("Content-Disposition: attachment; filename=\"" . str_replace(" ", "_", $reldata["searchname"]) . ".nzb\"");

	readgzfile($nzbpath);
}

