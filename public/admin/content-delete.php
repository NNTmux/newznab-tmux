<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Contents;

$page = new AdminPage();

if ($page->request->has('id')) {
    $contents = new Contents();
    $contents->delete($page->request->input('id'));
}

$referrer = $page->request->server('HTTP_REFERER');
header('Location: '.$referrer);
