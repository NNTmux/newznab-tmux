<?php
require_once './config.php';

use newznab\controllers\AdminPage;
use newznab\controllers\Forum;

$page = new AdminPage();

if (isset($_GET['id']))
{
	$forum = new Forum();
	$forum->deletePost($_GET['id']);
}

if (isset($_GET['from']))
	$referrer = $_GET['from'];
else
	$referrer = $_SERVER['HTTP_REFERER'];
header("Location: " . $referrer);
