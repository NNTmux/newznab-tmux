<?php

require_once("config.php");

$page = new AdminPage();

$releases = new Releases();
$num = (isset($_GET["id"])) ? $releases->removeThetvdbIdFromReleases($_GET["id"]) : 0;

$page->smarty->assign('numtv',$num);

$page->title = "Remove tvdbID from Releases";
$page->content = $page->smarty->fetch('thetvdb-remove.tpl');
$page->render();

