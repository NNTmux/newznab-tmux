<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Genre;
use App\Services\GamesService;
use App\Services\GenreService;
use Illuminate\Http\Request;

class GamesController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function show(Request $request): mixed
    {
        $games = new GamesService;
        $gen = new GenreService;

        $concats = Category::getChildren(Category::PC_ROOT);
        $ctmp = [];
        foreach ($concats as $ccat) {
            $ctmp[$ccat['id']] = $ccat;
        }
        $category = Category::PC_GAMES;
        $categoryInput = $this->scalarInput($request, 't');
        if ($categoryInput !== '' && isset($ctmp[$categoryInput])) {
            $category = (int) $categoryInput;
        }

        $catarray = [];
        $catarray[] = $category;

        $page = $this->resolvePage($request);
        $ordering = $games->getGamesOrdering();
        $orderby = $this->resolveOrderBy($request, $ordering);
        $perPage = (int) config('nntmux.items_per_cover_page');
        $offset = $this->paginationOffset($page, $perPage);
        $rslt = $games->getGamesRange($page, $catarray, $offset, $perPage, $orderby, '', (array) $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt, $rslt[0]->_totalcount ?? 0, $perPage, $page, $request->url(), $request->query());

        $titleInput = $this->scalarInput($request, 'title');
        $title = $titleInput !== '' ? stripslashes($titleInput) : '';

        $genres = $gen->getGenres((string) GenreService::GAME_TYPE, true);
        $tmpgnr = [];
        foreach ($genres as $gn) {
            /** @var Genre $gn */
            $tmpgnr[$gn->id] = $gn->title;
        }

        $years = range(1903, date('Y') + 1);
        rsort($years);
        $yearInput = $this->scalarInput($request, 'year');
        $yearValue = is_numeric($yearInput) ? (int) $yearInput : null;
        $year = $yearValue !== null && \in_array($yearValue, $years, true) ? $yearValue : '';

        $genreInput = $this->scalarInput($request, 'genre');
        $genre = isset($tmpgnr[$genreInput]) ? $genreInput : '';

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
            $orderByUrls['orderby'.$orderType] = url('Games?ob='.$orderType);
        }

        $this->viewData = array_merge($this->viewData, [
            'catlist' => $ctmp,
            'ordering' => $ordering,
            'category' => $category,
            'catname' => $catname,
            'title' => $title,
            'genres' => $genres,
            'genre' => $genre,
            'years' => $years,
            'year' => $year,
            'results' => $results,
            'covgroup' => 'games',
            'meta_title' => 'Browse Games',
            'meta_keywords' => 'browse,nzb,games,description,details',
            'meta_description' => 'Browse for Games',
        ], $orderByUrls);

        return view('games.index', $this->viewData);
    }
}
