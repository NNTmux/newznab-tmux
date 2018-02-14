<?php

use App\Models\User;
use Blacklight\Movie;

if (! User::isLoggedIn()) {
    $page->show403();
}

if (isset($_GET['modal'], $_GET['id']) && ctype_digit($_GET['id'])) {
    $movie = new Movie(['Settings' => $page->settings]);
    $mov = $movie->getMovieInfo($_GET['id']);

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

    if (isset($_GET['modal'])) {
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
