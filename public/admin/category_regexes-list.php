<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Regexes;

$page = new AdminPage();
$regexes = new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'category_regexes']);

$page->title = 'Category Regex List';

$group = $page->request->has('group') && ! empty($page->request->input('group')) ? $page->request->input('group') : '';
$offset = ($page->request->input('offset') ?? 0);
$regex = $regexes->getRegex($group, env('ITEMS_PER_PAGE', 50), $offset);

$page->smarty->assign(
    [
        'group'                => $group,
        'pagertotalitems'   => $regexes->getCount($group),
        'pagerquerysuffix'  => '',
        'pageroffset'       => $offset,
        'pageritemsperpage' => env('ITEMS_PER_PAGE', 50),
        'regex'             => $regex,
        'pagerquerybase'    => WWW_TOP.'/category_regexes-list.php?'.$group.'offset=',
    ]
);

$page->smarty->assign('pager', $page->smarty->fetch('pager.tpl'));

$page->content = $page->smarty->fetch('category_regexes-list.tpl');
$page->render();
