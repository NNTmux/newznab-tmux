<?php

use nntmux\AniDB;
use nntmux\Releases;
use nntmux\Category;

if (!$page->users->isLoggedIn()) {
	$page->show403();
}

$releases = new Releases(['Settings' => $page->settings]);
$aniDB = new AniDB(['Settings' => $page->settings]);

if (isset($_GET['id']) && ctype_digit($_GET['id'])) {

	# force the category to TV_ANIME as it should be for anime, as $catarray was NULL and we know the category for sure for anime
	$aniDbReleases = $releases->searchbyAnidbId($_GET['id'], 0, 1000, '', [Category::TV_ANIME], -1);
	$aniDbInfo = $aniDB->getAnimeInfo($_GET['id']);

	if (!$releases && !$aniDbInfo) {
		$page->show404();
	} else if (!$aniDbInfo) {
		$page->smarty->assign('nodata', 'No AniDB information for this series.');
	} elseif (!$aniDbReleases) {
		$page->smarty->assign('nodata', 'No releases for this series.');
	} else {

		$page->smarty->assign('anidb', $aniDbInfo);
		$page->smarty->assign('animeEpisodeTitles', $aniDbReleases);
		$page->smarty->assign('animeAnidbid', $aniDbInfo['anidbid']);
		$page->smarty->assign('animeTitle', $aniDbInfo['title']);
		$page->smarty->assign('animeType', $aniDbInfo['type']);
		$page->smarty->assign('animePicture', $aniDbInfo['picture']);
		$page->smarty->assign('animeStartDate', $aniDbInfo['startdate']);
		$page->smarty->assign('animeEndDate', $aniDbInfo['enddate']);
		$page->smarty->assign('animeDescription', $aniDbInfo['description']);
		$page->smarty->assign('animeRating', $aniDbInfo['rating']);
		$page->smarty->assign('animeRelated', $aniDbInfo['related']);
		$page->smarty->assign('animeSimilar', $aniDbInfo['similar']);
		$page->smarty->assign('animeCategories', $aniDbInfo['categories']);

		$page->smarty->assign('nodata', '');

		$page->title = $aniDbInfo['title'];
		$page->meta_title = 'View Anime ' . $aniDbInfo['title'];
		$page->meta_keywords = 'view,anime,anidb,description,details';
		$page->meta_description = 'View ' . $aniDbInfo['title'] . ' Anime';
	}
	$page->content = $page->smarty->fetch('viewanime.tpl');
	$page->render();
} else {
	$letter = (isset($_GET['id']) && preg_match('/^(0\-9|[A-Z])$/i', $_GET['id'])) ? $_GET['id'] : '0-9';

	$animetitle = (isset($_GET['title']) && !empty($_GET['title'])) ? $_GET['title'] : '';

	if ($animetitle !== '' && !isset($_GET['id'])) {
		$letter = '';
	}

	$masterserieslist = $AniDB->getAnimeList($letter, $animetitle);

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
	$page->smarty->assign('animetitle', $animetitle);

	$page->content = $page->smarty->fetch('viewanimelist.tpl');
	$page->render();
}
