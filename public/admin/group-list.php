<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Group;

$page = new AdminPage();

$groupName = $_REQUEST['groupname'] ?? '';
$offset = $_REQUEST['offset'] ?? 0;

$page->smarty->assign(
    [
        'groupname' => $groupName,
        'pagertotalitems' => Group::getGroupsCount($groupName, -1),
        'pageroffset' => $offset,
        'pageritemsperpage' => ITEMS_PER_PAGE,
        'pagerquerybase' => WWW_TOP.'/group-list.php?'.(($groupName !== '') ? "groupname=$groupName" : '').'&offset=',
        'pagerquerysuffix' => '',
        'grouplist' => Group::getGroupsRange($offset, ITEMS_PER_PAGE, $groupName),
    ]
);
$page->smarty->assign('pager', $page->smarty->fetch('pager.tpl'));

$page->title = 'Group List';
$page->content = $page->smarty->fetch('group-list.tpl');
$page->render();
