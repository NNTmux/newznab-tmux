<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\AniDB;

$page = new AdminPage();

$AniDB = new AniDB();

$page->title = 'AniDB List';

$aname = '';
if ($page->request->has('animetitle') && ! empty($page->request->input('animetitle'))) {
    $aname = $page->request->input('animetitle');
}

$animecount = $AniDB->getAnimeCount($aname);

$offset = $page->request->input('offset') ?? 0;
$asearch = ($aname !== '') ? 'animetitle='.$aname.'&amp;' : '';

$page->smarty->assign('pagertotalitems', $animecount);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', env('ITEMS_PER_PAGE', 50));
$page->smarty->assign('pagerquerybase', WWW_TOP.'/anidb-list.php?'.$asearch.'&offset=');
$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$page->smarty->assign('animetitle', $aname);

$anidblist = $AniDB->getAnimeRange($offset, env('ITEMS_PER_PAGE', 50), $aname);
$page->smarty->assign('anidblist', $anidblist);

$page->content = $page->smarty->fetch('anidb-list.tpl');
$page->render();
