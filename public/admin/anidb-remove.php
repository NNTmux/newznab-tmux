<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Release;

$page = new AdminPage();

$success = false;

if (isset($_GET['id'])) {
    $success = Release::removeAnidbIdFromReleases($_GET['id']);
    $page->smarty->assign('anidbid', $_GET['id']);
}
$page->smarty->assign('success', $success);

$page->title = 'Remove anidbID from Releases';
$page->content = $page->smarty->fetch('anidb-remove.tpl');
$page->render();
