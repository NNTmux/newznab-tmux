<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Releases\ReleaseBrowseService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AdultController extends BasePageController
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
    public function show(Request $request, string $id = ''): mixed
    {
        $moviecats = Category::getChildren(Category::XXX_ROOT);
        $mtmp = [];
        foreach ($moviecats as $mcat) {
            $mtmp[] = [
                'id' => $mcat->id,
                'title' => $mcat->title,
            ];
        }
        $category = $request->has('t') ? $request->input('t') : Category::XXX_ROOT;
        if ($id && \in_array($id, Arr::pluck($mtmp, 'title'), true)) {
            $cat = Category::query()
                ->where('title', $id)
                ->where('root_categories_id', '=', Category::XXX_ROOT)
                ->first(['id']);
            $category = $cat !== null ? $cat['id'] : Category::XXX_ROOT;
        }
        $catarray = [];
        $catarray[] = $category;

        $ordering = $this->releaseBrowseService->getBrowseOrdering();
        $orderby = $request->has('ob') && \in_array($request->input('ob'), $ordering, true) ? $request->input('ob') : '';

        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_page');
        $rslt = $this->releaseBrowseService->getBrowseRange($page, $catarray, $offset, config('nntmux.items_per_page'), $orderby, -1, (array) ($this->userdata->categoryexclusions ?? []), -1);
        $results = $this->paginate($rslt ?? [], isset($rslt[0]->_totalcount) ? $rslt[0]->_totalcount : 0, config('nntmux.items_per_page'), $page, $request->url(), $request->query());

        if ((int) $category === -1) {
            $catname = 'All';
        } else {
            $cdata = Category::find($category);
            $catname = $cdata !== null ? $cdata->title : 'All';
        }

        $orderByUrls = [];
        foreach ($ordering as $ordertype) {
            $orderByUrls['orderby'.$ordertype] = url('/XXX/'.($id ?: 'All').'?t='.$category.'&ob='.$ordertype.'&offset=0');
        }

        $this->viewData = array_merge($this->viewData, [
            'catlist' => $mtmp,
            'category' => $category,
            'categorytitle' => $id,
            'catname' => $catname,
            'ordering' => $ordering,
            'results' => $results,
            'lastvisit' => $this->userdata['lastlogin'] ?? null,
            'meta_title' => 'Browse XXX',
            'meta_keywords' => 'browse,xxx,nzb,description,details',
            'meta_description' => 'Browse for XXX Movies',
        ], $orderByUrls);

        return view('xxx.index', $this->viewData);
    }
}
