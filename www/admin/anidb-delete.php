<?php
require_once("config.php");
use newznab\AniDB;

$page = new AdminPage();

if (isset($_GET['id']))
{
	$AniDB = new AniDB();
	$AniDB->deleteTitle($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];
header("Location: " . $referrer);
