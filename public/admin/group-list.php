<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use nntmux\Groups;

$page = new AdminPage();
$groups = new Groups(['Settings' => $page->pdo]);

$groupName = $_REQUEST['groupname'] ?? '';
$offset = $_REQUEST['offset'] ?? 0;

$page->smarty->assign(
    [
        'groupname' => $groupName,
        'pagertotalitems' => $groups->getCount($groupName, -1),
        'pageroffset' => $offset,
        'pageritemsperpage' => ITEMS_PER_PAGE,
        'pagerquerybase' => WWW_TOP.'/group-list.php?'.(($groupName !== '') ? "groupname=$groupName" : '').'&offset=',
        'pagerquerysuffix' => '',
        'grouplist' => $groups->getRange($offset, ITEMS_PER_PAGE, $groupName, -1),
    ]
);
$page->smarty->assign('pager', $page->smarty->fetch('pager.tpl'));

$page->title = 'Group List';
$page->content = $page->smarty->fetch('group-list.tpl');
$page->render();
