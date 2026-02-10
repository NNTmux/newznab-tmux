<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Services\RegexService;
use Illuminate\Http\Request;

class AdminCategoryRegexesController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $this->setAdminPrefs();
        $regexes = new RegexService('category_regexes');

        $meta_title = $title = 'Category Regex List';

        $group = $request->has('group') && ! empty($request->input('group')) ? $request->input('group') : '';
        $regex = $regexes->getRegex($group);

        $this->viewData = array_merge($this->viewData, [
            'group' => $group,
            'regex' => $regex,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.regexes.category-list', $this->viewData);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();
        $regexes = new RegexService('category_regexes');

        // Set the current action.
        $action = $request->input('action') ?? 'view';

        $regex = [
            'id' => '',
            'group_regex' => '',
            'regex' => '',
            'description' => '',
            'ordinal' => '',
            'categories_id' => '',
            'status' => 1,
        ];

        $error = '';
        $meta_title = $title = 'Category Regex';

        switch ($action) {
            case 'submit':
                if (empty($request->input('group_regex'))) {
                    $error = 'Group regex must not be empty!';
                    break;
                }

                if (empty($request->input('regex'))) {
                    $error = 'Regex cannot be empty';
                    break;
                }

                if (! is_numeric($request->input('ordinal')) || $request->input('ordinal') < 0) {
                    $error = 'Ordinal must be a number, 0 or higher.';
                    break;
                }

                if (empty($request->input('id'))) {
                    $regexes->addRegex($request->all());
                } else {
                    $regexes->updateRegex($request->all());
                }

                return redirect()->to('admin/category_regexes-list');

            case 'view':
            default:
                if ($request->has('id')) {
                    $meta_title = $title = 'Category Regex Edit';
                    $id = $request->input('id');
                    $regex = $regexes->getRegexByID($id);
                } else {
                    $meta_title = $title = 'Category Regex Add';
                }
                break;
        }

        $categories_db = Category::query()
            ->select(['c.id', 'c.title', 'cp.title as parent_title'])
            ->from('categories as c')
            ->join('root_categories as cp', 'c.root_categories_id', '=', 'cp.id')
            ->whereNotNull('c.root_categories_id')
            ->orderBy('c.id')
            ->get();

        $category_ids = [];
        $category_names = [];
        foreach ($categories_db as $category_db) {
            $category_ids[] = $category_db->id;
            /** @phpstan-ignore property.notFound */
            $category_names[] = $category_db->parent_title.' '.$category_db->title.': '.$category_db->id;
        }

        $this->viewData = array_merge($this->viewData, [
            'error' => $error,
            'regex' => (object) $regex,
            'status_ids' => [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE],
            'status_names' => ['Yes', 'No'],
            'category_ids' => $category_ids,
            'category_names' => $category_names,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.regexes.category-edit', $this->viewData);
    }
}
