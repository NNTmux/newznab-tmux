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

        $title = 'Failed Releases List';

        $frellist = Release::getFailedRange();
        $this->smarty->assign('releaselist', $frellist);

        $content = $this->smarty->fetch('failrel-list.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );
        $this->adminrender();
    }
}
