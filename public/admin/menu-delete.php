<?php
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Menu;


$page = new AdminPage();

if (isset($_GET['id'])) {
    Menu::deleteMenu($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];
header('Location: '.$referrer);
