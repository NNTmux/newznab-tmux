<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\RootCategory;
use App\Services\Releases\ReleaseBrowseService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

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
    public function index(Request $request): mixed
    {
        $ordering = $this->releaseBrowseService->getBrowseOrdering();
        $orderBy = $this->resolveOrderBy($request, $ordering);
        $page = $this->resolvePage($request);
        $results = $this->getBrowsePaginator($request, $page, [-1], $orderBy);

        // Build order by URLs
        $orderByUrls = $this->buildOrderByUrls($ordering, 'browse/All');

        $this->viewData = array_merge($this->viewData, [
            'browseMenu' => $this->browseMenu(),
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
    public function show(Request $request, string $parentCategory, string $id = 'All'): mixed
    {

        $parentIdValue = RootCategory::query()->where('title', $parentCategory)->value('id');
        $parentId = $parentIdValue === null ? null : (int) $parentIdValue;

        $query = Category::query()->where('title', $id)->where('root_categories_id', $parentId);
        if ($id !== 'All') {
            $cat = $query->first();
            $category = $cat !== null ? (int) $cat->id : -1;
        } else {
            $category = $parentId ?? -1;
        }

        $grp = -1;

        $catarray = [];
        $catarray[] = $category;

        $ordering = $this->releaseBrowseService->getBrowseOrdering();
        $orderBy = $this->resolveOrderBy($request, $ordering);
        $page = $this->resolvePage($request);
        $results = $this->getBrowsePaginator($request, $page, $catarray, $orderBy, $grp);

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
            $orderByUrls = $this->buildOrderByUrls($ordering, 'browse/'.$parentCategory);
        } else {
            $meta_title = 'Browse '.$parentCategory.' / '.$id.' releases';
            $orderByUrls = $this->buildOrderByUrls($ordering, 'browse/'.$parentCategory.'/'.$id);
        }

        $viewData = [
            'browseMenu' => $this->browseMenu(),
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
    public function group(Request $request): mixed
    {
        if ($request->has('g')) {
            $groupInput = $request->input('g');
            if (! is_scalar($groupInput)) {
                return redirect()->back()->with('error', 'Group parameter is invalid');
            }

            $group = (string) $groupInput;
            $page = $this->resolvePage($request);
            $results = $this->getBrowsePaginator($request, $page, [-1], '', $group);

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

    /**
     * Main categories with their subcategories for the browse filters (honours the user's exclusions).
     *
     * @return array<string, mixed>
     */
    private function browseMenu(): array
    {
        return Category::getForMenu((array) ($this->userdata->categoryexclusions ?? []));
    }

    /**
     * @param  array<int>  $categories
     * @return LengthAwarePaginator<int, mixed>
     */
    private function getBrowsePaginator(Request $request, int $page, array $categories, string $orderBy, int|string $group = -1): LengthAwarePaginator
    {
        $perPage = (int) config('nntmux.items_per_page');
        $offset = $this->paginationOffset($page, $perPage);
        $rslt = $this->releaseBrowseService->getBrowseRange($page, $categories, $offset, $perPage, $orderBy, -1, (array) $this->userdata->categoryexclusions, $group);

        return $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, $perPage, $page, $request->url(), $request->query());
    }
}
