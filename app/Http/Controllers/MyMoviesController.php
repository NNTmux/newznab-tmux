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
    public function show(Request $request)
    {
        $this->setPreferences();
        $mv = new Movie;

        $action = $request->input('id') ?? '';
        $imdbid = $request->input('imdb') ?? '';

        if ($request->has('from')) {
            $this->viewData['from'] = url($request->input('from'));
        } else {
            $this->viewData['from'] = url('/mymovies');
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
                    if ((int) $c['id'] === Category::MOVIE_WEBDL && (int) Settings::settingValue('catwebdl') === 0) {
                        continue;
                    }
                    $categories[$c['id']] = $c['title'];
                }
                $this->viewData['type'] = 'add';
                $this->viewData['cat_ids'] = array_keys($categories);
                $this->viewData['cat_names'] = $categories;
                $this->viewData['cat_selected'] = [];
                $this->viewData['imdbid'] = $imdbid;
                $this->viewData['movie'] = $movie;
                $this->viewData['content'] = view('themes/Gentele/mymovies-add', $this->viewData)->render();
                return $this->pagerender();

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

                $this->viewData['type'] = 'edit';
                $this->viewData['cat_ids'] = array_keys($categories);
                $this->viewData['cat_names'] = $categories;
                $this->viewData['cat_selected'] = explode('|', $movie['categories']);
                $this->viewData['imdbid'] = $imdbid;
                $this->viewData['movie'] = $movie;
                $this->viewData['content'] = view('themes/Gentele/mymovies-add', $this->viewData)->render();
                return $this->pagerender();

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

                $ordering = (new Releases)->getBrowseOrdering();

                $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;

                $results = $mv->getMovieRange($page, $movie['categoryNames'], $offset, config('nntmux.items_per_cover_page'), $ordering, -1, $this->userdata->categoryexclusions);

                $this->viewData['covgroup'] = '';

                foreach ($ordering as $ordertype) {
                    $this->viewData['orderby'.$ordertype] = url('/mymovies/browse?ob='.$ordertype.'&amp;offset=0');
                }

                $this->viewData['lastvisit'] = $this->userdata->lastlogin;
                $this->viewData['results'] = $results;
                $this->viewData['movies'] = true;
                $this->viewData['content'] = view('themes/Gentele/browse', $this->viewData)->render();
                $this->viewData = array_merge($this->viewData, compact('title', 'meta_title', 'meta_keywords', 'meta_description'));
                return $this->pagerender();

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
                $this->viewData['movies'] = $results;
                $this->viewData['content'] = view('themes/Gentele/mymovies', $this->viewData)->render();
                $this->viewData = array_merge($this->viewData, compact('title', 'meta_title', 'meta_keywords', 'meta_description'));
                return $this->pagerender();
        }

        // Fallback return in case no case matches
        return redirect()->to('/mymovies');
    }
}
