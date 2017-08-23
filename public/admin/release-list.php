<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smarty.php';


use nntmux\Releases;

$page = new AdminPage();

$releases = new Releases();

$page->title = 'Release List';

$releasecount = $releases->getCount();

$offset = $_REQUEST['offset'] ?? 0;

$page->smarty->assign([
	'pagertotalitems' => $releasecount,
	'pagerquerysuffix'  => '#results',
	'pageroffset' => $offset,
	'pageritemsperpage' => ITEMS_PER_PAGE,
	'pagerquerybase' => WWW_TOP. '/release-list.php?offset=',
]);

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$releaselist = $releases->getRange($offset, ITEMS_PER_PAGE);
$page->smarty->assign('releaselist',$releaselist);

$page->content = $page->smarty->fetch('release-list.tpl');
$page->render();

