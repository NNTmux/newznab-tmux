<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Blacklight\Genres;
use Blacklight\Music;
use Illuminate\Http\Request;

class MusicController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function show(Request $request)
    {
        $music = new Music(['Settings' => $this->settings]);
        $gen = new Genres(['Settings' => $this->settings]);

        $musiccats = Category::getChildren(Category::MUSIC_ROOT);
        $mtmp = [];
        foreach ($musiccats as $mcat) {
            $mtmp[$mcat['id']] = $mcat;
        }
        $category = Category::MUSIC_ROOT;
        if ($request->has('t') && array_key_exists($request->input('t'), $mtmp)) {
            $category = $request->input('t') + 0;
        }

        $catarray = [];
        $catarray[] = $category;

        $this->smarty->assign('catlist', $mtmp);
        $this->smarty->assign('category', $category);

        $page = $request->has('page') ? $request->input('page') : 1;

        $musics = [];
        $results = $music->getMusicRange($page, $catarray, $this->userdata['categoryexclusions']);

        $artist = ($request->has('artist') && ! empty($request->input('artist'))) ? stripslashes($request->input('artist')) : '';
        $this->smarty->assign('artist', $artist);

        $title = ($request->has('title') && ! empty($request->input('title'))) ? stripslashes($request->input('title')) : '';
        $this->smarty->assign('title', $title);

        $genres = $gen->getGenres(Genres::MUSIC_TYPE, true);
        $tmpgnr = [];
        foreach ($genres as $gn) {
            $tmpgnr[$gn['id']] = $gn['title'];
        }

        foreach ($results as $result) {
            $result['genre'] = $tmpgnr[$result['genres_id']];
            $musics[] = $result;
        }

        $genre = ($request->has('genre') && array_key_exists($request->input('genre'), $tmpgnr)) ? $request->input('genre') : '';
        $this->smarty->assign('genres', $genres);
        $this->smarty->assign('genre', $genre);

        $years = range(1950, date('Y') + 1);
        rsort($years);
        $year = ($request->has('year') && \in_array($request->input('year'), $years, false)) ? $request->input('year') : '';
        $this->smarty->assign('years', $years);
        $this->smarty->assign('year', $year);

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

        $this->smarty->assign('results', $musics);

        $meta_title = 'Browse Albums';
        $meta_keywords = 'browse,nzb,albums,description,details';
        $meta_description = 'Browse for Albums';

        $content = $this->smarty->fetch('music.tpl');

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
