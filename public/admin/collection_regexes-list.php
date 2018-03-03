<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Regexes;

$page = new AdminPage();
$regexes = new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'collection_regexes']);

$page->title = 'Collections Regex List';

$group = (request()->has('group') && ! empty(request()->input('group')) ? request()->input('group') : '');
$offset = request()->input('offset') ?? 0;
$regex = $regexes->getRegex($group, config('nntmux.items_per_page'), $offset);
$page->smarty->assign(
    [
        'group'             => $group,
        'regex'             => $regex,
        'pagertotalitems'   => $regexes->getCount($group),
        'pageroffset'       => $offset,
        'pageritemsperpage' => config('nntmux.items_per_page'),
        'pagerquerybase'    => WWW_TOP.'/collection_regexes-list.php?'.$group.'offset=',
        'pagerquerysuffix'  => '',
    ]
);

$page->smarty->assign('pager', $page->smarty->fetch('pager.tpl'));

$page->content = $page->smarty->fetch('collection_regexes-list.tpl');
$page->render();
