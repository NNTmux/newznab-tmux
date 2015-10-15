<?php

require_once './config.php';

use newznab\controllers\AdminPage;
use newznab\controllers\Contents;

$page = new AdminPage();

if (isset($_GET['id']))
{
	$contents = new Contents();
	$contents->delete($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];
header("Location: " . $referrer);

