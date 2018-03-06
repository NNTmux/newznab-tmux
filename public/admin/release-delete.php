<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Releases;
use Blacklight\http\AdminPage;

$page = new AdminPage();
if (request()->has('id')) {
    $releases = new Releases(['Settings' => $page->pdo]);
    $releases->deleteMultiple(request()->input('id'));
}

$referrer = request()->server('HTTP_REFERER');

header('Location: '.$referrer);
