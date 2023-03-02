<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Blacklight\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MovieController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function showMovies(Request $request, string $id = ''): void
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

        $category = $request->has('imdb') ? -1 : ($request->has('t') ? $request->input('t') : Category::MOVIE_ROOT);
        if ($id && \in_array($id, Arr::pluck($mtmp, 'title'), false)) {
            $cat = Category::query()
                ->where(['title' => $id, 'root_categories_id' => Category::MOVIE_ROOT])
                ->first(['id']);
            $category = $cat !== null ? $cat['id'] : Category::MOVIE_ROOT;
        }

        $this->smarty->assign('cpapi', $this->userdata->cp_api);
        $this->smarty->assign('cpurl', $this->userdata->cp_url);

        $catarray = [];
        if ((int) $category !== -1) {
            $catarray[] = $category;
        }

        $this->smarty->assign('catlist', $mtmp);
        $this->smarty->assign('category', $category);
        $this->smarty->assign('categorytitle', $id);

        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_cover_page');

        $ordering = $movie->getMovieOrdering();
        $orderby = request()->has('ob') && \in_array(request()->input('ob'), $ordering, false) ? request()->input('ob') : '';

        $movies = [];
        $rslt = $movie->getMovieRange($page, $catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, -1, $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_cover_page'), $page, $request->url(), $request->query());

        foreach ($results as $result) {
            $result->genre = makeFieldLinks($result, 'genre', 'movies');
            $result->actors = makeFieldLinks($result, 'actors', 'movies');
            $result->director = makeFieldLinks($result, 'director', 'movies');
            $result->languages = explode(', ', $result->language);

            $movies[] = $result;
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

        $years = range(1903, now()->addYear()->year);
        rsort($years);
        $year = ($request->has('year') && \in_array($request->input('year'), $years, false)) ? $request->input('year') : '';
        $this->smarty->assign('years', $years);
        $this->smarty->assign('year', $year);

        if ((int) $category === -1) {
            $this->smarty->assign('catname', 'All');
        } else {
            $cdata = Category::find($category);
            if ($cdata !== null) {
                $this->smarty->assign('catname', $cdata);
            } else {
                $this->smarty->assign('catname', 'All');
            }
        }

        $this->smarty->assign(
            [
                'resultsadd' => $movies,
                'results' => $results,
                'covgroup' => 'movies',
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

        $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));
        $this->pagerender();
    }

    /**
     * @throws \Exception
     */
    public function showTrailer(Request $request): \Illuminate\Http\JsonResponse
    {
        $movie = new Movie;

        if ($request->has('id') && ctype_digit($request->input('id'))) {
            $mov = $movie->getMovieInfo($request->input('id'));

            if (! $mov) {
                return response()->json(['message' => 'There is no trailer for this movie.'], 404);
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
                $this->smarty->assign(compact('content', 'title', 'meta_title', 'meta_keywords', 'meta_description'));
                $this->pagerender();
            }
        }
    }
}
