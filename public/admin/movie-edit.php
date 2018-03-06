<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\http\AdminPage;
use Blacklight\Movie;

$page = new AdminPage();

$movie = new Movie();
$id = 0;

// set the current action
$action = request()->input('action') ?? 'view';

if (request()->has('id')) {
    $id = request()->input('id');
    $mov = $movie->getMovieInfo($id);

    if (! $mov) {
        $page->show404();
    }

    switch ($action) {
        case 'submit':
            $coverLoc = WWW_DIR.'covers/movies/'.$id.'-cover.jpg';
            $backdropLoc = WWW_DIR.'covers/movies/'.$id.'-backdrop.jpg';

            if ($_FILES['cover']['size'] > 0) {
                $tmpName = $_FILES['cover']['tmp_name'];
                $file_info = getimagesize($tmpName);
                if (! empty($file_info)) {
                    move_uploaded_file($_FILES['cover']['tmp_name'], $coverLoc);
                }
            }

            if ($_FILES['backdrop']['size'] > 0) {
                $tmpName = $_FILES['backdrop']['tmp_name'];
                $file_info = getimagesize($tmpName);
                if (! empty($file_info)) {
                    move_uploaded_file($_FILES['backdrop']['tmp_name'], $backdropLoc);
                }
            }

            request()->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
            request()->merge(['backdrop' => file_exists($backdropLoc) ? 1 : 0]);

            $movie->update([
                'actors'   => request()->input('actors'),
                'backdrop' => request()->input('backdrop'),
                'cover'    => request()->input('cover'),
                'director' => request()->input('director'),
                'genre'    => request()->input('genre'),
                'imdbid'   => $id,
                'language' => request()->input('language'),
                'plot'     => request()->input('plot'),
                'rating'   => request()->input('rating'),
                'tagline'  => request()->input('tagline'),
                'title'    => request()->input('title'),
                'year'     => request()->input('year'),
            ]);

            header('Location:'.WWW_TOP.'/movie-list.php');
            die();
        break;
        case 'view':
        default:
            $page->title = 'Movie Edit';
            $page->smarty->assign('movie', $mov);
        break;
    }
}

$page->content = $page->smarty->fetch('movie-edit.tpl');
$page->render();
