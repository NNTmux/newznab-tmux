<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\RootCategory;
use Blacklight\Releases;
use Illuminate\Http\Request;

class BrowseController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setPreferences();
        $releases = new Releases();

        $this->smarty->assign('category', -1);

        $ordering = $releases->getBrowseOrdering();
        $orderBy = request()->has('ob') && ! empty(request()->input('ob')) ? request()->input('ob') : '';
        $page = request()->has('page') && is_numeric(request()->input('page')) ? request()->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_page');

        $rslt = $releases->getBrowseRange($page, [-1], $offset, config('nntmux.items_per_page'), $orderBy, -1, $this->userdata->categoryexclusions, -1);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_page'), $page, request()->url(), request()->query());

        $this->smarty->assign('catname', 'All');

        $this->smarty->assign('lastvisit', $this->userdata->lastlogin);

        $browse = [];
        foreach ($results as $result) {
            $browse[] = $result;
        }

        $this->smarty->assign(
            [
                'results' => $results,
                'resultsadd' => $browse,
            ]
        );

        foreach ($ordering as $orderType) {
            $this->smarty->assign('orderby'.$orderType, url('browse/All?ob='.$orderType));
        }

        $meta_title = 'Browse All Releases';
        $meta_keywords = 'browse,nzb,description,details';
        $meta_description = 'Browse for Nzbs';

        $content = $this->smarty->fetch('browse.tpl');
        $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));
        $this->pagerender();
    }

    /**
     * @throws \Exception
     */
    public function show(string $parentCategory, string $id = 'All'): void
    {
        $this->setPreferences();
        $releases = new Releases();

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

        $this->smarty->assign('parentcat', ucfirst($parentCategory));
        $this->smarty->assign('category', $category);

        $ordering = $releases->getBrowseOrdering();
        $orderBy = request()->has('ob') && ! empty(request()->input('ob')) ? request()->input('ob') : '';
        $page = request()->has('page') && is_numeric(request()->input('page')) ? request()->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_page');

        $rslt = $releases->getBrowseRange($page, $catarray, $offset, config('nntmux.items_per_page'), $orderBy, -1, $this->userdata->categoryexclusions, $grp);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_page'), $page, request()->url(), request()->query());

        $browse = [];

        foreach ($results as $result) {
            $browse[] = $result;
        }

        $this->smarty->assign('catname', $id);

        $this->smarty->assign('lastvisit', $this->userdata->lastlogin);

        $this->smarty->assign(
            [
                'results' => $results,
                'resultsadd' => $browse,
            ]
        );

        $covgroup = '';
        if ($category === -1 && $grp === -1) {
            $this->smarty->assign('catname', 'All');
        } elseif ($category !== -1 && $grp === -1) {
            $cdata = Category::find($category);
            if ($cdata !== null) {
                if ($cdata->root_categories_id === Category::GAME_ROOT) {
                    $covgroup = 'console';
                } elseif ($cdata->root_categories_id === Category::MOVIE_ROOT) {
                    $covgroup = 'movies';
                } elseif ($cdata->root_categories_id === Category::XXX_ROOT) {
                    $covgroup = 'xxx';
                } elseif ($cdata->root_categories_id === Category::PC_ROOT) {
                    $covgroup = 'games';
                } elseif ($cdata->root_categories_id === Category::MUSIC_ROOT) {
                    $covgroup = 'music';
                } elseif ($cdata->root_categories_id === Category::BOOKS_ROOT) {
                    $covgroup = 'books';
                }
            }
        } elseif ($grp !== -1) {
            $this->smarty->assign('catname', $grp);
        }

        if ($id === 'All' && $parentCategory === 'All') {
            $meta_title = 'Browse '.$parentCategory.' releases';
            foreach ($ordering as $orderType) {
                $this->smarty->assign('orderby'.$orderType, url('browse/'.$parentCategory.'?ob='.$orderType));
            }
        } else {
            $meta_title = 'Browse '.$parentCategory.' / '.$id.' releases';
            foreach ($ordering as $orderType) {
                $this->smarty->assign('orderby'.$orderType, url('browse/'.$parentCategory.'/'.$id.'?ob='.$orderType));
            }
        }
        $meta_keywords = 'browse,nzb,description,details';
        $meta_description = 'Browse for Nzbs';

        $content = $this->smarty->fetch('browse.tpl');
        $this->smarty->assign(compact('content', 'covgroup', 'meta_title', 'meta_keywords', 'meta_description'));
        $this->pagerender();
    }

    /**
     * @throws \Exception
     */
    public function group(Request $request): void
    {
        $this->setPreferences();
        $releases = new Releases();
        if ($request->has('g')) {
            $group = $request->input('g');
            $page = request()->has('page') && is_numeric(request()->input('page')) ? request()->input('page') : 1;
            $offset = ($page - 1) * config('nntmux.items_per_page');
            $rslt = $releases->getBrowseRange($page, [-1], $offset, config('nntmux.items_per_page'), '', -1, $this->userdata->categoryexclusions, $group);
            $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_page'), $page, request()->url(), request()->query());

            $browse = [];

            foreach ($results as $result) {
                $browse[] = $result;
            }

            $this->smarty->assign(
                [
                    'results' => $results,
                    'resultsadd' => $browse,
                    'parentcat' => $group,
                    'catname' => 'all',
                ]
            );
            $meta_title = 'Browse Groups';
            $meta_keywords = 'browse,nzb,description,details';
            $meta_description = 'Browse Groups';
            $content = $this->smarty->fetch('browse.tpl');

            $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));

            $this->pagerender();
        }
    }

    /**
     * @throws \Exception
     */
    public function tags(Request $request): void
    {
        $this->setPreferences();
        $releases = new Releases();
        if ($request->has('tags')) {
            $tags = explode(',', $request->input('tags'));
            $page = request()->has('page') && is_numeric(request()->input('page')) ? request()->input('page') : 1;
            $offset = ($page - 1) * config('nntmux.items_per_page');
            $rslt = $releases->getBrowseRange($page, [-1], $offset, config('nntmux.items_per_page'), '', -1, $this->userdata->categoryexclusions, -1, 0, $tags);
            $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_page'), $page, request()->url(), request()->query());

            $browse = [];

            foreach ($results as $result) {
                $browse[] = $result;
            }

            $this->smarty->assign(
                [
                    'results' => $results,
                    'resultsadd' => $browse,
                    'catname' => 'all',
                ]
            );
            $meta_title = 'Browse Groups';
            $meta_keywords = 'browse,nzb,description,details';
            $meta_description = 'Browse Groups';
            $content = $this->smarty->fetch('browse.tpl');

            $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));

            $this->pagerender();
        }
    }
}
