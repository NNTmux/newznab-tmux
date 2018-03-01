<?php

use App\Models\User;
use App\Models\Settings;
use App\Models\Forumpost;

if (! User::isLoggedIn()) {
    $page->show403();
}

$id = \request()->input('id') + 0;

if (! empty(\request()->input('addMessage')) && $page->isPostBack()) {
    Forumpost::add($id, User::currentUserId(), '', \request()->input('addMessage'));
    header('Location:'.WWW_TOP.'/forumpost/'.$id.'#last');
    die();
}

$results = Forumpost::getPosts($id);
if (count($results) === 0) {
    header('Location:'.WWW_TOP.'/forum');
    die();
}

$page->meta_title = 'Forum Post';
$page->meta_keywords = 'view,forum,post,thread';
$page->meta_description = 'View forum post';

$page->smarty->assign('results', $results);
$page->smarty->assign('privateprofiles', (int) Settings::settingValue('..privateprofiles') === 1);

$page->content = $page->smarty->fetch('forumpost.tpl');
$page->render();
