<?php
require_once(WWW_DIR.'/../misc/update_scripts/nix_scripts/tmux/lib/prehash.php');

if (!$users->isLoggedIn()) {
	$page->show403();
}

$predb = new PreHash();

$offset = (isset($_REQUEST["offset"]) && ctype_digit($_REQUEST['offset'])) ? $_REQUEST["offset"] : 0;

if (isset($_REQUEST['prehashsearch'])) {
	$lastSearch = $_REQUEST['prehashsearch'];
	$parr = $predb->getAll($offset, ITEMS_PER_PAGE, $_REQUEST['prehashsearch']);
} else {
	$lastSearch = '';
	$parr = $predb->getAll($offset, ITEMS_PER_PAGE);
}

$page->smarty->assign('pagertotalitems', $parr['count']);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', ITEMS_PER_PAGE);
$page->smarty->assign('pagerquerybase', WWW_TOP . "/prehash/&amp;offset=");
$page->smarty->assign('pagerquerysuffix', "#results");

$pager = $page->smarty->fetch("pager.tpl");
$page->smarty->assign('pager', $pager);

$page->smarty->assign('results', $parr['arr']);

$page->title = "Browse Prehash";
$page->meta_title = "View Prehash info";
$page->meta_keywords = "view,prehash,info,description,details";
$page->meta_description = "View Prehash info";

$page->content = $page->smarty->fetch('prehash.tpl');
$page->render();
