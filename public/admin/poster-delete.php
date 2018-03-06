<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use App\Models\MultigroupPoster;

if (request()->has('id')) {
    MultigroupPoster::query()->where('id', '=', request()->input('id'))->delete();
}

if (request()->has('from')) {
    $referrer = request()->input('from');
} else {
    $referrer = request()->server('HTTP_REFERER');
}
header('Location: '.$referrer);
