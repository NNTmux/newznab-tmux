<?php

namespace App\Http\Controllers;

use App\Http\Requests\Disable2faPasswordSecurityRequest;
use App\Models\PasswordSecurity;
use App\Services\PasswordBreachService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PasswordSecurityController extends Controller
{
    public function show2faForm(Request $request): Application|View|Factory|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')
                ->with('message', 'Please log in to access 2FA settings.')
                ->with('message_type', 'danger');
        }

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

        if (! $user) {
            return redirect()->route('login')
                ->with('message', 'Please log in to access 2FA settings.')
                ->with('message_type', 'danger');
        }

        // Add the secret key to the registration data
        PasswordSecurity::create(
            [
                'user_id' => $user->id,
                'google2fa_enable' => 0,
                'google2fa_secret' => \Google2FA::generateSecretKey(),
            ]
        );

        // Check if request is from profile page
        if ($request->has('from_profile') || $request->headers->get('referer') && str_contains($request->headers->get('referer'), 'profileedit')) {
            return redirect()->to('profileedit#security')->with('success_2fa', 'Secret Key is generated, Please scan the QR code and verify to Enable 2FA');
        }

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

        if (! $user) {
            return redirect()->route('login')
                ->with('message', 'Please log in to access 2FA settings.')
                ->with('message_type', 'danger');
        }

        $secret = $request->input('verify-code');
        $valid = \Google2FA::verifyKey($user->passwordSecurity->google2fa_secret, $secret);
        if ($valid) {
            $user->passwordSecurity->google2fa_enable = 1;
            $user->passwordSecurity->save();

            // Always redirect to profile page after enabling 2FA
            return redirect()->to('profileedit#security')->with('success_2fa', '2FA is Enabled Successfully.');
        }

        // Always redirect to profile page on failure as well
        return redirect()->to('profileedit#security')->with('error_2fa', 'Invalid Verification Code, Please try again.');
    }

    public function cancelSetup(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')
                ->with('message', 'Please log in to access 2FA settings.')
                ->with('message_type', 'danger');
        }

        // Only allow canceling if 2FA is not yet enabled
        if ($user->passwordSecurity()->exists() && ! $user->passwordSecurity->google2fa_enable) {
            $user->passwordSecurity()->delete();

            return redirect()->to('profileedit#security')->with('success_2fa', '2FA setup has been cancelled.');
        }

        return redirect()->to('profileedit#security')->with('error_2fa', 'Unable to cancel 2FA setup.');
    }

    public function disable2fa(Disable2faPasswordSecurityRequest $request): \Illuminate\Routing\Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')
                ->with('message', 'Please log in to access 2FA settings.')
                ->with('message_type', 'danger');
        }

        if (! (Hash::check($request->get('current-password'), $user->password))) {
            // Password doesn't match - always redirect to profile page with error
            return redirect()->to('profileedit#security')->with('error_2fa', 'Your password does not match with your account password. Please try again.');
        }

        $validatedData = $request->validated();

        // Delete the password security record entirely to fully disable 2FA
        if ($user->passwordSecurity) {
            $user->passwordSecurity->delete();
        }

        // Always redirect to profile page after disabling 2FA
        return redirect()->to('profileedit#security')->with('success_2fa', '2FA is now Disabled.');
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

        // Get the remember me preference from session (defaults to false if not set)
        $rememberMe = $request->session()->get('2fa:remember', false);

        // Log the user back in with the remember me preference
        Auth::login($user, $rememberMe);

        // Mark the user as having passed 2FA
        session([config('google2fa.session_var') => true]);

        // Store the timestamp for determining how long the 2FA session is valid
        session([config('google2fa.session_var').'.auth.passed_at' => time()]);

        // Clean up the temporary session variables
        $passwordToCheck = $request->session()->get('2fa:password_check');
        $request->session()->forget(['2fa:user:id', '2fa:remember', '2fa:password_check']);

        // Determine where to redirect after successful verification
        $redirectUrl = $request->session()->pull('url.intended', '/');

        // Create the redirect response
        $redirect = redirect()->to($redirectUrl)
            ->with('message', 'Two-factor authentication verified successfully.')
            ->with('message_type', 'success');

        // Check for password breach if we have the password stored
        if ($passwordToCheck) {
            try {
                $breachService = app(PasswordBreachService::class);
                if ($breachService->isPasswordBreached($passwordToCheck)) {
                    $redirect = $redirect->with('warning', 'Security Alert: Your password has been found in a data breach. We strongly recommend changing it immediately in your account settings.');
                }
            } catch (\Exception $e) {
                Log::error('Password breach check failed during 2FA verification', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // If the user has checked "trust this device", create a trust token
        if ($request->has('trust_device') && $request->input('trust_device') == 1) {

            // Generate a unique token for this device
            $token = hash('sha256', $user->id.uniqid().time());

            // Store the token with an expiry time of 30 days
            $expiresAt = time() + (60 * 60 * 24 * 30); // 30 days in seconds

            // Create the cookie data
            $cookieData = [
                'user_id' => $user->id,
                'token' => $token,
                'expires_at' => $expiresAt,
            ];

            $cookieValue = json_encode($cookieData);

            // Use PHP's native setcookie function as the primary method
            setcookie(
                '2fa_trusted_device',
                $cookieValue,
                [
                    'expires' => $expiresAt,
                    'path' => '/',
                    'domain' => '',
                    'secure' => request()->secure(),
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]
            );

            // Also attach the cookie to the Laravel response as a backup approach
            $redirect->withCookie(
                cookie('2fa_trusted_device', $cookieValue, 43200, '/', null, null, false)
            );
        }

        return $redirect;
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

        return view('auth.2fa_verify', compact('user'));
    }

    /**
     * Handle disabling 2FA directly from profile page to avoid form conflicts.
     * This route is specifically for the profile page 2FA section.
     */
    public function profileDisable2fa(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')
                ->with('message', 'Please log in to access 2FA settings.')
                ->with('message_type', 'danger');
        }

        $request->validate([
            'current-password' => 'required',
        ]);

        if (! (Hash::check($request->get('current-password'), $user->password))) {
            return redirect()->to('profileedit#security')->with('error_2fa', 'Your password does not match with your account password. Please try again.');
        }

        if ($user->passwordSecurity) {
            $user->passwordSecurity->google2fa_enable = 0;
            $user->passwordSecurity->save();
        }

        return redirect()->to('profileedit#security')->with('success_2fa', '2FA is now Disabled.');
    }

    /**
     * Show the 2FA enable form on a dedicated page
     */
    public function showEnable2faForm(Request $request): Application|View|Factory|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')
                ->with('message', 'Please log in to access 2FA settings.')
                ->with('message_type', 'danger');
        }

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
     * Show the 2FA disable form on a dedicated page
     */
    public function showDisable2faForm(Request $request): Application|View|Factory|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')
                ->with('message', 'Please log in to access 2FA settings.')
                ->with('message_type', 'danger');
        }

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
}
