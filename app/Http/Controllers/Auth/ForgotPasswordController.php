<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\ShowLinkRequestFormForgotPasswordRequest;
use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordForgottenEmail;
use App\Models\Settings;
use App\Models\User;
use DariusIII\Token\Facades\Token;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;

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

    /**
     * @throws \Exception
     */
    public function showLinkRequestForm(ShowLinkRequestFormForgotPasswordRequest $request): void
    {
        $sent = '';
        $email = $request->input('email') ?? '';
        $rssToken = $request->input('apikey') ?? '';
        if (empty($email) && empty($rssToken)) {
            app('smarty.view')->assign('error', 'Missing parameter(email and/or apikey to send password reset');
        } else {
            if (config('captcha.enabled') === true && (! empty(config('captcha.secret')) && ! empty(config('captcha.sitekey')))) {
            }
            //
            // Check users exists and send an email
            //
            $ret = ! empty($rssToken) ? User::getByRssToken($rssToken) : User::getByEmail($email);
            if ($ret === null) {
                app('smarty.view')->assign('error', 'The email or apikey are not recognised.');
                $sent = true;
            } else {
                //
                // Generate a forgottenpassword guid, store it in the user table
                //
                $guid = Token::random(32);
                User::updatePassResetGuid($ret['id'], $guid);
                //
                // Send the email
                //
                $resetLink = url('/').'/resetpassword?guid='.$guid;
                SendPasswordForgottenEmail::dispatch($ret, $resetLink);
                $sent = true;
            }
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
                'email' => $email,
                'apikey' => $rssToken,
                'sent' => $sent,
            ]
        );
        app('smarty.view')->display($theme.'/basepage.tpl');
    }
}
