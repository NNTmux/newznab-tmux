<?php

use Blacklight\Movie;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}


if (request()->has('modal') && request()->has('id') && ctype_digit(request()->input('id'))) {
    $movie = new Movie(['Settings' => $page->settings]);
    $mov = $movie->getMovieInfo(request()->input('id'));

    if (! $mov) {
        $page->show404();
    }

    $mov['actors'] = makeFieldLinks($mov, 'actors', 'movies');
    $mov['genre'] = makeFieldLinks($mov, 'genre', 'movies');
    $mov['director'] = makeFieldLinks($mov, 'director', 'movies');

    $page->smarty->assign(['movie' => $mov, 'modal' => true]);

    $page->title = 'Info for '.$mov['title'];
    $page->meta_title = '';
    $page->meta_keywords = '';
    $page->meta_description = '';
    $page->smarty->registerPlugin('modifier', 'ss', 'stripslashes');

    if (request()->has('modal')) {
        $page->content = $page->smarty->fetch('viewmovie.tpl');
        $page->smarty->assign('modal', true);
        echo $page->content;
    } else {
        $page->content = $page->smarty->fetch('viewmoviefull.tpl');
        $page->render();
    }
} else {
    $page->render();
}
