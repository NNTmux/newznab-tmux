<?php

namespace App\Http\Controllers\Admin;

use App\Models\Release;
use App\Http\Controllers\BasePageController;

class FailedReleasesController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Failed Releases List';

        $frellist = Release::getFailedRange();
        $this->smarty->assign('releaselist', $frellist);

        $content = $this->smarty->fetch('failrel-list.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }
}
