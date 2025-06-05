<?php

namespace App\Http\Controllers;

use App\Http\Requests\Disable2faPasswordSecurityRequest;
use App\Models\PasswordSecurity;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PasswordSecurityController extends Controller
{
    public function show2faForm(Request $request): Application|View|Factory|\Illuminate\Contracts\Foundation\Application
    {
        $user = $request->user();

        $google2fa_url = '';
        if ($user->passwordSecurity()->exists()) {
            $google2fa_url = \Google2FA::getQRCodeInline(
                config('app.name'),
                $user->email,
                $user->passwordSecurity->google2fa_secret
            );
        }
        $data = [
            'user' => $user,
            'google2fa_url' => $google2fa_url,
        ];

        return view('auth.2fa')->with('data', $data);
    }

    /**
     * @throws \PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException
     * @throws \PragmaRX\Google2FA\Exceptions\InvalidCharactersException
     * @throws \PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException
     */
    public function generate2faSecret(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Add the secret key to the registration data
        PasswordSecurity::create(
            [
                'user_id' => $user->id,
                'google2fa_enable' => 0,
                'google2fa_secret' => \Google2FA::generateSecretKey(),
            ]
        );

        return redirect()->to('2fa')->with('success', 'Secret Key is generated, Please verify Code to Enable 2FA');
    }

    /**
     * @throws \PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException
     * @throws \PragmaRX\Google2FA\Exceptions\InvalidCharactersException
     * @throws \PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException
     */
    public function enable2fa(Request $request): RedirectResponse
    {
        $user = $request->user();
        $secret = $request->input('verify-code');
        $valid = \Google2FA::verifyKey($user->passwordSecurity->google2fa_secret, $secret);
        if ($valid) {
            $user->passwordSecurity->google2fa_enable = 1;
            $user->passwordSecurity->save();

            return redirect()->to('2fa')->with('success', '2FA is Enabled Successfully.');
        }

        return redirect()->to('2fa')->with('error', 'Invalid Verification Code, Please try again.');
    }

    public function disable2fa(Disable2faPasswordSecurityRequest $request): \Illuminate\Routing\Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        if (! (Hash::check($request->get('current-password'), $request->user()->password))) {
            // The passwords matches
            return redirect()->back()->with('error', 'Your password does not match with your account password. Please try again.');
        }

        $validatedData = $request->validated();
        $user = $request->user();
        $user->passwordSecurity->google2fa_enable = 0;
        $user->passwordSecurity->save();

        return redirect()->to('2fa')->with('success', '2FA is now Disabled.');
    }

    /**
     * Verify the 2FA code provided by the user.
     */
    public function verify2fa(Request $request): RedirectResponse
    {
        $request->validate([
            'one_time_password' => 'required|numeric',
        ]);

        // Get the user ID from session
        if (!$request->session()->has('2fa:user:id')) {
            return redirect()->route('login')
                ->with('message', 'The two-factor authentication session has expired. Please login again.')
                ->with('message_type', 'danger');
        }

        $userId = $request->session()->get('2fa:user:id');
        $user = \App\Models\User::find($userId);

        if (!$user || !$user->passwordSecurity) {
            $request->session()->forget('2fa:user:id');
            return redirect()->route('login')
                ->with('message', 'User not found or 2FA not configured. Please login again.')
                ->with('message_type', 'danger');
        }

        // Verify the OTP code
        $valid = \Google2FA::verifyKey(
            $user->passwordSecurity->google2fa_secret,
            $request->input('one_time_password')
        );

        if (!$valid) {
            return redirect()->route('2fa.verify')
                ->with('message', 'Invalid authentication code. Please try again.')
                ->with('message_type', 'danger');
        }

        // Log the user back in
        Auth::login($user);

        // Mark the user as having passed 2FA
        session([config('google2fa.session_var') => true]);

        // Store the timestamp for determining how long the 2FA session is valid
        session([config('google2fa.session_var').'.auth.passed_at' => time()]);

        // Clean up the temporary session variable
        $request->session()->forget('2fa:user:id');

        // Determine where to redirect after successful verification
        $redirectUrl = $request->session()->pull('url.intended', '/');

        return redirect()->to($redirectUrl)
            ->with('message', 'Two-factor authentication verified successfully.')
            ->with('message_type', 'success');
    }

    /**
     * Display the 2FA verification form for a user who has already authenticated with username/password
     * but needs to enter their 2FA code.
     */
    public function getVerify2fa(Request $request)
    {
        // Check if user ID is stored in the session
        if (!$request->session()->has('2fa:user:id')) {
            return redirect()->route('login')
                ->withErrors(['msg' => 'The two-factor authentication session has expired. Please login again.']);
        }

        // Get the user ID from session
        $userId = $request->session()->get('2fa:user:id');

        // Get the user
        $user = \App\Models\User::find($userId);
        if (!$user) {
            $request->session()->forget('2fa:user:id');
            return redirect()->route('login')
                ->withErrors(['msg' => 'User not found. Please login again.']);
        }

        $theme = 'Gentele';
        $meta_title = 'Two Factor Authentication';
        $meta_keywords = 'Two Factor Authentication, 2FA';
        $meta_description = 'Two Factor Authentication Verification';

        app('smarty.view')->assign(compact('meta_title', 'meta_keywords', 'meta_description', 'user'));

        return app('smarty.view')->display($theme.'/2fa_verify.tpl');
    }
}
