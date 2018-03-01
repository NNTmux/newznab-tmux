<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Release;
use App\Models\DnzbFailure;

$page = new AdminPage();

$page->title = 'Failed Releases List';

$frelcount = DnzbFailure::getCount();

$offset = request()->input('offset') ?? 0;
$page->smarty->assign(
    [
        'pagertotalitems'   => $frelcount,
        'pagerquerysuffix'  => '#results',
        'pageroffset'       => $offset,
        'pageritemsperpage' => env('ITEMS_PER_PAGE', 50),
        'pagerquerybase'    => WWW_TOP.'/failrel-list.php?offset=',
    ]
);
$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$frellist = Release::getFailedRange($offset, env('ITEMS_PER_PAGE', 50));
$page->smarty->assign('releaselist', $frellist);

$page->content = $page->smarty->fetch('failrel-list.tpl');
$page->render();
