<?php
require_once("config.php");

use newznab\TheTVDB;

$page = new AdminPage();

if (isset($_GET['id']))
{
	$TheTVDB = new TheTVDB();
	$TheTVDB->deleteTitle($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];
header("Location: " . $referrer);

