<?php


require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';
require_once NN_WWW.'pages/smartyTV.php';

if (request()->has('id')) {
    (new smartyTV(['Settings' => $page->pdo]))->delete(request()->input('id'));
}

$referrer = request()->server('HTTP_REFERER');
header('Location: '.$referrer);
