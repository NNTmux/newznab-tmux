<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SignupError;
use App\Enums\UserRole;
use App\Http\Controllers\BasePageController;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserDownload;
use App\Models\UserRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class AdminUserController extends BasePageController
{
    /**
     * @throws \Throwable
     */
    public function index(Request $request): View
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'User List';

        $roles = Role::pluck('name', 'id')->toArray();

        $ordering = getUserBrowseOrdering();
        $orderBy = $request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '';
        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_page');

        $variables = [
            'username' => $request->has('username') ? $request->input('username') : '',
            'email' => $request->has('email') ? $request->input('email') : '',
            'host' => $request->has('host') ? $request->input('host') : '',
            'role' => $request->has('role') ? $request->input('role') : '',
            'created_from' => $request->has('created_from') ? $request->input('created_from') : '',
            'created_to' => $request->has('created_to') ? $request->input('created_to') : '',
        ];

        $result = User::getRange(
            $offset,
            (int) config('nntmux.items_per_page'),
            $orderBy,
            $variables['username'],
            $variables['email'],
            $variables['host'],
            $variables['role'],
            $variables['created_from'],
            $variables['created_to']
        );

        $results = $this->paginate($result, User::getCount($variables['role'], $variables['username'], $variables['host'], $variables['email'], $variables['created_from'], $variables['created_to']), config('nntmux.items_per_page'), $page, $request->url(), $request->query());

        // Build order by URLs
        $orderByUrls = [];
        foreach ($ordering as $orderType) {
            $orderByUrls['orderby'.$orderType] = url('admin/user-list?ob='.$orderType);
        }

        $this->viewData = array_merge($this->viewData, [
            'username' => $variables['username'],
            'email' => $variables['email'],
            'host' => $variables['host'],
            'role' => $variables['role'],
            'created_from' => $variables['created_from'],
            'created_to' => $variables['created_to'],
            'role_ids' => array_keys($roles),
            'role_names' => $roles,
            'userlist' => $results,
            'title' => $title,
            'meta_title' => $meta_title,
        ], $orderByUrls);

        return view('admin.users.index', $this->viewData);
    }

    /**
     * @return RedirectResponse|\Illuminate\View\View
     *
     * @throws \Exception|\Throwable
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();

        $user = [
            'id' => '',
            'username' => '',
            'email' => '',
            'password' => '',
            'role' => UserRole::USER->value,
            'notes' => '',
            'rate_limit' => 60,
        ];

        $meta_title = $title = 'View User';

        // set the current action
        $action = $request->input('action') ?? 'view';

        // get the user roles
        $userRoles = Role::cursor()->remember();
        $roles = [];
        $defaultRole = 'User';
        $defaultInvites = Invitation::DEFAULT_INVITES;
        foreach ($userRoles as $r) {
            $roles[$r->id] = $r->name;
            if ($r->isdefault === 1) {
                $defaultRole = $r->id;
                $defaultInvites = $r->defaultinvites;
            }
        }

        $error = null;

        switch ($action) {
            case 'add':
                $user += [
                    'role' => $defaultRole,
                    'notes' => '',
                    'invites' => $defaultInvites,
                    'movieview' => 0,
                    'musicview' => 0,
                    'consoleview' => 0,
                    'gameview' => 0,
                    'bookview' => 0,
                ];
                break;
            case 'submit':
                if (empty($request->input('id'))) {
                    $invites = $defaultInvites;
                    foreach ($userRoles as $role) {
                        if ($role['id'] === $request->input('role')) {
                            $invites = $role['defaultinvites'];
                        }
                    }
                    $ret = User::signUp($request->input('username'), $request->input('password'), $request->input('email'), '', $request->input('notes'), $invites, '', true, $request->input('role'), false);
                } else {
                    $editedUser = User::find($request->input('id'));

                    // Check if role is changing and get stack preference
                    $roleChanged = $editedUser->roles_id != $request->input('role');
                    $stackRole = $request->input('stack_role') ? true : false; // Check if checkbox is checked
                    $changedBy = auth()->check() ? auth()->id() : null;

                    // CRITICAL: Capture the ORIGINAL rolechangedate BEFORE any updates
                    // This is needed for accurate role history tracking
                    // Convert to string to avoid any Carbon object reference issues
                    $originalRoleChangeDate = $editedUser->rolechangedate
                        ? $editedUser->rolechangedate->toDateTimeString()
                        : null;

                    Log::info('AdminUserController - Before updates', [
                        'user_id' => $editedUser->id,
                        'originalRoleChangeDate' => $originalRoleChangeDate,
                        'current_roles_id' => $editedUser->roles_id,
                        'requested_role' => $request->input('role'),
                        'roleChanged' => $roleChanged,
                        'stackRole' => $stackRole,
                        'form_rolechangedate' => $request->input('rolechangedate'),
                    ]);

                    // Handle pending role cancellation
                    if ($request->has('cancel_pending_role') && $request->input('cancel_pending_role')) {
                        $editedUser->cancelPendingRole();
                    }

                    // Handle rolechangedate - Update the expiry for the CURRENT role FIRST
                    // This must happen BEFORE role change so the new expiry applies to the old role
                    $adminManuallySetExpiry = false;
                    if ($request->has('rolechangedate')) {
                        $roleChangeDate = $request->input('rolechangedate');
                        if (! empty($roleChangeDate)) {
                            User::updateUserRoleChangeDate($editedUser->id, $roleChangeDate);
                            $adminManuallySetExpiry = true; // Flag that admin set custom expiry
                        } else {
                            // Clear the rolechangedate if empty string is provided
                            $editedUser->update(['rolechangedate' => null]);
                        }
                        $editedUser->refresh();

                        Log::info('AdminUserController - After expiry update', [
                            'user_id' => $editedUser->id,
                            'new_rolechangedate' => $editedUser->rolechangedate,
                            'adminManuallySetExpiry' => $adminManuallySetExpiry,
                        ]);
                    }

                    // If role is changing, handle it with stacking logic
                    // Pass the original expiry so history records the correct old_expiry_date
                    if ($roleChanged && $request->input('role') !== null) {
                        Log::info('AdminUserController - About to call updateUserRole', [
                            'user_id' => $editedUser->id,
                            'new_role' => (int) $request->input('role'),
                            'originalRoleChangeDate_passed' => $originalRoleChangeDate,
                            'current_user_rolechangedate' => $editedUser->rolechangedate,
                        ]);

                        User::updateUserRole(
                            $editedUser->id,
                            (int) $request->input('role'), // Cast to integer
                            ! $adminManuallySetExpiry, // Only apply promotions if admin didn't set custom expiry
                            $stackRole, // Stack role if requested
                            $changedBy,
                            $originalRoleChangeDate, // Pass original expiry for history
                            $adminManuallySetExpiry // Preserve admin's manually set expiry date
                        );
                        $editedUser->refresh();
                    }
                    // Note: We don't call updateUserRole when role hasn't changed
                    // If admin manually set a rolechangedate, that's already applied above

                    // Update user basic information (but NOT the role - it's handled above)
                    // Use current role to avoid overwriting
                    $ret = User::updateUser(
                        $editedUser->id,
                        $request->input('username'),
                        $request->input('email'),
                        $editedUser->grabs,
                        $editedUser->roles_id, // Use current role, not the request role
                        $request->input('notes'),
                        (int) $request->input('invites', 0),
                        ($request->has('movieview') ? 1 : 0),
                        ($request->has('musicview') ? 1 : 0),
                        ($request->has('gameview') ? 1 : 0),
                        ($editedUser->xxxview ? 1 : 0),
                        ($request->has('consoleview') ? 1 : 0),
                        ($request->has('bookview') ? 1 : 0)
                    );

                    if ($request->input('password') !== null) {
                        User::updatePassword($editedUser->id, $request->input('password'));
                    }
                }

                if ($ret >= 0) {
                    return redirect()->to('admin/user-list');
                }

                $error = match ($ret) {
                    SignupError::BAD_USERNAME->value => 'Bad username. Try a better one.',
                    SignupError::BAD_PASSWORD->value => 'Bad password. Try a longer one.',
                    SignupError::BAD_EMAIL->value => 'Bad email.',
                    SignupError::USERNAME_IN_USE->value => 'Username in use.',
                    SignupError::EMAIL_IN_USE->value => 'Email in use.',
                    default => 'Unknown save error.',
                };
                $user += [
                    'id' => $request->input('id'),
                    'username' => $request->input('username'),
                    'email' => $request->input('email'),
                    'role' => $request->input('role'),
                    'notes' => $request->input('notes'),
                ];
                break;
            case 'view':
            default:
                if ($request->has('id')) {
                    $title = 'User Edit';
                    $id = $request->input('id');
                    $user = User::find($id);

                    // Add daily API and download counts
                    if ($user) {
                        try {
                            $user->daily_api_count = UserRequest::getApiRequests($user->id);
                            $user->daily_download_count = UserDownload::getDownloadRequests($user->id);
                        } catch (\Exception $e) {
                            $user->daily_api_count = 0;
                            $user->daily_download_count = 0;
                        }
                    }
                }

                break;
        }

        $this->viewData = array_merge($this->viewData, [
            'yesno_ids' => [1, 0],
            'yesno_names' => ['Yes', 'No'],
            'role_ids' => array_keys($roles),
            'role_names' => $roles,
            'user' => $user,
            'error' => $error,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.users.edit', $this->viewData);
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($request->has('id')) {
            $user = User::find($request->input('id'));
            if ($user === null) {
                return redirect()->back();
            }
            $username = $user->username;

            $user->delete();

            // Redirect with username to display in notification
            return redirect()->to('admin/user-list?deleted=1&username='.urlencode($username));
        }

        if ($request->has('redir')) {
            return redirect()->to($request->input('redir'));
        }

        return redirect()->to($request->server('HTTP_REFERER'));
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        if ($request->has('id')) {
            $user = User::find($request->input('id'));
            if ($user === null) {
                return redirect()->back()->with('error', 'User is invalid');
            }

            $user->sendEmailVerificationNotification();

            return redirect()->back()->with('success', 'Email verification for '.$user->username.' sent');
        }

        return redirect()->back()->with('error', 'User is invalid');
    }

    public function verify(Request $request): RedirectResponse
    {
        if ($request->has('id')) {
            $user = User::find($request->input('id'));
            if ($user === null) {
                return redirect()->back()->with('error', 'User is invalid');
            }

            $user->markEmailAsVerified();

            return redirect()->back()->with('success', 'Email verification for '.$user->username.' completed');
        }

        return redirect()->back()->with('error', 'User is invalid');
    }
}
