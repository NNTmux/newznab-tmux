<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\UsenetGroup;
use Blacklight\Releases;
use Illuminate\Http\Request;

class SearchController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function search(Request $request)
    {
        $this->setPrefs();
        $releases = new Releases();

        $meta_title = 'Search Nzbs';
        $meta_keywords = 'search,nzb,description,details';
        $meta_description = 'Search for Nzbs';

        $results = [];

        $searchType = 'basic';
        if ($request->has('search_type') && $request->input('search_type') === 'adv') {
            $searchType = 'advanced';
        }

        $ordering = $releases->getBrowseOrdering();
        $orderBy = ($request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '');
        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_page');

        $this->smarty->assign(
            [
                'subject' => '', 'search' => '', 'category' => [0], 'covgroup' => '',
            ]
        );

        if ($searchType === 'basic' && $request->missing('searchadvr') && ($request->has('id') || $request->has('subject') || $request->has('search'))) {
            $searchString = [];
            switch (true) {
                case $request->filled('subject'):
                    $searchString['searchname'] = (string) $request->input('subject');
                    $this->smarty->assign('subject', $searchString['searchname']);
                    break;
                case $request->filled('id'):
                    $searchString['searchname'] = (string) $request->input('id');
                    $this->smarty->assign('id', $searchString['searchname']);
                    break;
                case $request->filled('search'):
                    $searchString['searchname'] = (string) $request->input('search');
                    $this->smarty->assign('search', $searchString['searchname']);
                    break;
            }

            $categoryID[] = -1;
            if ($request->has('t')) {
                $categoryID = explode(',', $request->input('t'));
            }
            foreach ($releases->getBrowseOrdering() as $orderType) {
                $this->smarty->assign(
                    'orderby'.$orderType,
                    url('/search?id='.htmlentities($searchString['searchname'], ENT_QUOTES | ENT_HTML5).'&t='.implode(',', $categoryID).'&amp;ob='.$orderType));
            }

            $tags = [];
            if ($request->has('tags')) {
                $tags = explode(',', $request->input('tags'));
            }

            $rslt = $releases->search(
                $searchString,
                -1,
                -1,
                -1,
                -1,
                -1,
                $offset,
                config('nntmux.items_per_page'),
                $orderBy,
                -1,
                $this->userdata->categoryexclusions ?? [],
                'basic',
                $categoryID,
                0,
                $tags ?? []
            );

            $results = $this->paginate($rslt ?? [], $rslt[0]->_totalrows ?? 0, config('nntmux.items_per_page'), $page, request()->url(), request()->query());

            $this->smarty->assign(
                [
                    'lastvisit' => $this->userdata->lastlogin,
                    'category' => $categoryID,
                ]
            );
        }

        $searchVars = [
            'searchadvr' => '',
            'searchadvsubject' => '',
            'searchadvposter' => '',
            'searchadvfilename' => '',
            'searchadvdaysnew' => '',
            'searchadvdaysold' => '',
            'searchadvgroups' => '',
            'searchadvcat' => '',
            'searchadvsizefrom' => '',
            'searchadvsizeto' => '',
            'searchadvhasnfo' => '',
            'searchadvhascomments' => '',
        ];

        foreach ($searchVars as $searchVarKey => $searchVar) {
            $searchVars[$searchVarKey] = ($request->has($searchVarKey) ? (string) $request->input($searchVarKey) : '');
        }

        $searchVars['selectedgroup'] = $searchVars['searchadvgroups'];
        $searchVars['selectedcat'] = $searchVars['searchadvcat'];
        $searchVars['selectedsizefrom'] = $searchVars['searchadvsizefrom'];
        $searchVars['selectedsizeto'] = $searchVars['searchadvsizeto'];
        foreach ($searchVars as $searchVarKey => $searchVar) {
            $this->smarty->assign($searchVarKey, $searchVars[$searchVarKey]);
        }

        if ($searchType !== 'basic' && $request->missing('id') && $request->missing('subject') && $request->anyFilled(['searchadvr', 'searchadvsubject', 'searchadvfilename', 'searchadvposter'])) {
            $orderByString = '';
            foreach ($searchVars as $searchVarKey => $searchVar) {
                $orderByString .= "&$searchVarKey=".htmlentities($searchVar, ENT_QUOTES | ENT_HTML5);
            }
            $orderByString = ltrim($orderByString, '&');

            foreach ($ordering as $orderType) {
                $this->smarty->assign(
                    'orderby'.$orderType,
                    url('/search?'.$orderByString.'&search_type=adv&ob='.$orderType
                ));
            }

            $searchArr = [
                'searchname' => $searchVars['searchadvr'] === '' ? -1 : $searchVars['searchadvr'],
                'name' => $searchVars['searchadvsubject'] === '' ? -1 : $searchVars['searchadvsubject'],
                'fromname' => $searchVars['searchadvposter'] === '' ? -1 : $searchVars['searchadvposter'],
                'filename' => $searchVars['searchadvfilename'] === '' ? -1 : $searchVars['searchadvfilename'],
            ];

            $rslt = $releases->search(
                $searchArr,
                $searchVars['searchadvgroups'],
                $searchVars['searchadvsizefrom'],
                $searchVars['searchadvsizeto'],
                ($searchVars['searchadvdaysnew'] === '' ? -1 : $searchVars['searchadvdaysnew']),
                ($searchVars['searchadvdaysold'] === '' ? -1 : $searchVars['searchadvdaysold']),
                $offset,
                config('nntmux.items_per_page'),
                $orderBy,
                -1,
                $this->userdata->categoryexclusions ?? [],
                'advanced',
                [$searchVars['searchadvcat'] === '' ? -1 : $searchVars['searchadvcat']]
            );

            $results = $this->paginate($rslt ?? [], $rslt[0]->_totalrows ?? 0, config('nntmux.items_per_page'), $page, request()->url(), request()->query());

            $this->smarty->assign(
                [
                    'lastvisit' => $this->userdata->lastlogin,
                ]
            );
        }

        $this->smarty->assign(
            [
                'sizelist' => [
                    -1 => '--Select--', 1  => '100MB', 2  => '250MB', 3  => '500MB', 4  => '1GB', 5  => '2GB',
                    6  => '3GB', 7  => '4GB', 8  => '8GB', 9  => '16GB', 10 => '32GB', 11 => '64GB',
                ],
                'results' => $results,
                'sadvanced' => $searchType !== 'basic',
                'grouplist' => UsenetGroup::getGroupsForSelect(),
                'catlist' => Category::getForSelect(),
            ]
        );

        $content = $this->smarty->fetch('search.tpl');
        $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));
        $this->pagerender();
    }
}
