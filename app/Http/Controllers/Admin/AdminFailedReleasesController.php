<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Release;

class AdminFailedReleasesController extends BasePageController
{
    /**
     * Display a listing of failed releases
     */
    public function index(): mixed
    {
        $meta_title = $title = 'Failed Releases List';
        $releaselist = Release::getFailedRange();

        return view('admin.releases.failed', compact('title', 'meta_title', 'releaselist'));
    }
}
