<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Releases;

$page = new AdminPage();

if (isset($_GET['id'])) {
    $releases = new Releases(['Settings' => $page->pdo]);
    $releases->deleteMultiple($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];

header('Location: '.$referrer);
