<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Release;

$page = new AdminPage();

$success = false;

if (request()->has('id')) {
    $success = Release::removeVideoIdFromReleases(request()->input('id'));
    $page->smarty->assign('videoid', request()->input('id'));
}

$page->smarty->assign('success', $success);

$page->title = 'Remove Video and Episode IDs from Releases';
$page->content = $page->smarty->fetch('show-remove.tpl');
$page->render();
