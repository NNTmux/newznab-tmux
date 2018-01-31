<?php

use App\Models\User;

if (! User::isLoggedIn()) {
    $page->show403();
}

$userId = $_GET['id'];

if ($userId !== null) {
    User::logout();
    User::deleteUser($userId);
    header('Location: '.WWW_TOP.'/');
}
