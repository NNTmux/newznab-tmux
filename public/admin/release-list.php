<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use App\Models\Release;
use Blacklight\http\BasePage;

$page = new BasePage();
$page->setAdminPrefs();

$page->title = 'Release List';

$releasecount = Release::getReleasesCount();

$offset = request()->input('offset') ?? 0;

$page->smarty->assign([
    'pagertotalitems' => $releasecount,
    'pagerquerysuffix'  => '#results',
    'pageroffset' => $offset,
    'pageritemsperpage' => config('nntmux.items_per_page'),
    'pagerquerybase' => WWW_TOP.'/release-list.php?offset=',
]);

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$releaselist = Release::getReleasesRange($offset, config('nntmux.items_per_page'));
$page->smarty->assign('releaselist', $releaselist);

$page->content = $page->smarty->fetch('release-list.tpl');
$page->adminrender();
