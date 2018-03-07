<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use App\Models\Menu;

if (request()->has('id')) {
    Menu::deleteMenu(request()->input('id'));
}

$referrer = request()->server('HTTP_REFERER');
header('Location: '.$referrer);
