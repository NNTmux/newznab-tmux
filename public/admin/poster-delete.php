<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\MultigroupPoster;

$page = new AdminPage();

if (\request()->has('id')) {
    MultigroupPoster::query()->where('id', '=', \request()->input('id'))->delete();
}

if (\request()->has('from')) {
    $referrer = \request()->input('from');
} else {
    $referrer = \request()->server('HTTP_REFERER');
}
header('Location: '.$referrer);
