<?php

namespace App\Http\Controllers;

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

        $browsecount = $results[0]['_totalcount'] ?? 0;

        $this->smarty->assign(
            [
                'pagertotalitems' => $browsecount,
                'pageroffset'=> $offset,
                'pageritemsperpage'=> config('nntmux.items_per_page'),
                'pagerquerybase' => WWW_TOP.'/browse?t='.$category.'&amp;g='.$grp.'&amp;ob='.$orderby.'&amp;offset=',
                'pagerquerysuffix' => '#results',
            ]
        );

        $pager = $this->smarty->fetch('pager.tpl');
        $this->smarty->assign('pager', $pager);

        $covgroup = '';
        $this->smarty->assign('catname', 'All');

        $this->smarty->assign('covgroup', $covgroup);

        $this->smarty->assign('lastvisit', $this->userdata['lastlogin']);

        $this->smarty->assign('results', $results);

        $this->meta_title = 'Browse Nzbs';
        $this->meta_keywords = 'browse,nzb,description,details';
        $this->meta_description = 'Browse for Nzbs';

        $this->content = $this->smarty->fetch('browse.tpl');
        $this->pagerender();
    }
}
