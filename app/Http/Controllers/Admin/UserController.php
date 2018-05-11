<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\UserRole;
use App\Models\Invitation;
use App\Mail\AccountChange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\BasePageController;

class UserController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $this->setAdminPrefs();

        $title = 'User List';

        $roles = [];
        foreach (UserRole::getRoles() as $userRole) {
            $roles[$userRole['id']] = $userRole['name'];
        }

        $ordering = getUserBrowseOrdering();
        $orderBy = $request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '';

        $variables = [
            'username' => $request->has('username') ? $request->input('username') : '',
            'email' => $request->has('email') ? $request->input('email') : '',
            'host' => $request->has('host') ? $request->input('host') : '',
            'role' => $request->has('role') ? $request->input('role') : '',
        ];

        $this->smarty->assign(
            [
                'username'          => $variables['username'],
                'email'             => $variables['email'],
                'host'              => $variables['host'],
                'role'              => $variables['role'],
                'role_ids'          => array_keys($roles),
                'role_names'        => $roles,
                'userlist' => User::getRange(
                    $orderBy,
                    $variables['username'],
                    $variables['email'],
                    $variables['host'],
                    $variables['role']
                ),
            ]
        );

        User::updateExpiredRoles();

        foreach ($ordering as $orderType) {
            $this->smarty->assign('orderby'.$orderType, WWW_TOP.'user-list?ob='.$orderType.'&offset=0');
        }

        $content = $this->smarty->fetch('user-list.tpl');
        $this->smarty->assign(
            [
                'title' => $title,
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

        $user = [
            'id' => '',
            'username' => '',
            'email' => '',
            'password' => '',
            'role' => User::ROLE_USER,
            'notes' => '',
        ];

        // set the current action
        $action = $request->input('action') ?? 'view';

        //get the user roles
        $userRoles = UserRole::getRoles();
        $roles = [];
        $defaultRole = User::ROLE_USER;
        $defaultInvites = Invitation::DEFAULT_INVITES;
        foreach ($userRoles as $r) {
            $roles[$r['id']] = $r['name'];
            if ($r['isdefault'] === 1) {
                $defaultRole = $r['id'];
                $defaultInvites = $r['defaultinvites'];
            }
        }

        switch ($action) {
            case 'add':
                $user += [
                    'role'        => $defaultRole,
                    'notes'       => '',
                    'invites'     => $defaultInvites,
                    'movieview'   => 0,
                    'xxxview'     => 0,
                    'musicview'   => 0,
                    'consoleview' => 0,
                    'gameview'    => 0,
                    'bookview'    => 0,
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
                    $ret = User::signup($request->input('username'), $request->input('password'), $request->input('email'), '', $request->input('role'), $request->input('notes'), $invites, '', true);
                    $this->smarty->assign('role', $request->input('role'));
                } else {
                    $ret = User::updateUser($request->input('id'), $request->input('username'), $request->input('email'), $request->input('grabs'), $request->input('role'), $request->input('notes'), $request->input('invites'), ($request->has('movieview') ? 1 : 0), ($request->has('musicview') ? 1 : 0), ($request->has('gameview') ? 1 : 0), ($request->has('xxxview') ? 1 : 0), ($request->has('consoleview') ? 1 : 0), ($request->has('bookview') ? 1 : 0));
                    if ($request->input('password') !== null) {
                        User::updatePassword($request->input('id'), $request->input('password'));
                    }
                    if ($request->input('rolechangedate') !== null) {
                        User::updateUserRoleChangeDate($request->input('id'), $request->input('rolechangedate'));
                    }
                    if ($request->input('role') !== null) {
                        UserRole::query()->where('id', $request->input('role'))->value('name');
                        $email = $request->input('email') ?? $request->input('email');
                        Mail::to($email)->send(new AccountChange($request->input('id')));
                    }
                }

                if ($ret >= 0) {
                    return redirect('admin/user-list');
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
                    'id'        => $request->input('id'),
                    'username'  => $request->input('username'),
                    'email'     => $request->input('email'),
                    'role'      => $request->input('role'),
                    'notes'     => $request->input('notes'),
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

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );

        $this->adminrender();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function destroy(Request $request)
    {
        if ($request->has('id')) {
            User::deleteUser($request->input('id'));
        }

        if ($request->has('redir')) {
            return redirect($request->input('redir'));
        }

        return redirect($request->server('HTTP_REFERER'));
    }
}
