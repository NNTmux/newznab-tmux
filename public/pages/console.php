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
if ($page->request->has('t') && array_key_exists($page->request->input('t'), $ctmp)) {
    $category = $page->request->input('t') + 0;
}

$catarray = [];
$catarray[] = $category;

$page->smarty->assign('catlist', $ctmp);
$page->smarty->assign('category', $category);

$offset = ($page->request->has('offset') && ctype_digit($page->request->input('offset'))) ? $page->request->input('offset') : 0;
$ordering = $console->getConsoleOrdering();
$orderby = $page->request->has('ob') && in_array($page->request->input('ob'), $ordering, false) ? $page->request->input('ob') : '';

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

$platform = ($page->request->has('platform') && ! empty($page->request->input('platform'))) ? stripslashes($page->request->input('platform')) : '';
$page->smarty->assign('platform', $platform);

$title = ($page->request->has('title') && ! empty($page->request->input('title'))) ? stripslashes($page->request->input('title')) : '';
$page->smarty->assign('title', $title);

$genres = $gen->getGenres(Genres::CONSOLE_TYPE, true);
$tmpgnr = [];
foreach ($genres as $gn) {
    $tmpgnr[$gn['id']] = $gn['title'];
}
$genre = ($page->request->has('genre') && array_key_exists($page->request->input('genre'), $tmpgnr)) ? $page->request->input('genre') : '';
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
