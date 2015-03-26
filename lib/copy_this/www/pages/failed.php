<?php
require_once(WWW_DIR . "/lib/releases.php");
require_once(WWW_DIR . "/lib/users.php");
require_once(WWW_DIR . "/lib/page.php");

$releases = new Releases(['Settings' => $page->settings]);
$users = new Users();
$page = new Page();

if (!$users->isLoggedIn())
	$page->show403();

	if (isset($_GET["id"])) {
		$rel = $releases->getByGuid($_GET["id"]);

		if (!$rel)
			$page->show404();

		$alt = $releases->getAlternate($rel['guid'], $rel['searchname']);
		if (!$alt) {
			$page->show404();
		}
		$userid = $users->getById($_GET["id"]);
		$uid = $userid['id'];
		$rsstoken = $userid['rsstoken'];
		//http://blah.net/getnzb/GUID.nzb&i=<usernumber>&r=APIKEY
		$url = $page->serverurl . 'getnzb/' . $alt['guid'] . '.nzb' . '&i=' . $uid . '&r=' . $rsstoken;
		header('Location: ' . $url . '');
	}