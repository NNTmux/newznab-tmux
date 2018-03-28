<?php

use Blacklight\utility\Utility;
use Illuminate\Support\Facades\Auth;

if (Auth::check()) {
    redirect('/');
}

$error = $userName = $password = $confirmPassword = $email = $inviteCode = $inviteCodeQuery = '';
$showRegister = 1;

$page->smarty->assign(
    [
        'username'          => Utility::htmlfmt($userName),
        'password'          => Utility::htmlfmt($password),
        'confirmpassword'   => Utility::htmlfmt($confirmPassword),
        'email'             => Utility::htmlfmt($email),
        'invitecode'        => Utility::htmlfmt($inviteCode),
        'invite_code_query' => Utility::htmlfmt($inviteCodeQuery),
        'showregister'      => $showRegister,
        'error'             => $error,
    ]
);
$page->meta_title = 'Register';
$page->meta_keywords = 'register,signup,registration';
$page->meta_description = 'Register';

$page->content = $page->smarty->fetch('register.tpl');
$page->pagerender();
