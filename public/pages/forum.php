<?php

use App\Models\User;
use App\Models\Settings;
use App\Models\Forumpost;

if (! User::isLoggedIn()) {
    $page->show403();
}

if (! empty($page->request->input('addMessage')) && ! empty($page->request->input('addSubject')) && $page->isPostBack()) {
    Forumpost::add(0, User::currentUserId(), $page->request->input('addSubject'), $page->request->input('addMessage'));
    header('Location:'.WWW_TOP.'/forum');
    die();
}

$lock = $unlock = null;

if (! empty($page->request->input('lock'))) {
    $lock = $page->request->input('lock');
}

if (! empty($page->request->input('unlock'))) {
    $unlock = $page->request->input('unlock');
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

$offset = $page->request->has('offset') && ctype_digit($page->request->input('offset')) ? $page->request->input('offset') : 0;

$results = Forumpost::getBrowseRange($offset, env('ITEMS_PER_PAGE', 50));

$page->smarty->assign('pagertotalitems', $browsecount);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', env('ITEMS_PER_PAGE', 50));
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
