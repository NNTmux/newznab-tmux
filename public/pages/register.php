<?php

use App\Models\User;
use Blacklight\Captcha;
use App\Models\Settings;
use App\Models\UserRole;
use App\Models\Invitation;
use Blacklight\utility\Utility;
use Illuminate\Support\Facades\Auth;

if (Auth::check()) {
    redirect('/');
}

$error = $userName = $password = $confirmPassword = $email = $inviteCode = $inviteCodeQuery = '';
$showRegister = 1;

if ((int) Settings::settingValue('..registerstatus') === Settings::REGISTER_STATUS_CLOSED) {
    $error = 'Registrations are currently disabled.';
    $showRegister = 0;
} elseif (Settings::settingValue('..registerstatus') === Settings::REGISTER_STATUS_INVITE && (! request()->has('invitecode') || empty(request()->input('invitecode')))) {
    $error = 'Registrations are currently invite only.';
    $showRegister = 0;
}

if ($showRegister === 1) {
    $action = request()->input('action') ?? 'view';

    //Be sure to persist the invite code in the event of multiple form submissions. (errors)
    if (request()->has('invitecode')) {
        $inviteCodeQuery = '&invitecode='.request()->input('invitecode');
    }

    $captcha = new Captcha($page);

    switch ($action) {
        case 'submit':
            if ($captcha->getError() === false) {
                $userName = request()->input('username');
                $password = request()->input('password');
                $confirmPassword = request()->input('confirmpassword');
                $email = request()->input('email');
                if (! empty(request()->input('invitecode'))) {
                    $inviteCode = request()->input('invitecode');
                }

                // Check uname/email isn't in use, password valid. If all good create new user account and redirect back to home page.
                if ($password !== $confirmPassword) {
                    $error = 'Password Mismatch';
                } else {
                    // Get the default user role.
                    $userDefault = UserRole::getDefaultRole();

                    $ret = User::signup(
                            $userName,
                            $password,
                            $email,
                            request()->ip(),
                            $userDefault['id'],
                            '',
                            $userDefault['defaultinvites'],
                            $inviteCode
                        );

                    if ($ret > 0) {
                        session()->flash('You are already a member!');
                        redirect('/');
                    } else {
                        switch ($ret) {
                                case User::ERR_SIGNUP_BADUNAME:
                                    $error = 'Your username must be at least five characters.';
                                    break;
                                case User::ERR_SIGNUP_BADPASS:
                                    $error = 'Your password must be longer than eight characters, have at least 1 number, at least 1 capital and at least one lowercase letter';
                                    break;
                                case User::ERR_SIGNUP_BADEMAIL:
                                    $error = 'Your email is not a valid format.';
                                    break;
                                case User::ERR_SIGNUP_UNAMEINUSE:
                                    $error = 'Sorry, the username is already taken.';
                                    break;
                                case User::ERR_SIGNUP_EMAILINUSE:
                                    $error = 'Sorry, the email is already in use.';
                                    break;
                                case User::ERR_SIGNUP_BADINVITECODE:
                                    $error = 'Sorry, the invite code is old or has been used.';
                                    break;
                                default:
                                    $error = 'Failed to register.';
                                    break;
                            }
                    }
                }
            }
            break;
        case 'view': {
            $inviteCode = request()->input('invitecode') ?? null;
            if (isset($inviteCode)) {
                // See if it is a valid invite.
                $invite = Invitation::getInvite($inviteCode);
                if (! $invite) {
                    $error = sprintf('Bad or invite code older than %d days.', Invitation::DEFAULT_INVITE_EXPIRY_DAYS);
                    $showRegister = 0;
                } else {
                    $inviteCode = $invite['guid'];
                }
            }
            break;
        }
    }
}
$page->smarty->assign(
    [
        'username'          => Utility::htmlfmt($userName),
        'password'          => Utility::htmlfmt($password),
        'confirmpassword'   => Utility::htmlfmt($confirmPassword),
        'email'             => Utility::htmlfmt($email),
        'invitecode'        => Utility::htmlfmt($inviteCode),
        'invite_code_query' => Utility::htmlfmt($inviteCodeQuery),
        'showregister'      => $showRegister,
        'error'             => $error,
    ]
);
$page->meta_title = 'Register';
$page->meta_keywords = 'register,signup,registration';
$page->meta_description = 'Register';

$page->content = $page->smarty->fetch('register.tpl');
$page->render();
