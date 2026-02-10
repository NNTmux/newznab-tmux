<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Models\RootCategory;
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

        return view('admin.categories.index', $this->viewData);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     *
     * @throws \Exception
     */
    public function create(Request $request)
    {
        $this->setAdminPrefs();

        // set the current action
        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                // Create new category
                $category = new Category;

                // Allow custom ID only when creating
                if ($request->filled('id')) {
                    $customId = $request->input('id');
                    // Check if ID already exists
                    if (Category::where('id', $customId)->exists()) {
                        return redirect()->back()->withInput()->with('error', 'Category ID '.$customId.' already exists. Please choose a different ID.');
                    }
                    $category->id = $customId;
                }

                $category->title = $request->input('title');
                $category->root_categories_id = $request->input('root_categories_id') ?: null;
                $category->status = Category::STATUS_ACTIVE;  // Always active
                $category->description = $request->input('description');
                $category->disablepreview = false;  // Always enabled
                $category->minsizetoformrelease = 0;
                $category->maxsizetoformrelease = 0;
                $category->save();

                return redirect()->to('admin/category-list')->with('success', 'Category created successfully');
            case 'view':
            default:
                $title = 'Add New Category';
                break;
        }

        // Get root categories for parent selection
        $rootCategories = RootCategory::orderBy('title')->get();

        $this->viewData = array_merge($this->viewData, [
            'category' => null,
            'rootCategories' => $rootCategories,
            'title' => $title,
            'meta_title' => 'Add New Category',
            'isCreate' => true,
        ]);

        return view('admin.categories.edit', $this->viewData);
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
                $category = Category::find($request->input('id'));
                if ($category) {
                    $category->root_categories_id = $request->input('root_categories_id') ?: null;
                    $category->status = Category::STATUS_ACTIVE;  // Always active
                    $category->description = $request->input('description');
                    $category->disablepreview = false;  // Always enabled
                    $category->minsizetoformrelease = 0;
                    $category->maxsizetoformrelease = 0;
                    $category->save();
                }

                return redirect()->to('admin/category-list')->with('success', 'Category updated successfully');
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

        // Get root categories for parent selection
        $rootCategories = RootCategory::orderBy('title')->get();

        $this->viewData = array_merge($this->viewData, [
            'category' => $category,
            'rootCategories' => $rootCategories,
            'title' => $title,
            'meta_title' => 'View/Edit categories',
            'isCreate' => false,
        ]);

        return view('admin.categories.edit', $this->viewData);
    }
}
