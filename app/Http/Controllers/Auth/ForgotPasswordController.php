<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Settings;
use App\Mail\ForgottenPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

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

    public function showLinkRequestForm(Request $request)
    {
        $sent = '';
        $email = request()->input('email') ?? '';
        $rssToken = request()->input('apikey') ?? '';
        if (empty($email) && empty($rssToken)) {
            app('smarty.view')->assign('error', 'Missing parameter(email and/or apikey to send password reset');
        } else {
            if (env('NOCAPTCHA_ENABLED') === true && (!empty(env('NOCAPTCHA_SECRET')) && ! empty(env('NOCAPTCHA_SITEKEY')))) {
                $this->validate($request, [
                    'g-recaptcha-response' => 'required|captcha',
                ]);
            }
            //
            // Check users exists and send an email
            //
            $ret = ! empty($rssToken) ? User::getByRssToken($rssToken) : User::getByEmail($email);
            if ($ret === null) {
                app('smarty.view')->assign('error', 'The email or apikey are not recognised.');
                $sent = true;
            }
            //
            // Generate a forgottenpassword guid, store it in the user table
            //
            $guid = md5(uniqid('', false));
            User::updatePassResetGuid($ret['id'], $guid);
            //
            // Send the email
            //
            $resetLink = $request->server('SERVER_NAME').'/forgottenpassword?action=reset&guid='.$guid;
            Mail::to($ret['email'])->send(new ForgottenPassword($resetLink));
            $sent = true;
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
                'sent'      => $sent,
            ]
        );
        app('smarty.view')->display($theme.'/basepage.tpl');
    }
}
