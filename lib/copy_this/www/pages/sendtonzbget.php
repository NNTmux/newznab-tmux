<?php
require_once(WWW_DIR."/lib/nzbget.php");

if (!$users->isLoggedIn())
	$page->show403();

if (empty($_GET["id"]))
	$page->show404();

$nzbget = new NZBGet($page);

if (empty($nzbget->url))
	$page->show404();

if (empty($nzbget->username))
	$page->show404();

if (empty($nzbget->password))
    $page->show404();

$guid = $_GET["id"];

$nzbget->sendToNZBGet($guid);

