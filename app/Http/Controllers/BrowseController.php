<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\RootCategory;
use App\Services\Releases\ReleaseBrowseService;
use Illuminate\Http\Request;

class BrowseController extends BasePageController
{
    private ReleaseBrowseService $releaseBrowseService;

    public function __construct(ReleaseBrowseService $releaseBrowseService)
    {
        parent::__construct();
        $this->releaseBrowseService = $releaseBrowseService;
    }

    /**
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $ordering = $this->releaseBrowseService->getBrowseOrdering();
        $orderBy = $request->has('ob') && ! empty($request->input('ob')) ? $request->input('ob') : '';
        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_page');

        $rslt = $this->releaseBrowseService->getBrowseRange($page, [-1], $offset, config('nntmux.items_per_page'), $orderBy, -1, $this->userdata->categoryexclusions, -1);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_page'), $page, $request->url(), $request->query());

        // Build order by URLs
        $orderByUrls = [];
        foreach ($ordering as $orderType) {
            $orderByUrls['orderby'.$orderType] = url('browse/All?ob='.$orderType);
        }

        $this->viewData = array_merge($this->viewData, [
            'category' => -1,
            'catname' => 'All',
            'results' => $results,
            'lastvisit' => $this->userdata->lastlogin,
            'meta_title' => 'Browse All Releases',
            'meta_keywords' => 'browse,nzb,description,details',
            'meta_description' => 'Browse for Nzbs',
        ], $orderByUrls);

        return view('browse.index', $this->viewData);
    }

    /**
     * @throws \Exception
     */
    public function show(Request $request, string $parentCategory, string $id = 'All')
    {

        $parentId = RootCategory::query()->where('title', $parentCategory)->value('id');

        $query = Category::query()->where('title', $id)->where('root_categories_id', $parentId);
        if ($id !== 'All') {
            $cat = $query->first();
            $category = $cat !== null ? $cat->id : -1;
        } else {
            $category = $parentId ?? -1;
        }

        $grp = -1;

        $catarray = [];
        $catarray[] = $category;

        $ordering = $this->releaseBrowseService->getBrowseOrdering();
        $orderBy = $request->has('ob') && ! empty($request->input('ob')) ? $request->input('ob') : '';
        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_page');

        $rslt = $this->releaseBrowseService->getBrowseRange($page, $catarray, $offset, config('nntmux.items_per_page'), $orderBy, -1, $this->userdata->categoryexclusions, $grp);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_page'), $page, $request->url(), $request->query());

        $covgroup = '';
        $shows = false;
        if ($category === -1 && $grp === -1) {
            $catname = 'All';
        } elseif ($category !== -1 && $grp === -1) {
            $catname = $id;
            $cdata = Category::find($category);
            if ($cdata !== null) {
                if ($cdata->root_categories_id === Category::GAME_ROOT) {
                    $covgroup = 'console';
                } elseif ($cdata->root_categories_id === Category::MOVIE_ROOT) {
                    $covgroup = 'movies';
                } elseif ($cdata->root_categories_id === Category::PC_ROOT) {
                    $covgroup = 'games';
                } elseif ($cdata->root_categories_id === Category::MUSIC_ROOT) {
                    $covgroup = 'music';
                } elseif ($cdata->root_categories_id === Category::BOOKS_ROOT) {
                    $covgroup = 'books';
                } elseif ($cdata->root_categories_id === Category::TV_ROOT) {
                    $shows = true;
                }
            }
        } else {
            $catname = $grp;
        }

        // Build order by URLs
        $orderByUrls = [];
        if ($id === 'All' && $parentCategory === 'All') {
            $meta_title = 'Browse '.$parentCategory.' releases';
            foreach ($ordering as $orderType) {
                $orderByUrls['orderby'.$orderType] = url('browse/'.$parentCategory.'?ob='.$orderType);
            }
        } else {
            $meta_title = 'Browse '.$parentCategory.' / '.$id.' releases';
            foreach ($ordering as $orderType) {
                $orderByUrls['orderby'.$orderType] = url('browse/'.$parentCategory.'/'.$id.'?ob='.$orderType);
            }
        }

        $viewData = [
            'parentcat' => ucfirst($parentCategory),
            'category' => $category,
            'catname' => $catname,
            'results' => $results,
            'lastvisit' => $this->userdata->lastlogin,
            'covgroup' => $covgroup,
            'meta_title' => $meta_title,
            'meta_keywords' => 'browse,nzb,description,details',
            'meta_description' => 'Browse for Nzbs',
        ];

        if ($shows) {
            $viewData['shows'] = true;
        }

        $this->viewData = array_merge($this->viewData, $viewData, $orderByUrls);

        return view('browse.index', $this->viewData);
    }

    /**
     * @throws \Exception
     */
    public function group(Request $request)
    {
        if ($request->has('g')) {
            $group = $request->input('g');
            $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
            $offset = ($page - 1) * config('nntmux.items_per_page');
            $rslt = $this->releaseBrowseService->getBrowseRange($page, [-1], $offset, config('nntmux.items_per_page'), '', -1, $this->userdata->categoryexclusions, $group);
            $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_page'), $page, $request->url(), $request->query());

            $this->viewData = array_merge($this->viewData, [
                'results' => $results,
                'parentcat' => $group,
                'catname' => 'all',
                'lastvisit' => $this->userdata->lastlogin,
                'meta_title' => 'Browse Groups',
                'meta_keywords' => 'browse,nzb,description,details',
                'meta_description' => 'Browse Groups',
            ]);

            return view('browse.index', $this->viewData);
        }

        return redirect()->back()->with('error', 'Group parameter is required');
    }
}
