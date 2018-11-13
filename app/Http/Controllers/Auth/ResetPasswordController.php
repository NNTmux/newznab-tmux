<?php

namespace App\Http\Controllers\Auth;

use App\Jobs\SendPasswordResetEmail;
use App\Models\User;
use App\Models\Settings;
use App\Mail\PasswordReset;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Auth\ResetsPasswords;

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
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function reset(Request $request)
    {
        if (! $request->has('guid')) {
            app('smarty.view')->assign('error', 'No reset code provided.');
        }

        $ret = User::getByPassResetGuid($request->input('guid'));
        if ($ret === null) {
            app('smarty.view')->assign('error', 'Bad reset code provided.');
        }

        //
        // reset the password, inform the user, send out the email
        //
        User::updatePassResetGuid($ret['id'], '');
        $newpass = User::generatePassword();
        User::updatePassword($ret['id'], $newpass);

        $onscreen = 'Your password has been reset to <strong>'.$newpass.'</strong> and sent to your e-mail address.';
        SendPasswordResetEmail::dispatch($ret['email'], $ret['id'], $newpass);
        app('smarty.view')->assign('notice', $onscreen);
        $confirmed = true;

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
                'email'     => $ret['email'],
                'confirmed' => $confirmed,
            ]
        );
        app('smarty.view')->display($theme.'/basepage.tpl');
    }
}
