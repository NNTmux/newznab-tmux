<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\XxxInfo;
use App\Services\XxxBrowseService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AdultController extends BasePageController
{
    private XxxBrowseService $xxxBrowseService;

    public function __construct(XxxBrowseService $xxxBrowseService)
    {
        parent::__construct();
        $this->xxxBrowseService = $xxxBrowseService;
    }

    /**
     * @throws \Exception
     */
    public function show(Request $request, string $id = ''): mixed
    {
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

        $ordering = $this->xxxBrowseService->getXXXOrdering();
        $orderby = $request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '';

        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_page');
        $rslt = $this->xxxBrowseService->getXXXRange($page, $catarray, $offset, config('nntmux.items_per_page'), $orderby, -1, $this->userdata['categoryexclusions']); // @phpstan-ignore argument.type
        $results = $this->paginate($rslt, $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_page'), $page, $request->url(), $request->query());

        $title = ($request->has('title') && ! empty($request->input('title'))) ? stripslashes($request->input('title')) : '';

        $actors = ($request->has('actors') && ! empty($request->input('actors'))) ? stripslashes($request->input('actors')) : '';

        $director = ($request->has('director') && ! empty($request->input('director'))) ? stripslashes($request->input('director')) : '';

        $genres = XxxInfo::getAllGenres(true);
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
            $orderByUrls['orderby'.$ordertype] = url('/XXX/'.($id ?: 'All').'?t='.$category.$browseby_link.'&ob='.$ordertype.'&offset=0');
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
            'results' => $results,
            'lastvisit' => $this->userdata['lastlogin'] ?? null,
            'meta_title' => 'Browse XXX',
            'meta_keywords' => 'browse,xxx,nzb,description,details',
            'meta_description' => 'Browse for XXX Movies',
        ], $orderByUrls);

        return view('xxx.index', $this->viewData);
    }
}
