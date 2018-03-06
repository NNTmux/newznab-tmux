<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\UserRole;

if (request()->has('id')) {
    UserRole::deleteRole(request()->input('id'));
}

$referrer = request()->server('HTTP_REFERER');
header('Location: '.$referrer);
