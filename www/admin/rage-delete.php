<?php
require_once("config.php");

use newznab\TvRage;

$page = new AdminPage();

if (isset($_GET['id']))
{
	$tvrage = new TvRage();
	$tvrage->delete($_GET['id']);
}

if(isset($_GET['from']) && !empty($_GET['from']))
{
	header("Location:".$_GET['from']);
	die();
}

$referrer = $_SERVER['HTTP_REFERER'];
header("Location: " . $referrer);
die();
