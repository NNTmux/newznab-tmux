<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Release;

$page = new AdminPage();

$page->title = 'Release List';

$releasecount = Release::getReleasesCount();

$offset = $page->request->input('offset') ?? 0;

$page->smarty->assign([
    'pagertotalitems' => $releasecount,
    'pagerquerysuffix'  => '#results',
    'pageroffset' => $offset,
    'pageritemsperpage' => env('ITEMS_PER_PAGE', 50),
    'pagerquerybase' => WWW_TOP.'/release-list.php?offset=',
]);

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$releaselist = Release::getReleasesRange($offset, env('ITEMS_PER_PAGE', 50));
$page->smarty->assign('releaselist', $releaselist);

$page->content = $page->smarty->fetch('release-list.tpl');
$page->render();
