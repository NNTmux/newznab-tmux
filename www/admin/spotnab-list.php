<?php

require_once("config.php");

use newznab\SpotNab;

$page = new AdminPage();

$spotnab = new SpotNab();

// set the current action
$toggle = isset($_REQUEST['toggle']) ? $_REQUEST['toggle'] : 'view';

if ( (isset($_GET["toggle"])) && (isset($_GET["id"])) ) {
	$spotnab->toggleSource($_GET["id"],$_GET["toggle"]);
}

$page->title = "Spotnab Sources List";

//get the list of Sources
$spotnab = $spotnab->getSources();

$page->smarty->assign('spotnab',$spotnab);
$page->content = $page->smarty->fetch('spotnab-list.tpl');
$page->render();
