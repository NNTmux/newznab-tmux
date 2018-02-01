<?php

use App\Models\User;

if (! User::isLoggedIn()) {
    $page->show403();
}

$userId = $_GET['id'];

if ($userId !== null && (int) $userId === User::currentUserId()) {
    User::logout();
    User::deleteUser($userId);
    header('Location: '.WWW_TOP.'/');
} else {
    $page->showBadBoy();
}
