<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use Illuminate\Http\Request;

class AdminCategoryController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();
        $meta_title = $title = 'Category List';

        $categorylist = Category::getFlat();

        $this->viewData = array_merge($this->viewData, [
            'categorylist' => $categorylist,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.category-list', $this->viewData);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();

        // set the current action
        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                Category::updateCategory(
                    $request->input('id'),
                    $request->input('status'),
                    $request->input('description'),
                    $request->input('disablepreview'),
                    $request->input('minsizetoformrelease'),
                    $request->input('maxsizetoformrelease')
                );

                return redirect()->to('admin/category-list');
            case 'view':
            default:
                $category = null;
                $title = 'Category Edit';
                if ($request->has('id')) {
                    $id = $request->input('id');
                    $category = Category::find($id);
                }
                break;
        }

        $this->viewData = array_merge($this->viewData, [
            'category' => $category,
            'status_ids' => [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE, Category::STATUS_DISABLED],
            'status_names' => ['Yes', 'No', 'Disabled'],
            'title' => $title,
            'meta_title' => 'View/Edit categories',
        ]);

        return view('admin.category-edit', $this->viewData);
    }
}
