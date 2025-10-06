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
    public function show(Request $request, string $id = '')
    {
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

        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_cover_page');
        $ordering = $music->getMusicOrdering();
        $orderby = $request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '';

        $musics = [];
        $rslt = $music->getMusicRange($page, $catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_cover_page'), $page, $request->url(), $request->query());

        $artist = ($request->has('artist') && ! empty($request->input('artist'))) ? stripslashes($request->input('artist')) : '';

        $title = ($request->has('title') && ! empty($request->input('title'))) ? stripslashes($request->input('title')) : '';

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

        $years = range(1950, date('Y') + 1);
        rsort($years);
        $year = ($request->has('year') && \in_array($request->input('year'), $years, false)) ? $request->input('year') : '';

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
            $orderByUrls['orderby'.$orderType] = url('music/'.($id ?: 'All').'?ob='.$orderType);
        }

        $this->viewData = array_merge($this->viewData, [
            'catlist' => $mtmp,
            'category' => $category,
            'categorytitle' => $id,
            'catname' => $catname,
            'artist' => $artist,
            'title' => $title,
            'genres' => $genres,
            'genre' => $genre,
            'years' => $years,
            'year' => $year,
            'resultsadd' => $musics,
            'results' => $results,
            'covgroup' => 'music',
            'meta_title' => 'Browse Albums',
            'meta_keywords' => 'browse,nzb,albums,description,details',
            'meta_description' => 'Browse for Albums',
        ], $orderByUrls);

        return view('music.index', $this->viewData);
    }
}
