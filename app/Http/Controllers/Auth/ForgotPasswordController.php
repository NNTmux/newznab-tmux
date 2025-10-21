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
    public function showLinkRequestForm(Request $request)
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
            return view('auth.passwords.email')->withErrors(['error' => 'Missing parameter (email and/or apikey) to send password reset']);
        }

        if (\App\Support\CaptchaHelper::isEnabled()) {
            $validate = Validator::make($request->all(), \App\Support\CaptchaHelper::getValidationRules());
            if ($validate->fails()) {
                return view('auth.passwords.email')->withErrors(['error' => 'Captcha validation failed.']);
            }
        }

        // Check users exists and send an email
        $ret = ! empty($rssToken) ? User::getByRssToken($rssToken) : User::getByEmail($email);
        if ($ret === null) {
            return view('auth.passwords.email')->withErrors(['error' => 'The email or apikey are not recognised.']);
        }

        // Check if user is soft deleted
        $user = User::withTrashed()->find($ret['id']);
        if ($user && $user->trashed()) {
            return view('auth.passwords.email')->withErrors(['error' => 'This account has been deactivated.']);
        }

        // Generate a forgottenpassword guid, store it in the user table
        $guid = Str::random(32);
        User::updatePassResetGuid($ret['id'], $guid);

        // Send the email
        $resetLink = url('/').'/resetpassword?guid='.$guid;
        SendPasswordForgottenEmail::dispatch($ret, $resetLink);

        return view('auth.passwords.email')->with('status', 'Password reset email has been sent!');
    }
}
