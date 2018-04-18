<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\Console;
use Blacklight\http\BasePage;
use Blacklight\utility\Utility;

$page = new BasePage();
$page->setAdminPrefs();
$con = new Console(['Settings' => $page->pdo]);

$page->title = 'Console List';

$conCount = Utility::getCount('consoleinfo');

$offset = request()->input('offset') ?? 0;

$page->smarty->assign([
    'pagertotalitems' => $conCount,
    'pagerquerysuffix'  => '#results',
    'pageroffset' => $offset,
    'pageritemsperpage' => config('nntmux.items_per_page'),
    'pagerquerybase' => WWW_TOP.'/console-list.php?offset=',
]);

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$consoleList = Utility::getRange('consoleinfo', $offset, config('nntmux.items_per_page'));

$page->smarty->assign('consolelist', $consoleList);

$page->content = $page->smarty->fetch('console-list.tpl');
$page->adminrender();
