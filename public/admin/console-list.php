<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Console;
use Blacklight\utility\Utility;

$page = new AdminPage();
$con = new Console(['Settings' => $page->pdo]);

$page->title = 'Console List';

$conCount = Utility::getCount('consoleinfo');

$offset = $_REQUEST['offset'] ?? 0;

$page->smarty->assign([
    'pagertotalitems' => $conCount,
    'pagerquerysuffix'  => '#results',
    'pageroffset' => $offset,
    'pageritemsperpage' => env('ITEMS_PER_PAGE', 50),
    'pagerquerybase' => WWW_TOP.'/console-list.php?offset=',
]);

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$consoleList = Utility::getRange('consoleinfo', $offset, env('ITEMS_PER_PAGE', 50));

$page->smarty->assign('consolelist', $consoleList);

$page->content = $page->smarty->fetch('console-list.tpl');
$page->render();
