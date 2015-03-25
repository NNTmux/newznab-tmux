<?php
require_once(WWW_DIR . "/lib/releases.php");
require_once(WWW_DIR . "/lib/nzb.php");

$releases = new Releases(['Settings' => $page->settings]);
$nzb = new NZB();

if (!$users->isLoggedIn())
	$page->show403();

	if (isset($_GET["id"])) {
		$rel = $releases->getByGuid($_GET["id"]);
		$nzbpath = $nzb->getNZBPath($_GET["id"]);

		if (!file_exists($nzbpath)) {
			header("X-DNZB-RCode: 404");
			header("X-DNZB-RText: NZB file not found!");
			$page->show404();
		}

		if (!$rel) {
			header("X-DNZB-RCode: 404");
			header("X-DNZB-RText: Release not found!");
			$page->show404();
		}

		$alt = $releases->getAlternate($rel['guid'], $rel['searchname']);
		if (!$alt) {
			$page->show404();
		}

		// Start reading output buffer.
		ob_start();
		// De-gzip the NZB and store it in the output buffer.
		readgzfile($nzbpath);

		// Set the NZB file name.
		header("Content-Disposition: attachment; filename=" . str_replace(array(',', ' '), '_', $alt["searchname"]) . ".nzb");
		// Get the size of the NZB file.
		header("Content-Length: " . ob_get_length());
		header("Content-Type: application/x-nzb");
		header("Expires: " . date('r', time() + 31536000));
		// Set X-DNZB header data.
		header("X-DNZB-Category: " . $alt["category_name"]);
		header("X-DNZB-Details: " . $page->serverurl . 'details/' . $_GET["id"]);
		if (!empty($alt['imdbid']) && $alt['imdbid'] > 0) {
			header("X-DNZB-MoreInfo: http://www.imdb.com/title/tt" . $alt['imdbid']);
		} else if (!empty($alt['rageid']) && $alt['rageid'] > 0) {
			header("X-DNZB-MoreInfo: http://www.tvrage.com/shows/id-" . $alt['rageid']);
		}
		header("X-DNZB-Name: " . $alt["searchname"]);
		if ($alt['nfostatus'] == 1) {
			header("X-DNZB-NFO: " . $page->serverurl . 'nfo/' . $_GET["id"]);
		}
		header("X-DNZB-FailureLink: " . $page->serverurl . 'failed/' . $_GET["id"]);
		header("X-DNZB-RCode: 200");
		header("X-DNZB-RText: OK, NZB content follows.");

		// Print buffer and flush it.
		ob_end_flush();
	}