<?php

declare(strict_types=1);

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
    public function index(Request $request): mixed
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'User Role History';

        // Get all roles for filter
        $roles = Role::all()->pluck('name', 'id')->toArray();

        $filters = [
            'user_id' => $this->scalarInput($request, 'user_id'),
            'username' => $this->scalarInput($request, 'username'),
            'role_id' => $this->scalarInput($request, 'role_id'),
            'change_reason' => $this->scalarInput($request, 'change_reason'),
            'date_from' => $this->scalarInput($request, 'date_from'),
            'date_to' => $this->scalarInput($request, 'date_to'),
        ];

        // Build query
        $query = UserRoleHistory::with(['user', 'oldRole', 'newRole', 'changedByUser'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($filters['user_id'] !== '') {
            $query->where('user_id', $filters['user_id']);
        }

        if ($filters['username'] !== '') {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('username', 'like', '%'.$filters['username'].'%');
            });
        }

        if ($filters['role_id'] !== '') {
            $query->where(function ($q) use ($filters) {
                $q->where('old_role_id', $filters['role_id'])
                    ->orWhere('new_role_id', $filters['role_id']);
            });
        }

        if ($filters['change_reason'] !== '') {
            $query->where('change_reason', 'like', '%'.$filters['change_reason'].'%');
        }

        if ($filters['date_from'] !== '') {
            $query->where('created_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if ($filters['date_to'] !== '') {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        // Pagination
        $page = $this->resolvePage($request);
        $perPage = config('nntmux.items_per_page', 50);

        $results = $query->paginate($perPage, ['*'], 'page', $page);

        $this->viewData = array_merge($this->viewData, [
            'title' => $title,
            'meta_title' => $meta_title,
            'history' => $results,
            'roles' => $roles,
            'filters' => $filters,
        ]);

        return view('admin.user-role-history.index', $this->viewData);
    }

    /**
     * Display role history for a specific user
     */
    public function show(Request $request, int $userId): mixed
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
