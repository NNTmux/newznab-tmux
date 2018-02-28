<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';
require_once NN_WWW.'pages/smartyTV.php';

$page = new AdminPage();

if ($page->request->has('id')) {
    (new smartyTV(['Settings' => $page->pdo]))->delete($page->request->input('id'));
}

$referrer = $page->request->server('HTTP_REFERER');
header('Location: '.$referrer);
