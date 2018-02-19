<?php

use App\Models\User;
use Blacklight\Genres;
use Blacklight\Console;
use App\Models\Category;

if (! User::isLoggedIn()) {
    $page->show403();
}

$console = new Console(['Settings' => $page->settings]);
$gen = new Genres(['Settings' => $page->settings]);

$concats = Category::getChildren(Category::GAME_ROOT);
$ctmp = [];
foreach ($concats as $ccat) {
    $ctmp[$ccat['id']] = $ccat;
}
$category = Category::GAME_ROOT;
if (isset($_REQUEST['t']) && array_key_exists($_REQUEST['t'], $ctmp)) {
    $category = $_REQUEST['t'] + 0;
}

$catarray = [];
$catarray[] = $category;

$page->smarty->assign('catlist', $ctmp);
$page->smarty->assign('category', $category);

$offset = (isset($_REQUEST['offset']) && ctype_digit($_REQUEST['offset'])) ? $_REQUEST['offset'] : 0;
$ordering = $console->getConsoleOrdering();
$orderby = isset($_REQUEST['ob']) && in_array($_REQUEST['ob'], $ordering, false) ? $_REQUEST['ob'] : '';

$consoles = [];
$results = $console->getConsoleRange($catarray, $offset, env('ITEMS_PER_COVER_PAGE', 20), $orderby, $page->userdata['categoryexclusions']);

$maxwords = 50;
foreach ($results as $result) {
    if (! empty($result['review'])) {
        $words = explode(' ', $result['review']);
        if (count($words) > $maxwords) {
            $newwords = array_slice($words, 0, $maxwords);
            $result['review'] = implode(' ', $newwords).'...';
        }
    }
    $consoles[] = $result;
}

$platform = (isset($_REQUEST['platform']) && ! empty($_REQUEST['platform'])) ? stripslashes($_REQUEST['platform']) : '';
$page->smarty->assign('platform', $platform);

$title = (isset($_REQUEST['title']) && ! empty($_REQUEST['title'])) ? stripslashes($_REQUEST['title']) : '';
$page->smarty->assign('title', $title);

$genres = $gen->getGenres(Genres::CONSOLE_TYPE, true);
$tmpgnr = [];
foreach ($genres as $gn) {
    $tmpgnr[$gn['id']] = $gn['title'];
}
$genre = (isset($_REQUEST['genre']) && array_key_exists($_REQUEST['genre'], $tmpgnr)) ? $_REQUEST['genre'] : '';
$page->smarty->assign('genres', $genres);
$page->smarty->assign('genre', $genre);

$browseby_link = '&amp;title='.$title.'&amp;platform='.$platform;

$page->smarty->assign('pagertotalitems', $results[0]['_totalcount'] ?? 0);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', env('ITEMS_PER_COVER_PAGE', 20));
$page->smarty->assign('pagerquerybase', WWW_TOP.'/console?t='.$category.$browseby_link.'&amp;ob='.$orderby.'&amp;offset=');
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
    $page->smarty->assign('orderby'.$ordertype, WWW_TOP.'/console?t='.$category.$browseby_link.'&amp;ob='.$ordertype.'&amp;offset=0');
}

$page->smarty->assign('results', $consoles);

$page->meta_title = 'Browse Console';
$page->meta_keywords = 'browse,nzb,console,games,description,details';
$page->meta_description = 'Browse for Console Games';

$page->content = $page->smarty->fetch('console.tpl');
$page->render();
