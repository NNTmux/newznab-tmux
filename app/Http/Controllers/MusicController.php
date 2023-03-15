<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Blacklight\Genres;
use Blacklight\Music;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MusicController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function show(Request $request, string $id = ''): void
    {
        $this->setPreferences();
        $music = new Music(['Settings' => $this->settings]);
        $gen = new Genres(['Settings' => $this->settings]);

        $musiccats = Category::getChildren(Category::MUSIC_ROOT);
        $mtmp = [];
        foreach ($musiccats as $mcat) {
            $mtmp[] =
                [
                    'id' => $mcat->id,
                    'title' => $mcat->title,
                ];
        }

        $category = $request->has('t') ? $request->input('t') : Category::MUSIC_ROOT;
        if ($id && \in_array($id, Arr::pluck($mtmp, 'title'), false)) {
            $cat = Category::query()
                ->where('title', $id)
                ->where('root_categories_id', '=', Category::MUSIC_ROOT)
                ->first(['id']);
            $category = $cat !== null ? $cat['id'] : Category::MUSIC_ROOT;
        }

        $catarray = [];
        $catarray[] = $category;

        $this->smarty->assign('catlist', $mtmp);
        $this->smarty->assign('category', $category);
        $this->smarty->assign('categorytitle', $id);

        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_cover_page');
        $ordering = $music->getMusicOrdering();
        $orderby = request()->has('ob') && \in_array(request()->input('ob'), $ordering, false) ? request()->input('ob') : '';

        $musics = [];
        $rslt = $music->getMusicRange($page, $catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_cover_page'), $page, $request->url(), $request->query());

        $artist = ($request->has('artist') && ! empty($request->input('artist'))) ? stripslashes($request->input('artist')) : '';
        $this->smarty->assign('artist', $artist);

        $title = ($request->has('title') && ! empty($request->input('title'))) ? stripslashes($request->input('title')) : '';
        $this->smarty->assign('title', $title);

        $genres = $gen->getGenres(Genres::MUSIC_TYPE, true);
        $tmpgnr = [];
        foreach ($genres as $gn) {
            $tmpgnr[$gn->id] = $gn->title;
        }

        foreach ($results as $result) {
            $res = $result;
            $result->genre = $tmpgnr[$res->genres_id];
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
            if ($cdata !== null) {
                $this->smarty->assign('catname', $cdata);
            } else {
                $this->smarty->assign('catname', 'All');
            }
        }

        $this->smarty->assign(
            [
                'resultsadd' => $musics,
                'results' => $results,
                'covgroup' => 'music',
            ]
        );

        $meta_title = 'Browse Albums';
        $meta_keywords = 'browse,nzb,albums,description,details';
        $meta_description = 'Browse for Albums';

        $content = $this->smarty->fetch('music.tpl');

        $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));

        $this->pagerender();
    }
}
