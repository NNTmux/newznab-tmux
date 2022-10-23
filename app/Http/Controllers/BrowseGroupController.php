<?php

namespace App\Http\Controllers;

use App\Models\UsenetGroup;

class BrowseGroupController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function show(): void
    {
        $this->setPrefs();
        $groupList = UsenetGroup::getGroupsRange('', true);
        $this->smarty->assign('results', $groupList);

        $meta_title = 'Browse Groups';
        $meta_keywords = 'browse,groups,description,details';
        $meta_description = 'Browse groups';

        $content = $this->smarty->fetch('browsegroup.tpl');
        $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));
        $this->pagerender();
    }
}
