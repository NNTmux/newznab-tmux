<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Releases;

$page = new AdminPage();

if ($page->request->has('id')) {
    $releases = new Releases(['Settings' => $page->pdo]);
    $releases->deleteMultiple($page->request->input('id'));
}

$referrer = $page->request->server('HTTP_REFERER');

header('Location: '.$referrer);
