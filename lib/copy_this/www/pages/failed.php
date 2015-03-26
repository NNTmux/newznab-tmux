<?php
require_once(WWW_DIR . "/lib/releases.php");
require_once(WWW_DIR . "/lib/users.php");
require_once(WWW_DIR . "/lib/page.php");

$releases = new Releases(['Settings' => $page->settings]);
$users = new Users();
$page = new Page();

	if (isset($_GET["userid"])) {
		$rel = $releases->getByGuid($_GET["guid"]);

		if (!$rel)
			$page->show404();

		$alt = $releases->getAlternate($rel['guid'], $rel['searchname']);
		if (!$alt) {
			$page->show404();
		}
		$userid = $users->getById($_GET["userid"]);
		$uid = $userid['id'];
		$rsstoken = $userid['rsstoken'];
		//http://blah.net/getnzb/GUID.nzb&i=<usernumber>&r=APIKEY
		$url = $page->serverurl . 'getnzb/' . $alt['guid'] . '.nzb' . '&i=' . $_GET['userid'] . '&r=' . $_GET['rsstoken'];
		header('Location: ' . $url . '');
	}