<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\Movie;
use Blacklight\http\AdminPage;
use Blacklight\utility\Utility;

$page = new AdminPage();

$movie = new Movie(['Settings' => $page->pdo]);

$page->title = 'Movie List';

$movCount = Utility::getCount('movieinfo');

$offset = request()->input('offset') ?? 0;

$page->smarty->assign([
    'pagertotalitems' => $movCount,
    'pagerquerysuffix'  => '#results',
    'pageroffset' => $offset,
    'pageritemsperpage' => config('nntmux.items_per_page'),
    'pagerquerybase' => WWW_TOP.'/movie-list.php?offset=',
]);
$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$movieList = Utility::getRange('movieinfo', $offset, config('nntmux.items_per_page'));
$page->smarty->assign('movielist', $movieList);

$page->content = $page->smarty->fetch('movie-list.tpl');
$page->render();
