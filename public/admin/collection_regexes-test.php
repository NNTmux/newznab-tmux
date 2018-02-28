<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Regexes;

$page = new AdminPage();

$page->title = 'Collections Regex Test';

$group = trim($page->request->has('group') && ! empty($page->request->input('group')) ? $page->request->input('group') : '');
$regex = trim($page->request->has('regex') && ! empty($page->request->input('regex')) ? $page->request->input('regex') : '');
$limit = ($page->request->has('limit') && is_numeric($page->request->input('limit')) ? $page->request->input('limit') : 50);
$page->smarty->assign(['group' => $group, 'regex' => $regex, 'limit' => $limit]);

if ($group && $regex) {
    $page->smarty->assign('data', (new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'collection_regexes']))->testCollectionRegex($group, $regex, $limit));
}

$page->content = $page->smarty->fetch('collection_regexes-test.tpl');
$page->render();
