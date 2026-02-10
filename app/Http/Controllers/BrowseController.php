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

        $rslt = $this->releaseBrowseService->getBrowseRange($page, [-1], $offset, config('nntmux.items_per_page'), $orderBy, -1, (array) $this->userdata->categoryexclusions, -1);
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

        $rslt = $this->releaseBrowseService->getBrowseRange($page, $catarray, $offset, config('nntmux.items_per_page'), $orderBy, -1, (array) $this->userdata->categoryexclusions, $grp);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_page'), $page, $request->url(), $request->query());

        $covgroup = '';
        $shows = false;
        if ($category === -1) {
            $catname = 'All';
        } else {
            $catname = $id;

            // Determine the root category ID - either from the category's root_categories_id
            // or the category itself if it IS a root category
            $rootCategoryId = null;
            $cdata = Category::find($category);
            if ($cdata !== null) {
                $rootCategoryId = $cdata->root_categories_id ?? $category;
            } else {
                // Category not found in categories table, might be a root category
                // Check if it matches a known root category ID
                $rootCategoryId = $category;
            }

            // Also check RootCategory table for parent categories (when $id is 'All')
            if ($id === 'All' && $parentId !== null) {
                $rootCategoryId = $parentId;
            }

            // Set covgroup based on root category
            if ($rootCategoryId === Category::GAME_ROOT) {
                $covgroup = 'console';
            } elseif ($rootCategoryId === Category::MOVIE_ROOT) {
                $covgroup = 'movies';
            } elseif ($rootCategoryId === Category::PC_ROOT) {
                $covgroup = 'games';
            } elseif ($rootCategoryId === Category::MUSIC_ROOT) {
                $covgroup = 'music';
            } elseif ($rootCategoryId === Category::BOOKS_ROOT) {
                $covgroup = 'books';
            } elseif ($rootCategoryId === Category::XXX_ROOT) {
                $covgroup = 'xxx';
            } elseif ($rootCategoryId === Category::TV_ROOT) {
                $shows = true;
            }
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
            $rslt = $this->releaseBrowseService->getBrowseRange($page, [-1], $offset, config('nntmux.items_per_page'), '', -1, (array) $this->userdata->categoryexclusions, $group);
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
