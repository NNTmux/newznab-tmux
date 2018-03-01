<?php

use Blacklight\XXX;
use App\Models\User;

if (! User::isLoggedIn()) {
    $page->show403();
}

if (request()->has('modal') && request()->has('id') && ctype_digit(request()->input('id'))) {
    $movie = new XXX(['Settings' => $page->settings]);
    $mov = $movie->getXXXInfo(request()->input('id'));

    if (! $mov) {
        $page->show404();
    }

    $mov['actors'] = makeFieldLinks($mov, 'actors', 'xxx');
    $mov['genre'] = makeFieldLinks($mov, 'genre', 'xxx');
    $mov['director'] = makeFieldLinks($mov, 'director', 'xxx');

    $page->smarty->assign(['movie' => $mov, 'modal' => true]);

    $page->title = 'Info for '.$mov['title'];
    $page->meta_title = '';
    $page->meta_keywords = '';
    $page->meta_description = '';
    $page->smarty->registerPlugin('modifier', 'ss', 'stripslashes');

    if (request()->has('modal')) {
        $page->content = $page->smarty->fetch('viewxxx.tpl');
        $page->smarty->assign('modal', true);
        echo $page->content;
    } else {
        $page->content = $page->smarty->fetch('viewxxxfull.tpl');
        $page->render();
    }
} else {
    $page->render();
}
