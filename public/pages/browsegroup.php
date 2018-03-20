<?php

use App\Models\User;
use App\Models\Group;

$grouplist = Group::getGroupsRange(false, false, '', true);
$page->smarty->assign('results', $grouplist);

$page->meta_title = 'Browse Groups';
$page->meta_keywords = 'browse,groups,description,details';
$page->meta_description = 'Browse groups';

$page->content = $page->smarty->fetch('browsegroup.tpl');
$page->render();
