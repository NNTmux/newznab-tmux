<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Settings;
use App\Models\UserSerie;
use App\Models\Video;
use App\Services\Releases\ReleaseBrowseService;
use Illuminate\Http\Request;

class MyShowsController extends BasePageController
{
    private ReleaseBrowseService $releaseBrowseService;

    public function __construct(ReleaseBrowseService $releaseBrowseService)
    {
        parent::__construct();
        $this->releaseBrowseService = $releaseBrowseService;
    }

    public function show(Request $request): mixed
    {
        $action = $this->scalarInput($request, 'action');
        abort_if(in_array($action, ['delete', 'doadd', 'doedit'], true) && ! $request->isMethod('post'), 405);

        $videoId = $this->scalarInput($request, 'id');
        $from = $this->localReturnUrl($request, '/myshows');

        $this->viewData['from'] = $from;

        switch ($action) {
            case 'delete':
                $show = UserSerie::getShow($this->userdata->id, $videoId);
                if (! $show) {
                    return redirect()->back();
                }

                UserSerie::delShow($this->userdata->id, $videoId);

                return redirect()->to($from);
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
                    $category = $this->selectedCategoryIds($request, $this->showCategories(true));
                    UserSerie::addShow($this->userdata->id, $videoId, $category);

                    return redirect()->to($from);
                }

                $categories = $this->showCategories(true);
                $this->viewData['type'] = 'add';
                $this->viewData['cat_ids'] = array_keys($categories);
                $this->viewData['cat_names'] = $categories;
                $this->viewData['cat_selected'] = [];
                $this->viewData['video'] = $videoId;
                $this->viewData['show'] = $show;
                $this->viewData['userdata'] = $this->userdata;
                $this->viewData['content'] = view('myshows.add', $this->viewData)->render();

                return $this->pagerender();

            case 'edit':
            case 'doedit':
                $show = UserSerie::getShow($this->userdata->id, $videoId);

                if (! $show) {
                    return redirect()->to('myshows');
                }

                if ($action === 'doedit') {
                    $category = $this->selectedCategoryIds($request, $this->showCategories(false));
                    UserSerie::updateShow($this->userdata->id, $videoId, $category);

                    return redirect()->to($from);
                }

                $categories = $this->showCategories(false);

                $this->viewData['type'] = 'edit';
                $this->viewData['cat_ids'] = array_keys($categories);
                $this->viewData['cat_names'] = $categories;
                $this->viewData['cat_selected'] = explode('|', $show['categories']);
                $this->viewData['video'] = $videoId;
                $this->viewData['show'] = $show;
                $this->viewData['userdata'] = $this->userdata;
                $this->viewData['content'] = view('myshows.add', $this->viewData)->render();

                return $this->pagerender();

            default:

                $title = 'My Shows';
                $meta_title = 'My Shows';
                $meta_keywords = 'search,add,to,cart,nzb,description,details';
                $meta_description = 'Manage Your Shows';

                $categories = $this->showCategories(false);

                $shows = UserSerie::getShows($this->userdata->id);
                $results = [];
                if ($shows !== null) {
                    foreach ($shows as $showk => $show) {
                        $catArr = [];
                        $showcats = explode('|', $show['categories'] ?? '');
                        if (! empty($showcats)) {
                            foreach ($showcats as $scat) {
                                if (! empty($scat) && isset($categories[$scat])) {
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
                $this->viewData['userdata'] = $this->userdata;
                $this->viewData['content'] = view('myshows.index', $this->viewData)->render();
                $this->viewData = array_merge($this->viewData, compact('title', 'meta_title', 'meta_keywords', 'meta_description'));

                return $this->pagerender();
        }

    }

    /**
     * @throws \Exception
     */
    public function browse(Request $request): mixed
    {
        $title = 'Browse My Shows';
        $meta_title = 'My Shows';
        $meta_keywords = 'search,add,to,cart,nzb,description,details';
        $meta_description = 'Browse Your Shows';

        $shows = UserSerie::getShows($this->userdata->id);

        $page = $this->resolvePage($request);
        $perPage = (int) config('nntmux.items_per_page');
        $offset = $this->paginationOffset($page, $perPage);
        $ordering = $this->releaseBrowseService->getBrowseOrdering();
        $orderby = $this->resolveOrderBy($request, $ordering);
        $browseCount = $shows ? $shows->count() : 0;

        $rslt = $this->releaseBrowseService->getShowsRange($shows ?? [], $offset, $perPage, $orderby, -1, (array) $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt ?? [], $browseCount, $perPage, $page, $request->url(), $request->query());

        $this->viewData['covgroup'] = '';

        foreach ($ordering as $ordertype) {
            $this->viewData['orderby'.$ordertype] = url('/myshows/browse?ob='.$ordertype.'&amp;offset=0');
        }

        $this->viewData['lastvisit'] = $this->userdata->lastlogin;
        $this->viewData['results'] = $results;
        $this->viewData['resultsadd'] = $rslt;
        $this->viewData['shows'] = true;
        $this->viewData['content'] = view('browse.index', $this->viewData)->render();
        $this->viewData = array_merge($this->viewData, compact('title', 'meta_title', 'meta_keywords', 'meta_description'));

        return $this->pagerender();
    }

    /**
     * @return array<int, string>
     */
    private function showCategories(bool $excludeDisabledWebdl): array
    {
        $categories = [];
        foreach (Category::getChildren(Category::TV_ROOT) as $category) {
            if ($excludeDisabledWebdl && (int) $category['id'] === Category::TV_WEBDL && (int) Settings::settingValue('catwebdl') === 0) {
                continue;
            }

            $categories[(int) $category['id']] = (string) $category['title'];
        }

        return $categories;
    }

    /**
     * @param  array<int, string>  $allowedCategories
     * @return array<int, int>
     */
    private function selectedCategoryIds(Request $request, array $allowedCategories): array
    {
        $selected = [];
        foreach ($this->arrayInput($request, 'category') as $categoryId) {
            if (is_scalar($categoryId) && preg_match('/^\d+$/', (string) $categoryId) === 1 && isset($allowedCategories[(int) $categoryId])) {
                $selected[] = (int) $categoryId;
            }
        }

        return array_values(array_unique($selected));
    }
}
