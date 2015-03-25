<?php
require_once(WWW_DIR . "/lib/releases.php");

$releases = new Releases(['Settings' => $page->settings]);

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
	return $page->serverurl . 'getnzb/' . $alt['guid'] . '/' . $alt['searchname'];
}