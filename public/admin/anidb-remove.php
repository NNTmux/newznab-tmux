<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use App\Models\Release;
use Blacklight\http\BasePage;

$page = new BasePage();
$page->setAdminPrefs();

$success = false;

if (request()->has('id')) {
    $success = Release::removeAnidbIdFromReleases(request()->input('id'));
    $page->smarty->assign('anidbid', request()->input('id'));
}
$page->smarty->assign('success', $success);

$page->title = 'Remove anidbID from Releases';
$page->content = $page->smarty->fetch('anidb-remove.tpl');
$page->adminrender();
