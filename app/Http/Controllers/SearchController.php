<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\UsenetGroup;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseSearchService;
use App\Services\Search\Contracts\SearchServiceInterface;
use Illuminate\Http\Request;

class SearchController extends BasePageController
{
    private SearchServiceInterface $searchService;
    private ReleaseSearchService $releaseSearchService;
    private ReleaseBrowseService $releaseBrowseService;

    public function __construct(
        SearchServiceInterface $searchService,
        ReleaseSearchService $releaseSearchService,
        ReleaseBrowseService $releaseBrowseService
    ) {
        parent::__construct();
        $this->searchService = $searchService;
        $this->releaseSearchService = $releaseSearchService;
        $this->releaseBrowseService = $releaseBrowseService;
    }

    /**
     * @throws \Exception
     */
    public function search(Request $request)
    {

        $results = [];

        $searchType = 'basic';
        if ($request->has('search_type') && $request->input('search_type') === 'adv') {
            $searchType = 'advanced';
        }

        $ordering = $this->releaseBrowseService->getBrowseOrdering();
        $orderBy = ($request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '');
        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_page');

        $subject = '';
        $search = '';
        $id = '';
        $category = [0];
        $lastvisit = $this->userdata->lastlogin;

        if ($searchType === 'basic' && $request->missing('searchadvr') && ($request->has('id') || $request->has('subject') || $request->has('search'))) {
            $searchString = [];
            switch (true) {
                case $request->filled('subject'):
                    $searchString['searchname'] = (string) $request->input('subject') ?? [];
                    $subject = $searchString['searchname'];
                    break;
                case $request->filled('id'):
                    $searchString['searchname'] = (string) $request->input('id') ?? [];
                    $id = $searchString['searchname'];
                    break;
                case $request->filled('search'):
                    $searchString['searchname'] = (string) $request->input('search') ?? [];
                    $search = $searchString['searchname'];
                    break;
                default:
                    $searchString['searchname'] = '';
            }

            $categoryID[] = -1;
            if ($request->has('t')) {
                $categoryID = explode(',', $request->input('t'));
            }

            $orderByUrls = [];
            foreach ($this->releaseBrowseService->getBrowseOrdering() as $orderType) {
                $orderByUrls['orderby'.$orderType] = url('/search?search='.htmlentities($searchString['searchname'], ENT_QUOTES | ENT_HTML5).'&t='.implode(',', $categoryID).'&ob='.$orderType);
            }

            $rslt = $this->releaseSearchService->search(
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
                $categoryID);

            $results = $this->paginate($rslt ?? [], $rslt[0]->_totalrows ?? 0, config('nntmux.items_per_page'), $page, $request->url(), $request->query());
            $category = $categoryID;
        } else {
            $orderByUrls = [];
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

        // Map new form field names to old internal names
        if ($request->has('minage')) {
            $searchVars['searchadvdaysnew'] = (string) $request->input('minage');
        }
        if ($request->has('maxage')) {
            $searchVars['searchadvdaysold'] = (string) $request->input('maxage');
        }
        if ($request->has('group')) {
            $searchVars['searchadvgroups'] = (string) $request->input('group');
        }
        if ($request->has('minsize')) {
            $searchVars['searchadvsizefrom'] = (string) $request->input('minsize');
        }
        if ($request->has('maxsize')) {
            $searchVars['searchadvsizeto'] = (string) $request->input('maxsize');
        }
        // Map basic search field to advanced search when in advanced mode
        if ($request->has('search') && $searchType === 'advanced') {
            $searchVars['searchadvr'] = (string) $request->input('search');
        }
        // Map basic category field to advanced category when in advanced mode
        if ($request->has('t') && $searchType === 'advanced') {
            $searchVars['searchadvcat'] = (string) $request->input('t');
        }

        $searchVars['selectedgroup'] = $searchVars['searchadvgroups'];
        $searchVars['selectedcat'] = $searchVars['searchadvcat'];
        $searchVars['selectedsizefrom'] = $searchVars['searchadvsizefrom'];
        $searchVars['selectedsizeto'] = $searchVars['searchadvsizeto'];

        // Get spell correction suggestions if we have a search query but few/no results
        $spellSuggestion = null;
        $searchQuery = $search ?: ($searchVars['searchadvr'] ?? '');
        if (! empty($searchQuery) && $this->searchService->isSuggestEnabled()) {
            // Get suggestions from search service
            $suggestions = $this->searchService->suggest($searchQuery);
            if (! empty($suggestions)) {
                // Sort by doc count descending to get best suggestion
                usort($suggestions, fn ($a, $b) => $b['docs'] - $a['docs']);
                // Only show suggestion if it's different from the query
                if ($suggestions[0]['suggest'] !== $searchQuery) {
                    $spellSuggestion = $suggestions[0]['suggest'];
                }
            }
        }

        if ($searchType !== 'basic' && $request->missing('id') && $request->missing('subject') && $request->anyFilled(['searchadvr', 'searchadvsubject', 'searchadvfilename', 'searchadvposter', 'minage', 'maxage', 'group', 'minsize', 'maxsize', 'search'])) {
            $orderByString = '';
            foreach ($searchVars as $searchVarKey => $searchVar) {
                $orderByString .= "&$searchVarKey=".htmlentities($searchVar, ENT_QUOTES | ENT_HTML5);
            }
            $orderByString = ltrim($orderByString, '&');

            $orderByUrls = [];
            foreach ($ordering as $orderType) {
                $orderByUrls['orderby'.$orderType] = url('/search?'.$orderByString.'&search_type=adv&ob='.$orderType);
            }

            $searchArr = [
                'searchname' => $searchVars['searchadvr'] === '' ? -1 : $searchVars['searchadvr'],
                'name' => $searchVars['searchadvsubject'] === '' ? -1 : $searchVars['searchadvsubject'],
                'fromname' => $searchVars['searchadvposter'] === '' ? -1 : $searchVars['searchadvposter'],
                'filename' => $searchVars['searchadvfilename'] === '' ? -1 : $searchVars['searchadvfilename'],
            ];

            $rslt = $this->releaseSearchService->search(
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

            $results = $this->paginate($rslt ?? [], $rslt[0]->_totalrows ?? 0, config('nntmux.items_per_page'), $page, $request->url(), $request->query());
        }

        $this->viewData = array_merge($this->viewData, $searchVars, $orderByUrls, [
            'subject' => $subject,
            'search' => $search,
            'id' => $id,
            'category' => $category,
            'covgroup' => '',
            'lastvisit' => $lastvisit,
            'sizelist' => [
                -1 => '--Select--', 1 => '100MB', 2 => '250MB', 3 => '500MB', 4 => '1GB', 5 => '2GB',
                6 => '3GB', 7 => '4GB', 8 => '8GB', 9 => '16GB', 10 => '32GB', 11 => '64GB',
            ],
            'results' => $results,
            'sadvanced' => $searchType !== 'basic',
            'grouplist' => UsenetGroup::getGroupsForSelect(),
            'catlist' => Category::getForSelect(),
            'meta_title' => 'Search Nzbs',
            'meta_keywords' => 'search,nzb,description,details',
            'meta_description' => 'Search for Nzbs',
            // Search enhanced features
            'spellSuggestion' => $spellSuggestion,
            'autocompleteEnabled' => $this->searchService->isAutocompleteEnabled(),
            'suggestEnabled' => $this->searchService->isSuggestEnabled(),
        ]);

        return view('search.index', $this->viewData);
    }
}
