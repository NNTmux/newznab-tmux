<?php

use App\Models\Forumpost;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';


$page = new AdminPage();

if (isset($_GET['id'])) {
    Forumpost::deletePost($_GET['id']);
}

if (isset($_GET['from'])) {
    $referrer = $_GET['from'];
} else {
    $referrer = $_SERVER['HTTP_REFERER'];
}
header('Location: '.$referrer);
