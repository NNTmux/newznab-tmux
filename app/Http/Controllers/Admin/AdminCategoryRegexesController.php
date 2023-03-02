<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use Blacklight\Regexes;
use Illuminate\Http\Request;

class AdminCategoryRegexesController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(Request $request): void
    {
        $this->setAdminPrefs();
        $regexes = new Regexes(['Settings' => null, 'Table_Name' => 'category_regexes']);

        $meta_title = $title = 'Category Regex List';

        $group = $request->has('group') && ! empty($request->input('group')) ? $request->input('group') : '';
        $regex = $regexes->getRegex($group);

        $this->smarty->assign(
            [
                'group' => $group,
                'regex' => $regex,
            ]
        );

        $content = $this->smarty->fetch('category_regexes-list.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|void
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();
        $regexes = new Regexes(['Settings' => null, 'Table_Name' => 'category_regexes']);

        // Set the current action.
        $action = $request->input('action') ?? 'view';

        $regex = [
            'id' => '',
            'group_regex' => '',
            'regex' => '',
            'description' => '',
            'ordinal' => '',
            'categories_id' => '',
            'status' => 1, ];

        $this->smarty->assign('regex', $regex);

        switch ($action) {
            case 'submit':
                if (empty($request->input('group_regex'))) {
                    $this->smarty->assign('error', 'Group regex must not be empty!');
                    break;
                }

                if (empty($request->input('regex'))) {
                    $this->smarty->assign('error', 'Regex cannot be empty');
                    break;
                }

                if (! is_numeric($request->input('ordinal')) || $request->input('ordinal') < 0) {
                    $this->smarty->assign('error', 'Ordinal must be a number, 0 or higher.');
                    break;
                }

                if (empty($request->input('id'))) {
                    $regexes->addRegex($request->all());
                } else {
                    $regexes->updateRegex($request->all());
                }

                return redirect('admin/category_regexes-list');
                break;

            case 'view':
            default:
                if ($request->has('id')) {
                    $meta_title = $title = 'Category Regex Edit';
                    $id = $request->input('id');
                    $regex = $regexes->getRegexByID($id);
                } else {
                    $meta_title = $title = 'Category Regex Add';
                }
                $this->smarty->assign('regex', $regex);
                break;
        }

        $this->smarty->assign('status_ids', [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE]);
        $this->smarty->assign('status_names', ['Yes', 'No']);

        $categories_db = Category::query()
            ->select(['c.id', 'c.title', 'cp.title as parent_title'])
            ->from('categories as c')
            ->join('root_categories as cp', 'c.root_categories_id', '=', 'cp.id')
            ->whereNotNull('c.root_categories_id')
            ->orderBy('c.id')
            ->get();
        $categories = ['category_names', 'category_ids'];
        if (count($categories_db) !== 0) {
            foreach ($categories_db as $category_db) {
                $categories['category_names'] = $category_db->parent_title.' '.$category_db->title.': '.$category_db->id;
                $categories['category_ids'] = $category_db->id;
            }
        }
        $this->smarty->assign('category_names', $categories['category_names']);
        $this->smarty->assign('category_ids', $categories['category_ids']);

        $content = $this->smarty->fetch('category_regexes-edit.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }
}
