<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Books;
use Blacklight\Genres;
use Illuminate\Support\Carbon;

$page = new AdminPage();
$book = new Books();
$gen = new Genres();
$id = 0;

// set the current action
$action = $page->request->input('action') ?? 'view';

if ($page->request->has('id')) {
    $id = $page->request->input('id');
    $b = $book->getBookInfo($id);

    if (! $b) {
        $page->show404();
    }

    switch ($action) {
	    case 'submit':
	    	$coverLoc = WWW_DIR.'covers/book/'.$id.'.jpg';

			if ($_FILES['cover']['size'] > 0) {
			    $tmpName = $_FILES['cover']['tmp_name'];
			    $file_info = getimagesize($tmpName);
			    if (! empty($file_info)) {
			        move_uploaded_file($_FILES['cover']['tmp_name'], $coverLoc);
			    }
			}

			$page->request->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
			$page->request->merge(['publishdate' => (empty($page->request->input('publishdate')) || ! strtotime($page->request->input('publishdate'))) ? $con['publishdate'] : Carbon::parse($page->request->input('publishdate'))->timestamp]);
			$book->update($id, $page->request->input('title'), $page->request->input('asin'), $page->request->input('url'), $page->request->input('author'), $page->request->input('publisher'), $page->request->input('publishdate'), $page->request->input('cover'));

			header('Location:'.WWW_TOP.'/book-list.php');
	        die();
	    break;
	    case 'view':
	    default:
			$page->title = 'Book Edit';
			$page->smarty->assign('book', $b);
		break;
	}
}

$page->content = $page->smarty->fetch('book-edit.tpl');
$page->render();
