<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use Blacklight\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

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
     *
     * @throws \Exception
     */
    public function showMovies(Request $request)
    {
        $this->setPrefs();
        $movie = new Movie(['Settings' => $this->settings]);

        $moviecats = Category::getChildren(Category::MOVIE_ROOT);
        $mtmp = [];
        foreach ($moviecats as $mcat) {
            $mtmp[$mcat['id']] = $mcat;
        }

        $category = $request->has('imdb') ? -1 : Category::MOVIE_ROOT;
        if ($request->has('t') && array_key_exists($request->input('t'), $mtmp)) {
            $category = $request->input('t') + 0;
        }

        $user = User::find(Auth::id());
        $cpapi = $user['cp_api'];
        $cpurl = $user['cp_url'];
        $this->smarty->assign('cpapi', $cpapi);
        $this->smarty->assign('cpurl', $cpurl);

        $catarray = [];
        if ((int) $category !== -1) {
            $catarray[] = $category;
        }

        $this->smarty->assign('catlist', $mtmp);
        $this->smarty->assign('category', $category);

        $page = $request->has('page') ? $request->input('page') : 1;

        $movies = [];
        $results = $movie->getMovieRange($page, $catarray, $this->userdata['categoryexclusions']);
        foreach ($results as $result) {
            $result['genre'] = makeFieldLinks($result, 'genre', 'movies');
            $result['actors'] = makeFieldLinks($result, 'actors', 'movies');
            $result['director'] = makeFieldLinks($result, 'director', 'movies');
            $result['languages'] = explode(', ', $result['language']);

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

        $this->smarty->assign('results', $movies);

        $meta_title = 'Browse Nzbs';
        $meta_keywords = 'browse,nzb,description,details';
        $meta_description = 'Browse for Nzbs';

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
