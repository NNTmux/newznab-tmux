<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiHelpController extends BasePageController
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setPrefs();
        $title = 'Api Help';
        $meta_title = 'Api Help Topics';
        $meta_keywords = 'view,nzb,api,details,help,json,rss,atom';
        $meta_description = 'View description of the site Nzb Api.';

        $content = $this->smarty->fetch('apidesc.tpl');
        $this->smarty->assign(
            [
                'content' => $content,
                'title' => $title,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );
        $this->pagerender();
    }

    /**
     * @throws \Exception
     */
    public function apiv2()
    {
        $this->setPrefs();
        $title = 'Api V2 Help';
        $meta_title = 'Api V2 Help Topics';
        $meta_keywords = 'view,nzb,api,details,help,json,rss,atom';
        $meta_description = 'View description of the site Nzb version 2 Api.';

        $content = $this->smarty->fetch('apiv2desc.tpl');
        $this->smarty->assign(
            [
                'content' => $content,
                'title' => $title,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );
        $this->pagerender();
    }
}
