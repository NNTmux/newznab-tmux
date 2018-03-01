<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Games;

$page = new AdminPage();
$game = new Games(['Settings' => $page->pdo]);

$page->title = 'Game List';

$gameCount = $game->getCount();

$offset = request()->input('offset') ?? 0;

$page->smarty->assign([
    'pagertotalitems' => $gameCount,
    'pagerquerysuffix'  => '#results',
    'pageroffset' => $offset,
    'pageritemsperpage' => env('ITEMS_PER_PAGE', 50),
    'pagerquerybase' => WWW_TOP.'/game-list.php?offset=',
]);

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$gamelist = $game->getRange($offset, env('ITEMS_PER_PAGE', 50));

$page->smarty->assign('gamelist', $gamelist);

$page->content = $page->smarty->fetch('game-list.tpl');
$page->render();
