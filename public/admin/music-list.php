<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Music;
use Blacklight\utility\Utility;

$page = new AdminPage();

$music = new Music();

$page->title = 'Music List';

$musCount = Utility::getCount('musicinfo');

$offset = request()->input('offset') ?? 0;

$page->smarty->assign([
	'pagertotalitems' => $musCount,
	'pagerquerysuffix'  => '#results',
	'pageroffset' => $offset,
	'pageritemsperpage' => env('ITEMS_PER_PAGE', 50),
	'pagerquerybase' => WWW_TOP.'/music-list.php?offset=',
]);

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$musicList = Utility::getRange('musicinfo', $offset, env('ITEMS_PER_PAGE', 50));

$page->smarty->assign('musiclist', $musicList);

$page->content = $page->smarty->fetch('music-list.tpl');
$page->render();
