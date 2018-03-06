<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Games;
use Blacklight\Genres;
use Blacklight\http\AdminPage;
use Illuminate\Support\Carbon;

$page = new AdminPage();
$games = new Games(['Settings' => $page->pdo]);
$gen = new Genres(['Settings' => $page->pdo]);
$id = 0;

// Set the current action.
$action = request()->input('action') ?? 'view';

if (request()->has('id')) {
    $id = request()->input('id');
    $game = $games->getGamesInfoById($id);

    if (! $game) {
        $page->show404();
    }

    switch ($action) {
        case 'submit':
            $coverLoc = NN_COVERS.'games/'.$id.'.jpg';

            if ($_FILES['cover']['size'] > 0) {
                $tmpName = $_FILES['cover']['tmp_name'];
                $file_info = getimagesize($tmpName);
                if (! empty($file_info)) {
                    move_uploaded_file($_FILES['cover']['tmp_name'], $coverLoc);
                }
            }

            request()->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
            request()->merge(['releasedate' => (empty(request()->input('releasedate')) || ! strtotime(request()->input('releasedate'))) ? $game['releasedate'] : Carbon::parse(request()->input('releasedate'))->timestamp]);

            $games->update($id, request()->input('title'), request()->input('asin'), request()->input('url'), request()->input('publisher'), request()->input('releasedate'), request()->input('esrb'), request()->input('cover'), request()->input('trailerurl'), request()->input('genre'));

            header('Location:'.WWW_TOP.'/game-list.php');
            die();
        break;

        case 'view':
        default:
            $page->title = 'Game Edit';
            $page->smarty->assign('game', $game);
            $page->smarty->assign('genres', $gen->getGenres(Genres::GAME_TYPE));
        break;
    }
}

$page->content = $page->smarty->fetch('game-edit.tpl');
$page->render();
