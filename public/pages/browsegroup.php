<?php

use nntmux\Groups;

if (! User::isLoggedIn()) {
    $page->show403();
}

use App\Models\User;

$groups = new Groups(['Settings' => $page->settings]);

$grouplist = $groups->getRange(false, false, '', true);
$page->smarty->assign('results', $grouplist);

$page->meta_title = 'Browse Groups';
$page->meta_keywords = 'browse,groups,description,details';
$page->meta_description = 'Browse groups';

$page->content = $page->smarty->fetch('browsegroup.tpl');
$page->render();
