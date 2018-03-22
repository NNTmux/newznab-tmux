<?php

use App\Models\User;
use App\Models\Settings;
use App\Mail\AccountDeleted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

if (! Auth::check()) {
    $page->show403();
}

$userId = request()->input('id');

if ($userId !== null && $page->userdata->role->id !== User::ROLE_ADMIN && (int) $userId === Auth::id()) {
    Mail::to(Settings::settingValue('site.main.email'))->send(new AccountDeleted($userId));
    Auth::logout();
    User::deleteUser($userId);
    redirect(WWW_TOP.'/');
} elseif ($page->userdata->role->id === User::ROLE_ADMIN) {
    redirect(WWW_TOP.'profile');
} else {
    $page->showBadBoy();
}
