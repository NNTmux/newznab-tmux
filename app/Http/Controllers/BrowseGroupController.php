<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Blacklight\Releases;
use Illuminate\Http\Request;

class BrowseGroupController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function show()
    {
        $this->setPrefs();
        $groupList = Group::getGroupsRange(false, false, '', true);
        $this->smarty->assign('results', $groupList);

        $meta_title = 'Browse Groups';
        $meta_keywords = 'browse,groups,description,details';
        $meta_description = 'Browse groups';

        $content = $this->smarty->fetch('browsegroup.tpl');
        $this->smarty->assign(
            [
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );
        $this->pagerender();
    }
}
