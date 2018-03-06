<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Music;
use Blacklight\Genres;
use Blacklight\http\AdminPage;
use Illuminate\Support\Carbon;

$page = new AdminPage();
$music = new Music();
$gen = new Genres();
$id = 0;

// set the current action
$action = request()->input('action') ?? 'view';

if (request()->has('id')) {
    $id = request()->input('id');
    $mus = $music->getMusicInfo($id);

    if (! $mus) {
        $page->show404();
    }

    switch ($action) {
        case 'submit':
            $coverLoc = WWW_DIR.'covers/music/'.$id.'.jpg';

            if ($_FILES['cover']['size'] > 0) {
                $tmpName = $_FILES['cover']['tmp_name'];
                $file_info = getimagesize($tmpName);
                if (! empty($file_info)) {
                    move_uploaded_file($_FILES['cover']['tmp_name'], $coverLoc);
                }
            }

            request()->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
            request()->merge(['salesrank' => (empty(request()->input('salesrank')) || ! ctype_digit(request()->input('salesrank'))) ? 'null' : request()->input('salesrank')]);
            request()->merge(['releasedate' => (empty(request()->input('releasedate')) || ! strtotime(request()->input('releasedate'))) ? $mus['releasedate'] : Carbon::parse(request()->input('releasedate'))->timestamp]);

            $music->update($id, request()->input('title'), request()->input('asin'), request()->input('url'), request()->input('salesrank'), request()->input('artist'), request()->input('publisher'), request()->input('releasedate'), request()->input('year'), request()->input('tracks'), request()->input('cover'), request()->input('genre'));

            header('Location:'.WWW_TOP.'/music-list.php');
            die();
        break;
        case 'view':
        default:
            $page->title = 'Music Edit';
            $page->smarty->assign('music', $mus);
            $page->smarty->assign('genres', $gen->getGenres(Genres::MUSIC_TYPE));
        break;
    }
}

$page->content = $page->smarty->fetch('music-edit.tpl');
$page->render();
