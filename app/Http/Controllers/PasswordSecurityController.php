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

            // Check if we should redirect to profile page
            if ($request->has('redirect_to_profile')) {
                return redirect()->to('profileedit#security')->with('success_2fa', '2FA is Enabled Successfully.');
            }

            return redirect()->to('2fa')->with('success', '2FA is Enabled Successfully.');
        }

        // Check if we should redirect to profile page on failure as well
        if ($request->has('redirect_to_profile')) {
            return redirect()->to('profileedit#security')->with('error_2fa', 'Invalid Verification Code, Please try again.');
        }

        return redirect()->to('2fa')->with('error', 'Invalid Verification Code, Please try again.');
    }

    public function disable2fa(Disable2faPasswordSecurityRequest $request): \Illuminate\Routing\Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        if (! (Hash::check($request->get('current-password'), $request->user()->password))) {
            // Password doesn't match
            if ($request->has('redirect_to_profile') || $request->has('from_profile')) {
                return redirect()->to('profileedit#security')->with('error_2fa', 'Your password does not match with your account password. Please try again.');
            }

            return redirect()->back()->with('error', 'Your password does not match with your account password. Please try again.');
        }

        $validatedData = $request->validated();
        $user = $request->user();
        $user->passwordSecurity->google2fa_enable = 0;
        $user->passwordSecurity->save();

        // Check if this request is from the profile edit page
        if ($request->has('redirect_to_profile') || $request->has('from_profile')) {
            return redirect()->to('profileedit#security')->with('success_2fa', '2FA is now Disabled.');
        }

        return redirect()->to('2fa')->with('success', '2FA is now Disabled.');
    }

    /**
     * Verify the 2FA code provided by the user.
     */
    public function verify2fa(Request $request): RedirectResponse
    {
        $request->validate([
            'one_time_password' => 'required|numeric',
            'trust_device' => 'nullable|boolean',
        ]);

        // Get the user ID from session
        if (! $request->session()->has('2fa:user:id')) {
            return redirect()->route('login')
                ->with('message', 'The two-factor authentication session has expired. Please login again.')
                ->with('message_type', 'danger');
        }

        $userId = $request->session()->get('2fa:user:id');
        $user = \App\Models\User::find($userId);

        if (! $user || ! $user->passwordSecurity) {
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

        if (! $valid) {
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

        // If the user has checked "trust this device", create a trust token
        if ($request->has('trust_device') && $request->input('trust_device') == 1) {
            // Generate a unique token for this device
            $token = hash('sha256', $user->id . uniqid() . time());

            // Store the token with an expiry time of 30 days
            $expiresAt = now()->addDays(30)->timestamp;

            // Store the trusted device token in a cookie
            cookie()->queue(
                '2fa_trusted_device',
                json_encode([
                    'user_id' => $user->id,
                    'token' => $token,
                    'expires_at' => $expiresAt
                ]),
                60 * 24 * 30 // 30 days in minutes
            );

            // Store the token in the database if you want to manage/revoke trusted devices
            // This would require creating a new table for trusted devices
            // We're using cookies only for this implementation
        }

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
        if (! $request->session()->has('2fa:user:id')) {
            return redirect()->route('login')
                ->withErrors(['msg' => 'The two-factor authentication session has expired. Please login again.']);
        }

        // Get the user ID from session
        $userId = $request->session()->get('2fa:user:id');

        // Get the user
        $user = \App\Models\User::find($userId);
        if (! $user) {
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

    /**
     * Handle disabling 2FA directly from profile page to avoid form conflicts.
     * This route is specifically for the profile page 2FA section.
     */
    public function profileDisable2fa(Request $request): RedirectResponse
    {
        $request->validate([
            'current-password' => 'required',
        ]);

        if (! (Hash::check($request->get('current-password'), $request->user()->password))) {
            return redirect()->to('profileedit#security')->with('error_2fa', 'Your password does not match with your account password. Please try again.');
        }

        $user = $request->user();
        if ($user->passwordSecurity) {
            $user->passwordSecurity->google2fa_enable = 0;
            $user->passwordSecurity->save();
        }

        return redirect()->to('profileedit#security')->with('success_2fa', '2FA is now Disabled.');
    }

    /**
     * Show the 2FA enable form on a dedicated page
     */
    public function showEnable2faForm(Request $request): Application|View|Factory|\Illuminate\Contracts\Foundation\Application
    {
        $user = $request->user();
        $success = $request->session()->get('success');
        $error = $request->session()->get('error');

        $google2fa_url = '';
        if ($user->passwordSecurity()->exists()) {
            $google2fa_url = \Google2FA::getQRCodeInline(
                config('app.name'),
                $user->email,
                $user->passwordSecurity->google2fa_secret
            );
        }

        return view('themes.Gentele.2fa_enable', compact('user', 'google2fa_url', 'success', 'error'));
    }

    /**
     * Show the 2FA disable form on a dedicated page
     */
    public function showDisable2faForm(Request $request): Application|View|Factory|\Illuminate\Contracts\Foundation\Application
    {
        $user = $request->user();
        $success = $request->session()->get('success');
        $error = $request->session()->get('error');

        return view('themes.Gentele.2fa_disable', compact('user', 'success', 'error'));
    }
}
