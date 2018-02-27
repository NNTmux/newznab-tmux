<?php

use Blacklight\XXX;
use App\Models\User;
use App\Models\Category;

if (! User::isLoggedIn()) {
    $page->show403();
}

$movie = new XXX();

$moviecats = Category::getChildren(Category::XXX_ROOT);
$mtmp = [];
foreach ($moviecats as $mcat) {
    $mtmp[$mcat['id']] = $mcat;
}
$category = Category::XXX_ROOT;
if ($page->request->has('t') && array_key_exists($page->request->input('t'), $mtmp)) {
    $category = $page->request->input('t') + 0;
}
$catarray = [];
$catarray[] = $category;

$page->smarty->assign('catlist', $mtmp);
$page->smarty->assign('category', $category);

$offset = ($page->request->has('offset') && ctype_digit($page->request->input('offset'))) ? $page->request->input('offset') : 0;
$ordering = $movie->getXXXOrdering();
$orderby = $page->request->has('ob') && in_array($page->request->input('ob'), $ordering, false) ? $page->request->input('ob') : '';

$movies = [];
$results = $movie->getXXXRange($catarray, $offset, env('ITEMS_PER_COVER_PAGE', 20), $orderby, -1, $page->userdata['categoryexclusions']);
foreach ($results as $result) {
    $result['genre'] = makeFieldLinks($result, 'genre', 'xxx');
    $result['actors'] = makeFieldLinks($result, 'actors', 'xxx');
    $result['director'] = makeFieldLinks($result, 'director', 'xxx');
    $movies[] = $result;
}
$title = ($page->request->has('title') && ! empty($page->request->input('title'))) ? stripslashes($page->request->input('title')) : '';
$page->smarty->assign('title', stripslashes($title));

$actors = ($page->request->has('actors') && ! empty($page->request->input('actors'))) ? stripslashes($page->request->input('actors')) : '';
$page->smarty->assign('actors', $actors);

$director = ($page->request->has('director') && ! empty($page->request->input('director'))) ? stripslashes($page->request->input('director')) : '';
$page->smarty->assign('director', $director);

$genres = $movie->getAllGenres(true);
$genre = ($page->request->has('genre') && in_array($page->request->input('genre'), $genres, false)) ? $page->request->input('genre') : '';
$page->smarty->assign('genres', $genres);
$page->smarty->assign('genre', $genre);

$browseby_link = '&amp;title='.$title.'&amp;actors='.$actors.'&amp;director='.$director.'&amp;genre='.$genre;

$page->smarty->assign('pagertotalitems', $results[0]['_totalcount'] ?? 0);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', env('ITEMS_PER_COVER_PAGE', 20));
$page->smarty->assign('pagerquerybase', WWW_TOP.'/xxx?t='.$category.$browseby_link.'&amp;ob='.$orderby.'&amp;offset=');
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
    $page->smarty->assign('orderby'.$ordertype, WWW_TOP.'/xxx?t='.$category.$browseby_link.'&amp;ob='.$ordertype.'&amp;offset=0');
}

$page->smarty->assign('results', $movies);

$page->meta_title = 'Browse XXX';
$page->meta_keywords = 'browse,xxx,nzb,description,details';
$page->meta_description = 'Browse for XXX Movies';

if ($page->request->has('id')) {
    $page->content = $page->smarty->fetch('viewxxxfull.tpl');
} else {
    $page->content = $page->smarty->fetch('xxx.tpl');
}
$page->render();
