<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Settings;
use App\Models\UserMovie;
use Blacklight\Movie;
use Blacklight\Releases;
use Illuminate\Http\Request;

class MyMoviesController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function show(Request $request): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        $this->setPreferences();
        $mv = new Movie(['Settings' => $this->settings]);

        $action = $request->input('id') ?? '';
        $imdbid = $request->input('imdb') ?? '';

        if ($request->has('from')) {
            $this->smarty->assign('from', url($request->input('from')));
        } else {
            $this->smarty->assign('from', url('/mymovies'));
        }

        switch ($action) {
            case 'delete':
                $movie = UserMovie::getMovie($this->userdata->id, $imdbid);
                if (! $movie) {
                    return redirect()->to('/mymovies');
                }
                UserMovie::delMovie($this->userdata->id, $imdbid);
                if ($request->has('from')) {
                    header('Location:'.url($request->input('from')));
                } else {
                    return redirect()->to('/mymovies');
                }

                break;
            case 'add':
            case 'doadd':
                $movie = UserMovie::getMovie($this->userdata->id, $imdbid);
                if ($movie) {
                    return redirect()->to('/mymovies');
                }

                $movie = $mv->getMovieInfo($imdbid);
                if (! $movie) {
                    return redirect()->to('/mymovies');
                }

                if ($action === 'doadd') {
                    $category = ($request->has('category') && \is_array($request->input('category')) && ! empty($request->input('category'))) ? $request->input('category') : [];
                    UserMovie::addMovie($this->userdata->id, $imdbid, $category);
                    if ($request->has('from')) {
                        return redirect()->to($request->input('from'));
                    }

                    return redirect()->to('/mymovies');
                }

                $tmpcats = Category::getChildren(Category::MOVIE_ROOT);
                $categories = [];
                foreach ($tmpcats as $c) {
                    // If MOVIE WEB-DL categorization is disabled, don't include it as an option
                    if ((int) $c['id'] === Category::MOVIE_WEBDL && (int) Settings::settingValue('indexer.categorise.catwebdl') === 0) {
                        continue;
                    }
                    $categories[$c['id']] = $c['title'];
                }
                $this->smarty->assign('type', 'add');
                $this->smarty->assign('cat_ids', array_keys($categories));
                $this->smarty->assign('cat_names', $categories);
                $this->smarty->assign('cat_selected', []);
                $this->smarty->assign('imdbid', $imdbid);
                $this->smarty->assign('movie', $movie);
                $content = $this->smarty->fetch('mymovies-add.tpl');
                $this->smarty->assign('content', $content);
                $this->pagerender();
                break;
            case 'edit':
            case 'doedit':
                $movie = UserMovie::getMovie($this->userdata->id, $imdbid);

                if (! $movie) {
                    return redirect()->to('/mymovies');
                }

                if ($action === 'doedit') {
                    $category = ($request->has('category') && \is_array($request->input('category')) && ! empty($request->input('category'))) ? $request->input('category') : [];
                    UserMovie::updateMovie($this->userdata->id, $imdbid, $category);
                    if ($request->has('from')) {
                        return redirect()->to($request->input('from'));
                    }

                    return redirect()->to('mymovies');
                }

                $tmpcats = Category::getChildren(Category::MOVIE_ROOT);
                $categories = [];
                foreach ($tmpcats as $c) {
                    $categories[$c['id']] = $c['title'];
                }

                $this->smarty->assign('type', 'edit');
                $this->smarty->assign('cat_ids', array_keys($categories));
                $this->smarty->assign('cat_names', $categories);
                $this->smarty->assign('cat_selected', explode('|', $movie['categories']));
                $this->smarty->assign('imdbid', $imdbid);
                $this->smarty->assign('movie', $movie);
                $content = $this->smarty->fetch('mymovies-add.tpl');
                $this->smarty->assign('content', $content);
                $this->pagerender();
                break;
            case 'browse':

                $title = 'Browse My Movies';
                $meta_title = 'My Movies';
                $meta_keywords = 'search,add,to,cart,nzb,description,details';
                $meta_description = 'Browse Your Movies';

                $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;

                $offset = ($page - 1) * config('nntmux.items_per_cover_page');

                $movies = UserMovie::getMovies($this->userdata->id);
                $categories = $movie = [];
                foreach ($movies as $moviek => $movie) {
                    $showcats = explode('|', $movie['categories']);
                    if (\is_array($showcats) && \count($showcats) > 0) {
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
                }

                $ordering = (new Releases())->getBrowseOrdering();

                $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;

                $results = $mv->getMovieRange($page, $movie['categoryNames'], $offset, config('nntmux.items_per_cover_page'), $ordering, -1, $this->userdata->categoryexclusions);

                $this->smarty->assign('covgroup', '');

                foreach ($ordering as $ordertype) {
                    $this->smarty->assign('orderby'.$ordertype, url('/mymovies/browse?ob='.$ordertype.'&amp;offset=0'));
                }

                $this->smarty->assign('lastvisit', $this->userdata->lastlogin);

                $this->smarty->assign('results', $results);

                $this->smarty->assign('movies', true);

                $content = $this->smarty->fetch('browse.tpl');
                $this->smarty->assign(compact('content', 'title', 'meta_title', 'meta_keywords', 'meta_description'));
                $this->pagerender();
                break;
            default:

                $title = 'My Movies';
                $meta_title = 'My Movies';
                $meta_keywords = 'search,add,to,cart,nzb,description,details';
                $meta_description = 'Manage Your Movies';

                $tmpcats = Category::getChildren(Category::MOVIE_ROOT);
                $categories = [];
                foreach ($tmpcats as $c) {
                    $categories[$c['id']] = $c['title'];
                }

                $movies = UserMovie::getMovies($this->userdata->id);
                $results = [];
                foreach ($movies as $moviek => $movie) {
                    $showcats = explode('|', $movie['categories']);
                    if (\is_array($showcats) && \count($showcats) > 0) {
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
                $this->smarty->assign('movies', $results);

                $content = $this->smarty->fetch('mymovies.tpl');
                $this->smarty->assign(compact('content', 'title', 'meta_title', 'meta_keywords', 'meta_description'));
                $this->pagerender();
                break;
        }
    }
}
