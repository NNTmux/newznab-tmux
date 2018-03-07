<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use App\Models\User;

if (request()->has('id')) {
    User::deleteUser(request()->input('id'));
}

if (request()->has('redir')) {
    header('Location: '.request()->input('redir'));
} else {
    $referrer = request()->server('HTTP_REFERER');
    header('Location: '.$referrer);
}
