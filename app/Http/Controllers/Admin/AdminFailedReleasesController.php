<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Release;

class AdminFailedReleasesController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(): void
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
