<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Jobs\SendAccountChangedEmail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Jrean\UserVerification\Facades\UserVerification;
use Spatie\Permission\Models\Role;

class AdminUserController extends BasePageController
{
    /**
     * @throws \Throwable
     */
    public function index(Request $request): void
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

        $this->smarty->assign(
            [
                'username' => $variables['username'],
                'email' => $variables['email'],
                'host' => $variables['host'],
                'role' => $variables['role'],
                'role_ids' => array_keys($roles),
                'role_names' => $roles,
                'userlist' => $results,
            ]
        );

        foreach ($ordering as $orderType) {
            $this->smarty->assign('orderby'.$orderType, url('admin/user-list?ob='.$orderType));
        }

        $content = $this->smarty->fetch('user-list.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @return RedirectResponse|void
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
                $this->smarty->assign('user', $user);
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
                    $this->smarty->assign('role', $request->input('role'));
                } else {
                    $editedUser = User::find($request->input('id'));
                    $ret = User::updateUser($editedUser->id, $request->input('username'), $request->input('email'), $request->input('grabs'), $request->input('role'), $request->input('notes'), $request->input('invites'), ($request->has('movieview') ? 1 : 0), ($request->has('musicview') ? 1 : 0), ($request->has('gameview') ? 1 : 0), ($request->has('xxxview') ? 1 : 0), ($request->has('consoleview') ? 1 : 0), ($request->has('bookview') ? 1 : 0));
                    if ($request->input('password') !== null) {
                        User::updatePassword($editedUser->id, $request->input('password'));
                    }
                    if ($request->input('rolechangedate') !== null) {
                        User::updateUserRoleChangeDate($editedUser->id, $request->input('rolechangedate'));
                    }
                    if ($request->input('role') !== null) {
                        $editedUser->refresh();
                        SendAccountChangedEmail::dispatch($editedUser)->onQueue('emails');
                    }
                }

                if ($ret >= 0) {
                    return redirect()->to('admin/user-list');
                }

                switch ($ret) {
                    case User::ERR_SIGNUP_BADUNAME:
                        $this->smarty->assign('error', 'Bad username. Try a better one.');
                        break;
                    case User::ERR_SIGNUP_BADPASS:
                        $this->smarty->assign('error', 'Bad password. Try a longer one.');
                        break;
                    case User::ERR_SIGNUP_BADEMAIL:
                        $this->smarty->assign('error', 'Bad email.');
                        break;
                    case User::ERR_SIGNUP_UNAMEINUSE:
                        $this->smarty->assign('error', 'Username in use.');
                        break;
                    case User::ERR_SIGNUP_EMAILINUSE:
                        $this->smarty->assign('error', 'Email in use.');
                        break;
                    default:
                        $this->smarty->assign('error', 'Unknown save error.');
                        break;
                }
                $user += [
                    'id' => $request->input('id'),
                    'username' => $request->input('username'),
                    'email' => $request->input('email'),
                    'role' => $request->input('role'),
                    'notes' => $request->input('notes'),
                ];
                $this->smarty->assign('user', $user);
                break;
            case 'view':
            default:
                if ($request->has('id')) {
                    $title = 'User Edit';
                    $id = $request->input('id');
                    $user = User::find($id);

                    $this->smarty->assign('user', $user);
                }

                break;
        }

        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['Yes', 'No']);

        $this->smarty->assign('role_ids', array_keys($roles));
        $this->smarty->assign('role_names', $roles);
        $this->smarty->assign('user', $user);

        $content = $this->smarty->fetch('user-edit.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($request->has('id')) {
            $user = User::find($request->input('id'));

            $user->delete();

            return redirect()->to('admin/user-list');
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
