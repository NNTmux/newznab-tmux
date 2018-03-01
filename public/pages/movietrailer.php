<?php

use App\Models\User;
use Blacklight\Movie;

$movie = new Movie;

if (! User::isLoggedIn()) {
    $page->show403();
}

if (request()->has('id') && ctype_digit(request()->input('id'))) {
    $mov = $movie->getMovieInfo(request()->input('id'));

    if (! $mov) {
        $page->show404();
    }

    $page->smarty->assign('movie', $mov);

    $page->title = 'Info for '.$mov['title'];
    $page->meta_title = '';
    $page->meta_keywords = '';
    $page->meta_description = '';
    $page->smarty->registerPlugin('modifier', 'ss', 'stripslashes');

    $modal = false;
    if (request()->has('modal')) {
        $modal = true;
        $page->smarty->assign('modal', true);
    }

    $page->content = $page->smarty->fetch('viewmovietrailer.tpl');

    if ($modal) {
        echo $page->content;
    } else {
        $page->render();
    }
}
