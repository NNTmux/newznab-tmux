<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordResetEmail;
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
     */
    protected string $redirectTo = '/';

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
    public function reset(Request $request): mixed
    {
        if ($request->missing('guid')) {
            return redirect()->route('password.request')->withErrors(['error' => 'No reset code provided.']);
        }

        $user = User::getByPassResetGuid($request->input('guid'));
        if ($user === null) {
            return redirect()->route('password.request')->withErrors(['error' => 'Bad reset code provided.']);
        }

        // Check if user is soft deleted
        if ($user->trashed()) {
            return redirect()->route('password.request')->withErrors(['error' => 'This account has been deactivated.']);
        }

        // Reset the password, inform the user, send out the email
        User::updatePassResetGuid($user->id, '');
        $newpass = User::generatePassword();
        User::updatePassword($user->id, $newpass);

        SendPasswordResetEmail::dispatch($user, $newpass);

        return redirect()->route('login')
            ->with('message', 'Your password has been reset to <strong>'.$newpass.'</strong> and sent to your e-mail address.')
            ->with('message_type', 'success');
    }

    public function showResetForm(Request $request, mixed $token = null): mixed
    {
        return view('auth.passwords.reset')->with([
            'token' => $token,
            'email' => $request->email,
        ]);
    }
}
