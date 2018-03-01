<?php

use App\Models\User;
use Blacklight\Movie;
use App\Models\Category;
use App\Models\Settings;
use Blacklight\Releases;
use App\Models\UserMovie;

if (! User::isLoggedIn()) {
    $page->show403();
}

$mv = new Movie(['Settings' => $page->settings]);

$action = \request()->input('id') ?? '';
$imdbid = \request()->input('subpage') ?? '';

if (\request()->has('from')) {
    $page->smarty->assign('from', WWW_TOP.\request()->input('from'));
} else {
    $page->smarty->assign('from', WWW_TOP.'/mymovies');
}

switch ($action) {
    case 'delete':
        $movie = UserMovie::getMovie(User::currentUserId(), $imdbid);
        if (\request()->has('from')) {
            header('Location:'.WWW_TOP.\request()->input('from'));
        } else {
            header('Location:'.WWW_TOP.'/mymovies');
        }
        if (! $movie) {
            $page->show404('Not subscribed');
        } else {
            UserMovie::delMovie(User::currentUserId(), $imdbid);
        }

        break;
    case 'add':
    case 'doadd':
        $movie = UserMovie::getMovie(User::currentUserId(), $imdbid);
        if ($movie) {
            $page->show404('Already subscribed');
        } else {
            $movie = $mv->getMovieInfo($imdbid);
            if (! $movie) {
                $page->show404('No matching movie.');
            }
        }

        if ($action === 'doadd') {
            $category = (\request()->has('category') && is_array(\request()->input('category')) && ! empty(\request()->input('category'))) ? \request()->input('category') : [];
            UserMovie::addMovie(User::currentUserId(), $imdbid, $category);
            if (\request()->has('from')) {
                header('Location:'.WWW_TOP.\request()->input('from'));
            } else {
                header('Location:'.WWW_TOP.'/mymovies');
            }
        } else {
            $tmpcats = Category::getChildren(Category::MOVIE_ROOT);
            $categories = [];
            foreach ($tmpcats as $c) {
                // If MOVIE WEB-DL categorization is disabled, don't include it as an option
                if ((int) Settings::settingValue('indexer.categorise.catwebdl') === 0 && (int) $c['id'] === Category::MOVIE_WEBDL) {
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
        $movie = UserMovie::getMovie(User::currentUserId(), $imdbid);

        if (! $movie) {
            $page->show404();
        }

        if ($action === 'doedit') {
            $category = (\request()->has('category') && is_array(\request()->input('category')) && ! empty(\request()->input('category'))) ? \request()->input('category') : [];
            UserMovie::updateMovie(User::currentUserId(), $imdbid, $category);
            if (\request()->has('from')) {
                header('Location:'.WWW_TOP.\request()->input('from'));
            } else {
                header('Location:'.WWW_TOP.'/mymovies');
            }
        } else {
            $tmpcats = Category::getChildren(Category::MOVIE_ROOT);
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

        $page->title = 'Browse My Shows';
        $page->meta_title = 'My Shows';
        $page->meta_keywords = 'search,add,to,cart,nzb,description,details';
        $page->meta_description = 'Browse Your Shows';

        $movies = UserMovie::getMovies(User::currentUserId());

        $releases = new Releases(['Settings' => $page->settings]);
        $browsecount = $releases->getMovieCount($movies, -1, $page->userdata['categoryexclusions']);

        $offset = (\request()->has('offset') && ctype_digit(\request()->input('offset'))) ? \request()->input('offset') : 0;
        $ordering = $releases->getBrowseOrdering();
        $orderby = \request()->has('ob') && \in_array(\request()->input('ob'), $ordering, false) ? \request()->input('ob') : '';

        $results = $mv->getMovieRange($movies, $offset, env('ITEMS_PER_PAGE', 50), $orderby, -1, $page->userdata['categoryexclusions']);

        $page->smarty->assign('pagertotalitems', $browsecount);
        $page->smarty->assign('pageroffset', $offset);
        $page->smarty->assign('pageritemsperpage', env('ITEMS_PER_PAGE', 50));
        $page->smarty->assign('pagerquerybase', WWW_TOP.'/mymovies/browse?ob='.$orderby.'&amp;offset=');
        $page->smarty->assign('pagerquerysuffix', '#results');
        $page->smarty->assign('covgroup', '');

        $pager = $page->smarty->fetch('pager.tpl');
        $page->smarty->assign('pager', $pager);

        foreach ($ordering as $ordertype) {
            $page->smarty->assign('orderby'.$ordertype, WWW_TOP.'/mymovies/browse?ob='.$ordertype.'&amp;offset=0');
        }

        $page->smarty->assign('lastvisit', $page->userdata['lastlogin']);

        $page->smarty->assign('results', $results);

        $page->smarty->assign('movies', true);

        $page->content = $page->smarty->fetch('browse.tpl');
        $page->render();
        break;
    default:

        $page->title = 'My Movies';
        $page->meta_title = 'My Movies';
        $page->meta_keywords = 'search,add,to,cart,nzb,description,details';
        $page->meta_description = 'Manage Your Movies';

        $tmpcats = Category::getChildren(Category::MOVIE_ROOT);
        $categories = [];
        foreach ($tmpcats as $c) {
            $categories[$c['id']] = $c['title'];
        }

        $movies = UserMovie::getMovies(User::currentUserId());
        $results = [];
        foreach ($movies as $moviek => $movie) {
            $showcats = explode('|', $movie['categories']);
            if (is_array($showcats) && count($showcats) > 0) {
                $catarr = [];
                foreach ($showcats as $scat) {
                    if (! empty($scat)) {
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
