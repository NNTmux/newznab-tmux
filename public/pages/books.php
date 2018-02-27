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
if ($page->request->has('t') && array_key_exists($page->request->input('t'), $btmp)) {
    $category = $page->request->input('t') + 0;
}

$catarray = [];
$catarray[] = $category;

$page->smarty->assign('catlist', $btmp);
$page->smarty->assign('category', $category);

$offset = ($page->request->has('offset') && ctype_digit($page->request->input('offset'))) ? $page->request->input('offset') : 0;
$ordering = $book->getBookOrdering();
$orderby = $page->request->has('ob') && in_array($page->request->input('ob'), $ordering, false) ? $page->request->input('ob') : '';

$books = [];
$results = $book->getBookRange($catarray, $offset, env('ITEMS_PER_COVER_PAGE', 20), $orderby, $page->userdata['categoryexclusions']);

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

$author = ($page->request->has('author') && ! empty($page->request->input('author'))) ? stripslashes($page->request->input('author')) : '';
$page->smarty->assign('author', $author);

$title = ($page->request->has('title') && ! empty($page->request->input('title'))) ? stripslashes($page->request->input('title')) : '';
$page->smarty->assign('title', $title);

$browseby_link = '&amp;title='.$title.'&amp;author='.$author;

$page->smarty->assign('pagertotalitems', $results[0]['_totalcount'] ?? 0);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', env('ITEMS_PER_COVER_PAGE', 20));
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
