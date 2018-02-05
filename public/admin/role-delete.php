<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\UserRole;

$page = new AdminPage();

if (isset($_GET['id'])) {
    UserRole::deleteRole($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];
header('Location: '.$referrer);
