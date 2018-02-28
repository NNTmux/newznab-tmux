<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\MultigroupPoster;

$page = new AdminPage();

if ($page->request->has('id')) {
    MultigroupPoster::query()->where('id', '=', $page->request->input('id'))->delete();
}

if ($page->request->has('from')) {
    $referrer = $page->request->input('from');
} else {
    $referrer = $page->request->server('HTTP_REFERER');
}
header('Location: '.$referrer);
