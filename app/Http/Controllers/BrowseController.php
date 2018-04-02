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
        $this->middleware('auth');
    }

    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setPrefs();
        $releases = new Releases(['Settings' => $this->settings]);

        $category = -1;

        $grp = -1;

        $catarray = [];
        $catarray[] = $category;

        $this->smarty->assign('category', $category);

        $offset = 0;
        $orderby = '';

        $results = $releases->getBrowseRange($catarray, $offset, config('nntmux.items_per_page'), $orderby, -1, $this->userdata['categoryexclusions'], $grp);

        $covgroup = '';
        $this->smarty->assign('catname', 'All');

        $this->smarty->assign('lastvisit', $this->userdata['lastlogin']);

        $this->smarty->assign('results', $results);

        $this->meta_title = 'Browse Nzbs';
        $this->meta_keywords = 'browse,nzb,description,details';
        $this->meta_description = 'Browse for Nzbs';

        $this->content = $this->smarty->fetch('browse.tpl');
        $this->smarty->assign('content', $this->content);
        $this->pagerender();
    }

    /**
     * @param $id
     * @throws \Exception
     */
    public function showMovies($id)
    {
        $this->setPrefs();
        $releases = new Releases(['Settings' => $this->settings]);

        $category = Category::query()->where('title', $id)->where('parentid', '2000')->first(['id']);

        $grp = -1;

        $catarray = [];
        $catarray[] = $category['id'];

        $this->smarty->assign('category', $category);

        $orderby = '';

        $results = $releases->getBrowseRange($catarray, $orderby, -1, $this->userdata['categoryexclusions'], $grp);


        $covgroup = '';
        $this->smarty->assign('catname', $id);

        $this->smarty->assign('lastvisit', $this->userdata['lastlogin']);

        $this->smarty->assign('results', $results);

        $this->meta_title = 'Browse Movies > ' . $id;
        $this->meta_keywords = 'browse,nzb,description,details';
        $this->meta_description = 'Browse for Nzbs';

        $this->content = $this->smarty->fetch('browse.tpl');
        $this->smarty->assign('content', $this->content);
        $this->pagerender();
    }
}
