<?php

use App\Models\User;
use Blacklight\Releases;
use App\Models\Category;

$releases = new Releases(['Settings' => $page->settings]);

if (! User::isLoggedIn()) {
    $page->show403();
}

$category = -1;
if (isset($_REQUEST['t'])) {
    $category = $_REQUEST['t'];
}

$grp = -1;
if (isset($_REQUEST['g'])) {
    $grp = is_numeric($_REQUEST['g']) ? -1 : $_REQUEST['g'];
}

$catarray = [];
$catarray[] = $category;

$page->smarty->assign('category', $category);

$offset = (isset($_REQUEST['offset']) && ctype_digit($_REQUEST['offset'])) ? $_REQUEST['offset'] : 0;
$ordering = $releases->getBrowseOrdering();
$orderby = isset($_REQUEST['ob']) && in_array($_REQUEST['ob'], $ordering, false) ? $_REQUEST['ob'] : '';

$results = $releases->getBrowseRange($catarray, $offset, ITEMS_PER_PAGE, $orderby, -1, $page->userdata['categoryexclusions'], $grp);

$browsecount = $results[0]['_totalcount'] ?? 0;

$page->smarty->assign(
    [
        'pagertotalitems' => $browsecount,
        'pageroffset'=> $offset,
        'pageritemsperpage'=> ITEMS_PER_PAGE,
        'pagerquerybase' => WWW_TOP.'/browse?t='.$category.'&amp;g='.$grp.'&amp;ob='.$orderby.'&amp;offset=',
        'pagerquerysuffix' => '#results',
    ]
);

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$covgroup = '';
if ((int) $category === -1 && (int) $grp === -1) {
    $page->smarty->assign('catname', 'All');
} elseif ((int) $category !== -1 && (int) $grp === -1) {
    $cdata = Category::find($category);
    if ($cdata) {
        $page->smarty->assign('catname', $cdata->parent !== null ? $cdata->parent->title.' > '.$cdata->title : $cdata->title);
        if ($cdata['parentid'] === Category::GAME_ROOT || $cdata['id'] === Category::GAME_ROOT) {
            $covgroup = 'console';
        } elseif ($cdata['parentid'] === Category::MOVIE_ROOT || $cdata['id'] === Category::MOVIE_ROOT) {
            $covgroup = 'movies';
        } elseif ($cdata['parentid'] === Category::XXX_ROOT || $cdata['id'] === Category::XXX_ROOT) {
            $covgroup = 'xxx';
        } elseif ($cdata['parentid'] === Category::PC_ROOT || $cdata['id'] === Category::PC_GAMES) {
            $covgroup = 'games';
        } elseif ($cdata['parentid'] === Category::MUSIC_ROOT || $cdata['id'] === Category::MUSIC_ROOT) {
            $covgroup = 'music';
        } elseif ($cdata['parentid'] === Category::BOOKS_ROOT || $cdata['id'] === Category::BOOKS_ROOT) {
            $covgroup = 'books';
        }
    } else {
        $page->show404();
    }
} elseif ((int) $grp !== -1) {
    $page->smarty->assign('catname', $grp);
}

$page->smarty->assign('covgroup', $covgroup);

foreach ($ordering as $ordertype) {
    $page->smarty->assign('orderby'.$ordertype, WWW_TOP.'/browse?t='.$category.'&amp;g='.$grp.'&amp;ob='.$ordertype.'&amp;offset=0');
}
$page->smarty->assign('lastvisit', $page->userdata['lastlogin']);

$page->smarty->assign('results', $results);

$page->meta_title = 'Browse Nzbs';
$page->meta_keywords = 'browse,nzb,description,details';
$page->meta_description = 'Browse for Nzbs';

$page->content = $page->smarty->fetch('browse.tpl');
$page->render();
