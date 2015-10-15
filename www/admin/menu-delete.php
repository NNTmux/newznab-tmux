<?php
require_once './config.php';

use newznab\controllers\AdminPage;
use newznab\controllers\Menu;

$page = new AdminPage();

if (isset($_GET['id']))
{
	$menu = new Menu();
	$menu->delete($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];
header("Location: " . $referrer);

