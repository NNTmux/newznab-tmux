<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\Books;
use Blacklight\Genres;
use Blacklight\http\AdminPage;
use Illuminate\Support\Carbon;

$page = new AdminPage();
$book = new Books();
$gen = new Genres();
$id = 0;

// set the current action
$action = request()->input('action') ?? 'view';

if (request()->has('id')) {
    $id = request()->input('id');
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

            request()->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
            request()->merge(['publishdate' => (empty(request()->input('publishdate')) || ! strtotime(request()->input('publishdate'))) ? $con['publishdate'] : Carbon::parse(request()->input('publishdate'))->timestamp]);
            $book->update($id, request()->input('title'), request()->input('asin'), request()->input('url'), request()->input('author'), request()->input('publisher'), request()->input('publishdate'), request()->input('cover'));

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
