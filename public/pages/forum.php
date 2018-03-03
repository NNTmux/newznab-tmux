<?php

use App\Models\User;
use App\Models\Settings;
use App\Models\Forumpost;

if (! User::isLoggedIn()) {
    $page->show403();
}

if (! empty(request()->input('addMessage')) && ! empty(request()->input('addSubject')) && $page->isPostBack()) {
    Forumpost::add(0, User::currentUserId(), request()->input('addSubject'), request()->input('addMessage'));
    header('Location:'.WWW_TOP.'/forum');
    die();
}

$lock = $unlock = null;

if (! empty(request()->input('lock'))) {
    $lock = request()->input('lock');
}

if (! empty(request()->input('unlock'))) {
    $unlock = request()->input('unlock');
}

if ($lock !== null) {
    Forumpost::lockUnlockTopic($lock, 1);
    header('Location:'.WWW_TOP.'/forum');
    die();
}

if ($unlock !== null) {
    Forumpost::lockUnlockTopic($unlock, 0);
    header('Location:'.WWW_TOP.'/forum');
    die();
}

$browsecount = Forumpost::getBrowseCount();

$offset = request()->has('offset') && ctype_digit(request()->input('offset')) ? request()->input('offset') : 0;

$results = Forumpost::getBrowseRange($offset, config('nntmux.items_per_page'));

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
$page->render();
