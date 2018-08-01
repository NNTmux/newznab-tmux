<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
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
        $userroles = Role::all()->toArray();

        $this->smarty->assign('userroles', $userroles);

        $content = $this->smarty->fetch('role-list.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'meta_title' => $title,
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
        $userRoles = Role::all()->toArray();
        $roles = [];
        foreach ($userRoles as $userRole) {
            $roles[$userRole['id']] = $userRole['name'];
        }

        switch ($request->input('action') ?? 'view') {
            case 'add':
                $title = 'Add User Role';
                $role = [
                    'id'               => '',
                    'name'             => '',
                    'apirequests'      => '',
                    'downloadrequests' => '',
                    'defaultinvites'   => '',
                    'isdefault'        => 0,
                    'canpreview'       => 0,
                    'hideads'          => 0,
                    'donation'         => 0,
                    'addyears'         => 0,
                ];
                $this->smarty->assign('role', $role);
                break;

            case 'submit':
                if (empty($request->input('id'))) {
                    $title = 'Add User Role';
                    $role = Role::create([
                        'name' => $request->input('name'),
                        'apirequests' => $request->input('apirequests'),
                        'downloadrequests' => $request->input('downloadrequests'),
                        'defaultinvites' => $request->input('defaultinvites'),
                        'donation' => $request->input('donation'),
                        'addyears' => $request->input('addyears'),
                        'rate_limit' => $request->input('rate_limit'),
                    ]);
                    if ((int) $request->input('canpreview') === 1) {
                        $role->givePermissionTo('preview');
                    }

                    if ((int) $request->input('hideads') === 1) {
                        $role->givePermissionTo('hideads');
                    }
                } else {
                    $title = 'Update User Role';
                    $role = Role::find($request->input('id'));
                    $role->update([
                        'name' => $request->input('name'),
                        'apirequests' => $request->input('apirequests'),
                        'downloadrequests' => $request->input('downloadrequests'),
                        'defaultinvites' => $request->input('defaultinvites'),
                        'donation' => $request->input('donation'),
                        'addyears' => $request->input('addyears'),
                        'rate_limit' => $request->input('rate_limit'),
                    ]);

                    if ((int) $request->input('canpreview') === 1 && $role->hasPermissionTo('preview') === false) {
                        $role->givePermissionTo('preview');
                    } elseif ((int) $request->input('canpreview') === 0 && $role->hasPermissionTo('preview') === true) {
                        $role->revokePermissionTo('preview');
                    }

                    if ((int) $request->input('hideads') === 1 && $role->hasPermissionTo('hideads') === false) {
                        $role->givePermissionTo('hideads');
                    } elseif ((int) $request->input('hideads') === 0 && $role->hasPermissionTo('hideads') === true) {
                        $role->revokePermissionTo('hideads');
                    }

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
                    $role = Role::findById($request->input('id'));
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
                'meta_title' => $title,
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
            Role::query()->where('id', $request->input('id'))->delete();
        }

        return redirect($request->server('HTTP_REFERER'));
    }
}
