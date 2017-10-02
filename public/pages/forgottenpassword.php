<?php

use App\Mail\ForgottenPassword;
use App\Mail\PasswordReset;
use Illuminate\Support\Facades\Mail;
use nntmux\Captcha;

if ($page->users->isLoggedIn()) {
    header('Location: '.WWW_TOP.'/');
}

$action = $_REQUEST['action'] ?? 'view';

$captcha = new Captcha($page);
$email = $sent = $confirmed = '';

switch ($action) {
    case 'reset':
        if (! isset($_REQUEST['guid'])) {
            $page->smarty->assign('error', 'No reset code provided.');
            break;
        }

        $ret = $page->users->getByPassResetGuid($_REQUEST['guid']);
        if (! $ret) {
            $page->smarty->assign('error', 'Bad reset code provided.');
            break;
        }

        //
        // reset the password, inform the user, send out the email
        //
        $page->users->updatePassResetGuid($ret['id'], '');
        $newpass = $page->users->generatePassword();
        $page->users->updatePassword($ret['id'], $newpass);

        $to = $ret['email'];
        $onscreen = 'Your password has been reset to <strong>'.$newpass.'</strong> and sent to your e-mail address.';
        Mail::to($to)->send(new PasswordReset($ret['id'], $newpass));
        $page->smarty->assign('notice', $onscreen);
        $confirmed = true;
        break;

        break;
    case 'submit':

        if ($captcha->getError() === false) {
            $email = $_POST['email'] ?? '';
            if (empty($email)) {
                $page->smarty->assign('error', 'Missing Email');
            } else {
                //
                // Check users exists and send an email
                //
                $ret = $page->users->getByEmail($email);
                if (! $ret) {
                    $page->smarty->assign('error', 'The email address is not recognised.');
                    $sent = true;
                    break;
                }
                //
                // Generate a forgottenpassword guid, store it in the user table
                //
                $guid = md5(uniqid('', false));
                $page->users->updatePassResetGuid($ret['id'], $guid);
                //
                // Send the email
                //
                $to = $ret['email'];
                $resetLink = $page->serverurl.'forgottenpassword?action=reset&guid='.$guid;
                Mail::to($to)->send(new ForgottenPassword($ret['id'], $resetLink));
                $sent = true;
                break;
            }
            break;
        }
}
$page->smarty->assign(
    [
        'email'     => $email,
        'confirmed' => $confirmed,
        'sent'      => $sent,
    ]
);

$page->title = 'Forgotten Password';
$page->meta_title = 'Forgotten Password';
$page->meta_keywords = 'forgotten,password,signup,registration';
$page->meta_description = 'Forgotten Password';

$page->content = $page->smarty->fetch('forgottenpassword.tpl');
$page->render();
