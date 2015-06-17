<?php

require_once("config.php");

$page = new AdminPage();
$releases = new Releases();
$category = new Category();
$previewtype = 1;

$page->title = "Preview List";

$previewcat="-1";
if (isset($_REQUEST["previewcat"]))
    $previewcat = $_REQUEST["previewcat"];

$catarray = array();
$catarray[] = $previewcat;

$releasecount = $releases->getPreviewCount($previewtype, $catarray);

$offset = isset($_REQUEST["offset"]) ? $_REQUEST["offset"] : 0;
$page->smarty->assign('pagertotalitems',$releasecount);
$page->smarty->assign('pageroffset',$offset);
$page->smarty->assign('pageritemsperpage',ITEMS_PER_PAGE);
$page->smarty->assign('pagerquerybase', WWW_TOP."/preview-list.php?previewcat=".$previewcat."&offset=");
$pager = $page->smarty->fetch("pager.tpl");
$page->smarty->assign('pager', $pager);

$parentcatlist = $category->getForMenu();
$page->smarty->assign('catlist',$parentcatlist);
$page->smarty->assign('previewcat',$previewcat);

$releaselist = $releases->getPreviewRange($previewtype, $catarray, $offset, ITEMS_PER_PAGE);
$page->smarty->assign('releaselist',$releaselist);

$page->content = $page->smarty->fetch('preview-list.tpl');
$page->render();

