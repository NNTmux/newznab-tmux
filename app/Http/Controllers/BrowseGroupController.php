<?php

        namespace App\Http\Controllers;

        use App\Models\UsenetGroup;
        use Illuminate\Http\Request;

        class BrowseGroupController extends BasePageController
        {
            /**
             * @throws \Exception
             */
            public function show(Request $request): void
            {
                $this->setPreferences();

                // Get the search term from the request
                $search = $request->get('search', '') ?? '';

                // Get groups based on search term
                $groupList = UsenetGroup::getGroupsRange($search, true);

                // Pass data to the view
                $this->smarty->assign('results', $groupList);
                $this->smarty->assign('search', $search);

                $meta_title = 'Browse Groups';
                $meta_keywords = 'browse,groups,description,details';
                $meta_description = 'Browse groups';
                if (!empty($search)) {
                    $meta_title .= ' - Search: '.$search;
                    $meta_description = 'Browse groups search results for '.$search;
                }

                $content = $this->smarty->fetch('browsegroup.tpl');
                $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));
                $this->pagerender();
            }
        }
