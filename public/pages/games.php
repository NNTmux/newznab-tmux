<?php

use App\Models\User;
use Blacklight\Games;
use Blacklight\Genres;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}


$games = new Games(['Settings' => $page->settings]);
$gen = new Genres(['Settings' => $page->settings]);

$concats = Category::getChildren(Category::PC_ROOT);
$ctmp = [];
foreach ($concats as $ccat) {
    $ctmp[$ccat['id']] = $ccat;
}
$category = Category::PC_GAMES;
if (request()->has('t') && array_key_exists(request()->input('t'), $ctmp)) {
    $category = request()->input('t') + 0;
}

$catarray = [];
$catarray[] = $category;

$page->smarty->assign('catlist', $ctmp);
$page->smarty->assign('category', $category);

$offset = (request()->has('offset') && ctype_digit(request()->input('offset'))) ? request()->input('offset') : 0;
$ordering = $games->getGamesOrdering();

$orderby = request()->has('ob') && in_array(request()->input('ob'), $ordering, false) ? request()->input('ob') : '';

$games2 = [];
$results = $games->getGamesRange($catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, '', $page->userdata['categoryexclusions']);
$maxwords = 50;
foreach ($results as $result) {
    if (! empty($result['review'])) {
        // remove "Overview" from start of review if present
        if (0 === strpos($result['review'], 'Overview')) {
            $result['review'] = substr($result['review'], 8);
        }
        $words = explode(' ', $result['review']);
        if (count($words) > $maxwords) {
            $newwords = array_slice($words, 0, $maxwords);
            $result['review'] = implode(' ', $newwords).'...';
        }
    }
    $games2[] = $result;
}

$title = (request()->has('title') && ! empty(request()->input('title'))) ? stripslashes(request()->input('title')) : '';
$page->smarty->assign('title', $title);

$genres = $gen->getGenres(Genres::GAME_TYPE, true);
$tmpgnr = [];
foreach ($genres as $gn) {
    $tmpgnr[$gn['id']] = $gn['title'];
}

$years = range(1903, date('Y') + 1);
rsort($years);
$year = (request()->has('year') && in_array(request()->input('year'), $years, false)) ? request()->input('year') : '';
$page->smarty->assign('years', $years);
$page->smarty->assign('year', $year);

$genre = (request()->has('genre') && array_key_exists(request()->input('genre'), $tmpgnr)) ? request()->input('genre') : '';
$page->smarty->assign('genres', $genres);
$page->smarty->assign('genre', $genre);

$browseby_link = '&amp;title='.$title.'&amp;year='.$year;

$page->smarty->assign('pagertotalitems', $results[0]['_totalcount'] ?? 0);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', config('nntmux.items_per_cover_page'));
$page->smarty->assign('pagerquerybase', WWW_TOP.'/games?t='.$category.$browseby_link.'&amp;ob='.$orderby.'&amp;offset=');
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
    $page->smarty->assign('orderby'.$ordertype, WWW_TOP.'/games?t='.$category.$browseby_link.'&amp;ob='.$ordertype.'&amp;offset=0');
}

$page->smarty->assign('results', $games2);

$page->meta_title = 'Browse Games';
$page->meta_keywords = 'browse,nzb,games,description,details';
$page->meta_description = 'Browse for Games';

$page->content = $page->smarty->fetch('games.tpl');
$page->render();
