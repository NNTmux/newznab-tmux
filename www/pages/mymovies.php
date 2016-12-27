<?php

use app\models\Settings;
use nntmux\Category;
use nntmux\Releases;
use nntmux\UserMovies;
use nntmux\Movie;

if (!$page->users->isLoggedIn()) {
	$page->show403();
}

$um = new UserMovies(['Settings' => $page->settings]);
$mv = new Movie(['Settings' => $page->settings]);

$action = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$imdbid = isset($_REQUEST['subpage']) ? $_REQUEST['subpage'] : '';

if (isset($_REQUEST['from'])) {
	$page->smarty->assign('from', WWW_TOP . $_REQUEST['from']);
} else {
	$page->smarty->assign('from', WWW_TOP . '/mymovies');
}

switch ($action) {
	case 'delete':
		$movie = $um->getMovie($page->users->currentUserId(), $imdbid);
		if (isset($_REQUEST['from'])) {
			header("Location:" . WWW_TOP . $_REQUEST['from']);
		} else {
			header("Location:" . WWW_TOP . "/mymovies");
		}
		if (!$movie) {
			$page->show404('Not subscribed');
		} else {
			$um->delMovie($page->users->currentUserId(), $imdbid);
		}

		break;
	case 'add':
	case 'doadd':
		$movie = $um->getMovie($page->users->currentUserId(), $imdbid);
		if ($movie) {
			$page->show404('Already subscribed');
		} else {
			$movie = $mv->getMovieInfo($imdbid);
			if (!$movie) {
				$page->show404('No matching movie.');
			}
		}

		if ($action == 'doadd') {
			$category = (isset($_REQUEST['category']) && is_array($_REQUEST['category']) && !empty($_REQUEST['category'])) ? $_REQUEST['category'] : [];
			$um->addMovie($page->users->currentUserId(), $imdbid, $category);
			if (isset($_REQUEST['from'])) {
				header("Location:" . WWW_TOP . $_REQUEST['from']);
			} else {
				header("Location:" . WWW_TOP . "/mymovies");
			}
		} else {
			$cat = new Category(['Settings' => $page->settings]);
			$tmpcats = $cat->getChildren(Category::MOVIE_ROOT);
			$categories = [];
			foreach ($tmpcats as $c) {
				// If MOVIE WEB-DL categorization is disabled, don't include it as an option
				if (Settings::value('indexer.categorise.catwebdl') == 0 && $c['id'] == Category::MOVIE_WEBDL) {
					continue;
				}
				$categories[$c['id']] = $c['title'];
			}
			$page->smarty->assign('type', 'add');
			$page->smarty->assign('cat_ids', array_keys($categories));
			$page->smarty->assign('cat_names', $categories);
			$page->smarty->assign('cat_selected', []);
			$page->smarty->assign('imdbid', $imdbid);
			$page->smarty->assign('movie', $movie);
			$page->content = $page->smarty->fetch('mymovies-add.tpl');
			$page->render();
		}
		break;
	case 'edit':
	case 'doedit':
		$movie = $um->getMovie($page->users->currentUserId(), $imdbid);

		if (!$movie) {
			$page->show404();
		}

		if ($action == 'doedit') {
			$category = (isset($_REQUEST['category']) && is_array($_REQUEST['category']) && !empty($_REQUEST['category'])) ? $_REQUEST['category'] : [];
			$um->updateMovie($page->users->currentUserId(), $imdbid, $category);
			if (isset($_REQUEST['from'])) {
				header("Location:" . WWW_TOP . $_REQUEST['from']);
			} else {
				header("Location:" . WWW_TOP . "/mymovies");
			}
		} else {
			$cat = new Category(['Settings' => $page->settings]);

			$tmpcats = $cat->getChildren(Category::MOVIE_ROOT);
			$categories = [];
			foreach ($tmpcats as $c) {
				$categories[$c['id']] = $c['title'];
			}

			$page->smarty->assign('type', 'edit');
			$page->smarty->assign('cat_ids', array_keys($categories));
			$page->smarty->assign('cat_names', $categories);
			$page->smarty->assign('cat_selected', explode('|', $movie['categories']));
			$page->smarty->assign('imdbid', $imdbid);
			$page->smarty->assign('movie', $movie);
			$page->content = $page->smarty->fetch('mymovies-add.tpl');
			$page->render();
		}
		break;
	case 'browse':

		$page->title = "Browse My Shows";
		$page->meta_title = "My Shows";
		$page->meta_keywords = "search,add,to,cart,nzb,description,details";
		$page->meta_description = "Browse Your Shows";

		$movies = $um->getMovies($page->users->currentUserId());

		$releases = new Releases(['Settings' => $page->settings]);
		$browsecount = $releases->getMovieCount($movies, -1, $page->userdata["categoryexclusions"]);

		$offset = (isset($_REQUEST["offset"]) && ctype_digit($_REQUEST['offset'])) ? $_REQUEST["offset"] : 0;
		$ordering = $releases->getBrowseOrdering();
		$orderby = isset($_REQUEST["ob"]) && in_array($_REQUEST['ob'], $ordering) ? $_REQUEST["ob"] : '';

		$results = [];
		$results = $mv->getMovieRange($movies, $offset, ITEMS_PER_PAGE, $orderby, -1, $page->userdata["categoryexclusions"]);

		$page->smarty->assign('pagertotalitems', $browsecount);
		$page->smarty->assign('pageroffset', $offset);
		$page->smarty->assign('pageritemsperpage', ITEMS_PER_PAGE);
		$page->smarty->assign('pagerquerybase', WWW_TOP . "/mymovies/browse?ob=" . $orderby . "&amp;offset=");
		$page->smarty->assign('pagerquerysuffix', "#results");
		$page->smarty->assign('covgroup', '');

		$pager = $page->smarty->fetch("pager.tpl");
		$page->smarty->assign('pager', $pager);

		foreach ($ordering as $ordertype) {
			$page->smarty->assign('orderby' . $ordertype, WWW_TOP . "/mymovies/browse?ob=" . $ordertype . "&amp;offset=0");
		}

		$page->smarty->assign('lastvisit', $page->userdata['lastlogin']);

		$page->smarty->assign('results', $results);

		$page->smarty->assign('movies', true);

		$page->content = $page->smarty->fetch('browse.tpl');
		$page->render();
		break;
	default:

		$page->title = "My Movies";
		$page->meta_title = "My Movies";
		$page->meta_keywords = "search,add,to,cart,nzb,description,details";
		$page->meta_description = "Manage Your Movies";

		$cat = new Category(['Settings' => $page->settings]);
		$tmpcats = $cat->getChildren(Category::MOVIE_ROOT);
		$categories = [];
		foreach ($tmpcats as $c) {
			$categories[$c['id']] = $c['title'];
		}

		$movies = $um->getMovies($page->users->currentUserId());
		$results = [];
		foreach ($movies as $moviek => $movie) {
			$showcats = explode('|', $movie['categories']);
			if (is_array($showcats) && sizeof($showcats) > 0) {
				$catarr = [];
				foreach ($showcats as $scat) {
					if (!empty($scat)) {
						$catarr[] = $categories[$scat];
					}
				}
				$movie['categoryNames'] = implode(', ', $catarr);
			} else {
				$movie['categoryNames'] = '';
			}

			$results[$moviek] = $movie;
		}
		$page->smarty->assign('movies', $results);

		$page->content = $page->smarty->fetch('mymovies.tpl');
		$page->render();
		break;
}
