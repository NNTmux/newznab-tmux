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
    public function index(): void
    {
        $this->setAdminPrefs();
        $meta_title = $title = 'Category List';

        $categorylist = Category::getFlat();

        $this->smarty->assign('categorylist', $categorylist);

        $content = $this->smarty->fetch('category-list.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }

    /**
     * @throws \Exception
     */
    public function edit(Request $request): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
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

                return redirect('admin/category-list');
                break;
            case 'view':
            default:
                if ($request->has('id')) {
                    $this->title = 'Category Edit';
                    $id = $request->input('id');
                    $cat = Category::find($id);
                    $this->smarty->assign('category', $cat);
                }
                break;
        }

        $this->smarty->assign('status_ids', [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE, Category::STATUS_DISABLED]);
        $this->smarty->assign('status_names', ['Yes', 'No', 'Disabled']);

        $content = $this->smarty->fetch('category-edit.tpl');

        $this->smarty->assign(
            [
                'content' => $content,
                'meta_title' => 'View/Edit categories',
            ]
        );
        $this->adminrender();
    }
}
