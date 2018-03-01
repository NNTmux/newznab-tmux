<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Release;

$page = new AdminPage();

$success = false;

if (\request()->has('id')) {
    $success = Release::removeAnidbIdFromReleases(\request()->input('id'));
    $page->smarty->assign('anidbid', \request()->input('id'));
}
$page->smarty->assign('success', $success);

$page->title = 'Remove anidbID from Releases';
$page->content = $page->smarty->fetch('anidb-remove.tpl');
$page->render();
