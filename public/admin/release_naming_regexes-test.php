<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Regexes;

$page = new AdminPage();

$page->title = 'Release Naming Regex Test';

$group = trim(request()->has('group') && ! empty(request()->input('group')) ? request()->input('group') : '');
$regex = trim(request()->has('regex') && ! empty(request()->input('regex')) ? request()->input('regex') : '');
$showLimit = (request()->has('showlimit') && is_numeric(request()->input('showlimit')) ? request()->input('showlimit') : 250);
$queryLimit = (request()->has('querylimit') && is_numeric(request()->input('querylimit')) ? request()->input('querylimit') : 100000);
$page->smarty->assign(['group' => $group, 'regex' => $regex, 'showlimit' => $showLimit, 'querylimit' => $queryLimit]);

if ($group && $regex) {
    $page->smarty->assign('data', (new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'release_naming_regexes']))->testReleaseNamingRegex($group, $regex, $showLimit, $queryLimit));
}

$page->content = $page->smarty->fetch('release_naming_regexes-test.tpl');
$page->render();
