<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordForgottenEmail;
use App\Models\User;
use App\Support\CaptchaHelper;
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
    public function showLinkRequestForm(Request $request): mixed
    {
        // If it's a GET request, just show the form
        if ($request->isMethod('get')) {
            return view('auth.passwords.email');
        }

        // Handle POST request
        $sent = '';
        $email = $request->input('email') ?? '';
        $rssToken = $request->input('apikey') ?? '';

        if (empty($email) && empty($rssToken)) {
            return redirect()
                ->route('forgottenpassword')
                ->withErrors(['error' => 'Missing parameter (email and/or apikey) to send password reset'])
                ->withInput($request->except(CaptchaHelper::getResponseFieldName()));
        }

        if (CaptchaHelper::isEnabled()) {
            $validate = Validator::make($request->all(), CaptchaHelper::getValidationRules());
            if ($validate->fails()) {
                return redirect()
                    ->route('forgottenpassword')
                    ->withErrors(['error' => 'Captcha validation failed.'])
                    ->withInput($request->except(CaptchaHelper::getResponseFieldName()));
            }
        }

        // Check users exists and send an email
        $ret = ! empty($rssToken) ? User::findByRssToken($rssToken) : User::findByEmail($email);
        if ($ret === null) {
            return redirect()
                ->route('forgottenpassword')
                ->withErrors(['error' => 'The email or apikey are not recognised.'])
                ->withInput($request->except(CaptchaHelper::getResponseFieldName()));
        }

        // Check if user is soft deleted
        $user = User::withTrashed()->find($ret['id']);
        if ($user && $user->trashed()) {
            return redirect()
                ->route('forgottenpassword')
                ->withErrors(['error' => 'This account has been deactivated.'])
                ->withInput($request->except(CaptchaHelper::getResponseFieldName()));
        }

        // Generate a forgottenpassword guid, store it in the user table
        $guid = Str::random(32);
        User::updatePassResetGuid($ret['id'], $guid);

        // Send the email
        $resetLink = url('/').'/resetpassword?guid='.$guid;
        SendPasswordForgottenEmail::dispatch($ret, $resetLink);

        return redirect()->route('forgottenpassword')->with('success', 'Password reset email has been sent!');
    }
}
