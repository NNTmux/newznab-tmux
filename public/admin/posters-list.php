<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\http\AdminPage;
use App\Models\MultigroupPoster;

$page = new AdminPage();

$posters = MultigroupPoster::all('id', 'poster')->sortBy('poster');

$postersCheck = $posters->first();

$poster = request()->has('poster') && ! empty(request()->input('poster')) ? request()->input('poster') : '';

$page->smarty->assign(
    [
        'poster' => $poster,
        'posters' => $posters,
        'check' => $postersCheck,
    ]
);

$page->title = 'MultiGroup Posters List';
$page->content = $page->smarty->fetch('posters-list.tpl');
$page->render();
