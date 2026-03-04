<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Support\PermissionSyncHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AdminRoleController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(): View
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
    public function create(Request $request): View|RedirectResponse
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
                PermissionSyncHelper::grantRolePermissions($role, $request);

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
    public function edit(Request $request): View|RedirectResponse
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'User Roles';
        $role = null;

        $roles = Role::pluck('name', 'id')->toArray();

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

                PermissionSyncHelper::syncRolePermissions($role, $request);

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

    public function destroy(Request $request): RedirectResponse
    {
        if ($request->has('id')) {
            Role::query()->where('id', $request->input('id'))->delete();
        }

        return redirect()->to($request->server('HTTP_REFERER'));
    }
}
