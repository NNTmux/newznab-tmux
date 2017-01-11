<?php
require_once './config.php';


use nntmux\processing\ProcessReleasesMultiGroup;

$page = new AdminPage();

if (isset($_GET['id']))
{
	$mgr = new ProcessReleasesMultiGroup();
	$mgr->deletePoster($_GET['id']);
}

if (isset($_GET['from']))
	$referrer = $_GET['from'];
else
	$referrer = $_SERVER['HTTP_REFERER'];
header("Location: " . $referrer);
