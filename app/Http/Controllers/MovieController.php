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
    public function showMovies(Request $request, string $id = ''): void
    {
        $this->setPreferences();
        $movie = new Movie;

        $moviecats = Category::getChildren(Category::MOVIE_ROOT)->map(function ($mcat) {
            return ['id' => $mcat->id, 'title' => $mcat->title];
        });

        $category = $request->has('imdb') ? -1 : ($request->input('t', Category::MOVIE_ROOT));
        if ($id && $moviecats->pluck('title')->contains($id)) {
            $cat = Category::where(['title' => $id, 'root_categories_id' => Category::MOVIE_ROOT])->first(['id']);
            $category = $cat->id ?? Category::MOVIE_ROOT;
        }

        $this->smarty->assign('cpapi', $this->userdata->cp_api);
        $this->smarty->assign('cpurl', $this->userdata->cp_url);

        $catarray = $category !== -1 ? [$category] : [];

        $this->smarty->assign('catlist', $moviecats);
        $this->smarty->assign('category', $category);
        $this->smarty->assign('categorytitle', $id);

        $page = $request->input('page', 1);
        $offset = ($page - 1) * config('nntmux.items_per_cover_page');

        $orderby = $request->input('ob', '');
        $ordering = $movie->getMovieOrdering();
        if (! in_array($orderby, $ordering, false)) {
            $orderby = '';
        }

        $rslt = $movie->getMovieRange($page, $catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, -1, $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_cover_page'), $page, $request->url(), $request->query());

        // First process the original data mapping that was removed
        $movies = $results->map(function ($result) {
            $result['genre'] = makeFieldLinks($result, 'genre', 'movies');
            $result['actors'] = makeFieldLinks($result, 'actors', 'movies');
            $result['director'] = makeFieldLinks($result, 'director', 'movies');
            $result['languages'] = explode(', ', $result['language']);

            return $result;
        });

        // Then move heavy processing from frontend template to backend
        $processedMovies = $movie->processMovieDataForDisplay($movies->toArray());

        // Update the results collection with processed data
        $results->setCollection(collect($processedMovies));

        $this->smarty->assign('title', stripslashes($request->input('title', '')));
        $this->smarty->assign('actors', stripslashes($request->input('actors', '')));
        $this->smarty->assign('director', stripslashes($request->input('director', '')));
        $this->smarty->assign('ratings', range(1, 9));
        $this->smarty->assign('rating', $request->input('rating', ''));
        $this->smarty->assign('genres', $movie->getGenres());
        $this->smarty->assign('genre', $request->input('genre', ''));
        $years = range(1903, now()->addYear()->year);
        rsort($years);
        $this->smarty->assign('years', $years);
        $this->smarty->assign('year', $request->input('year', ''));

        $catname = $category === -1 ? 'All' : Category::find($category) ?? 'All';
        $this->smarty->assign('catname', $catname);

        $this->smarty->assign([
            'resultsadd' => $movies,
            'results' => $results,
            'covgroup' => 'movies',
        ]);

        $meta_title = 'Browse Movies';
        $meta_keywords = 'browse,nzb,description,details';
        $meta_description = 'Browse for Movies';

        $content = $request->has('imdb') ? $this->smarty->fetch('viewmoviefull.tpl') : $this->smarty->fetch('movies.tpl');
        $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));
        $this->pagerender();
    }

    /**
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function showTrailer(Request $request)
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
