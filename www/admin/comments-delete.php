<?php
require_once './config.php';

use newznab\controllers\AdminPage;
use newznab\controllers\ReleaseComments;

$page = new AdminPage();

if (isset($_GET['id'])) {
	$rc = new ReleaseComments($page->settings);
	$rc->deleteComment($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];
header("Location: " . $referrer);
