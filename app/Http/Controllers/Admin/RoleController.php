<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\UserRole;
use Illuminate\Http\Request;
use App\Models\RoleExcludedCategory;
use App\Http\Controllers\BasePageController;

class RoleController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();

        $title = 'User Role List';

        //get the user roles
        $userroles = UserRole::getRoles();

        $this->smarty->assign('userroles', $userroles);

        $content = $this->smarty->fetch('role-list.tpl');

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

        $title = 'User Roles';

        // Get the user roles.
        $userRoles = UserRole::getRoles();
        $roles = [];
        foreach ($userRoles as $userRole) {
            $roles[$userRole['id']] = $userRole['name'];
        }

        switch ($request->input('action') ?? 'view') {
            case 'add':
                $title = 'User Roles Add';
                $role = [
                    'id'               => '',
                    'name'             => '',
                    'apirequests'      => '',
                    'downloadrequests' => '',
                    'defaultinvites'   => '',
                    'canpreview'       => 0,
                    'hideads'          => 0,
                    'donation'         => 0,
                    'addyears'         => 0,
                ];
                $this->smarty->assign('role', $role);
                break;

            case 'submit':
                if (! $request->has('id')) {
                    $role = UserRole::addRole($request->all());
                } else {
                    $title = 'Update User Role';
                    $role = UserRole::updateRole($request->all());

                    $request->merge(['exccat' => (! $request->has('exccat') || ! \is_array($request->input('exccat'))) ? [] : $request->input('exccat')]);
                    RoleExcludedCategory::addRoleCategoryExclusions($request->input('id'), $request->input('exccat'));
                }
                $this->smarty->assign('role', $role);
                redirect()->to('admin/role-list')->sendHeaders();
                break;

            case 'view':
            default:
                if ($request->has('id')) {
                    $title = 'User Roles Edit';
                    $role = UserRole::getRoleById($request->input('id'));
                    $this->smarty->assign('role', $role);
                    $this->smarty->assign('roleexccat', RoleExcludedCategory::getRoleCategoryExclusion($request->input('id')));
                }
                break;
        }

        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['Yes', 'No']);
        $this->smarty->assign('catlist', Category::getForSelect(false));

        $content = $this->smarty->fetch('role-edit.tpl');

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
     */
    public function destroy(Request $request)
    {
        if ($request->has('id')) {
            UserRole::deleteRole($request->input('id'));
        }

        return redirect($request->server('HTTP_REFERER'));
    }
}
