<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Blacklight\Console;
use Blacklight\Genres;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ConsoleController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function show(Request $request, string $id = '')
    {
        if ($id === 'WiiVare') {
            $id = 'WiiVareVC';
        }
        $console = new Console;
        $gen = new Genres;

        $concats = Category::getChildren(Category::GAME_ROOT);
        $ctmp = [];
        foreach ($concats as $ccat) {
            $ctmp[] =
                [
                    'id' => $ccat->id,
                    'title' => $ccat->title,
                ];
        }
        $category = $request->has('t') ? $request->input('t') : Category::GAME_ROOT;
        if ($id && \in_array($id, Arr::pluck($ctmp, 'title'), false)) {
            $cat = Category::query()
                ->where('title', $id)
                ->where('root_categories_id', '=', Category::GAME_ROOT)
                ->first(['id']);
            $category = $cat !== null ? $cat['id'] : Category::GAME_ROOT;
        }

        $catarray = [];
        $catarray[] = $category;

        $ordering = $console->getConsoleOrdering();
        $orderby = $request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '';
        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_cover_page');

        $consoles = [];
        $rslt = $console->getConsoleRange($page, $catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_cover_page'), $page, $request->url(), $request->query());

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

        $platform = ($request->has('platform') && ! empty($request->input('platform'))) ? stripslashes($request->input('platform')) : '';
        $title = ($request->has('title') && ! empty($request->input('title'))) ? stripslashes($request->input('title')) : '';

        $genres = $gen->getGenres(Genres::CONSOLE_TYPE, true);
        $tmpgnr = [];
        foreach ($genres as $gn) {
            $tmpgnr[$gn->id] = $gn->title;
        }
        $genre = ($request->has('genre') && array_key_exists($request->input('genre'), $tmpgnr)) ? $request->input('genre') : '';

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
