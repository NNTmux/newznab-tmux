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

            // Add cover image URL using helper function
            $result['cover'] = getReleaseCover($result);

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
     * Show a single movie with all its releases
     *
     * @throws \Exception
     */
    public function showMovie(Request $request, string $imdbid)
    {
        $movie = new Movie(['Settings' => $this->settings]);

        // Get movie info
        $movieInfo = $movie->getMovieInfo($imdbid);

        if (! $movieInfo) {
            return redirect()->route('Movies')->with('error', 'Movie not found');
        }

        // Get all releases for this movie
        $rslt = $movie->getMovieRange(1, [], 0, 1000, '', -1, $this->userdata->categoryexclusions);

        // Filter to only this movie's IMDB ID
        $movieData = collect($rslt)->firstWhere('imdbid', $imdbid);

        if (! $movieData) {
            return redirect()->route('Movies')->with('error', 'No releases found for this movie');
        }

        // Process movie data - ensure we handle both objects and arrays
        if (is_object($movieInfo)) {
            // If it's an Eloquent model, use toArray()
            if (method_exists($movieInfo, 'toArray')) {
                $movieArray = $movieInfo->toArray();
            } else {
                $movieArray = get_object_vars($movieInfo);
            }
        } else {
            $movieArray = $movieInfo;
        }

        // Ensure we have at least the basic fields
        if (empty($movieArray['title'])) {
            $movieArray['title'] = 'Unknown Title';
        }
        if (empty($movieArray['imdbid'])) {
            $movieArray['imdbid'] = $imdbid;
        }

        // Only process fields if they exist and are not empty
        if (! empty($movieArray['genre'])) {
            $movieArray['genre'] = makeFieldLinks($movieArray, 'genre', 'movies');
        }
        if (! empty($movieArray['actors'])) {
            $movieArray['actors'] = makeFieldLinks($movieArray, 'actors', 'movies');
        }
        if (! empty($movieArray['director'])) {
            $movieArray['director'] = makeFieldLinks($movieArray, 'director', 'movies');
        }

        // Add cover image URL using helper function
        $movieArray['cover'] = getReleaseCover($movieArray);

        // Process all releases
        $releaseNames = isset($movieData->grp_release_name) ? explode('#', $movieData->grp_release_name) : [];
        $releaseSizes = isset($movieData->grp_release_size) ? explode(',', $movieData->grp_release_size) : [];
        $releaseGuids = isset($movieData->grp_release_guid) ? explode(',', $movieData->grp_release_guid) : [];
        $releasePostDates = isset($movieData->grp_release_postdate) ? explode(',', $movieData->grp_release_postdate) : [];
        $releaseAddDates = isset($movieData->grp_release_adddate) ? explode(',', $movieData->grp_release_adddate) : [];

        $releases = [];
        foreach ($releaseNames as $index => $releaseName) {
            if ($releaseName && isset($releaseGuids[$index])) {
                $releases[] = [
                    'name' => $releaseName,
                    'guid' => $releaseGuids[$index],
                    'size' => $releaseSizes[$index] ?? 0,
                    'postdate' => $releasePostDates[$index] ?? null,
                    'adddate' => $releaseAddDates[$index] ?? null,
                ];
            }
        }

        $this->viewData = array_merge($this->viewData, [
            'movie' => $movieArray,
            'releases' => $releases,
            'meta_title' => ($movieArray['title'] ?? 'Movie').' - Movie Details',
            'meta_keywords' => 'movie,details,releases',
            'meta_description' => 'View all releases for '.($movieArray['title'] ?? 'this movie'),
        ]);

        return view('movies.viewmoviefull', $this->viewData);
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
