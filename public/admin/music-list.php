<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Music;
use Blacklight\utility\Utility;

$page = new AdminPage();

$music = new Music();

$page->title = 'Music List';

$musCount = Utility::getCount('musicinfo');

$offset = $_REQUEST['offset'] ?? 0;

$page->smarty->assign([
	'pagertotalitems' => $musCount,
	'pagerquerysuffix'  => '#results',
	'pageroffset' => $offset,
	'pageritemsperpage' => ITEMS_PER_PAGE,
	'pagerquerybase' => WWW_TOP.'/music-list.php?offset=',
]);

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$musicList = Utility::getRange('musicinfo', $offset, ITEMS_PER_PAGE);

$page->smarty->assign('musiclist', $musicList);

$page->content = $page->smarty->fetch('music-list.tpl');
$page->render();
