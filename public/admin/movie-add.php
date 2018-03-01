<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Movie;

$page = new AdminPage();
$movie = new Movie(['Settings' => $page->pdo]);
$id = 0;

$page->title = 'Movie Add';

if (\request()->has('id') && ctype_digit(\request()->input('id')) && strlen(\request()->input('id')) === 7) {
    $id = \request()->input('id');

    $movCheck = $movie->getMovieInfo($id);
    if (! $movCheck || (\request()->has('update') && \request()->input('update') === 1)) {
        if ($movie->updateMovieInfo($id)) {
            header('Location:'.WWW_TOP.'/movie-list.php');
            die();
        }
    }
}

$page->content = $page->smarty->fetch('movie-add.tpl');
$page->render();
