<?php

namespace App\Http\Controllers;

use Blacklight\Movie;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class MovieController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function showMovie(Request $request)
    {
        $this->setPrefs();
        if ($request->has('modal') && $request->has('id') && ctype_digit($request->input('id'))) {
            $movie = new Movie(['Settings' => $this->settings]);
            $mov = $movie->getMovieInfo($request->input('id'));

            if (! $mov) {
                $this->show404();
            }

            $mov['actors'] = makeFieldLinks($mov, 'actors', 'movies');
            $mov['genre'] = makeFieldLinks($mov, 'genre', 'movies');
            $mov['director'] = makeFieldLinks($mov, 'director', 'movies');

            $this->smarty->assign(['movie' => $mov, 'modal' => true]);

            $title = 'Info for '.$mov['title'];
            $meta_title = '';
            $meta_keywords = '';
            $meta_description = '';
            $this->smarty->registerPlugin('modifier', 'ss', 'stripslashes');

            if ($request->has('modal')) {
                $content = $this->smarty->fetch('viewmovie.tpl');
                $this->smarty->assign('modal', true);
                echo $this->content;
            } else {
                $content = $this->smarty->fetch('viewmoviefull.tpl');
            }
        }

        $this->smarty->assign([
                'content' => $content,
                'title' => $title,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]);
        $this->pagerender();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $id
     *
     * @throws \Exception
     */
    public function showMovies(Request $request, $id = '')
    {
        $this->setPrefs();
        $movie = new Movie(['Settings' => $this->settings]);

        $moviecats = Category::getChildren(Category::MOVIE_ROOT);
        $mtmp = [];
        foreach ($moviecats as $mcat) {
            $mtmp[] =
                [
                    'id' => $mcat->id,
                    'title' => $mcat->title,
                ];
        }

        $category = $request->has('imdb') ? -1 : Category::MOVIE_ROOT;
        if ($id && \in_array($id, array_pluck($mtmp, 'title'), false)) {
            $cat = Category::query()
                ->where('title', $id)
                ->where('parentid', '=', Category::MOVIE_ROOT)
                ->first(['id']);
            $category = $cat !== null ? $cat['id'] : Category::MOVIE_ROOT;
        }

        $cpapi = $this->userdata['cp_api'];
        $cpurl = $this->userdata['cp_url'];
        $this->smarty->assign('cpapi', $cpapi);
        $this->smarty->assign('cpurl', $cpurl);

        $catarray = [];
        if ((int) $category !== -1) {
            $catarray[] = $category;
        }

        $this->smarty->assign('catlist', $mtmp);
        $this->smarty->assign('category', $category);
        $this->smarty->assign('categorytitle', $id);

        $page = $request->has('page') ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_cover_page');

        $ordering = $movie->getMovieOrdering();
        $orderby = request()->has('ob') && \in_array(request()->input('ob'), $ordering, false) ? request()->input('ob') : '';

        $movies = [];
        $rslt = $movie->getMovieRange($page, $catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, -1, $this->userdata['categoryexclusions']);
        $results = new LengthAwarePaginator($rslt, $rslt['_totalcount'], config('nntmux.items_per_cover_page'), $page, ['path' => $request->url()]);

        foreach ($results as $result) {
            if (!empty($result->id)) {
                $result->genre = makeFieldLinks((array) $result, 'genre', 'movies');
                $result->actors = makeFieldLinks((array) $result, 'actors', 'movies');
                $result->director = makeFieldLinks((array) $result, 'director', 'movies');
                $result->languages = explode(', ', $result->language);

                $movies[] = $result;
            }
        }

        $title = ($request->has('title') && ! empty($request->input('title'))) ? stripslashes($request->input('title')) : '';
        $this->smarty->assign('title', $title);

        $actors = ($request->has('actors') && ! empty($request->input('actors'))) ? stripslashes($request->input('actors')) : '';
        $this->smarty->assign('actors', $actors);

        $director = ($request->has('director') && ! empty($request->input('director'))) ? stripslashes($request->input('director')) : '';
        $this->smarty->assign('director', $director);

        $ratings = range(1, 9);
        $rating = ($request->has('rating') && \in_array($request->input('rating'), $ratings, false)) ? $request->input('rating') : '';
        $this->smarty->assign('ratings', $ratings);
        $this->smarty->assign('rating', $rating);

        $genres = $movie->getGenres();
        $genre = ($request->has('genre') && \in_array($request->input('genre'), $genres, false)) ? $request->input('genre') : '';
        $this->smarty->assign('genres', $genres);
        $this->smarty->assign('genre', $genre);

        $years = range(1903, Carbon::now()->addYear()->year);
        rsort($years);
        $year = ($request->has('year') && \in_array($request->input('year'), $years, false)) ? $request->input('year') : '';
        $this->smarty->assign('years', $years);
        $this->smarty->assign('year', $year);

        if ((int) $category === -1) {
            $this->smarty->assign('catname', 'All');
        } else {
            $cdata = Category::find($category);
            if ($cdata) {
                $this->smarty->assign('catname', $cdata->parent !== null ? $cdata->parent->title.' > '.$cdata->title : $cdata->title);
            } else {
                $this->show404();
            }
        }

        $this->smarty->assign(
            [
                'resultsadd'=>  $movies,
                'results' => $results,
            ]
        );

        $meta_title = 'Browse Movies';
        $meta_keywords = 'browse,nzb,description,details';
        $meta_description = 'Browse for Movies';

        if ($request->has('imdb')) {
            $content = $this->smarty->fetch('viewmoviefull.tpl');
        } else {
            $content = $this->smarty->fetch('movies.tpl');
        }

        $this->smarty->assign([
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]);
        $this->pagerender();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function showTrailer(Request $request)
    {
        $movie = new Movie;

        if ($request->has('id') && ctype_digit($request->input('id'))) {
            $mov = $movie->getMovieInfo($request->input('id'));

            if (! $mov) {
                $this->show404();
            }

            $this->smarty->assign('movie', $mov);

            $title = 'Info for '.$mov['title'];
            $meta_title = '';
            $meta_keywords = '';
            $meta_description = '';
            $this->smarty->registerPlugin('modifier', 'ss', 'stripslashes');

            $modal = false;
            if ($request->has('modal')) {
                $modal = true;
                $this->smarty->assign('modal', true);
            }

            $content = $this->smarty->fetch('viewmovietrailer.tpl');

            if ($modal) {
                echo $content;
            } else {
                $this->smarty->assign([
                    'content' => $content,
                    'title' => $title,
                    'meta_title' => $meta_title,
                    'meta_keywords' => $meta_keywords,
                    'meta_description' => $meta_description,
                ]);
                $this->pagerender();
            }
        }
    }
}
