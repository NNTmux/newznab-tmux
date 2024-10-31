<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use App\Models\Category;
use App\Models\Settings;
use App\Models\UserSerie;
use App\Models\Video;
use Blacklight\Releases;
use Illuminate\Http\Request;

class MyShowsController extends BasePageController
{
    /**
     * @return \Illuminate\Http\RedirectResponse|void
     *
     * @throws \Exception
     */
    public function show(Request $request): RedirectResponse
    {
        $this->setPreferences();
        $action = $request->input('action') ?? '';
        $videoId = $request->input('id') ?? '';

        if ($request->has('from')) {
            $this->smarty->assign('from', url($request->input('from')));
        } else {
            $this->smarty->assign('from', url('/myshows'));
        }

        switch ($action) {
            case 'delete':
                $show = UserSerie::getShow($this->userdata->id, $videoId);
                if (! $show) {
                    return redirect()->back();
                }

                UserSerie::delShow($this->userdata->id, $videoId);
                if ($request->has('from')) {
                    header('Location:'.url($request->input('from')));
                } else {
                    return redirect()->to('myshows');
                }

                break;
            case 'add':
            case 'doadd':
                $show = UserSerie::getShow($this->userdata->id, $videoId);
                if ($show) {
                    return redirect()->to('myshows');
                }

                $show = Video::getByVideoID($videoId);
                if (! $show) {
                    return redirect()->to('myshows');
                }

                if ($action === 'doadd') {
                    $category = ($request->has('category') && \is_array($request->input('category')) && ! empty($request->input('category'))) ? $request->input('category') : [];
                    UserSerie::addShow($this->userdata->id, $videoId, $category);
                    if ($request->has('from')) {
                        return redirect()->to($request->input('from'));
                    }

                    return redirect()->to('myshows');
                }

                $tmpcats = Category::getChildren(Category::TV_ROOT);
                $categories = [];
                foreach ($tmpcats as $c) {
                    // If TV WEB-DL categorization is disabled, don't include it as an option
                    if ((int) $c['id'] === Category::TV_WEBDL && (int) Settings::settingValue('catwebdl') === 0) {
                        continue;
                    }
                    $categories[$c['id']] = $c['title'];
                }
                $this->smarty->assign('type', 'add');
                $this->smarty->assign('cat_ids', array_keys($categories));
                $this->smarty->assign('cat_names', $categories);
                $this->smarty->assign('cat_selected', []);
                $this->smarty->assign('video', $videoId);
                $this->smarty->assign('show', $show);
                $content = $this->smarty->fetch('myshows-add.tpl');
                $this->smarty->assign([
                    'content' => $content,
                ]);
                $this->pagerender();
                break;
            case 'edit':
            case 'doedit':
                $show = UserSerie::getShow($this->userdata->id, $videoId);

                if (! $show) {
                    return redirect()->to('myshows');
                }

                if ($action === 'doedit') {
                    $category = ($request->has('category') && \is_array($request->input('category')) && ! empty($request->input('category'))) ? $request->input('category') : [];
                    UserSerie::updateShow($this->userdata->id, $videoId, $category);
                    if ($request->has('from')) {
                        return redirect()->to($request->input('from'));
                    }

                    return redirect()->to('myshows');
                }

                $tmpcats = Category::getChildren(Category::TV_ROOT);
                $categories = [];
                foreach ($tmpcats as $c) {
                    $categories[$c['id']] = $c['title'];
                }

                $this->smarty->assign('type', 'edit');
                $this->smarty->assign('cat_ids', array_keys($categories));
                $this->smarty->assign('cat_names', $categories);
                $this->smarty->assign('cat_selected', explode('|', $show['categories']));
                $this->smarty->assign('video', $videoId);
                $this->smarty->assign('show', $show);
                $content = $this->smarty->fetch('myshows-add.tpl');
                $this->smarty->assign([
                    'content' => $content,
                ]);
                $this->pagerender();
                break;
            default:

                $title = 'My Shows';
                $meta_title = 'My Shows';
                $meta_keywords = 'search,add,to,cart,nzb,description,details';
                $meta_description = 'Manage Your Shows';

                $tmpcats = Category::getChildren(Category::TV_ROOT);
                $categories = [];
                foreach ($tmpcats as $c) {
                    $categories[$c['id']] = $c['title'];
                }

                $shows = UserSerie::getShows($this->userdata->id);
                $results = [];
                $catArr = [];
                foreach ($shows as $showk => $show) {
                    $showcats = explode('|', $show['categories']);
                    if (\is_array($showcats) && ! empty($showcats)) {
                        foreach ($showcats as $scat) {
                            if (! empty($scat)) {
                                $catArr[] = $categories[$scat];
                            }
                        }
                        $show['categoryNames'] = implode(', ', $catArr);
                    } else {
                        $show['categoryNames'] = '';
                    }

                    $results[$showk] = $show;
                }
                $this->smarty->assign('shows', $results);

                $content = $this->smarty->fetch('myshows.tpl');
                $this->smarty->assign(compact('content', 'title', 'meta_title', 'meta_keywords', 'meta_description'));
                $this->pagerender();
                break;
        }
    }

    /**
     * @throws \Exception
     */
    public function browse(Request $request): void
    {
        $this->setPreferences();
        $title = 'Browse My Shows';
        $meta_title = 'My Shows';
        $meta_keywords = 'search,add,to,cart,nzb,description,details';
        $meta_description = 'Browse Your Shows';

        $shows = UserSerie::getShows($this->userdata->id);

        $releases = new Releases;

        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_page');
        $ordering = $releases->getBrowseOrdering();
        $orderby = $request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '';
        $browseCount = $releases->getShowsCount($shows, -1, $this->userdata->categoryexclusions);

        $rslt = $releases->getShowsRange($shows ?? [], $offset, config('nntmux.items_per_page'), $orderby, -1, $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt ?? [], $browseCount, config('nntmux.items_per_page'), $page, $request->url(), $request->query());

        $this->smarty->assign('covgroup', '');

        foreach ($ordering as $ordertype) {
            $this->smarty->assign('orderby'.$ordertype, url('/myshows/browse?ob='.$ordertype.'&amp;offset=0'));
        }

        $this->smarty->assign('lastvisit', $this->userdata->lastlogin);

        $this->smarty->assign(['results' => $results, 'resultsadd' => $rslt]);

        $this->smarty->assign('shows', true);

        $content = $this->smarty->fetch('browse.tpl');
        $this->smarty->assign([
            'content' => $content,
            'title' => $title,
            'meta_title' => $meta_title,
            'meta_keywords' => $meta_keywords,
            'meta_description' => $meta_description,
        ]);
        $this->pagerender();
    }
}
