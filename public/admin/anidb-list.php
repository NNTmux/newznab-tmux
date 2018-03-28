<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';
use Blacklight\AniDB;
use Blacklight\http\BasePage;

$page = new BasePage();
$page->setAdminPrefs();

$AniDB = new AniDB();
$page->title = 'AniDB List';

$aname = '';
if (request()->has('animetitle') && ! empty(request()->input('animetitle'))) {
    $aname = request()->input('animetitle');
}

$animecount = $AniDB->getAnimeCount($aname);

$offset = request()->input('offset') ?? 0;
$asearch = ($aname !== '') ? 'animetitle='.$aname.'&amp;' : '';

$page->smarty->assign('pagertotalitems', $animecount);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', config('nntmux.items_per_page'));
$page->smarty->assign('pagerquerybase', WWW_TOP.'/anidb-list.php?'.$asearch.'&offset=');
$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$page->smarty->assign('animetitle', $aname);

$anidblist = $AniDB->getAnimeRange($offset, config('nntmux.items_per_page'), $aname);
$page->smarty->assign('anidblist', $anidblist);

$page->content = $page->smarty->fetch('anidb-list.tpl');
$page->adminrender();
