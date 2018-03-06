<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\Books;
use Blacklight\http\AdminPage;
use Blacklight\utility\Utility;

$page = new AdminPage();
$book = new Books();

$page->title = 'Book List';

$bookCount = Utility::getCount('bookinfo');

$offset = request()->input('offset') ?? 0;

$page->smarty->assign([
    'pagertotalitems' => $bookCount,
    'pagerquerysuffix'  => '#results',
    'pageroffset' => $offset,
    'pageritemsperpage' => config('nntmux.items_per_page'),
    'pagerquerybase' => WWW_TOP.'/book-list.php?offset=',
]);

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$bookList = Utility::getRange('bookinfo', $offset, config('nntmux.items_per_page'));

$page->smarty->assign('booklist', $bookList);

$page->content = $page->smarty->fetch('book-list.tpl');
$page->render();
