<?php

use App\Models\Video;
use App\Models\Category;
use App\Models\Settings;
use Blacklight\Releases;
use App\Models\UserSerie;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}

$action = request()->input('id') ?? '';
$videoId = request()->input('subpage') ?? '';

if (request()->has('from')) {
    $page->smarty->assign('from', WWW_TOP.request()->input('from'));
} else {
    $page->smarty->assign('from', WWW_TOP.'/myshows');
}

switch ($action) {
    case 'delete':
        $show = UserSerie::getShow(Auth::id(), $videoId);
        if (request()->has('from')) {
            header('Location:'.WWW_TOP.request()->input('from'));
        } else {
            redirect('/myshows');
        }
        if (! $show) {
            $page->show404('Not subscribed');
        } else {
            UserSerie::delShow(Auth::id(), $videoId);
        }

        break;
    case 'add':
    case 'doadd':
        $show = UserSerie::getShow(Auth::id(), $videoId);
        if ($show) {
            $page->show404('Already subscribed');
        } else {
            $show = Video::getByVideoID($videoId);
            if (! $show) {
                $page->show404('No matching show.');
            }
        }

        if ($action === 'doadd') {
            $category = (request()->has('category') && is_array(request()->input('category')) && ! empty(request()->input('category'))) ? request()->input('category') : [];
            UserSerie::addShow(Auth::id(), $videoId, $category);
            if (request()->has('from')) {
                header('Location:'.WWW_TOP.request()->input('from'));
            } else {
                redirect('/myshows');
            }
        } else {
            $tmpcats = Category::getChildren(Category::TV_ROOT);
            $categories = [];
            foreach ($tmpcats as $c) {
                // If TV WEB-DL categorization is disabled, don't include it as an option
                if ((int) $c['id'] === Category::TV_WEBDL && (int) Settings::settingValue('indexer.categorise.catwebdl') === 0) {
                    continue;
                }
                $categories[$c['id']] = $c['title'];
            }
            $page->smarty->assign('type', 'add');
            $page->smarty->assign('cat_ids', array_keys($categories));
            $page->smarty->assign('cat_names', $categories);
            $page->smarty->assign('cat_selected', []);
            $page->smarty->assign('video', $videoId);
            $page->smarty->assign('show', $show);
            $page->content = $page->smarty->fetch('myshows-add.tpl');
            $page->pagerender();
        }
        break;
    case 'edit':
    case 'doedit':
        $show = UserSerie::getShow(Auth::id(), $videoId);

        if (! $show) {
            $page->show404();
        }

        if ($action === 'doedit') {
            $category = (request()->has('category') && is_array(request()->input('category')) && ! empty(request()->input('category'))) ? request()->input('category') : [];
            UserSerie::updateShow(Auth::id(), $videoId, $category);
            if (request()->has('from')) {
                redirect(request()->input('from'));
            } else {
                redirect('/myshows');
            }
        } else {
            $tmpcats = Category::getChildren(Category::TV_ROOT);
            $categories = [];
            foreach ($tmpcats as $c) {
                $categories[$c['id']] = $c['title'];
            }

            $page->smarty->assign('type', 'edit');
            $page->smarty->assign('cat_ids', array_keys($categories));
            $page->smarty->assign('cat_names', $categories);
            $page->smarty->assign('cat_selected', explode('|', $show['categories']));
            $page->smarty->assign('video', $videoId);
            $page->smarty->assign('show', $show);
            $page->content = $page->smarty->fetch('myshows-add.tpl');
            $page->pagerender();
        }
        break;
    case 'browse':

        $page->title = 'Browse My Shows';
        $page->meta_title = 'My Shows';
        $page->meta_keywords = 'search,add,to,cart,nzb,description,details';
        $page->meta_description = 'Browse Your Shows';

        $shows = UserSerie::getShows(Auth::id());

        $releases = new Releases(['Settings' => $page->settings]);
        $browsecount = $releases->getShowsCount($shows, -1, $page->userdata['categoryexclusions']);

        $offset = (request()->has('offset') && ctype_digit(request()->input('offset'))) ? request()->input('offset') : 0;
        $ordering = $releases->getBrowseOrdering();
        $orderby = request()->has('ob') && in_array(request()->input('ob'), $ordering, false) ? request()->input('ob') : '';

        $results = $releases->getShowsRange($shows, $offset, config('nntmux.items_per_page'), $orderby, -1, $page->userdata['categoryexclusions']);

        $page->smarty->assign('pagertotalitems', $browsecount);
        $page->smarty->assign('pageroffset', $offset);
        $page->smarty->assign('pageritemsperpage', config('nntmux.items_per_page'));
        $page->smarty->assign('pagerquerybase', WWW_TOP.'/myshows/browse?ob='.$orderby.'&amp;offset=');
        $page->smarty->assign('pagerquerysuffix', '#results');
        $page->smarty->assign('covgroup', '');

        $pager = $page->smarty->fetch('pager.tpl');
        $page->smarty->assign('pager', $pager);

        foreach ($ordering as $ordertype) {
            $page->smarty->assign('orderby'.$ordertype, WWW_TOP.'/myshows/browse?ob='.$ordertype.'&amp;offset=0');
        }

        $page->smarty->assign('lastvisit', $page->userdata['lastlogin']);

        $page->smarty->assign('results', $results);

        $page->smarty->assign('shows', true);

        $page->content = $page->smarty->fetch('browse.tpl');
        $page->pagerender();
        break;
    default:

        $page->title = 'My Shows';
        $page->meta_title = 'My Shows';
        $page->meta_keywords = 'search,add,to,cart,nzb,description,details';
        $page->meta_description = 'Manage Your Shows';

        $tmpcats = Category::getChildren(Category::TV_ROOT);
        $categories = [];
        foreach ($tmpcats as $c) {
            $categories[$c['id']] = $c['title'];
        }

        $shows = UserSerie::getShows(Auth::id());
        $results = [];
        foreach ($shows as $showk => $show) {
            $showcats = explode('|', $show['categories']);
            if (is_array($showcats) && count($showcats) > 0) {
                $catarr = [];
                foreach ($showcats as $scat) {
                    if (! empty($scat)) {
                        $catarr[] = $categories[$scat];
                    }
                }
                $show['categoryNames'] = implode(', ', $catarr);
            } else {
                $show['categoryNames'] = '';
            }

            $results[$showk] = $show;
        }
        $page->smarty->assign('shows', $results);

        $page->content = $page->smarty->fetch('myshows.tpl');
        $page->pagerender();
        break;
}
