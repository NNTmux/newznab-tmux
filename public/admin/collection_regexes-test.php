<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\Regexes;
use Blacklight\http\AdminPage;

$page = new AdminPage();
$page->title = 'Collections Regex Test';

$group = trim(request()->has('group') && ! empty(request()->input('group')) ? request()->input('group') : '');
$regex = trim(request()->has('regex') && ! empty(request()->input('regex')) ? request()->input('regex') : '');
$limit = (request()->has('limit') && is_numeric(request()->input('limit')) ? request()->input('limit') : 50);
$page->smarty->assign(['group' => $group, 'regex' => $regex, 'limit' => $limit]);

if ($group && $regex) {
    $page->smarty->assign('data', (new Regexes(['Settings' => $page->pdo, 'Table_Name' => 'collection_regexes']))->testCollectionRegex($group, $regex, $limit));
}

$page->content = $page->smarty->fetch('collection_regexes-test.tpl');
$page->render();
