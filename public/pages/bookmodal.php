<?php

use App\Models\User;
use Blacklight\Books;

$b = new Books;

if (! User::isLoggedIn()) {
    $page->show403();
}

if (\request()->has('id') && ctype_digit(\request()->input('id'))) {
    $book = $b->getBookInfo(\request()->input('id'));
    if (! $book) {
        $page->show404();
    }

    $page->smarty->assign('book', $book);

    $page->title = 'Info for '.$book['title'];
    $page->meta_title = '';
    $page->meta_keywords = '';
    $page->meta_description = '';
    $page->smarty->registerPlugin('modifier', 'ss', 'stripslashes');

    $modal = false;
    if (\request()->has('modal')) {
        $modal = true;
        $page->smarty->assign('modal', true);
    }

    $page->content = $page->smarty->fetch('viewbook.tpl');

    if ($modal) {
        echo $page->content;
    } else {
        $page->render();
    }
}
