<?php

use App\Models\User;
use App\Models\Video;
use Blacklight\Releases;
use App\Models\UserSerie;

if (! User::isLoggedIn()) {
    $page->show403();
}

$releases = new Releases(['Settings' => $page->settings]);

if ($page->request->has('id') && ctype_digit($page->request->input('id'))) {
    $category = -1;
    if ($page->request->has('t') && ctype_digit($page->request->input('t'))) {
        $category = $page->request->input('t');
    }

    $catarray = [];
    $catarray[] = $category;

    $rel = $releases->tvSearch(['id' => $_GET['id']], '', '', '', 0, 1000, '', $catarray, -1);
    $show = Video::getByVideoID($page->request->input('id'));

    if (! $show) {
        $page->smarty->assign('nodata', 'No video information for this series.');
    } elseif (! $rel) {
        $page->smarty->assign('nodata', 'No releases for this series.');
    } else {
        $myshows = UserSerie::getShow(User::currentUserId(), $show['id']);

        // Sort releases by season, episode, date posted.
        $series = $episode = $posted = [];
        foreach ($rel as $rlk => $rlv) {
            $series[$rlk] = $rlv['series'];
            $episode[$rlk] = $rlv['episode'];
            $posted[$rlk] = $rlv['postdate'];
        }
        array_multisort($series, SORT_DESC, $episode, SORT_DESC, $posted, SORT_DESC, $rel);

        $series = [];
        foreach ($rel as $r) {
            $series[$r['series']][$r['episode']][] = $r;
        }

        $page->smarty->assign('seasons', $series);
        $page->smarty->assign('show', $show);
        $page->smarty->assign('myshows', $myshows);

        //get series name(s), description, country and genre
        $seriestitles = $seriesdescription = $seriescountry = [];
        $seriestitles[] = $show['title'];

        if (! empty($show['summary'])) {
            $seriessummary[] = $show['summary'];
        }

        if (! empty($show['countries_id'])) {
            $seriescountry[] = $show['countries_id'];
        }

        $seriestitles = implode('/', array_map('trim', $seriestitles));
        $page->smarty->assign('seriestitles', $seriestitles);
        $page->smarty->assign('seriessummary', array_shift($seriessummary));
        $page->smarty->assign('seriescountry', array_shift($seriescountry));

        $page->title = 'Series';
        $page->meta_title = 'View TV Series';
        $page->meta_keywords = 'view,series,tv,show,description,details';
        $page->meta_description = 'View TV Series';

        if ($category !== -1) {
            $catid = $category;
        } else {
            $catid = '';
        }
        $page->smarty->assign('category', $catid);
        $page->smarty->assign('nodata', '');
    }
    $page->content = $page->smarty->fetch('viewseries.tpl');
    $page->render();
} else {
    $letter = ($page->request->has('id') && preg_match('/^(0\-9|[A-Z])$/i', $page->request->input('id'))) ? $page->request->input('id') : '0-9';

    $showname = (isset($_GET['title']) && ! empty($_GET['title'])) ? $_GET['title'] : '';

    if ($showname !== '' && ! isset($page->request->input('id'))) {
        $letter = '';
    }

    $masterserieslist = Video::getSeriesList(User::currentUserId(), $letter, $showname);

    $page->title = 'Series List';
    $page->meta_title = 'View Series List';
    $page->meta_keywords = 'view,series,tv,show,description,details';
    $page->meta_description = 'View Series List';

    $serieslist = [];
    foreach ($masterserieslist as $s) {
        if (preg_match('/^[0-9]/', $s['title'])) {
            $thisrange = '0-9';
        } else {
            preg_match('/([A-Z]).*/i', $s['title'], $matches);
            $thisrange = strtoupper($matches[1]);
        }
        $serieslist[$thisrange][] = $s;
    }
    ksort($serieslist);

    $page->smarty->assign('serieslist', $serieslist);
    $page->smarty->assign('seriesrange', range('A', 'Z'));
    $page->smarty->assign('seriesletter', $letter);
    $page->smarty->assign('showname', $showname);

    $page->content = $page->smarty->fetch('viewserieslist.tpl');
    $page->render();
}
