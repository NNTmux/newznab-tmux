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
     * @return void
     *
     * @throws \Exception
     */
    public function show(Request $request, string $id = '')
    {
        $this->setPreferences();
        if ($id === 'WiiVare') {
            $id = 'WiiVareVC';
        }
        $console = new Console(['Settings' => $this->settings]);
        $gen = new Genres(['Settings' => $this->settings]);

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

        $this->smarty->assign('catlist', $ctmp);
        $this->smarty->assign('category', $category);
        $this->smarty->assign('categorytitle', $id);

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
        $this->smarty->assign('platform', $platform);

        $title = ($request->has('title') && ! empty($request->input('title'))) ? stripslashes($request->input('title')) : '';
        $this->smarty->assign('title', $title);

        $genres = $gen->getGenres(Genres::CONSOLE_TYPE, true);
        $tmpgnr = [];
        foreach ($genres as $gn) {
            $tmpgnr[$gn->id] = $gn->title;
        }
        $genre = ($request->has('genre') && array_key_exists($request->input('genre'), $tmpgnr)) ? $request->input('genre') : '';
        $this->smarty->assign('genres', $genres);
        $this->smarty->assign('genre', $genre);

        if ((int) $category === -1) {
            $this->smarty->assign('catname', 'All');
        } else {
            $cdata = Category::find($category);
            if ($cdata !== null) {
                $this->smarty->assign('catname', $cdata);
            } else {
                $this->smarty->assign('catname', 'All');
            }
        }

        $this->smarty->assign(
            [
                'resultsadd' => $consoles,
                'results' => $results,
                'covgroup' => 'console',
            ]
        );

        $meta_title = 'Browse Console';
        $meta_keywords = 'browse,nzb,console,games,description,details';
        $meta_description = 'Browse for Console Games';
        $content = $this->smarty->fetch('console.tpl');

        $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));

        $this->pagerender();
    }
}
