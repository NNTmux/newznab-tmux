<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use nntmux\Releases;

$page = new AdminPage();
$releases = new Releases(['Settings' => $page->pdo]);

$success = false;

if (isset($_GET['id'])) {
    $success = $releases->removeAnidbIdFromReleases($_GET['id']);
    $page->smarty->assign('anidbid', $_GET['id']);
}
$page->smarty->assign('success', $success);

$page->title = 'Remove anidbID from Releases';
$page->content = $page->smarty->fetch('anidb-remove.tpl');
$page->render();
