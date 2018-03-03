<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Regexes;

$page = new AdminPage();
$regexes = new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'release_naming_regexes']);

$page->title = 'Release Naming Regex List';

$group = '';
if (request()->has('group') && ! empty(request()->input('group'))) {
    $group = request()->input('group');
}

$offset = request()->input('offset') ?? 0;
$regex = $regexes->getRegex($group, config('nntmux.items_per_page', $offset);
$page->smarty->assign('regex', $regex);

$count = $regexes->getCount($group);
$page->smarty->assign('pagertotalitems', $count);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', config('nntmux.items_per_page');
$page->smarty->assign('pagerquerysuffix', '');

$page->smarty->assign('pagerquerybase', WWW_TOP.'/release_naming_regexes-list.php?'.$group.'offset=');
$page->smarty->assign('pager', $page->smarty->fetch('pager.tpl'));

$page->content = $page->smarty->fetch('release_naming_regexes-list.tpl');
$page->render();
