<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\Releases;
use Blacklight\http\BasePage;

$page = new BasePage();
if (request()->has('id')) {
    $releases = new Releases(['Settings' => $page->pdo]);
    $releases->deleteMultiple(request()->input('id'));
}

$referrer = request()->server('HTTP_REFERER');

header('Location: '.$referrer);
