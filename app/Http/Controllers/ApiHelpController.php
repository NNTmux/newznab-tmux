<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiHelpController extends BasePageController
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * @throws \Exception
     */
    public function index(): View
    {
        $this->setAdminPrefs();

        $this->viewData['title'] = 'Api Help';
        $this->viewData['meta_title'] = 'Api Help Topics';
        $this->viewData['meta_keywords'] = 'view,nzb,api,details,help,json,rss,atom';
        $this->viewData['meta_description'] = 'View description of the site Nzb Api.';
        $this->viewData['catClass'] = Category::class;

        return view('apidesc', $this->viewData);
    }

    /**
     * @throws \Exception
     */
    public function apiv2(): View
    {
        $this->setAdminPrefs();

        $this->viewData['title'] = 'Api V2 Help';
        $this->viewData['meta_title'] = 'Api V2 Help Topics';
        $this->viewData['meta_keywords'] = 'view,nzb,api,details,help,json,rss,atom';
        $this->viewData['meta_description'] = 'View description of the site Nzb version 2 Api.';
        $this->viewData['catClass'] = Category::class;

        return view('apiv2desc', $this->viewData);
    }
}
