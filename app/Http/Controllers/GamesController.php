<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Blacklight\Games;
use Blacklight\Genres;
use Illuminate\Http\Request;

class GamesController extends BasePageController
{
    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function show(Request $request)
    {
        $this->setPrefs();
        $games = new Games(['Settings' => $this->settings]);
        $gen = new Genres(['Settings' => $this->settings]);

        $concats = Category::getChildren(Category::PC_ROOT);
        $ctmp = [];
        foreach ($concats as $ccat) {
            $ctmp[$ccat['id']] = $ccat;
        }
        $category = Category::PC_GAMES;
        if ($request->has('t') && array_key_exists($request->input('t'), $ctmp)) {
            $category = $request->input('t') + 0;
        }

        $catarray = [];
        $catarray[] = $category;

        $this->smarty->assign('catlist', $ctmp);
        $this->smarty->assign('category', $category);

        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $ordering = $games->getGamesOrdering();
        $orderby = request()->has('ob') && \in_array(request()->input('ob'), $ordering, false) ? request()->input('ob') : '';
        $offset = ($page - 1) * config('nntmux.items_per_cover_page');
        $rslt = $games->getGamesRange($page, $catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, '', $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_cover_page'), $page, $request->url(), $request->query());

        $title = ($request->has('title') && ! empty($request->input('title'))) ? stripslashes($request->input('title')) : '';
        $this->smarty->assign('title', $title);

        $genres = $gen->getGenres(Genres::GAME_TYPE, true);
        $tmpgnr = [];
        foreach ($genres as $gn) {
            $tmpgnr[$gn->id] = $gn->title;
        }

        $years = range(1903, date('Y') + 1);
        rsort($years);
        $year = ($request->has('year') && \in_array($request->input('year'), $years, false)) ? $request->input('year') : '';
        $this->smarty->assign('years', $years);
        $this->smarty->assign('year', $year);

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

        $this->smarty->assign('results', $results);
        $this->smarty->assign('covgroup', 'games');

        $meta_title = 'Browse Games';
        $meta_keywords = 'browse,nzb,games,description,details';
        $meta_description = 'Browse for Games';

        $content = $this->smarty->fetch('games.tpl');
        $this->smarty->assign(
            compact('content', 'meta_title', 'meta_keywords', 'meta_description')
        );
        $this->pagerender();
    }
}
