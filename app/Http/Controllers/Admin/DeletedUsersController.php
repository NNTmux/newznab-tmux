<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DeletedUsersController extends BasePageController
{
    /**
     * Display a listing of soft-deleted users with filtering, sorting and pagination.
     */
    public function index(Request $request)
    {
        $this->setAdminPrefs();

        // Filters
        $username = $request->input('username', '');
        $email = $request->input('email', '');
        $host = $request->input('host', '');
        $orderBy = $request->filled('ob') ? $request->input('ob') : 'deleted_at_desc';
        $createdFrom = $request->input('created_from', '');
        $createdTo = $request->input('created_to', '');
        $deletedFrom = $request->input('deleted_from', '');
        $deletedTo = $request->input('deleted_to', '');

        $deletedUsers = User::onlyTrashed()
            ->leftJoin('roles', 'roles.id', '=', 'users.roles_id')
            ->select('users.*', 'roles.name as rolename')
            // Qualify all columns to avoid ambiguity with joined tables
            ->when($username !== '', fn ($q) => $q->where('users.username', 'like', "%$username%"))
            ->when($email !== '', fn ($q) => $q->where('users.email', 'like', "%$email%"))
            ->when($host !== '', fn ($q) => $q->where('users.host', 'like', "%$host%"))
            // Created date filters
            ->when($createdFrom !== '' || $createdTo !== '', function ($q) use ($createdFrom, $createdTo) {
                try {
                    if ($createdFrom !== '' && $createdTo !== '') {
                        $from = Carbon::createFromFormat('Y-m-d', $createdFrom)->startOfDay();
                        $to = Carbon::createFromFormat('Y-m-d', $createdTo)->endOfDay();
                        $q->whereBetween('users.created_at', [$from, $to]);
                    } elseif ($createdFrom !== '') {
                        $from = Carbon::createFromFormat('Y-m-d', $createdFrom)->startOfDay();
                        $q->where('users.created_at', '>=', $from);
                    } elseif ($createdTo !== '') {
                        $to = Carbon::createFromFormat('Y-m-d', $createdTo)->endOfDay();
                        $q->where('users.created_at', '<=', $to);
                    }
                } catch (\Exception $e) {
                    // ignore invalid dates
                }
            })
            // Deleted date filters
            ->when($deletedFrom !== '' || $deletedTo !== '', function ($q) use ($deletedFrom, $deletedTo) {
                try {
                    if ($deletedFrom !== '' && $deletedTo !== '') {
                        $from = Carbon::createFromFormat('Y-m-d', $deletedFrom)->startOfDay();
                        $to = Carbon::createFromFormat('Y-m-d', $deletedTo)->endOfDay();
                        $q->whereBetween('users.deleted_at', [$from, $to]);
                    } elseif ($deletedFrom !== '') {
                        $from = Carbon::createFromFormat('Y-m-d', $deletedFrom)->startOfDay();
                        $q->where('users.deleted_at', '>=', $from);
                    } elseif ($deletedTo !== '') {
                        $to = Carbon::createFromFormat('Y-m-d', $deletedTo)->endOfDay();
                        $q->where('users.deleted_at', '<=', $to);
                    }
                } catch (\Exception $e) {
                    // ignore invalid dates
                }
            });

        // Sorting
        [$orderField, $orderSort] = $this->getSortOrder($orderBy);
        $deletedUsers = $deletedUsers->orderBy($orderField, $orderSort)
            ->paginate(25)
            ->appends($request->except('page'));

        // Build query string (exclude ordering + pagination) for sort links
        $qsParams = $request->except(['ob', 'page']);
        $queryString = http_build_query(array_filter($qsParams, fn ($v) => $v !== '' && $v !== null));

        $this->viewData = array_merge($this->viewData, [
            'deletedusers' => $deletedUsers,
            'username' => $username,
            'email' => $email,
            'host' => $host,
            'orderby' => $orderBy,
            'created_from' => $createdFrom,
            'created_to' => $createdTo,
            'deleted_from' => $deletedFrom,
            'deleted_to' => $deletedTo,
            'queryString' => $queryString,
            'meta_title' => 'Deleted Users',
            'meta_keywords' => 'view,deleted,users,softdeleted',
            'meta_description' => 'View and restore soft-deleted user accounts',
            'title' => 'Deleted Users',
        ]);

        return view('admin.deleted-users', $this->viewData);
    }

    /**
     * Bulk restore or permanent delete.
     */
    public function bulkAction(Request $request)
    {
        $action = $request->input('action');
        $userIds = $request->input('user_ids', []);

        if (! in_array($action, ['restore', 'delete'], true) || empty($userIds) || ! is_array($userIds)) {
            return redirect()->route('admin.deleted.users.index')->with('error', 'Invalid bulk action request.');
        }

        $userIds = array_filter(array_map('intval', $userIds));
        if (empty($userIds)) {
            return redirect()->route('admin.deleted.users.index')->with('error', 'No valid users selected.');
        }

        if ($action === 'restore') {
            $count = User::onlyTrashed()->whereIn('id', $userIds)->restore();

            return redirect()->route('admin.deleted.users.index')->with('success', $count.' user(s) restored successfully.');
        }

        $count = User::onlyTrashed()->whereIn('id', $userIds)->forceDelete();

        return redirect()->route('admin.deleted.users.index')->with('success', $count.' user(s) permanently deleted.');
    }

    /**
     * Restore single user.
     */
    public function restore($id)
    {
        $user = User::onlyTrashed()->find($id);
        if ($user) {
            $user->restore();

            return redirect()->route('admin.deleted.users.index')->with('success', "User '{$user->username}' has been restored successfully.");
        }

        return redirect()->route('admin.deleted.users.index')->with('error', 'User not found.');
    }

    /**
     * Permanently delete single user.
     */
    public function permanentDelete($id)
    {
        $user = User::onlyTrashed()->find($id);
        if ($user) {
            $username = $user->username;
            $user->forceDelete();

            return redirect()->route('admin.deleted.users.index')->with('success', "User '{$username}' has been permanently deleted.");
        }

        return redirect()->route('admin.deleted.users.index')->with('error', 'User not found.');
    }

    /**
     * Parse and validate sort order.
     */
    private function getSortOrder(string $orderBy): array
    {
        // Accept patterns like `deleted_at_desc`, `deletedat_desc`, `username`, etc.
        $orderBy = strtolower(trim($orderBy));
        $orderField = 'users.deleted_at'; // sensible default
        $orderSort = 'desc';

        if (preg_match('/^(?P<field>[a-z0-9_]+?)(?:_(?P<dir>asc|desc))?$/', $orderBy, $m)) {
            $rawField = $m['field'];
            $normalized = str_replace(['_', '-'], '', $rawField); // normalize for mapping keys
            $dir = $m['dir'] ?? null;
            $map = [
                'username' => 'users.username',
                'email' => 'users.email',
                'host' => 'users.host',
                'createdat' => 'users.created_at',
                'deletedat' => 'users.deleted_at',
                'lastlogin' => 'users.lastlogin',
                'apiaccess' => 'users.apiaccess',
                'grabs' => 'users.grabs',
                'role' => 'rolename', // alias
                'rolename' => 'rolename',
            ];
            if (isset($map[$normalized])) {
                $orderField = $map[$normalized];
            }
            if ($dir) {
                $orderSort = $dir;
            }
        }

        return [$orderField, $orderSort];
    }
}
