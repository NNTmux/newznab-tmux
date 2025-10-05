<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Release;

class AdminFailedReleasesController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $title = 'Failed Releases List';
        $releaselist = Release::getFailedRange();

        return view('admin.failrel-list', compact('title', 'releaselist'));
    }
}
