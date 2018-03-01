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
$action = \request()->input('action') ?? 'view';

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
        if (empty(\request()->input('id'))) {
            $invites = $defaultInvites;
            foreach ($userRoles as $role) {
                if ($role['id'] === \request()->input('role')) {
                    $invites = $role['defaultinvites'];
                }
            }
            $ret = User::signup(\request()->input('username'), \request()->input('password'), \request()->input('email'), '', \request()->input('role'), \request()->input('notes'), $invites, '', true);
            $page->smarty->assign('role', \request()->input('role'));
        } else {
            $ret = User::updateUser(\request()->input('id'), \request()->input('username'), \request()->input('email'), \request()->input('grabs'), \request()->input('role'), \request()->input('notes'), \request()->input('invites'), (\request()->has('movieview') ? 1 : 0), (\request()->has('musicview') ? 1 : 0), (\request()->has('gameview') ? 1 : 0), (\request()->has('xxxview') ? 1 : 0), (\request()->has('consoleview') ? 1 : 0), (\request()->has('bookview') ? 1 : 0));
            if (\request()->input('password') !== '') {
                User::updatePassword(\request()->input('id'), \request()->input('password'));
            }
            if (\request()->input('rolechangedate') !== '') {
                User::updateUserRoleChangeDate(\request()->input('id'), \request()->input('rolechangedate'));
            }
            if (\request()->input('role') !== '') {
                $newRole = UserRole::query()->where('id', \request()->input('role'))->value('name');
                $email = \request()->input('email') ?? \request()->input('email');
                Mail::to($email)->send(new AccountChange(\request()->input('id')));
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
                'id'        => \request()->input('id'),
                'username'  => \request()->input('username'),
                'email'     => \request()->input('email'),
                'role'      => \request()->input('role'),
                'notes'     => \request()->input('notes'),
            ];
            $page->smarty->assign('user', $user);
        }
        break;
    case 'view':
    default:

    if (\request()->has('id')) {
        $page->title = 'User Edit';
        $id = \request()->input('id');
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
