<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\Regexes;
use Blacklight\http\BasePage;

$page = new BasePage();
$page->setAdminPrefs();
$regexes = new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'category_regexes']);

$page->title = 'Category Regex List';

$group = request()->has('group') && ! empty(request()->input('group')) ? request()->input('group') : '';
$offset = (request()->input('offset') ?? 0);
$regex = $regexes->getRegex($group, config('nntmux.items_per_page'), $offset);

$page->smarty->assign(
    [
        'group'                => $group,
        'pagertotalitems'   => $regexes->getCount($group),
        'pagerquerysuffix'  => '',
        'pageroffset'       => $offset,
        'pageritemsperpage' => config('nntmux.items_per_page'),
        'regex'             => $regex,
        'pagerquerybase'    => WWW_TOP.'/category_regexes-list.php?'.$group.'offset=',
    ]
);

$page->smarty->assign('pager', $page->smarty->fetch('pager.tpl'));

$page->content = $page->smarty->fetch('category_regexes-list.tpl');
$page->adminrender();
