<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\User;
use Illuminate\Http\Request;

class DeletedUsersController extends BasePageController
{
    /**
     * Display a listing of soft-deleted users.
     */
    public function index(Request $request)
    {
        $this->setAdminPrefs();

        $username = $request->has('username') ? $request->input('username') : '';
        $email = $request->has('email') ? $request->input('email') : '';
        $host = $request->has('host') ? $request->input('host') : '';
        $orderBy = $request->has('ob') && ! empty($request->input('ob')) ? $request->input('ob') : 'deleted_at_desc';

        $deletedUsers = User::onlyTrashed()
            ->when($username !== '', function ($query) use ($username) {
                return $query->where('username', 'like', '%'.$username.'%');
            })
            ->when($email !== '', function ($query) use ($email) {
                return $query->where('email', 'like', '%'.$email.'%');
            })
            ->when($host !== '', function ($query) use ($host) {
                return $query->where('host', 'like', '%'.$host.'%');
            });

        // Determine sort order
        [$orderField, $orderSort] = $this->getSortOrder($orderBy);
        $deletedUsers = $deletedUsers->orderBy($orderField, $orderSort)->paginate(25);

        $this->smarty->assign([
            'deletedusers' => $deletedUsers,
            'username' => $username,
            'email' => $email,
            'host' => $host,
            'orderby' => $orderBy,
        ]);

        $meta_title = 'Deleted Users';
        $meta_keywords = 'view,deleted,users,softdeleted';
        $meta_description = 'View and restore soft-deleted user accounts';

        $content = $this->smarty->fetch('deleted_users.tpl');
        $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));

        $this->adminrender();
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore($id)
    {
        $user = User::onlyTrashed()->find($id);

        if ($user) {
            $user->restore();

            return redirect()->route('admin.deleted.users.index')
                ->with('success', "User '{$user->username}' has been restored successfully.");
        }

        return redirect()->route('admin.deleted.users.index')
            ->with('error', 'User not found.');
    }

    /**
     * Permanently delete a soft-deleted user.
     */
    public function permanentDelete($id)
    {
        $user = User::onlyTrashed()->find($id);

        if ($user) {
            $username = $user->username;
            $user->forceDelete();

            return redirect()->route('admin.deleted.users.index')
                ->with('success', "User '{$username}' has been permanently deleted.");
        }

        return redirect()->route('admin.deleted.users.index')
            ->with('error', 'User not found.');
    }

    /**
     * Parse sort order from the orderBy parameter.
     */
    private function getSortOrder($orderBy): array
    {
        $orderArr = explode('_', $orderBy);
        $orderField = match ($orderArr[0]) {
            'email' => 'email',
            'host' => 'host',
            'createdat' => 'created_at',
            'deletedat' => 'deleted_at',
            'lastlogin' => 'lastlogin',
            'apiaccess' => 'apiaccess',
            'grabs' => 'grabs',
            'role' => 'roles_id',
            default => 'username',
        };
        $orderSort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

        return [$orderField, $orderSort];
    }
}
