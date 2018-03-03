<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Group;

$page = new AdminPage();

$groupName = request()->input('groupname') ?? '';
$offset = request()->input('offset') ?? 0;

$page->smarty->assign(
    [
        'groupname' => $groupName,
        'pagertotalitems' => Group::getGroupsCount($groupName, -1),
        'pageroffset' => $offset,
        'pageritemsperpage' => config('nntmux.items_per_page',
        'pagerquerybase' => WWW_TOP.'/group-list.php?'.(($groupName !== '') ? "groupname=$groupName" : '').'&offset=',
        'pagerquerysuffix' => '',
        'grouplist' => Group::getGroupsRange($offset, config('nntmux.items_per_page', $groupName),
    ]
);
$page->smarty->assign('pager', $page->smarty->fetch('pager.tpl'));

$page->title = 'Group List';
$page->content = $page->smarty->fetch('group-list.tpl');
$page->render();
