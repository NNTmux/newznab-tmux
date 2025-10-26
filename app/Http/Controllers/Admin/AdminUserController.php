<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Jrean\UserVerification\Facades\UserVerification;
use Spatie\Permission\Models\Role;
use Stevebauman\Location\Facades\Location;

class AdminUserController extends BasePageController
{
    /**
     * @throws \Throwable
     */
    public function index(Request $request)
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'User List';

        $roles = [];
        $userRoles = Role::cursor()->remember();
        foreach ($userRoles as $userRole) {
            $roles[$userRole->id] = $userRole->name;
        }

        $ordering = getUserBrowseOrdering();
        $orderBy = $request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '';
        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_page');

        $variables = [
            'username' => $request->has('username') ? $request->input('username') : '',
            'email' => $request->has('email') ? $request->input('email') : '',
            'host' => $request->has('host') ? $request->input('host') : '',
            'role' => $request->has('role') ? $request->input('role') : '',
        ];

        $result = User::getRange(
            $offset,
            config('nntmux.items_per_page'),
            $orderBy,
            $variables['username'],
            $variables['email'],
            $variables['host'],
            $variables['role'],
            true
        );

        $results = $this->paginate($result ?? [], User::getCount($variables['role'], $variables['username'], $variables['host'], $variables['email']) ?? 0, config('nntmux.items_per_page'), $page, $request->url(), $request->query());

        // Add country data to each user based on their host IP
        foreach ($results as $user) {
            $position = null;
            if (! empty($user->host) && filter_var($user->host, FILTER_VALIDATE_IP)) {
                $position = Location::get($user->host);
            }
            $user->country_name = $position ? $position->countryName : null;
            $user->country_code = $position ? $position->countryCode : null;
        }

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
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();

        $user = [
            'id' => '',
            'username' => '',
            'email' => '',
            'password' => '',
            'role' => User::ROLE_USER,
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
                    'xxxview' => 0,
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
                    $ret = User::updateUser($editedUser->id, $request->input('username'), $request->input('email'), $request->input('grabs'), $request->input('role'), $request->input('notes'), $request->input('invites'), ($request->has('movieview') ? 1 : 0), ($request->has('musicview') ? 1 : 0), ($request->has('gameview') ? 1 : 0), ($request->has('xxxview') ? 1 : 0), ($request->has('consoleview') ? 1 : 0), ($request->has('bookview') ? 1 : 0));
                    if ($request->input('password') !== null) {
                        User::updatePassword($editedUser->id, $request->input('password'));
                    }
                    // Handle rolechangedate - update if has value, clear if empty
                    if ($request->has('rolechangedate')) {
                        $roleChangeDate = $request->input('rolechangedate');
                        if (! empty($roleChangeDate)) {
                            User::updateUserRoleChangeDate($editedUser->id, $roleChangeDate);
                        } else {
                            // Clear the rolechangedate if empty string is provided
                            $editedUser->update(['rolechangedate' => null]);
                        }
                    }
                    if ($request->input('role') !== null) {
                        $editedUser->refresh();
                    }
                }

                if ($ret >= 0) {
                    return redirect()->to('admin/user-list');
                }

                switch ($ret) {
                    case User::ERR_SIGNUP_BADUNAME:
                        $error = 'Bad username. Try a better one.';
                        break;
                    case User::ERR_SIGNUP_BADPASS:
                        $error = 'Bad password. Try a longer one.';
                        break;
                    case User::ERR_SIGNUP_BADEMAIL:
                        $error = 'Bad email.';
                        break;
                    case User::ERR_SIGNUP_UNAMEINUSE:
                        $error = 'Username in use.';
                        break;
                    case User::ERR_SIGNUP_EMAILINUSE:
                        $error = 'Email in use.';
                        break;
                    default:
                        $error = 'Unknown save error.';
                        break;
                }
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
            $username = $user->username; // Store username before deletion

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
            UserVerification::generate($user);

            UserVerification::send($user, 'User email verification required');

            return redirect()->back()->with('success', 'Email verification for '.$user->username.' sent');
        }

        return redirect()->back()->with('error', 'User is invalid');
    }

    public function verify(Request $request): RedirectResponse
    {
        if ($request->has('id')) {
            $user = User::find($request->input('id'));
            User::query()->where('id', $request->input('id'))->update(['verified' => 1, 'email_verified_at' => now()]);

            return redirect()->back()->with('success', 'Email verification for '.$user->username.' sent');
        }

        return redirect()->back()->with('error', 'User is invalid');
    }
}
