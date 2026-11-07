<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Genre;
use App\Services\ConsoleService;
use App\Services\GenreService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ConsoleController extends BasePageController
{
    protected ConsoleService $consoleService;

    public function __construct(ConsoleService $consoleService)
    {
        parent::__construct();
        $this->consoleService = $consoleService;
    }

    /**
     * @throws \Exception
     */
    public function show(Request $request, string $id = ''): mixed
    {
        if ($id === 'WiiVare') {
            $id = 'WiiVareVC';
        }
        $gen = new GenreService;

        $concats = Category::getChildren(Category::GAME_ROOT);
        $ctmp = [];
        foreach ($concats as $ccat) {
            $ctmp[] =
                [
                    'id' => $ccat->id,
                    'title' => $ccat->title,
                ];
        }
        $category = $request->has('t') ? $this->scalarInput($request, 't', (string) Category::GAME_ROOT) : Category::GAME_ROOT;
        if ($id && \in_array($id, Arr::pluck($ctmp, 'title'), false)) {
            $cat = Category::query()
                ->where('title', $id)
                ->where('root_categories_id', '=', Category::GAME_ROOT)
                ->first(['id']);
            $category = $cat !== null ? (int) $cat['id'] : Category::GAME_ROOT;
        }

        $catarray = [];
        $catarray[] = (int) $category;

        $ordering = $this->consoleService->getConsoleOrdering();
        $orderby = $this->resolveOrderBy($request, $ordering);
        $page = $this->resolvePage($request);
        $perPage = (int) config('nntmux.items_per_cover_page');
        $offset = $this->paginationOffset($page, $perPage);

        $consoles = [];
        $rslt = $this->consoleService->getConsoleRange($page, $catarray, $offset, $perPage, $orderby, (array) $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt, $rslt[0]->_totalcount ?? 0, $perPage, $page, $request->url(), $request->query());

        $maxwords = 50;
        foreach ($results as $result) {
            if (! empty($result->review)) {
                $words = explode(' ', $result->review);
                if (\count($words) > $maxwords) {
                    $newwords = \array_slice($words, 0, $maxwords);
                    $result->review = implode(' ', $newwords).'...';
                }
            }
            $consoles[] = $result;
        }

        $platformInput = $this->scalarInput($request, 'platform');
        $platform = $platformInput !== '' ? stripslashes($platformInput) : '';
        $titleInput = $this->scalarInput($request, 'title');
        $title = $titleInput !== '' ? stripslashes($titleInput) : '';

        $genres = $gen->getGenres((string) GenreService::CONSOLE_TYPE, true);
        $tmpgnr = [];
        foreach ($genres as $gn) {
            /** @var Genre $gn */
            $tmpgnr[$gn->id] = $gn->title;
        }
        $genreInput = $this->scalarInput($request, 'genre');
        $genre = isset($tmpgnr[$genreInput]) ? $genreInput : '';

        if ((int) $category === -1) {
            $catname = 'All';
        } else {
            $cdata = Category::find($category);
            if ($cdata !== null) {
                $catname = $cdata;
            } else {
                $catname = 'All';
            }
        }

        $this->viewData = array_merge($this->viewData, [
            'catlist' => $ctmp,
            'ordering' => $ordering,
            'category' => $category,
            'categorytitle' => $id,
            'platform' => $platform,
            'title' => $title,
            'genres' => $genres,
            'genre' => $genre,
            'catname' => $catname,
            'resultsadd' => $consoles,
            'results' => $results,
            'covgroup' => 'console',
            'meta_title' => 'Browse Console',
            'meta_keywords' => 'browse,nzb,console,games,description,details',
            'meta_description' => 'Browse for Console Games',
        ]);

        return view('console.index', $this->viewData);
    }
}
