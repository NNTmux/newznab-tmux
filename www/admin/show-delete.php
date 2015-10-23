<?php
require_once './config.php';

use newznab\processing\tv\TVDB;

$page = new AdminPage();

if (isset($_GET['id'])) {
	(new TVDB(['Settings' => $page->settings]))->delete($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];
header("Location: " . $referrer);
