<?php

use App\Models\User;
use App\Models\Settings;
use App\Mail\AccountDeleted;
use Illuminate\Support\Facades\Mail;

if (! User::isLoggedIn()) {
    $page->show403();
}

$userId = request()->input('id');

if ($userId !== null && $page->userdata->role->id !== User::ROLE_ADMIN && (int) $userId === User::currentUserId()) {
    Mail::to(Settings::settingValue('site.main.email'))->send(new AccountDeleted($userId));
    User::logout();
    User::deleteUser($userId);
    header('Location: '.WWW_TOP.'/');
} elseif ($page->userdata->role->id === User::ROLE_ADMIN) {
    header('Location: '.WWW_TOP.'profile');
} else {
    $page->showBadBoy();
}
