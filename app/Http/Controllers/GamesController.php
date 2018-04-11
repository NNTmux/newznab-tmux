<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Blacklight\Games;
use Blacklight\Genres;
use Illuminate\Http\Request;

class GamesController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
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

        $page = $request->has('page') ? $request->input('page') : 1;

        $games2 = [];
        $results = $games->getGamesRange($page, $catarray, $this->userdata['categoryexclusions']);
        $maxwords = 50;
        foreach ($results as $result) {
            if (! empty($result['review'])) {
                // remove "Overview" from start of review if present
                if (0 === strpos($result['review'], 'Overview')) {
                    $result['review'] = substr($result['review'], 8);
                }
                $words = explode(' ', $result['review']);
                if (\count($words) > $maxwords) {
                    $newwords = \array_slice($words, 0, $maxwords);
                    $result['review'] = implode(' ', $newwords).'...';
                }
            }
            $games2[] = $result;
        }

        $title = ($request->has('title') && ! empty($request->input('title'))) ? stripslashes($request->input('title')) : '';
        $this->smarty->assign('title', $title);

        $genres = $gen->getGenres(Genres::GAME_TYPE, true);
        $tmpgnr = [];
        foreach ($genres as $gn) {
            $tmpgnr[$gn['id']] = $gn['title'];
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

            if ($cdata) {
                $this->smarty->assign('catname', $cdata->parent !== null ? $cdata->parent->title.' > '.$cdata->title : $cdata->title);
            } else {
                $this->show404();
            }
        }

        $this->smarty->assign('results', $games2);

        $meta_title = 'Browse Games';
        $meta_keywords = 'browse,nzb,games,description,details';
        $meta_description = 'Browse for Games';

        $content = $this->smarty->fetch('games.tpl');
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
