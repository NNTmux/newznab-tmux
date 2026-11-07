<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Genre;
use App\Services\GenreService;
use App\Services\MusicService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MusicController extends BasePageController
{
    protected MusicService $musicService;

    protected GenreService $genreService;

    public function __construct(MusicService $musicService, GenreService $genreService)
    {
        parent::__construct();
        $this->musicService = $musicService;
        $this->genreService = $genreService;
    }

    /**
     * @throws \Exception
     */
    public function show(Request $request, string $id = ''): mixed
    {
        $musiccats = Category::getChildren(Category::MUSIC_ROOT);
        $mtmp = [];
        foreach ($musiccats as $mcat) {
            $mtmp[] =
                [
                    'id' => $mcat->id,
                    'title' => $mcat->title,
                ];
        }

        $category = $request->has('t') ? $this->scalarInput($request, 't', (string) Category::MUSIC_ROOT) : Category::MUSIC_ROOT;
        if ($id && \in_array($id, Arr::pluck($mtmp, 'title'), true)) {
            $cat = Category::query()
                ->where('title', $id)
                ->where('root_categories_id', '=', Category::MUSIC_ROOT)
                ->first(['id']);
            $category = $cat !== null ? (int) $cat['id'] : Category::MUSIC_ROOT;
        }

        $catarray = [];
        $catarray[] = (int) $category;

        $page = $this->resolvePage($request);
        $perPage = (int) config('nntmux.items_per_cover_page');
        $offset = $this->paginationOffset($page, $perPage);
        $ordering = $this->musicService->getMusicOrdering();
        $orderby = $this->resolveOrderBy($request, $ordering);

        $musics = [];
        $rslt = $this->musicService->getMusicRange($page, $catarray, $offset, $perPage, $orderby, (array) $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, $perPage, $page, $request->url(), $request->query());

        $artistInput = $this->scalarInput($request, 'artist');
        $artist = $artistInput !== '' ? stripslashes($artistInput) : '';

        $titleInput = $this->scalarInput($request, 'title');
        $title = $titleInput !== '' ? stripslashes($titleInput) : '';

        $genres = $this->genreService->getGenres((string) GenreService::MUSIC_TYPE, true);
        $tmpgnr = [];
        foreach ($genres as $gn) {
            /** @var Genre $gn */
            $tmpgnr[$gn->id] = $gn->title;
        }

        foreach ($results as $result) {
            $res = $result;
            $result->genre = $tmpgnr[$res->genres_id] ?? '';
            $musics[] = $result;
        }

        $genreInput = $this->scalarInput($request, 'genre');
        $genre = isset($tmpgnr[$genreInput]) ? $genreInput : '';

        $years = range(1950, date('Y') + 1);
        rsort($years);
        $yearInput = $this->scalarInput($request, 'year');
        $yearValue = is_numeric($yearInput) ? (int) $yearInput : null;
        $year = $yearValue !== null && \in_array($yearValue, $years, true) ? $yearValue : '';

        if ((int) $category === -1) {
            $catname = 'All';
        } else {
            $cdata = Category::find($category);
            if ($cdata !== null) {
                $catname = $cdata->title;
            } else {
                $catname = 'All';
            }
        }

        // Build order by URLs
        $orderByUrls = [];
        foreach ($ordering as $orderType) {
            $orderByUrls['orderby'.$orderType] = url('music/'.($id ?: 'All').'?ob='.$orderType);
        }

        $this->viewData = array_merge($this->viewData, [
            'catlist' => $mtmp,
            'ordering' => $ordering,
            'category' => $category,
            'categorytitle' => $id,
            'catname' => $catname,
            'artist' => $artist,
            'title' => $title,
            'genres' => $genres,
            'genre' => $genre,
            'years' => $years,
            'year' => $year,
            'resultsadd' => $musics,
            'results' => $results,
            'covgroup' => 'music',
            'meta_title' => 'Browse Albums',
            'meta_keywords' => 'browse,nzb,albums,description,details',
            'meta_description' => 'Browse for Albums',
        ], $orderByUrls);

        return view('music.index', $this->viewData);
    }
}
