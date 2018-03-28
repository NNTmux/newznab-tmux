<?php

use App\Models\Settings;
use App\Models\Forumpost;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}

if ($page->isPostBack() && request()->has('addMessage') && request()->has('addSubject')) {
    Forumpost::add(0, Auth::id(), request()->input('addSubject'), request()->input('addMessage'));
    header('/forum');
}

$lock = $unlock = null;

if (request()->has('lock')) {
    $lock = request()->input('lock');
}

if (request()->has('unlock')) {
    $unlock = request()->input('unlock');
}

if ($lock !== null) {
    Forumpost::lockUnlockTopic($lock, 1);
    header('/forum');
    die();
}

if ($unlock !== null) {
    Forumpost::lockUnlockTopic($unlock, 0);
    header('/forum');
    die();
}

$browsecount = Forumpost::getBrowseCount();

$offset = request()->has('offset') && ctype_digit(request()->input('offset')) ? request()->input('offset') : 0;

$results = Forumpost::getBrowseRange($offset);

$page->smarty->assign('pagertotalitems', $browsecount);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', config('nntmux.items_per_page'));
$page->smarty->assign('pagerquerybase', WWW_TOP.'/forum?offset=');
$page->smarty->assign('pagerquerysuffix', '#results');
$page->smarty->assign('privateprofiles', (int) Settings::settingValue('..privateprofiles') === 1);

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);
$page->smarty->assign('results', $results);

$page->meta_title = 'Forum';
$page->meta_keywords = 'forum,chat,posts';
$page->meta_description = 'Forum';

$page->content = $page->smarty->fetch('forum.tpl');
$page->pagerender();
