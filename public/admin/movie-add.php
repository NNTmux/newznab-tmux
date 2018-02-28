<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Movie;

$page = new AdminPage();
$movie = new Movie(['Settings' => $page->pdo]);
$id = 0;

$page->title = 'Movie Add';

if ($page->request->has('id') && ctype_digit($page->request->input('id')) && strlen($page->request->input('id')) === 7) {
    $id = $page->request->input('id');

    $movCheck = $movie->getMovieInfo($id);
    if (! $movCheck || ($page->request->has('update') && $page->request->input('update') === 1)) {
        if ($movie->updateMovieInfo($id)) {
            header('Location:'.WWW_TOP.'/movie-list.php');
            die();
        }
    }
}

$page->content = $page->smarty->fetch('movie-add.tpl');
$page->render();
