<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\Music;
use Blacklight\http\BasePage;
use Blacklight\utility\Utility;

$page = new BasePage();
$page->setAdminPrefs();
$music = new Music();

$page->title = 'Music List';

$musCount = Utility::getCount('musicinfo');

$offset = request()->input('offset') ?? 0;

$page->smarty->assign([
	'pagertotalitems' => $musCount,
	'pagerquerysuffix'  => '#results',
	'pageroffset' => $offset,
	'pageritemsperpage' => config('nntmux.items_per_page'),
	'pagerquerybase' => WWW_TOP.'/music-list.php?offset=',
]);

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$musicList = Utility::getRange('musicinfo', $offset, config('nntmux.items_per_page'));

$page->smarty->assign('musiclist', $musicList);

$page->content = $page->smarty->fetch('music-list.tpl');
$page->adminrender();
