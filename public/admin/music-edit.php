<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Music;
use Blacklight\Genres;
use Illuminate\Support\Carbon;

$page = new AdminPage();
$music = new Music();
$gen = new Genres();
$id = 0;

// set the current action
$action = $page->request->input('action') ?? 'view';

if ($page->request->has('id')) {
    $id = $page->request->input('id');
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

			$page->request->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
			$page->request->merge(['salesrank' => (empty($page->request->input('salesrank')) || ! ctype_digit($page->request->input('salesrank'))) ? 'null' : $page->request->input('salesrank')]);
			$page->request->merge(['releasedate' => (empty($page->request->input('releasedate')) || ! strtotime($page->request->input('releasedate'))) ? $mus['releasedate'] : Carbon::parse($page->request->input('releasedate'))->timestamp]);

			$music->update($id, $page->request->input('title'), $page->request->input('asin'), $page->request->input('url'), $page->request->input('salesrank'), $page->request->input('artist'), $page->request->input('publisher'), $page->request->input('releasedate'), $page->request->input('year'), $page->request->input('tracks'), $page->request->input('cover'), $page->request->input('genre'));

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
