<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\AniDB;

$page = new AdminPage();

if ($page->request->has('id')) {
    $AniDB = new AniDB();
    $AniDB->deleteTitle($page->request->input('id'));
}

$referrer = $page->request->server('HTTP_REFERER');
header('Location: '.$referrer);
