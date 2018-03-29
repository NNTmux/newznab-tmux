<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ForgottenPassword;
use App\Mail\PasswordReset;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showLinkRequestForm()
    {
        $action = request()->input('action') ?? 'view';

        $email = $rssToken = $sent = $confirmed = '';

        switch ($action) {
            case 'reset':
                if (! request()->has('guid')) {
                    app('smarty.view')->assign('error', 'No reset code provided.');
                    break;
                }

                $ret = User::getByPassResetGuid(request()->input('guid'));
                if (! $ret) {
                    app('smarty.view')->assign('error', 'Bad reset code provided.');
                    break;
                }

                //
                // reset the password, inform the user, send out the email
                //
                User::updatePassResetGuid($ret['id'], '');
                $newpass = User::generatePassword();
                User::updatePassword($ret['id'], $newpass);

                $to = $ret['email'];
                $onscreen = 'Your password has been reset to <strong>'.$newpass.'</strong> and sent to your e-mail address.';
                Mail::to($to)->send(new PasswordReset($ret['id'], $newpass));
                app('smarty.view')->assign('notice', $onscreen);
                $confirmed = true;
                break;

                break;
            case 'submit':
                $email = request()->input('email') ?? '';
                $rssToken = request()->input('apikey') ?? '';
                if (empty($email) && empty($rssToken)) {
                    app('smarty.view')->assign('error', 'Missing parameter(email and/or apikey to send password reset');
                } else {
                    //
                    // Check users exists and send an email
                    //
                    $ret = ! empty($rssToken) ? User::getByRssToken($rssToken) : User::getByEmail($email);
                    if ($ret === null) {
                        app('smarty.view')->assign('error', 'The email or apikey are not recognised.');
                        $sent = true;
                        break;
                    }
                    //
                    // Generate a forgottenpassword guid, store it in the user table
                    //
                    $guid = md5(uniqid('', false));
                    User::updatePassResetGuid($ret['id'], $guid);
                    //
                    // Send the email
                    //
                    $resetLink = request()->server('SERVER_NAME').'/forgottenpassword?action=reset&guid='.$guid;
                    //Mail::to($ret['email'])->send(new ForgottenPassword($resetLink));
                    $sent = true;
                    break;
                }
                break;
        }
        $theme = Settings::settingValue('site.main.style');

        $title = 'Forgotten Password';
        $meta_title = 'Forgotten Password';
        $meta_keywords = 'forgotten,password,signup,registration';
        $meta_description = 'Forgotten Password';

        $content = app('smarty.view')->fetch($theme.'/forgottenpassword.tpl');

        app('smarty.view')->assign(
            [
                'content' => $content,
                'title' => $title,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
                'email'     => $email,
                'apikey'    => $rssToken,
                'confirmed' => $confirmed,
                'sent'      => $sent,
            ]
        );
        app('smarty.view')->display($theme.'/basepage.tpl');
    }
}
