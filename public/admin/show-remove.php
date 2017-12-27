<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Release;

$page = new AdminPage();

$success = false;

if (isset($_GET['id'])) {
    $success = Release::removeVideoIdFromReleases($_GET['id']);
    $page->smarty->assign('videoid', $_GET['id']);
}

$page->smarty->assign('success', $success);

$page->title = 'Remove Video and Episode IDs from Releases';
$page->content = $page->smarty->fetch('show-remove.tpl');
$page->render();
