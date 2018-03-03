<?php

use App\Models\User;
use Blacklight\Music;
use Blacklight\Genres;
use App\Models\Category;

if (! User::isLoggedIn()) {
    $page->show403();
}

$music = new Music(['Settings' => $page->settings]);
$gen = new Genres(['Settings' => $page->settings]);

$musiccats = Category::getChildren(Category::MUSIC_ROOT);
$mtmp = [];
foreach ($musiccats as $mcat) {
    $mtmp[$mcat['id']] = $mcat;
}
$category = Category::MUSIC_ROOT;
if (request()->has('t') && array_key_exists(request()->input('t'), $mtmp)) {
    $category = request()->input('t') + 0;
}

$catarray = [];
$catarray[] = $category;

$page->smarty->assign('catlist', $mtmp);
$page->smarty->assign('category', $category);

$offset = (request()->has('offset') && ctype_digit(request()->input('offset'))) ? request()->input('offset') : 0;
$ordering = $music->getMusicOrdering();
$orderby = request()->has('ob') && in_array(request()->input('ob'), $ordering) ? request()->input('ob') : '';

$results = $musics = [];
$results = $music->getMusicRange($catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, $page->userdata['categoryexclusions']);

$artist = (request()->has('artist') && ! empty(request()->input('artist'))) ? stripslashes(request()->input('artist')) : '';
$page->smarty->assign('artist', $artist);

$title = (request()->has('title') && ! empty(request()->input('title'))) ? stripslashes(request()->input('title')) : '';
$page->smarty->assign('title', $title);

$genres = $gen->getGenres(Genres::MUSIC_TYPE, true);
$tmpgnr = [];
foreach ($genres as $gn) {
    $tmpgnr[$gn['id']] = $gn['title'];
}

foreach ($results as $result) {
    $result['genre'] = $tmpgnr[$result['genres_id']];
    $musics[] = $result;
}

$genre = (request()->has('genre') && array_key_exists(request()->input('genre'), $tmpgnr)) ? request()->input('genre') : '';
$page->smarty->assign('genres', $genres);
$page->smarty->assign('genre', $genre);

$years = range(1950, (date('Y') + 1));
rsort($years);
$year = (request()->has('year') && in_array(request()->input('year'), $years)) ? request()->input('year') : '';
$page->smarty->assign('years', $years);
$page->smarty->assign('year', $year);

$browseby_link = '&amp;title='.$title.'&amp;artist='.$artist.'&amp;genre='.$genre.'&amp;year='.$year;

$page->smarty->assign('pagertotalitems', isset($results[0]['_totalcount']) ? $results[0]['_totalcount'] : 0);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', config('nntmux.items_per_cover_page'));
$page->smarty->assign('pagerquerybase', WWW_TOP.'/music?t='.$category.$browseby_link.'&amp;ob='.$orderby.'&amp;offset=');
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
    $page->smarty->assign('orderby'.$ordertype, WWW_TOP.'/music?t='.$category.$browseby_link.'&amp;ob='.$ordertype.'&amp;offset=0');
}

$page->smarty->assign('results', $musics);

$page->meta_title = 'Browse Albums';
$page->meta_keywords = 'browse,nzb,albums,description,details';
$page->meta_description = 'Browse for Albums';

$page->content = $page->smarty->fetch('music.tpl');
$page->render();
