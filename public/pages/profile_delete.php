<?php

use App\Models\User;

if (! User::isLoggedIn()) {
    $page->show403();
}

$userId = $_GET['id'];

if ($userId !== null && $page->userdata->role->id !== User::ROLE_ADMIN && (int) $userId === User::currentUserId()) {
    User::logout();
    User::deleteUser($userId);
    header('Location: '.WWW_TOP.'/');
} elseif ($page->userdata->role->id === User::ROLE_ADMIN) {
    header('Location: '.WWW_TOP.'profile');
} else {
    $page->showBadBoy();
}
