<?php

use App\Models\User;
use Blacklight\Movie;
use App\Models\Category;
use Illuminate\Support\Carbon;

if (! User::isLoggedIn()) {
    $page->show403();
}

$movie = new Movie(['Settings' => $page->settings]);

$moviecats = Category::getChildren(Category::MOVIE_ROOT);
$mtmp = [];
foreach ($moviecats as $mcat) {
    $mtmp[$mcat['id']] = $mcat;
}

$category = request()->has('imdb') ? -1 : Category::MOVIE_ROOT;
if (request()->has('t') && array_key_exists(request()->input('t'), $mtmp)) {
    $category = request()->input('t') + 0;
}

$user = User::find(User::currentUserId());
$cpapi = $user['cp_api'];
$cpurl = $user['cp_url'];
$page->smarty->assign('cpapi', $cpapi);
$page->smarty->assign('cpurl', $cpurl);

$catarray = [];
if ($category != -1) {
    $catarray[] = $category;
}

$page->smarty->assign('catlist', $mtmp);
$page->smarty->assign('category', $category);

$offset = (request()->has('offset') && ctype_digit(request()->input('offset'))) ? request()->input('offset') : 0;
$ordering = $movie->getMovieOrdering();
$orderby = request()->has('ob') && in_array(request()->input('ob'), $ordering, false) ? request()->input('ob') : '';

$movies = [];
$results = $movie->getMovieRange($catarray, $offset, config('nntmux.items_per_cover_page', $orderby, -1, $page->userdata['categoryexclusions']);
foreach ($results as $result) {
    $result['genre'] = makeFieldLinks($result, 'genre', 'movies');
    $result['actors'] = makeFieldLinks($result, 'actors', 'movies');
    $result['director'] = makeFieldLinks($result, 'director', 'movies');
    $result['languages'] = explode(', ', $result['language']);

    $movies[] = $result;
}

$title = (request()->has('title') && ! empty(request()->input('title'))) ? stripslashes(request()->input('title')) : '';
$page->smarty->assign('title', $title);

$actors = (request()->has('actors') && ! empty(request()->input('actors'))) ? stripslashes(request()->input('actors')) : '';
$page->smarty->assign('actors', $actors);

$director = (request()->has('director') && ! empty(request()->input('director'))) ? stripslashes(request()->input('director')) : '';
$page->smarty->assign('director', $director);

$ratings = range(1, 9);
$rating = (request()->has('rating') && in_array(request()->input('rating'), $ratings, false)) ? request()->input('rating') : '';
$page->smarty->assign('ratings', $ratings);
$page->smarty->assign('rating', $rating);

$genres = $movie->getGenres();
$genre = (request()->has('genre') && in_array(request()->input('genre'), $genres, false)) ? request()->input('genre') : '';
$page->smarty->assign('genres', $genres);
$page->smarty->assign('genre', $genre);

$years = range(1903, Carbon::now()->addYear()->year);
rsort($years);
$year = (request()->has('year') && in_array(request()->input('year'), $years, false)) ? request()->input('year') : '';
$page->smarty->assign('years', $years);
$page->smarty->assign('year', $year);

$browseby_link = '&amp;title='.$title.'&amp;actors='.$actors.'&amp;director='.$director.'&amp;rating='.$rating.'&amp;genre='.$genre.'&amp;year='.$year;

$page->smarty->assign('pagertotalitems', $results[0]['_totalcount'] ?? 0);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', config('nntmux.items_per_cover_page');
$page->smarty->assign('pagerquerybase', WWW_TOP.'/movies?t='.$category.$browseby_link.'&amp;ob='.$orderby.'&amp;offset=');
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
    $page->smarty->assign('orderby'.$ordertype, WWW_TOP.'/movies?t='.$category.$browseby_link.'&ob='.$ordertype.'&offset=0');
}

$page->smarty->assign('results', $movies);

$page->meta_title = 'Browse Nzbs';
$page->meta_keywords = 'browse,nzb,description,details';
$page->meta_description = 'Browse for Nzbs';

if (request()->has('imdb')) {
    $page->content = $page->smarty->fetch('viewmoviefull.tpl');
} else {
    $page->content = $page->smarty->fetch('movies.tpl');
}
$page->render();
