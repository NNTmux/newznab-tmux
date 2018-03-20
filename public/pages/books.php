<?php

use App\Models\User;
use Blacklight\Books;
use App\Models\Category;

if (! User::isLoggedIn()) {
    $page->show403();
}

$book = new Books(['Settings' => $page->settings]);

$boocats = Category::getChildren(Category::BOOKS_ROOT);
$btmp = [];
foreach ($boocats as $bcat) {
    $btmp[$bcat['id']] = $bcat;
}
$category = Category::BOOKS_ROOT;
if (request()->has('t') && array_key_exists(request()->input('t'), $btmp)) {
    $category = request()->input('t') + 0;
}

$catarray = [];
$catarray[] = $category;

$page->smarty->assign('catlist', $btmp);
$page->smarty->assign('category', $category);

$offset = (request()->has('offset') && ctype_digit(request()->input('offset'))) ? request()->input('offset') : 0;
$ordering = $book->getBookOrdering();
$orderby = request()->has('ob') && in_array(request()->input('ob'), $ordering, false) ? request()->input('ob') : '';

$books = [];
$results = $book->getBookRange($catarray, $orderby, $page->userdata['categoryexclusions']);

$maxwords = 50;
foreach ($results as $result) {
    if (! empty($result['overview'])) {
        $words = explode(' ', $result['overview']);
        if (count($words) > $maxwords) {
            $newwords = array_slice($words, 0, $maxwords);
            $result['overview'] = implode(' ', $newwords).'...';
        }
    }
    $books[] = $result;
}

$author = (request()->has('author') && ! empty(request()->input('author'))) ? stripslashes(request()->input('author')) : '';
$page->smarty->assign('author', $author);

$title = (request()->has('title') && ! empty(request()->input('title'))) ? stripslashes(request()->input('title')) : '';
$page->smarty->assign('title', $title);

$browseby_link = '&amp;title='.$title.'&amp;author='.$author;

$page->smarty->assign('pagertotalitems', $results[0]['_totalcount'] ?? 0);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', config('nntmux.items_per_cover_page'));
$page->smarty->assign('pagerquerybase', WWW_TOP.'/books?t='.$category.$browseby_link.'&amp;ob='.$orderby.'&amp;offset=');
$page->smarty->assign('pagerquerysuffix', '#results');

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

if ((int) $category === -1) {
    $page->smarty->assign('catname', 'All');
} else {
    $cdata = Category::find($category);
    if ($cdata) {
        $page->smarty->assign('catname', $cdata->parent !== null ? $cdata->parent->title.' > '.$cdata->title : $cdata->title);
    } else {
        $page->show404();
    }
}

foreach ($ordering as $ordertype) {
    $page->smarty->assign('orderby'.$ordertype, WWW_TOP.'/books?t='.$category.$browseby_link.'&amp;ob='.$ordertype.'&amp;offset=0');
}

$page->smarty->assign('results', $books);

$page->meta_title = 'Browse Books';
$page->meta_keywords = 'browse,nzb,books,description,details';
$page->meta_description = 'Browse for Books';

$page->content = $page->smarty->fetch('books.tpl');
$page->render();
