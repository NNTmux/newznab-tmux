<?php

require_once './config.php';

use newznab\controllers\AdminPage;
use newznab\controllers\Releases;

$page = new AdminPage();

$releases = new Releases();
$num = 0;
if (isset($_GET["id"]))
	$num = $releases->removeRageIdFromReleases($_GET["id"]);

$page->smarty->assign('numtv',$num);

$page->title = "Remove Rage Id from Releases";
$page->content = $page->smarty->fetch('rage-remove.tpl');
$page->render();

