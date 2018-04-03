<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Blacklight\Releases;
use Illuminate\Http\Request;

class BrowseController extends BasePageController
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
        $releases = new Releases(['Settings' => $this->settings]);

        $this->smarty->assign('category', -1);

        $orderby = '';

        $results = $releases->getBrowseRange([-1], $orderby, -1, $this->userdata['categoryexclusions'], -1);

        $this->smarty->assign('catname', 'All');

        $this->smarty->assign('lastvisit', $this->userdata['lastlogin']);

        $this->smarty->assign('results', $results);

        $meta_title = 'Browse Nzbs';
        $meta_keywords = 'browse,nzb,description,details';
        $meta_description = 'Browse for Nzbs';

        $content = $this->smarty->fetch('browse.tpl');
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

    /**
     * @param string $parentCategory
     * @param $id
     * @throws \Exception
     */
    public function show(string $parentCategory, string $id = 'all')
    {
        $this->setPrefs();
        $releases = new Releases(['Settings' => $this->settings]);

        $parentId = Category::query()->where('title', $parentCategory)->first(['id']);

        $query = Category::query();
        if ($id !== 'all') {
            $query->where('title', $id)->where('parentid', $parentId['id']);
        } else {
            $query->where('id', $parentId['id']);
        }
        $category = $query->first(['id']);

        $grp = -1;

        $catarray = [];
        $catarray[] = $category['id'];

        $this->smarty->assign('category', $category);

        $orderby = '';

        $results = $releases->getBrowseRange($catarray, $orderby, -1, $this->userdata['categoryexclusions'], $grp);

        $this->smarty->assign('catname', $id);

        $this->smarty->assign('lastvisit', $this->userdata['lastlogin']);

        $this->smarty->assign('results', $results);

        $meta_title = 'Browse '.$parentCategory.' > '.$id;
        $meta_keywords = 'browse,nzb,description,details';
        $meta_description = 'Browse for Nzbs';

        $content = $this->smarty->fetch('browse.tpl');
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
