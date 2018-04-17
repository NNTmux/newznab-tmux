<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\BasePageController;

class CategoryController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();
        $title = 'Category List';

        $categorylist = Category::getFlat();

        $this->smarty->assign('categorylist', $categorylist);

        $content = $this->smarty->fetch('category-list.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );
        $this->adminrender();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
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

                return redirect('category-list');
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

        $this->smarty->assign('status_ids', [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE]);
        $this->smarty->assign('status_names', ['Yes', 'No']);

        $content = $this->smarty->fetch('category-edit.tpl');

        $this->smarty->assign(
            [
                'content' => $content,
            ]
        );
        $this->adminrender();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
