<?php

namespace App\Http\Controllers;

use Blacklight\Genres;
use Blacklight\Console;
use App\Models\Category;
use Illuminate\Http\Request;

class ConsoleController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     * @throws \Exception
     */
    public function show(Request $request)
    {
        $this->setPrefs();
        $console = new Console(['Settings' => $this->settings]);
        $gen = new Genres(['Settings' => $this->settings]);

        $concats = Category::getChildren(Category::GAME_ROOT);
        $ctmp = [];
        foreach ($concats as $ccat) {
            $ctmp[$ccat['id']] = $ccat;
        }
        $category = Category::query()->where('id', '=', Category::GAME_ROOT)->first();

        $catarray = [];
        $catarray[] = $category;

        $this->smarty->assign('catlist', $ctmp);
        $this->smarty->assign('category', $category->title);

        $page = $request->has('page') ? $request->input('page') : 1;

        $consoles = [];
        $results = $console->getConsoleRange($page, $catarray, $this->userdata['categoryexclusions']);

        $maxwords = 50;
        foreach ($results as $result) {
            if (! empty($result['review'])) {
                $words = explode(' ', $result['review']);
                if (\count($words) > $maxwords) {
                    $newwords = \array_slice($words, 0, $maxwords);
                    $result['review'] = implode(' ', $newwords).'...';
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
                $this->smarty->assign('catname', array_first($cdata)->parent !== null ? array_first($cdata)->parent->title.' > '.array_first($cdata)->title : array_first($cdata)->title);
            } else {
                $this->show404();
            }
        }

        $this->smarty->assign('results', $consoles);

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
