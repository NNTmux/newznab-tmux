<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\MovieBrowseService;
use App\Services\MovieService;
use App\Support\TrailerUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MovieController extends BasePageController
{
    protected MovieBrowseService $movieBrowseService;

    protected MovieService $movieService;

    public function __construct(MovieBrowseService $movieBrowseService, MovieService $movieService)
    {
        parent::__construct();
        $this->movieBrowseService = $movieBrowseService;
        $this->movieService = $movieService;
    }

    /**
     * @throws \Exception
     */
    public function showMovies(Request $request, string $id = ''): mixed
    {
        $moviecats = Category::getChildren(Category::MOVIE_ROOT)->map(function ($mcat) {
            return ['id' => $mcat->id, 'title' => $mcat->title];
        });

        $category = $request->has('imdb') ? -1 : $this->integerInput($request, 't', Category::MOVIE_ROOT);
        if ($id && $moviecats->pluck('title')->contains($id)) {
            $cat = Category::where(['title' => $id, 'root_categories_id' => Category::MOVIE_ROOT])->first(['id']);
            $category = $cat !== null ? (int) $cat->id : Category::MOVIE_ROOT;
        }

        $catarray = $category !== -1 ? [$category] : [];

        $page = $this->resolvePage($request);
        $perPage = (int) config('nntmux.items_per_cover_page');
        $offset = $this->paginationOffset($page, $perPage);
        $ordering = $this->movieBrowseService->getMovieOrdering();
        $orderby = $this->resolveOrderBy($request, $ordering);

        $rslt = $this->movieBrowseService->getMovieRange($page, $catarray, $offset, $perPage, $orderby, -1, (array) $this->userdata->categoryexclusions);
        $totalCount = $rslt->isNotEmpty() ? ($rslt[0]->_totalcount ?? 0) : 0;
        $results = $this->paginate($rslt ?? [], $totalCount, $perPage, $page, $request->url(), $request->query());

        $movies = $results->map(function ($result) {
            $result->genre = makeFieldLinks($result, 'genre', 'movies');
            $result->actors = makeFieldLinks($result, 'actors', 'movies');
            $result->director = makeFieldLinks($result, 'director', 'movies');

            // Add cover image URL using helper function
            $result->cover = getReleaseCover($result);

            return $result;
        });

        $years = range(1903, now()->addYear()->year);
        rsort($years);

        $genres = $this->movieBrowseService->getGenres();
        $ratingInput = $this->integerInput($request, 'rating', 0);
        $genreInput = $this->scalarInput($request, 'genre');
        $yearInput = $this->integerInput($request, 'year', 0);

        $catname = $category === -1 ? 'All' : Category::find($category) ?? 'All';

        $this->viewData = array_merge($this->viewData, [
            'cpapi' => $this->userdata->cp_api,
            'cpurl' => $this->userdata->cp_url,
            'catlist' => $moviecats,
            'ordering' => $ordering,
            'category' => $category,
            'categorytitle' => $id,
            'title' => stripslashes($this->scalarInput($request, 'title')),
            'actors' => stripslashes($this->scalarInput($request, 'actors')),
            'director' => stripslashes($this->scalarInput($request, 'director')),
            'ratings' => range(1, 9),
            'rating' => \in_array($ratingInput, range(1, 9), true) ? $ratingInput : '',
            'genres' => $genres,
            'genre' => \in_array($genreInput, $genres, true) ? $genreInput : '',
            'years' => $years,
            'year' => \in_array($yearInput, $years, true) ? $yearInput : '',
            'catname' => $catname,
            'resultsadd' => $movies,
            'results' => $results,
            'covgroup' => 'movies',
            'meta_title' => 'Browse Movies',
            'meta_keywords' => 'browse,nzb,description,details',
            'meta_description' => 'Browse for Movies',
            'movie_layout' => $this->userdata->movie_layout ?? 2,
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
    public function showMovie(Request $request, string $imdbid): mixed
    {
        // Get movie info
        $movieInfo = $this->movieService->getMovieInfo($imdbid);

        if (! $movieInfo) {
            return redirect()->route('Movies')->with('error', 'Movie not found');
        }

        // Convert Eloquent model to array
        $movieArray = $movieInfo->toArray();

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
        $movieArray['trailer_url'] = TrailerUrl::watchUrl($movieArray['trailer'] ?? null);

        // Get all releases for this movie directly (no limit)
        $releases = $this->movieBrowseService->getMovieReleases($imdbid, (array) $this->userdata->categoryexclusions);

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
     * @return JsonResponse|View
     */
    public function showTrailer(Request $request)
    {
        $movieId = $this->scalarInput($request, 'id');
        if ($movieId !== '' && ctype_digit($movieId)) {
            $mov = $this->movieService->getMovieInfo($movieId);

            if (! $mov) {
                return response()->json(['message' => 'There is no trailer for this movie.'], 404);
            }

            $mov['trailer_url'] = TrailerUrl::watchUrl($mov['trailer'] ?? null);
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

    /**
     * Show trending movies (top 15 most downloaded in last 48 hours)
     *
     * @throws \Exception
     */
    public function showTrending(Request $request): mixed
    {

        // Cache key for trending movies (48 hours)
        $cacheKey = 'trending_movies_top_15_48h';

        // Get trending movies from cache or calculate (refresh every hour)
        $trendingMovies = Cache::remember($cacheKey, 3600, function () {
            // Calculate timestamp for 48 hours ago
            $fortyEightHoursAgo = Carbon::now()->subHours(48);

            // Get movies with their download counts from last 48 hours
            // Join with user_downloads to get actual download timestamps
            $query = DB::table('movieinfo as m')
                ->join('releases as r', 'm.imdbid', '=', 'r.imdbid')
                ->leftJoin('user_downloads as ud', 'r.id', '=', 'ud.releases_id')
                ->select([
                    'm.imdbid',
                    'm.title',
                    'm.year',
                    'm.rating',
                    'm.plot',
                    'm.genre',
                    'm.cover',
                    'm.tmdbid',
                    'm.traktid',
                    DB::raw('COUNT(DISTINCT ud.id) as total_downloads'),
                    DB::raw('COUNT(DISTINCT r.id) as release_count'),
                ])
                ->where('m.title', '!=', '')
                ->whereNotNull('m.imdbid')
                ->where('m.imdbid', '!=', '')
                ->where('ud.timestamp', '>=', $fortyEightHoursAgo)
                ->groupBy('m.imdbid', 'm.title', 'm.year', 'm.rating', 'm.plot', 'm.genre', 'm.cover', 'm.tmdbid', 'm.traktid')
                ->havingRaw('COUNT(DISTINCT ud.id) > 0')
                ->orderByDesc('total_downloads')
                ->limit(15)
                ->get();

            // Process the results
            return $query->map(function ($item) {
                // Add cover image URL using helper function
                $coverArray = [
                    'imdbid' => $item->imdbid,
                    'tmdbid' => $item->tmdbid,
                    'cover' => $item->cover,
                ];
                $item->cover = getReleaseCover($coverArray);

                return $item;
            });
        });

        $this->viewData = array_merge($this->viewData, [
            'trendingMovies' => $trendingMovies,
            'meta_title' => 'Trending Movies - Last 48 Hours',
            'meta_keywords' => 'trending,movies,popular,downloads,recent',
            'meta_description' => 'Browse the most popular and downloaded movies in the last 48 hours',
        ]);

        return view('movies.trending', $this->viewData);
    }

    /**
     * Update user's movie layout preference
     */
    public function updateLayout(Request $request): mixed
    {
        $request->validate([
            'layout' => 'required|integer|in:1,2',
        ]);

        $user = auth()->user();
        if ($user) {
            $user->movie_layout = (int) $request->input('layout');
            $user->save();

            return response()->json(['success' => true, 'layout' => $user->movie_layout]);
        }

        return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
    }
}
