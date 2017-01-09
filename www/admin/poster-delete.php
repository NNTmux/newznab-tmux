<?php
require_once './config.php';


use nntmux\ReleasesMultiGroup;

$page = new AdminPage();

if (isset($_GET['id']))
{
	$mgr = new ReleasesMultiGroup();
	$mgr->deletePoster($_GET['id']);
}

if (isset($_GET['from']))
	$referrer = $_GET['from'];
else
	$referrer = $_SERVER['HTTP_REFERER'];
header("Location: " . $referrer);
