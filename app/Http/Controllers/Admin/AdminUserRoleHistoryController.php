<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\User;
use App\Models\UserRoleHistory;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AdminUserRoleHistoryController extends BasePageController
{
    /**
     * Display user role history list
     */
    public function index(Request $request)
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'User Role History';

        // Get all roles for filter
        $roles = Role::all()->pluck('name', 'id')->toArray();

        // Build query
        $query = UserRoleHistory::with(['user', 'oldRole', 'newRole', 'changedByUser'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('user_id') && ! empty($request->input('user_id'))) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('username') && ! empty($request->input('username'))) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('username', 'like', '%'.$request->input('username').'%');
            });
        }

        if ($request->has('role_id') && ! empty($request->input('role_id'))) {
            $query->where(function ($q) use ($request) {
                $q->where('old_role_id', $request->input('role_id'))
                    ->orWhere('new_role_id', $request->input('role_id'));
            });
        }

        if ($request->has('change_reason') && ! empty($request->input('change_reason'))) {
            $query->where('change_reason', 'like', '%'.$request->input('change_reason').'%');
        }

        if ($request->has('date_from') && ! empty($request->input('date_from'))) {
            $query->where('created_at', '>=', $request->input('date_from').' 00:00:00');
        }

        if ($request->has('date_to') && ! empty($request->input('date_to'))) {
            $query->where('created_at', '<=', $request->input('date_to').' 23:59:59');
        }

        // Pagination
        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $perPage = config('nntmux.items_per_page', 50);

        $results = $query->paginate($perPage, ['*'], 'page', $page);

        $this->viewData = array_merge($this->viewData, [
            'title' => $title,
            'meta_title' => $meta_title,
            'history' => $results,
            'roles' => $roles,
            'filters' => [
                'user_id' => $request->input('user_id', ''),
                'username' => $request->input('username', ''),
                'role_id' => $request->input('role_id', ''),
                'change_reason' => $request->input('change_reason', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
        ]);

        return view('admin.user-role-history.index', $this->viewData);
    }

    /**
     * Display role history for a specific user
     */
    public function show(Request $request, int $userId)
    {
        $this->setAdminPrefs();

        $user = User::findOrFail($userId);
        $meta_title = $title = 'Role History for '.$user->username;

        $history = UserRoleHistory::with(['oldRole', 'newRole', 'changedByUser'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(config('nntmux.items_per_page', 50));

        $this->viewData = array_merge($this->viewData, [
            'title' => $title,
            'meta_title' => $meta_title,
            'user' => $user,
            'history' => $history,
        ]);

        return view('admin.user-role-history.show', $this->viewData);
    }
}
