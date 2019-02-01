<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Category;
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
        $releases = new Releases(['Groups' => null, 'Settings' => $this->settings]);

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

        if ($searchType === 'basic' && ! $request->has('searchadvr') && ($request->has('id') || $request->has('subject'))) {
            $searchString = '';
            switch (true) {
                case $request->has('subject'):
                    $searchString = (string) $request->input('subject');
                    $this->smarty->assign('subject', $searchString);
                    break;
                case $request->has('id'):
                    $searchString = (string) $request->input('id');
                    $this->smarty->assign('search', $searchString);
                    break;
            }

            $categoryID[] = -1;
            if ($request->has('t')) {
                $categoryID = explode(',', $request->input('t'));
            }
            foreach ($releases->getBrowseOrdering() as $orderType) {
                $this->smarty->assign(
                    'orderby'.$orderType,
                    WWW_TOP.'/search?id='.htmlentities($searchString, ENT_QUOTES | ENT_HTML5).'&t='.implode(',', $categoryID).'&amp;ob='.$orderType
                );
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
                -1,
                0,
                0,
                -1,
                -1,
                $offset,
                config('nntmux.items_per_page'),
                $orderBy,
                -1,
                $this->userdata['categoryexclusions'] ?? [],
                'basic',
                $categoryID,
                0,
                $tags ?? []
            );

            $results = $this->paginate($rslt ?? [], $rslt->_totalrows ?? 0, config('nntmux.items_per_page'), $page, $request->url(), $request->query());

            $this->smarty->assign(
                [
                    'lastvisit' => $this->userdata['lastlogin'],
                    'category' => $categoryID,
                ]
            );
        }

        $searchVars = [
            'searchadvr' => '', 'searchadvsubject' => '', 'searchadvposter' => '',
            'searchadvfilename' => '', 'searchadvdaysnew' => '', 'searchadvdaysold' => '',
            'searchadvgroups' => '', 'searchadvcat' => '', 'searchadvsizefrom' => '',
            'searchadvsizeto' => '', 'searchadvhasnfo' => '', 'searchadvhascomments' => '',
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

        if ($searchType !== 'basic' && ! $request->has('id') && $request->has('searchadvr') && ! $request->has('subject')) {
            $orderByString = '';
            foreach ($searchVars as $searchVarKey => $searchVar) {
                $orderByString .= "&$searchVarKey=".htmlentities($searchVar, ENT_QUOTES | ENT_HTML5);
            }
            $orderByString = ltrim($orderByString, '&');

            foreach ($ordering as $orderType) {
                $this->smarty->assign(
                    'orderby'.$orderType,
                    WWW_TOP.'/search?'.$orderByString.'&search_type=adv&ob='.$orderType
                );
            }

            $rslt = $releases->search(
                ($searchVars['searchadvr'] === '' ? -1 : $searchVars['searchadvr']),
                ($searchVars['searchadvsubject'] === '' ? -1 : $searchVars['searchadvsubject']),
                ($searchVars['searchadvposter'] === '' ? -1 : $searchVars['searchadvposter']),
                ($searchVars['searchadvfilename'] === '' ? -1 : $searchVars['searchadvfilename']),
                $searchVars['searchadvgroups'],
                $searchVars['searchadvsizefrom'],
                $searchVars['searchadvsizeto'],
                $searchVars['searchadvhasnfo'],
                $searchVars['searchadvhascomments'],
                ($searchVars['searchadvdaysnew'] === '' ? -1 : $searchVars['searchadvdaysnew']),
                ($searchVars['searchadvdaysold'] === '' ? -1 : $searchVars['searchadvdaysold']),
                $offset,
                config('nntmux.items_per_page'),
                $orderBy,
                -1,
                $this->userdata['categoryexclusions'] ?? [],
                'advanced',
                [$searchVars['searchadvcat'] === '' ? -1 : $searchVars['searchadvcat']]
            );

            $results = $this->paginate($rslt ?? [], $rslt->_totalrows ?? 0, config('nntmux.items_per_page'), $page, $request->url(), $request->query());

            $this->smarty->assign(
                [
                    'lastvisit' => $this->userdata['lastlogin'],
                ]
            );
        }

        $search_description =
            'Sphinx Search Rules:<br />
The search is case insensitive.<br />
All words must be separated by spaces.
Do not seperate words using . or _ or -, sphinx will match a space against those automatically.<br />
Putting | between words makes any of those words optional.<br />
Putting << between words makes the word on the left have to be before the word on the right.<br />
Putting - or ! in front of a word makes that word excluded. Do not add a space between the - or ! and the word.<br />
Quoting all the words using " will look for an exact match.<br />
Putting ^ at the start will limit searches to releases that start with that word.<br />
Putting $ at the end will limit searches to releases that end with that word.<br />
Putting a * after a word will do a partial word search. ie: fish* will match fishing.<br />
If your search is only words seperated by spaces, all those words will be mandatory, the order of the words is not important.<br />
You can enclose words using paranthesis. ie: (^game*|^dex*)s03*(x264<&lt;nogrp$)<br />
You can combine some of these rules, but not all.<br />';

        $this->smarty->assign(
            [
                'sizelist' => [
                    -1 => '--Select--', 1  => '100MB', 2  => '250MB', 3  => '500MB', 4  => '1GB', 5  => '2GB',
                    6  => '3GB', 7  => '4GB', 8  => '8GB', 9  => '16GB', 10 => '32GB', 11 => '64GB',
                ],
                'results' => $results,
                'sadvanced' => $searchType !== 'basic',
                'grouplist' => Group::getGroupsForSelect(),
                'catlist' => Category::getForSelect(),
                'search_description' => $search_description,
            ]
        );

        $content = $this->smarty->fetch('search.tpl');
        $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));
        $this->pagerender();
    }
}
