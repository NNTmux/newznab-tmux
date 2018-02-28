<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Games;
use Blacklight\Genres;
use Illuminate\Support\Carbon;

$page = new AdminPage();
$games = new Games(['Settings' => $page->pdo]);
$gen = new Genres(['Settings' => $page->pdo]);
$id = 0;

// Set the current action.
$action = $page->request->input('action') ?? 'view';

if ($page->request->has('id')) {
    $id = $page->request->input('id');
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

            $page->request->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
            $page->request->merge(['releasedate' => (empty($page->request->input('releasedate')) || ! strtotime($page->request->input('releasedate'))) ? $game['releasedate'] : Carbon::parse($page->request->input('releasedate'))->timestamp]);

            $games->update($id, $page->request->input('title'), $page->request->input('asin'), $page->request->input('url'), $page->request->input('publisher'), $page->request->input('releasedate'), $page->request->input('esrb'), $page->request->input('cover'), $page->request->input('trailerurl'), $page->request->input('genre'));

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
