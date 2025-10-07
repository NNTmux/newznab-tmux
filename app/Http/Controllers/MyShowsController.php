<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Settings;
use App\Models\UserSerie;
use App\Models\Video;
use Blacklight\Releases;
use Illuminate\Http\Request;

class MyShowsController extends BasePageController
{
    public function show(Request $request)
    {
        $action = $request->input('action') ?? '';
        $videoId = $request->input('id') ?? '';

        if ($request->has('from')) {
            $this->viewData['from'] = url($request->input('from'));
        } else {
            $this->viewData['from'] = url('/myshows');
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
                $this->viewData['type'] = 'add';
                $this->viewData['cat_ids'] = array_keys($categories);
                $this->viewData['cat_names'] = $categories;
                $this->viewData['cat_selected'] = [];
                $this->viewData['video'] = $videoId;
                $this->viewData['show'] = $show;
                $this->viewData['content'] = view('myshows.add', $this->viewData)->render();

                return $this->pagerender();

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

                $this->viewData['type'] = 'edit';
                $this->viewData['cat_ids'] = array_keys($categories);
                $this->viewData['cat_names'] = $categories;
                $this->viewData['cat_selected'] = explode('|', $show['categories']);
                $this->viewData['video'] = $videoId;
                $this->viewData['show'] = $show;
                $this->viewData['content'] = view('myshows.add', $this->viewData)->render();

                return $this->pagerender();

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
                if ($shows !== null) {
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
                }
                $this->viewData['shows'] = $results;
                $this->viewData['content'] = view('myshows.index', $this->viewData)->render();
                $this->viewData = array_merge($this->viewData, compact('title', 'meta_title', 'meta_keywords', 'meta_description'));

                return $this->pagerender();
        }

        // Fallback return in case no case matches
        return redirect()->to('/myshows');
    }

    /**
     * @throws \Exception
     */
    public function browse(Request $request)
    {
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
        $browseCount = $shows ? $shows->count() : 0;

        $rslt = $releases->getShowsRange($shows ?? [], $offset, config('nntmux.items_per_page'), $orderby, -1, $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt ?? [], $browseCount, config('nntmux.items_per_page'), $page, $request->url(), $request->query());

        $this->viewData['covgroup'] = '';

        foreach ($ordering as $ordertype) {
            $this->viewData['orderby'.$ordertype] = url('/myshows/browse?ob='.$ordertype.'&amp;offset=0');
        }

        $this->viewData['lastvisit'] = $this->userdata->lastlogin;
        $this->viewData['results'] = $results;
        $this->viewData['resultsadd'] = $rslt;
        $this->viewData['shows'] = true;
        $this->viewData['content'] = view('browse', $this->viewData)->render();
        $this->viewData = array_merge($this->viewData, compact('title', 'meta_title', 'meta_keywords', 'meta_description'));

        return $this->pagerender();
    }
}
