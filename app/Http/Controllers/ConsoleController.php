<?php

namespace App\Http\Controllers;

use Blacklight\Genres;
use Blacklight\Console;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ConsoleController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $id
     *
     * @throws \Exception
     */
    public function show(Request $request, $id = '')
    {
        $this->setPrefs();
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
        $category = Category::GAME_ROOT;
        if ($id && \in_array($id, array_pluck($ctmp, 'title'), false)) {
            $cat = Category::query()
                ->where('title', $id)
                ->where('parentid', '=', Category::GAME_ROOT)
                ->first(['id']);
            $category = $cat !== null ? $cat['id'] : Category::MUSIC_ROOT;
        }

        $catarray = [];
        $catarray[] = $category;

        $this->smarty->assign('catlist', $ctmp);
        $this->smarty->assign('category', $category);
        $this->smarty->assign('categorytitle', $id);

        $ordering = $console->getConsoleOrdering();
        $orderby = $request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '';
        $page = $request->has('page') ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_cover_page');

        $consoles = [];
        $rslt = $console->getConsoleRange($page, $catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, $this->userdata['categoryexclusions']);
        $results = $this->paginate($rslt, $rslt['_totalcount'], config('nntmux.items_per_cover_page'), $page, $request->url());

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
            $tmpgnr[$gn['id']] = $gn['title'];
        }
        $genre = ($request->has('genre') && array_key_exists($request->input('genre'), $tmpgnr)) ? $request->input('genre') : '';
        $this->smarty->assign('genres', $genres);
        $this->smarty->assign('genre', $genre);

        if ($category === -1) {
            $this->smarty->assign('catname', 'All');
        } else {
            $cdata = Category::find($category);
            if ($cdata) {
                $this->smarty->assign('catname', $cdata->parentid !== null ? $cdata->parent->title.' > '.$cdata->title : $cdata->title);
            } else {
                $this->show404();
            }
        }

        $this->smarty->assign(
            [
                'resultsadd'=>  $consoles,
                'results' => $results,
            ]
        );

        $meta_title = 'Browse Console';
        $meta_keywords = 'browse,nzb,console,games,description,details';
        $meta_description = 'Browse for Console Games';
        $content = $this->smarty->fetch('console.tpl');

        $this->smarty->assign(
            [
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );

        $this->pagerender();
    }
}
