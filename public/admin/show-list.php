<?php

use App\Models\Video;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

$page = new AdminPage();

$page->title = 'TV Shows List';

$tvshowname = (isset($_REQUEST['showname']) && ! empty($_REQUEST['showname']) ? $_REQUEST['showname'] : '');
$offset = $_REQUEST['offset'] ?? 0;

$page->smarty->assign(
    [
        'showname'          => $tvshowname,
        'tvshowlist'        => Video::getRange($offset, env('ITEMS_PER_PAGE', 50), $tvshowname),
        'pagertotalitems'   => Video::getCount($tvshowname),
        'pageroffset'       => $offset,
        'pageritemsperpage' => env('ITEMS_PER_PAGE', 50),
        'pagerquerysuffix'  => '',
        'pagerquerybase'    => WWW_TOP.'/show-list.php?'.
    ($tvshowname !== '' ? 'showname='.$tvshowname.'&amp;' : '').'&offset=',
    ]
);
$page->smarty->assign('pager', $page->smarty->fetch('pager.tpl'));

$page->content = $page->smarty->fetch('show-list.tpl');
$page->render();
