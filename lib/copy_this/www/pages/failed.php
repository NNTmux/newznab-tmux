<?php
require_once(WWW_DIR . "/lib/releases.php");
require_once(WWW_DIR . "/lib/users.php");
require_once(WWW_DIR . "/lib/page.php");

$releases = new Releases(['Settings' => $page->settings]);
$users = new Users();
$page = new Page();

	if (isset($_GET["userid"]) && isset($_GET['rsstoken']) && isset($_GET['guid'])) {
		$rel = $releases->getByGuid($_GET["guid"]);

		if (!$rel)
			$page->show404();

		$alt = $releases->getAlternate($rel['guid'], $rel['searchname'], $_GET['userid']);
		if (!$alt) {
			$page->show404();
		}
		//http://blah.net/getnzb/GUID.nzb&i=<usernumber>&r=APIKEY
		$url = $page->serverurl . 'getnzb/' . $alt['guid'] . '.nzb' . '&i=' . $_GET['userid'] . '&r=' . $_GET['rsstoken'];
		header('Location: ' . $url . '');
	}