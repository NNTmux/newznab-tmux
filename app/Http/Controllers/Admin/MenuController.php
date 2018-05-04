<?php

namespace App\Http\Controllers\Admin;

use App\Models\Menu;
use App\Models\UserRole;
use Illuminate\Http\Request;
use App\Http\Controllers\BasePageController;

class MenuController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();

        $title = 'Menu List';

        $menulist = Menu::getAll();
        $this->smarty->assign('menulist', $menulist);

        $content = $this->smarty->fetch('menu-list.tpl');
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
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();

        // Get the user roles.
        $userroles = UserRole::getRoles();
        $roles = [];
        foreach ($userroles as $r) {
            $roles[$r['id']] = $r['name'];
        }

        // set the current action
        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                if ($request->input('id') === '') {
                    Menu::addMenu($request->all());
                } else {
                    Menu::updateMenu($request->all());
                }

                return redirect('admin//menu-list');
                break;

            case 'view':
            default:
                $menuRow = [
                    'id' => '', 'title' => '', 'href' => '', 'tooltip' => '',
                    'menueval' => '', 'role' => 0, 'ordinal' => 0, 'newwindow' => 0,
                ];
                if ($request->has('id')) {
                    $id = $request->input('id');
                    $menuRow = Menu::find($id);
                }
                $title = 'Menu Edit';
                $this->smarty->assign('menu', $menuRow);
                break;
        }

        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['Yes', 'No']);

        $this->smarty->assign('role_ids', array_keys($roles));
        $this->smarty->assign('role_names', $roles);

        $content = $this->smarty->fetch('menu-edit.tpl');
        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );
        $this->adminrender();
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy($id)
    {
        if ($id) {
            Menu::deleteMenu($id);
        }

        return redirect('admin/menu-list');
    }
}
