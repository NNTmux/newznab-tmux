<?php

require_once("config.php");

use newznab\TheTVDB;

$page = new AdminPage();

$TheTVDB = new TheTVDB();

$page->title = "TheTVDB List";

$sname = "";
if (isset($_REQUEST['seriesname']) && !empty($_REQUEST['seriesname']))
	$sname = $_REQUEST['seriesname'];

$seriescount = $TheTVDB->getSeriesCount($sname);

$offset = isset($_REQUEST["offset"]) ? $_REQUEST["offset"] : 0;
$ssearch = ($sname != "") ? 'seriesname='.$sname.'&amp;' : '';

$page->smarty->assign('pagertotalitems',$seriescount);
$page->smarty->assign('pageroffset',$offset);
$page->smarty->assign('pageritemsperpage',ITEMS_PER_PAGE);
$page->smarty->assign('pagerquerybase', WWW_TOP."/thetvdb-list.php?".$ssearch."&offset=");
$pager = $page->smarty->fetch("pager.tpl");
$page->smarty->assign('pager', $pager);

$page->smarty->assign('seriesname',$sname);

$serieslist = $TheTVDB->getSeriesRange($offset, ITEMS_PER_PAGE, $sname);
$page->smarty->assign('serieslist',$serieslist);

$page->content = $page->smarty->fetch('thetvdb-list.tpl');
$page->render();

