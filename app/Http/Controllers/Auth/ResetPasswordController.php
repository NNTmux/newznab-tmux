<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordResetEmail;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/';

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
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function reset(Request $request)
    {
        $error = '';
        $confirmed = '';
        $onscreen = '';
        if ($request->missing('guid')) {
            $error = 'No reset code provided.';
        }

        if ($error === '') {
            $ret = User::getByPassResetGuid($request->input('guid'));
            if ($ret === null) {
                $error = 'Bad reset code provided.';
            } else {

                //
                // reset the password, inform the user, send out the email
                //
                User::updatePassResetGuid($ret['id'], '');
                $newpass = User::generatePassword();
                User::updatePassword($ret['id'], $newpass);

                $onscreen = 'Your password has been reset to <strong>'.$newpass.'</strong> and sent to your e-mail address.';
                SendPasswordResetEmail::dispatch($ret, $newpass);
                $confirmed = true;
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
                'email' => $ret['email'] ?? '',
                'confirmed' => $confirmed,
                'error' => $error,
                'notice' => $onscreen,
            ]
        );
        app('smarty.view')->display($theme.'/basepage.tpl');
    }
}
