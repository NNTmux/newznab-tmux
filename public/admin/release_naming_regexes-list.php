<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Regexes;

$page = new AdminPage();
$regexes = new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'release_naming_regexes']);

$page->title = 'Release Naming Regex List';

$group = '';
if (isset($_REQUEST['group']) && ! empty($_REQUEST['group'])) {
    $group = $_REQUEST['group'];
}

$offset = $_REQUEST['offset'] ?? 0;
$regex = $regexes->getRegex($group, env('ITEMS_PER_PAGE', 50), $offset);
$page->smarty->assign('regex', $regex);

$count = $regexes->getCount($group);
$page->smarty->assign('pagertotalitems', $count);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', env('ITEMS_PER_PAGE', 50));
$page->smarty->assign('pagerquerysuffix', '');

$page->smarty->assign('pagerquerybase', WWW_TOP.'/release_naming_regexes-list.php?'.$group.'offset=');
$page->smarty->assign('pager', $page->smarty->fetch('pager.tpl'));

$page->content = $page->smarty->fetch('release_naming_regexes-list.tpl');
$page->render();
