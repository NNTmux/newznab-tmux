<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\User;
use App\Models\UserRole;
use App\Models\Invitation;
use App\Mail\AccountChange;
use Illuminate\Support\Facades\Mail;

$page = new AdminPage();

$user = [
    'id' => '',
    'username' => '',
    'email' => '',
    'password' => '',
    'role' => User::ROLE_USER,
    'notes' => '',
];

// set the current action
$action = $page->request->input('action') ?? 'view';

//get the user roles
$userRoles = UserRole::getRoles();
$roles = [];
$defaultRole = User::ROLE_USER;
$defaultInvites = Invitation::DEFAULT_INVITES;
foreach ($userRoles as $r) {
    $roles[$r['id']] = $r['name'];
    if ($r['isdefault'] === 1) {
        $defaultrole = $r['id'];
        $defaultinvites = $r['defaultinvites'];
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
        $page->smarty->assign('user', $user);
        break;
    case 'submit':
        if (empty($page->request->input('id'))) {
            $invites = $defaultInvites;
            foreach ($userRoles as $role) {
                if ($role['id'] === $page->request->input('role')) {
                    $invites = $role['defaultinvites'];
                }
            }
            $ret = User::signup($page->request->input('username'), $page->request->input('password'), $page->request->input('email'), '', $page->request->input('role'), $page->request->input('notes'), $invites, '', true);
            $page->smarty->assign('role', $page->request->input('role'));
        } else {
            $ret = User::updateUser($page->request->input('id'), $page->request->input('username'), $page->request->input('email'), $page->request->input('grabs'), $page->request->input('role'), $page->request->input('notes'), $page->request->input('invites'), ($page->request->has('movieview') ? 1 : 0), ($page->request->has('musicview') ? 1 : 0), ($page->request->has('gameview') ? 1 : 0), ($page->request->has('xxxview') ? 1 : 0), ($page->request->has('consoleview') ? 1 : 0), ($page->request->has('bookview') ? 1 : 0));
            if ($page->request->input('password') !== '') {
                User::updatePassword($page->request->input('id'), $page->request->input('password'));
            }
            if ($page->request->input('rolechangedate') !== '') {
                User::updateUserRoleChangeDate($page->request->input('id'), $page->request->input('rolechangedate'));
            }
            if ($page->request->input('role') !== '') {
                $newRole = UserRole::query()->where('id', $page->request->input('role'))->value('name');
                $email = $page->request->input('email') ?? $page->request->input('email');
                Mail::to($email)->send(new AccountChange($page->request->input('id')));
            }
        }

        if ($ret >= 0) {
            header('Location:'.WWW_TOP.'/user-list.php');
        } else {
            switch ($ret) {
                case User::ERR_SIGNUP_BADUNAME:
                    $page->smarty->assign('error', 'Bad username. Try a better one.');
                    break;
                case User::ERR_SIGNUP_BADPASS:
                    $page->smarty->assign('error', 'Bad password. Try a longer one.');
                    break;
                case User::ERR_SIGNUP_BADEMAIL:
                    $page->smarty->assign('error', 'Bad email.');
                    break;
                case User::ERR_SIGNUP_UNAMEINUSE:
                    $page->smarty->assign('error', 'Username in use.');
                    break;
                case User::ERR_SIGNUP_EMAILINUSE:
                    $page->smarty->assign('error', 'Email in use.');
                    break;
                default:
                    $page->smarty->assign('error', 'Unknown save error.');
                    break;
            }
            $user += [
                'id'        => $page->request->input('id'),
                'username'  => $page->request->input('username'),
                'email'     => $page->request->input('email'),
                'role'      => $page->request->input('role'),
                'notes'     => $page->request->input('notes'),
            ];
            $page->smarty->assign('user', $user);
        }
        break;
    case 'view':
    default:

    if ($page->request->has('id')) {
        $page->title = 'User Edit';
        $id = $page->request->input('id');
        $user = User::find($id);

        $page->smarty->assign('user', $user);
    }

        break;
}

$page->smarty->assign('yesno_ids', [1, 0]);
$page->smarty->assign('yesno_names', ['Yes', 'No']);

$page->smarty->assign('role_ids', array_keys($roles));
$page->smarty->assign('role_names', $roles);
$page->smarty->assign('user', $user);

$page->content = $page->smarty->fetch('user-edit.tpl');
$page->render();
