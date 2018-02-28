<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Release;

$page = new AdminPage();

$success = false;

if ($page->request->has('id')) {
    $success = Release::removeVideoIdFromReleases($page->request->input('id'));
    $page->smarty->assign('videoid', $page->request->input('id'));
}

$page->smarty->assign('success', $success);

$page->title = 'Remove Video and Episode IDs from Releases';
$page->content = $page->smarty->fetch('show-remove.tpl');
$page->render();
