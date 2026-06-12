<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordForgottenEmail;
use App\Models\User;
use App\Support\CaptchaHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

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

        $email = (string) $request->input('email', '');

        if ($email === '') {
            return redirect()
                ->route('forgottenpassword')
                ->withErrors(['email' => 'Please enter your email address to send a password reset link.'])
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

        // Check whether the user exists, but always return the same success message
        // to avoid account enumeration.
        $user = User::findByEmail($email);
        if ($user === null) {
            return redirect()->route('forgottenpassword')->with('success', 'Password reset email has been sent!');
        }

        // Check if user is soft deleted
        if (User::withTrashed()->find($user->id)?->trashed()) {
            return redirect()->route('forgottenpassword')->with('success', 'Password reset email has been sent!');
        }

        $this->sendResetLink($user);

        // Clear any legacy GUID once a broker token has been issued.
        User::updatePassResetGuid($user->id, null);

        return redirect()->route('forgottenpassword')->with('success', 'Password reset email has been sent!');
    }

    private function sendResetLink(User $user): void
    {
        $token = Password::broker()->createToken($user);

        SendPasswordForgottenEmail::dispatch(
            $user,
            route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]),
        );
    }
}
