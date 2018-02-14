<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\AniDB;

$page = new AdminPage();

if (isset($_GET['id'])) {
    $AniDB = new AniDB();
    $AniDB->deleteTitle($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];
header('Location: '.$referrer);
