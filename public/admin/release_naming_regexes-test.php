<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Regexes;

$page = new AdminPage();

$page->title = 'Release Naming Regex Test';

$group = trim($page->request->has('group') && ! empty($page->request->input('group')) ? $page->request->input('group') : '');
$regex = trim($page->request->has('regex') && ! empty($page->request->input('regex')) ? $page->request->input('regex') : '');
$showLimit = ($page->request->has('showlimit') && is_numeric($page->request->input('showlimit')) ? $page->request->input('showlimit') : 250);
$queryLimit = ($page->request->has('querylimit') && is_numeric($page->request->input('querylimit')) ? $page->request->input('querylimit') : 100000);
$page->smarty->assign(['group' => $group, 'regex' => $regex, 'showlimit' => $showLimit, 'querylimit' => $queryLimit]);

if ($group && $regex) {
    $page->smarty->assign('data', (new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'release_naming_regexes']))->testReleaseNamingRegex($group, $regex, $showLimit, $queryLimit));
}

$page->content = $page->smarty->fetch('release_naming_regexes-test.tpl');
$page->render();
