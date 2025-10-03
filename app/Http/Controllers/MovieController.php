<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Blacklight\Movie;
use Illuminate\Http\Request;

class MovieController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function showMovies(Request $request, string $id = '')
    {
        $this->setPreferences();
        $movie = new Movie(['Settings' => $this->settings]);

        $moviecats = Category::getChildren(Category::MOVIE_ROOT)->map(function ($mcat) {
            return ['id' => $mcat->id, 'title' => $mcat->title];
        });

        $category = $request->has('imdb') ? -1 : ($request->input('t', Category::MOVIE_ROOT));
        if ($id && $moviecats->pluck('title')->contains($id)) {
            $cat = Category::where(['title' => $id, 'root_categories_id' => Category::MOVIE_ROOT])->first(['id']);
            $category = $cat->id ?? Category::MOVIE_ROOT;
        }

        $catarray = $category !== -1 ? [$category] : [];

        $page = $request->input('page', 1);
        $offset = ($page - 1) * config('nntmux.items_per_cover_page');

        $orderby = $request->input('ob', '');
        $ordering = $movie->getMovieOrdering();
        if (! in_array($orderby, $ordering, false)) {
            $orderby = '';
        }

        $rslt = $movie->getMovieRange($page, $catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, -1, $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_cover_page'), $page, $request->url(), $request->query());

        $movies = $results->map(function ($result) {
            $result['genre'] = makeFieldLinks($result, 'genre', 'movies');
            $result['actors'] = makeFieldLinks($result, 'actors', 'movies');
            $result['director'] = makeFieldLinks($result, 'director', 'movies');
            $result['languages'] = explode(', ', $result['language']);

            return $result;
        });

        $years = range(1903, now()->addYear()->year);
        rsort($years);

        $catname = $category === -1 ? 'All' : Category::find($category) ?? 'All';

        $this->viewData = array_merge($this->viewData, [
            'cpapi' => $this->userdata->cp_api,
            'cpurl' => $this->userdata->cp_url,
            'catlist' => $moviecats,
            'category' => $category,
            'categorytitle' => $id,
            'title' => stripslashes($request->input('title', '')),
            'actors' => stripslashes($request->input('actors', '')),
            'director' => stripslashes($request->input('director', '')),
            'ratings' => range(1, 9),
            'rating' => $request->input('rating', ''),
            'genres' => $movie->getGenres(),
            'genre' => $request->input('genre', ''),
            'years' => $years,
            'year' => $request->input('year', ''),
            'catname' => $catname,
            'resultsadd' => $movies,
            'results' => $results,
            'covgroup' => 'movies',
            'meta_title' => 'Browse Movies',
            'meta_keywords' => 'browse,nzb,description,details',
            'meta_description' => 'Browse for Movies',
        ]);

        // Return the appropriate view
        $viewName = $request->has('imdb') ? 'movies.viewmoviefull' : 'movies.index';

        return view($viewName, $this->viewData);
    }

    /**
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function showTrailer(Request $request)
    {
        $movie = new Movie;

        if ($request->has('id') && ctype_digit($request->input('id'))) {
            $mov = $movie->getMovieInfo($request->input('id'));

            if (! $mov) {
                return response()->json(['message' => 'There is no trailer for this movie.'], 404);
            }

            $modal = $request->has('modal');

            $viewData = [
                'movie' => $mov,
            ];

            // Return different views for modal vs full page
            if ($modal) {
                return view('movies.trailer-modal', $viewData);
            }

            $this->viewData = array_merge($this->viewData, [
                'movie' => $mov,
                'title' => 'Info for '.$mov['title'],
                'meta_title' => '',
                'meta_keywords' => '',
                'meta_description' => '',
            ]);

            return view('movies.viewmovietrailer', $this->viewData);
        }

        return response()->json(['message' => 'Invalid movie ID.'], 400);
    }
}
