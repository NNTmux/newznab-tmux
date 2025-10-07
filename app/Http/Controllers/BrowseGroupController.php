<?php

namespace App\Http\Controllers;

use App\Models\UsenetGroup;
use Illuminate\Http\Request;

class BrowseGroupController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function show(Request $request)
    {
        // Get the search term from the request
        $search = $request->get('search', '') ?? '';

        // Get groups based on search term
        $groupList = UsenetGroup::getGroupsRange($search, true);

        // Set meta data
        $meta_title = 'Browse Groups';
        $meta_keywords = 'browse,groups,description,details';
        $meta_description = 'Browse groups';
        if (! empty($search)) {
            $meta_title .= ' - Search: '.$search;
            $meta_description = 'Browse groups search results for '.$search;
        }

        // Render the content view
        $content = view('browsegroup.index', [
            'results' => $groupList,
            'search' => $search,
            'site' => $this->settings,
        ])->render();

        // Prepare view data for main layout
        $this->viewData = array_merge($this->viewData, [
            'content' => $content,
            'meta_title' => $meta_title,
            'meta_keywords' => $meta_keywords,
            'meta_description' => $meta_description,
        ]);

        return $this->pagerender();
    }
}
