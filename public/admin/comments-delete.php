<?php

use App\Models\ReleaseComment;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';


$page = new AdminPage();

if (isset($_GET['id'])) {
    ReleaseComment::deleteComment($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];
header('Location: '.$referrer);
