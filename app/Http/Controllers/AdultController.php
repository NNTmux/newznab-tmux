<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Blacklight\XXX;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AdultController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function show(Request $request, string $id = '')
    {
        $this->setPreferences();
        $adult = new XXX;

        $moviecats = Category::getChildren(Category::XXX_ROOT);
        $mtmp = [];
        foreach ($moviecats as $mcat) {
            $mtmp[] =
                [
                    'id' => $mcat->id,
                    'title' => $mcat->title,
                ];
        }
        $category = $request->has('t') ? $request->input('t') : Category::XXX_ROOT;
        if ($id && \in_array($id, Arr::pluck($mtmp, 'title'), false)) {
            $cat = Category::query()
                ->where('title', $id)
                ->where('root_categories_id', '=', Category::XXX_ROOT)
                ->first(['id']);
            $category = $cat !== null ? $cat['id'] : Category::XXX_ROOT;
        }
        $catarray = [];
        $catarray[] = $category;

        $ordering = $adult->getXXXOrdering();
        $orderby = $request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '';

        $movies = [];
        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_cover_page');
        $rslt = $adult->getXXXRange($page, $catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, -1, $this->userdata['categoryexclusions']);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_cover_page'), $page, $request->url(), $request->query());
        foreach ($results as $result) {
            $result->genre = makeFieldLinks((array) $result, 'genre', 'xxx');
            $result->actors = makeFieldLinks((array) $result, 'actors', 'xxx');
            $result->director = makeFieldLinks((array) $result, 'director', 'xxx');

            $movies[] = $result;
        }

        $title = ($request->has('title') && ! empty($request->input('title'))) ? stripslashes($request->input('title')) : '';

        $actors = ($request->has('actors') && ! empty($request->input('actors'))) ? stripslashes($request->input('actors')) : '';

        $director = ($request->has('director') && ! empty($request->input('director'))) ? stripslashes($request->input('director')) : '';

        $genres = $adult->getAllGenres(true);
        $genre = ($request->has('genre') && \in_array($request->input('genre'), $genres, false)) ? $request->input('genre') : '';

        $browseby_link = '&title='.$title.'&actors='.$actors.'&director='.$director.'&genre='.$genre;

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
        foreach ($ordering as $ordertype) {
            $orderByUrls['orderby'.$ordertype] = url('/XXX/' . ($id ?: 'All') . '?t='.$category.$browseby_link.'&ob='.$ordertype.'&offset=0');
        }

        $this->viewData = array_merge($this->viewData, [
            'catlist' => $mtmp,
            'category' => $category,
            'categorytitle' => $id,
            'catname' => $catname,
            'title' => stripslashes($title),
            'actors' => $actors,
            'director' => $director,
            'genres' => $genres,
            'genre' => $genre,
            'resultsadd' => $movies,
            'results' => $results,
            'covgroup' => 'xxx',
            'meta_title' => 'Browse XXX',
            'meta_keywords' => 'browse,xxx,nzb,description,details',
            'meta_description' => 'Browse for XXX Movies',
        ], $orderByUrls);

        return view('xxx.index', $this->viewData);
    }
}
