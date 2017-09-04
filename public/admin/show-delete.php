<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';
require_once NN_WWW.'pages/smartyTV.php';

$page = new AdminPage();

if (isset($_GET['id'])) {
    (new smartyTV(['Settings' => $page->pdo]))->delete($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];
header('Location: '.$referrer);
