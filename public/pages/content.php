<?php

use App\Models\User;
use Blacklight\Contents;

$contents = new Contents();

$role = 0;
if (! empty($page->userdata) && User::isLoggedIn()) {
    $role = $page->userdata['role'];
}

/* The role column in the content table values are :
 * 0 = everyone
 * 1 = logged in users
 * 2 = admins
 *
 * The user role values are:
 * 0 = guest
 * 1 = user
 * 2 = admin
 * 3 = disabled
 * 4 = moderator
 *
 * Admins and mods should be the only ones to see admin content.
 */
$page->smarty->assign('admin', (($role === 2 || $role === 4) ? 'true' : 'false'));

$contentId = 0;
if (! empty(request()->input('id'))) {
    $contentId = request()->input('id');
}

$request = false;
if (! empty(request()->input('page'))) {
    $request = request()->input('page');
}

if ($contentId === 0 && $request === 'content') {
    $content = $contents->getAllButFront();
    $page->smarty->assign('front', false);
    $page->meta_title = 'Contents page';
    $page->meta_keywords = 'contents';
    $page->meta_description = 'This is the contents page.';
} elseif ($contentId !== 0 && $request !== false) {
    $content = [$contents->getByID($contentId, $role)];
    $page->smarty->assign('front', false);
    $page->meta_title = 'Contents page';
    $page->meta_keywords = 'contents';
    $page->meta_description = 'This is the contents page.';
} else {
    $content = $contents->getFrontPage();
    $index = $contents->getIndex();
    $page->smarty->assign('front', true);
    $page->meta_title = $index->title;
    $page->meta_keywords = $index->metakeywords;
    $page->meta_description = $index->metadescription;
}

if (empty($content)) {
    $page->show404();
}

$page->smarty->assign('content', $content);

$page->content = $page->smarty->fetch('content.tpl');
$page->render();
