<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';
require_once NN_WWW.'pages/smartyTV.php';

$page = new AdminPage();

if (\request()->has('id')) {
    (new smartyTV(['Settings' => $page->pdo]))->delete(\request()->input('id'));
}

$referrer = \request()->server('HTTP_REFERER');
header('Location: '.$referrer);
