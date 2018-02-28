<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Group;

$page = new AdminPage();

$gname = '';
if (! empty($page->request->input('groupname'))) {
    $gname = $page->request->input('groupname');
}

$groupcount = Group::getGroupsCount($gname, 0);

$offset = $page->request->input('offset') ?? 0;
$groupname = ! empty($page->request->input('groupname')) ? $page->request->input('groupname') : '';

$page->smarty->assign('groupname', $groupname);
$page->smarty->assign('pagertotalitems', $groupcount);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', env('ITEMS_PER_PAGE', 50));
$page->smarty->assign('pagerquerysuffix', '#results');

$groupsearch = $gname !== '' ? 'groupname='.$gname.'&amp;' : '';
$page->smarty->assign('pagerquerybase', WWW_TOP.'/group-list-inactive.php?'.$groupsearch.'offset=');
$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$grouplist = Group::getGroupsRange($offset, env('ITEMS_PER_PAGE', 50), $gname, false);

$page->smarty->assign('grouplist', $grouplist);

$page->title = 'Group List';

$page->content = $page->smarty->fetch('group-list.tpl');
$page->render();
