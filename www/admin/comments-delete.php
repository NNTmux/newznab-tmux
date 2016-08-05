<?php
require_once './config.php';


use nntmux\ReleaseComments;

$page = new AdminPage();

if (isset($_GET['id'])) {
	$rc = new ReleaseComments($page->settings);
	$rc->deleteComment($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];
header("Location: " . $referrer);
