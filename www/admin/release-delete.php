<?php
require_once './config.php';

use newznab\controllers\AdminPage;
use newznab\controllers\Releases;

$page = new AdminPage();

if (isset($_GET['id']))
{
	$releases = new Releases(['Settings' => $page->settings]);
	$releases->deleteMultiple($_GET['id']);
}

if (isset($_GET['from']))
	$referrer = $_GET['from'];
else
	$referrer = $_SERVER['HTTP_REFERER'];
header("Location: " . $referrer);

