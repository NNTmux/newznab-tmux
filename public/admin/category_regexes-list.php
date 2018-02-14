<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Regexes;

$page = new AdminPage();
$regexes = new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'category_regexes']);

$page->title = 'Category Regex List';

$group = isset($_REQUEST['group']) && ! empty($_REQUEST['group']) ? $_REQUEST['group'] : '';
$offset = ($_REQUEST['offset'] ?? 0);
$regex = $regexes->getRegex($group, ITEMS_PER_PAGE, $offset);

$page->smarty->assign(
    [
        'group'                => $group,
        'pagertotalitems'   => $regexes->getCount($group),
        'pagerquerysuffix'  => '',
        'pageroffset'       => $offset,
        'pageritemsperpage' => ITEMS_PER_PAGE,
        'regex'             => $regex,
        'pagerquerybase'    => WWW_TOP.'/category_regexes-list.php?'.$group.'offset=',
    ]
);

$page->smarty->assign('pager', $page->smarty->fetch('pager.tpl'));

$page->content = $page->smarty->fetch('category_regexes-list.tpl');
$page->render();
