<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordForgottenEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * @throws \Exception
     */
    public function showLinkRequestForm(Request $request): void
    {
        $sent = '';
        $email = $request->input('email') ?? '';
        $rssToken = $request->input('apikey') ?? '';
        if (empty($email) && empty($rssToken)) {
            app('smarty.view')->assign('error', 'Missing parameter (email and/or apikey) to send password reset');
        } else {
            if (config('captcha.enabled') === true && (! empty(config('captcha.secret')) && ! empty(config('captcha.sitekey')))) {
                $validate = Validator::make($request->all(), [
                    'g-recaptcha-response' => 'required|captcha',
                ]);
                if ($validate->fails()) {
                    app('smarty.view')->assign('error', 'Captcha validation failed.');
                }
            }
            //
            // Check users exists and send an email
            //
            $ret = ! empty($rssToken) ? User::getByRssToken($rssToken) : User::getByEmail($email);
            if ($ret === null) {
                app('smarty.view')->assign('error', 'The email or apikey are not recognised.');
            } else {
                // Check if user is soft deleted
                $user = User::withTrashed()->find($ret['id']);
                if ($user && $user->trashed()) {
                    app('smarty.view')->assign('error', 'This account has been deactivated.');
                } else {
                    //
                    // Generate a forgottenpassword guid, store it in the user table
                    //
                    $guid = Str::random(32);
                    User::updatePassResetGuid($ret['id'], $guid);
                    //
                    // Send the email
                    //
                    $resetLink = url('/').'/resetpassword?guid='.$guid;
                    SendPasswordForgottenEmail::dispatch($ret, $resetLink);
                    app('smarty.view')->assign('success', 'Password reset email has been sent!');
                }
            }
            $sent = true;
        }

        $theme = 'Gentele';

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
                'email' => $email,
                'apikey' => $rssToken,
                'sent' => $sent,
            ]
        );
        app('smarty.view')->display($theme.'/basepage.tpl');
    }
}
