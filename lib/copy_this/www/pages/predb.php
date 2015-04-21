<?php

$PreDB = new PreDB();

if (!$users->isLoggedIn() || $page->userdata["canpre"] != 1)
	$page->show403();

$page->title = 'PreDB';
$page->meta_title = 'View PreDB';
$page->meta_keywords = 'view,predb,scene,release,information';
$page->meta_description = 'View PreDB';

$query = (isset($_GET['q']) && !empty($_GET['q'])) ? $_GET['q'] : '';
$category = (isset($_GET['c']) && !empty($_GET['c'])) ? $_GET['c'] : '';
$preCount = $PreDB->getPreCount($query, $category);

$offset = (isset($_REQUEST['offset']) && ctype_digit($_REQUEST['offset'])) ? $_REQUEST['offset'] : 0;
$results = $PreDB->getPreRange($offset, ITEMS_PER_PAGE, $query, $category);

$page->smarty->assign('pagertotalitems', $preCount);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', ITEMS_PER_PAGE);
$page->smarty->assign('pagerquerybase', WWW_TOP."/predb?q=".$query."&amp;c=".$category."&amp;offset=");
$page->smarty->assign('pagerquerysuffix', "#results");

$pager = $page->smarty->fetch("pager.tpl");
$page->smarty->assign('pager', $pager);
$page->smarty->assign('results', $results);
$page->smarty->assign('query', $query);
$page->smarty->assign('category', $category);

$page->content = $page->smarty->fetch('viewprelist.tpl');
$page->render();


