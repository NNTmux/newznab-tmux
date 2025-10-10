<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AdminRoleController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'User Role List';

        // get the user roles
        $userroles = Role::cursor()->remember();

        $this->viewData = array_merge($this->viewData, [
            'userroles' => $userroles,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.roles.index', $this->viewData);
    }

    /**
     * @throws \Exception
     */
    public function create(Request $request)
    {
        $this->setAdminPrefs();

        switch ($request->input('action') ?? 'view') {
            case 'submit':
                $meta_title = $title = 'Add User Role';
                $role = Role::create([
                    'name' => $request->input('name'),
                    'apirequests' => $request->input('apirequests'),
                    'downloadrequests' => $request->input('downloadrequests'),
                    'defaultinvites' => $request->input('defaultinvites'),
                    'donation' => $request->input('donation') ?? 0,
                    'addyears' => $request->input('addyears') ?? 0,
                    'rate_limit' => $request->input('rate_limit'),
                ]);
                if ((int) $request->input('canpreview') === 1) {
                    $role->givePermissionTo('preview');
                }

                if ((int) $request->input('hideads') === 1) {
                    $role->givePermissionTo('hideads');
                }

                if ((int) $request->input('editrelease') === 1) {
                    $role->givePermissionTo('edit release');
                }

                if ((int) $request->input('viewconsole') === 1) {
                    $role->givePermissionTo('view console');
                }

                if ((int) $request->input('viewmovies') === 1) {
                    $role->givePermissionTo('view movies');
                }

                if ((int) $request->input('viewaudio') === 1) {
                    $role->givePermissionTo('view audio');
                }

                if ((int) $request->input('viewpc') === 1) {
                    $role->givePermissionTo('view pc');
                }

                if ((int) $request->input('viewtv') === 1) {
                    $role->givePermissionTo('view tv');
                }

                if ((int) $request->input('viewadult') === 1) {
                    $role->givePermissionTo('view adult');
                }

                if ((int) $request->input('viewbooks') === 1) {
                    $role->givePermissionTo('view books');
                }

                if ((int) $request->input('viewother') === 1) {
                    $role->givePermissionTo('view other');
                }

                return redirect()->to('admin/role-list');
            case 'view':
            default:
                $meta_title = $title = 'Add User Role';
                $role = [];
                break;
        }

        $this->viewData = array_merge($this->viewData, [
            'yesno_ids' => [1, 0],
            'yesno_names' => ['Yes', 'No'],
            'title' => $title,
            'meta_title' => $meta_title,
            'role' => $role,
        ]);

        return view('admin.roles.add', $this->viewData);
    }

    /**
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'User Roles';
        $role = null;

        // Get the user roles.
        $userRoles = Role::cursor()->remember();
        $roles = [];
        foreach ($userRoles as $userRole) {
            $roles[$userRole->id] = $userRole->name;
        }

        switch ($request->input('action') ?? 'view') {
            case 'submit':
                $meta_title = $title = 'Update User Role';
                $role = Role::find($request->input('id'));
                $role->update([
                    'name' => $request->input('name'),
                    'apirequests' => $request->input('apirequests'),
                    'downloadrequests' => $request->input('downloadrequests'),
                    'defaultinvites' => $request->input('defaultinvites'),
                    'isdefault' => $request->input('isdefault'),
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

                if ((int) $request->input('editrelease') === 1 && $role->hasPermissionTo('edit release') === false) {
                    $role->givePermissionTo('edit release');
                } elseif ((int) $request->input('editrelease') === 0 && $role->hasPermissionTo('edit release') === true) {
                    $role->revokePermissionTo('edit release');
                }

                if ((int) $request->input('viewconsole') === 1 && $role->hasPermissionTo('view console') === false) {
                    $role->givePermissionTo('view console');
                } elseif ((int) $request->input('viewconsole') === 0 && $role->hasPermissionTo('view console') === true) {
                    $role->revokePermissionTo('view console');
                }

                if ((int) $request->input('viewmovies') === 1 && $role->hasPermissionTo('view movies') === false) {
                    $role->givePermissionTo('view movies');
                } elseif ((int) $request->input('viewmovies') === 0 && $role->hasPermissionTo('view movies') === true) {
                    $role->revokePermissionTo('view movies');
                }

                if ((int) $request->input('viewaudio') === 1 && $role->hasPermissionTo('view audio') === false) {
                    $role->givePermissionTo('view audio');
                } elseif ((int) $request->input('viewaudio') === 0 && $role->hasPermissionTo('view audio') === true) {
                    $role->revokePermissionTo('view audio');
                }

                if ((int) $request->input('viewpc') === 1 && $role->hasPermissionTo('view pc') === false) {
                    $role->givePermissionTo('view pc');
                } elseif ((int) $request->input('viewpc') === 0 && $role->hasPermissionTo('view pc') === true) {
                    $role->revokePermissionTo('view pc');
                }

                if ((int) $request->input('viewtv') === 1 && $role->hasPermissionTo('view tv') === false) {
                    $role->givePermissionTo('view tv');
                } elseif ((int) $request->input('viewtv') === 0 && $role->hasPermissionTo('view tv') === true) {
                    $role->revokePermissionTo('view tv');
                }

                if ((int) $request->input('viewadult') === 1 && $role->hasPermissionTo('view adult') === false) {
                    $role->givePermissionTo('view adult');
                } elseif ((int) $request->input('viewadult') === 0 && $role->hasPermissionTo('view adult') === true) {
                    $role->revokePermissionTo('view adult');
                }

                if ((int) $request->input('viewbooks') === 1 && $role->hasPermissionTo('view books') === false) {
                    $role->givePermissionTo('view books');
                } elseif ((int) $request->input('viewbooks') === 0 && $role->hasPermissionTo('view books') === true) {
                    $role->revokePermissionTo('view books');
                }

                if ((int) $request->input('viewother') === 1 && $role->hasPermissionTo('view other') === false) {
                    $role->givePermissionTo('view other');
                } elseif ((int) $request->input('viewother') === 0 && $role->hasPermissionTo('view other') === true) {
                    $role->revokePermissionTo('view other');
                }

                return redirect()->to('admin/role-list');

            case 'view':
            default:
                if ($request->has('id')) {
                    $meta_title = $title = 'User Roles Edit';
                    $role = Role::findById($request->input('id'));
                }
                break;
        }

        $this->viewData = array_merge($this->viewData, [
            'yesno_ids' => [1, 0],
            'yesno_names' => ['Yes', 'No'],
            'title' => $title,
            'meta_title' => $meta_title,
            'role' => $role,
        ]);

        return view('admin.roles.edit', $this->viewData);
    }

    public function destroy(Request $request): \Illuminate\Http\RedirectResponse
    {
        if ($request->has('id')) {
            Role::query()->where('id', $request->input('id'))->delete();
        }

        return redirect()->to($request->server('HTTP_REFERER'));
    }
}
