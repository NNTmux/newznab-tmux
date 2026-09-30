<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\UserMovie;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\MovieBrowseService;
use App\Services\MovieService;
use Illuminate\Http\Request;

class MyMoviesController extends BasePageController
{
    private MovieService $movieService;

    private MovieBrowseService $movieBrowseService;

    public function __construct(
        MovieService $movieService,
        MovieBrowseService $movieBrowseService
    ) {
        parent::__construct();
        $this->movieService = $movieService;
        $this->movieBrowseService = $movieBrowseService;
    }

    public function show(Request $request): mixed
    {
        $action = $this->scalarInput($request, 'id');
        $imdbid = $this->scalarInput($request, 'imdb');
        $from = $this->localReturnUrl($request, '/mymovies');

        $this->viewData['from'] = $from;

        switch ($action) {
            case 'delete':
                $movie = UserMovie::getMovie($this->userdata->id, $imdbid);
                if (! $movie) {
                    return redirect()->to('/mymovies');
                }
                UserMovie::delMovie($this->userdata->id, $imdbid);

                return redirect()->to($from);
            case 'add':
            case 'doadd':
                $movie = UserMovie::getMovie($this->userdata->id, $imdbid);
                if ($movie) {
                    return redirect()->to('/mymovies');
                }

                $movie = $this->movieService->getMovieInfo($imdbid);
                if (! $movie) {
                    return redirect()->to('/mymovies');
                }

                if ($action === 'doadd') {
                    $category = $this->selectedCategoryIds($request, $this->movieCategories(true));
                    UserMovie::addMovie($this->userdata->id, $imdbid, $category);

                    return redirect()->to($from);
                }

                $categories = $this->movieCategories(true);
                $this->viewData['type'] = 'add';
                $this->viewData['cat_ids'] = array_keys($categories);
                $this->viewData['cat_names'] = $categories;
                $this->viewData['cat_selected'] = [];
                $this->viewData['imdbid'] = $imdbid;
                $this->viewData['movie'] = $movie;
                $this->viewData['userdata'] = $this->userdata;
                $this->viewData['content'] = view('mymovies.add', $this->viewData)->render();

                return $this->pagerender();

            case 'edit':
            case 'doedit':
                $movie = UserMovie::getMovie($this->userdata->id, $imdbid);

                if (! $movie) {
                    return redirect()->to('/mymovies');
                }

                if ($action === 'doedit') {
                    $category = $this->selectedCategoryIds($request, $this->movieCategories(false));
                    UserMovie::updateMovie($this->userdata->id, $imdbid, $category);

                    return redirect()->to($from);
                }

                $categories = $this->movieCategories(false);

                $this->viewData['type'] = 'edit';
                $this->viewData['cat_ids'] = array_keys($categories);
                $this->viewData['cat_names'] = $categories;
                $this->viewData['cat_selected'] = ! empty($movie['categories']) ? explode('|', $movie['categories']) : [];
                $this->viewData['imdbid'] = $imdbid;
                $this->viewData['movie'] = $movie;
                $this->viewData['userdata'] = $this->userdata;
                $this->viewData['content'] = view('mymovies.add', $this->viewData)->render();

                return $this->pagerender();

            case 'browse':

                $title = 'Browse My Movies';
                $meta_title = 'My Movies';
                $meta_keywords = 'search,add,to,cart,nzb,description,details';
                $meta_description = 'Browse Your Movies';

                $page = $this->resolvePage($request);
                $perPage = (int) config('nntmux.items_per_cover_page');
                $offset = $this->paginationOffset($page, $perPage);

                $movies = UserMovie::getMovies($this->userdata->id);
                /** @var array<string, string> $categories */
                $categories = $this->movieCategories(false);
                foreach ($movies as $moviek => $movie) {
                    $showcats = explode('|', $movie['categories']);
                    if (\count($showcats) > 0) {
                        $catarr = [];
                        foreach ($showcats as $scat) {
                            if (! empty($scat) && isset($categories[$scat])) {
                                $catarr[] = $categories[$scat];
                            }
                        }
                        $movie['categoryNames'] = implode(', ', $catarr);
                    } else {
                        $movie['categoryNames'] = '';
                    }

                    $movies[$moviek] = $movie;
                }

                $ordering = $this->movieBrowseService->getMovieOrdering();
                $orderby = $this->resolveOrderBy($request, $ordering);

                $results = $this->movieBrowseService->getMovieRange($page, [], $offset, $perPage, $orderby, -1, (array) $this->userdata->categoryexclusions);

                $this->viewData['covgroup'] = '';

                foreach ($ordering as $ordertype) {
                    $this->viewData['orderby'.$ordertype] = url('/mymovies/browse?ob='.$ordertype.'&amp;offset=0');
                }

                $this->viewData['lastvisit'] = $this->userdata->lastlogin;
                $this->viewData['results'] = $results;
                $this->viewData['resultsadd'] = $results;
                $this->viewData['movies'] = true;
                /** @var view-string $browseView */
                $browseView = 'browse.index';
                $this->viewData['content'] = view($browseView, $this->viewData)->render();
                $this->viewData = array_merge($this->viewData, compact('title', 'meta_title', 'meta_keywords', 'meta_description'));

                return $this->pagerender();

            default:

                $title = 'My Movies';
                $meta_title = 'My Movies';
                $meta_keywords = 'search,add,to,cart,nzb,description,details';
                $meta_description = 'Manage Your Movies';

                $categories = $this->movieCategories(false);

                $movies = UserMovie::getMovies($this->userdata->id);
                $results = [];
                foreach ($movies as $moviek => $movie) {
                    $showcats = explode('|', $movie['categories'] ?? '');
                    if (\count($showcats) > 0) {
                        $catarr = [];
                        foreach ($showcats as $scat) {
                            if (! empty($scat) && isset($categories[$scat])) {
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
                $this->viewData['userdata'] = $this->userdata;
                $this->viewData['content'] = view('mymovies.index', $this->viewData)->render();
                $this->viewData = array_merge($this->viewData, compact('title', 'meta_title', 'meta_keywords', 'meta_description'));

                return $this->pagerender();
        }

    }

    /**
     * @return array<int, string>
     */
    private function movieCategories(bool $excludeDisabledWebdl): array
    {
        $categories = [];
        foreach (Category::getChildren(Category::MOVIE_ROOT) as $category) {
            if ($excludeDisabledWebdl && (int) $category['id'] === Category::MOVIE_WEBDL && (int) app(ConfigurationProvider::class)->ingestion()->categorizeWebDl === 0) {
                continue;
            }

            $categories[(int) $category['id']] = (string) $category['title'];
        }

        return $categories;
    }

    /**
     * @param  array<int, string>  $allowedCategories
     * @return array<int, int>
     */
    private function selectedCategoryIds(Request $request, array $allowedCategories): array
    {
        $selected = [];
        foreach ($this->arrayInput($request, 'category') as $categoryId) {
            if (is_scalar($categoryId) && preg_match('/^\d+$/', (string) $categoryId) === 1 && isset($allowedCategories[(int) $categoryId])) {
                $selected[] = (int) $categoryId;
            }
        }

        return array_values(array_unique($selected));
    }
}
