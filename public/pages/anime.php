<?php

use Blacklight\AniDB;
use App\Models\Category;
use Blacklight\Releases;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}

$releases = new Releases(['Settings' => $page->settings]);
$aniDB = new AniDB(['Settings' => $page->settings]);

if (request()->has('id') && ctype_digit(request()->input('id'))) {

    // force the category to TV_ANIME as it should be for anime, as $catarray was NULL and we know the category for sure for anime
    $aniDbReleases = $releases->animeSearch(request()->input('id'), 0, 1000, '', [Category::TV_ANIME], -1);
    $aniDbInfo = $aniDB->getAnimeInfo(request()->input('id'));

    if (! $releases && ! $aniDbInfo) {
        $page->show404();
    } elseif (! $aniDbInfo) {
        $page->smarty->assign('nodata', 'No AniDB information for this series.');
    } elseif (! $aniDbReleases) {
        $page->smarty->assign('nodata', 'No releases for this series.');
    } else {
        $page->smarty->assign('anidb', $aniDbInfo);
        $page->smarty->assign('animeEpisodeTitles', $aniDbReleases);
        $page->smarty->assign(
            [
                'animeAnidbid'     => $aniDbInfo['anidbid'],
                'animeTitle'       => $aniDbInfo['title'],
                'animeType'        => $aniDbInfo['type'],
                'animePicture'     => $aniDbInfo['picture'],
                'animeStartDate'   => $aniDbInfo['startdate'],
                'animeEndDate'     => $aniDbInfo['enddate'],
                'animeDescription' => $aniDbInfo['description'],
                'animeRating'      => $aniDbInfo['rating'],
                'animeRelated'     => $aniDbInfo['related'],
                'animeSimilar'     => $aniDbInfo['similar'],
                'animeCategories'  => $aniDbInfo['categories'],
                'animeCreators'    => $aniDbInfo['creators'],
                'animeCharacters'  => $aniDbInfo['characters'],
            ]
        );

        $page->smarty->assign('nodata', '');

        $page->title = $aniDbInfo['title'];
        $page->meta_title = 'View Anime '.$aniDbInfo['title'];
        $page->meta_keywords = 'view,anime,anidb,description,details';
        $page->meta_description = 'View '.$aniDbInfo['title'].' Anime';
    }
    $page->content = $page->smarty->fetch('viewanime.tpl');
    $page->pagerender();
} else {
    $letter = (request()->has('id') && preg_match('/^(0\-9|[A-Z])$/i', request()->input('id'))) ?
        request()->input('id') :
        '0-9';

    $animeTitle = (request()->has('title') && ! empty(request()->input('title'))) ? request()->input('title') : '';

    if ($animeTitle !== '' && ! request()->has('id')) {
        $letter = '';
    }

    $masterserieslist = $aniDB->getAnimeList($letter, $animeTitle);

    $page->title = 'Anime List';
    $page->meta_title = 'View Anime List';
    $page->meta_keywords = 'view,anime,series,description,details';
    $page->meta_description = 'View Anime List';

    $animelist = [];
    if ($masterserieslist instanceof \Traversable) {
        foreach ($masterserieslist as $s) {
            if (preg_match('/^[0-9]/', $s['title'])) {
                $thisrange = '0-9';
            } else {
                preg_match('/([A-Z]).*/i', $s['title'], $matches);
                $thisrange = strtoupper($matches[1]);
            }
            $animelist[$thisrange][] = $s;
        }
        ksort($animelist);
    }

    $page->smarty->assign('animelist', $animelist);
    $page->smarty->assign('animerange', range('A', 'Z'));
    $page->smarty->assign('animeletter', $letter);
    $page->smarty->assign('animetitle', $animeTitle);

    $page->content = $page->smarty->fetch('viewanimelist.tpl');
    $page->pagerender();
}
