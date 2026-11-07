<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Disable2faPasswordSecurityRequest;
use App\Models\PasswordSecurity;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException;
use PragmaRX\Google2FA\Exceptions\InvalidCharactersException;
use PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

class PasswordSecurityController extends Controller
{
    /**
     * Wrong one-time codes allowed per account before verification is locked.
     */
    private const int OTP_MAX_ATTEMPTS = 5;

    private const int OTP_LOCK_SECONDS = 900;

    public function show2faForm(Request $request): Application|View|Factory|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->redirectToLoginWithError('Please log in to access 2FA settings.');
        }

        $google2fa_url = $this->setupQrCode($user);
        $data = [
            'user' => $user,
            'google2fa_url' => $google2fa_url,
        ];

        return view('auth.2fa')->with('data', $data);
    }

    /**
     * @throws IncompatibleWithGoogleAuthenticatorException
     * @throws InvalidCharactersException
     * @throws SecretKeyTooShortException
     */
    public function generate2faSecret(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->redirectToLoginWithError('Please log in to access 2FA settings.');
        }

        if ($user->passwordSecurity()->exists()) {
            return redirect()->to('profileedit#security')->with('error_2fa', '2FA is already set up for this account.');
        }

        // Add the secret key to the registration data
        PasswordSecurity::create(
            [
                'user_id' => $user->id,
                'google2fa_enable' => 0,
                'google2fa_secret' => Google2FA::generateSecretKey(),
            ]
        );

        // Check if request is from profile page
        if ($request->has('from_profile') || $request->headers->get('referer') && str_contains($request->headers->get('referer'), 'profileedit')) {
            return redirect()->to('profileedit#security')->with('success_2fa', 'Secret Key is generated, Please scan the QR code and verify to Enable 2FA');
        }

        return redirect()->to('2fa')->with('success', 'Secret Key is generated, Please verify Code to Enable 2FA');
    }

    /**
     * @throws IncompatibleWithGoogleAuthenticatorException
     * @throws InvalidCharactersException
     * @throws SecretKeyTooShortException
     */
    public function enable2fa(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->redirectToLoginWithError('Please log in to access 2FA settings.');
        }

        $secret = $request->input('verify-code');
        $valid = Google2FA::verifyKey($user->passwordSecurity->google2fa_secret, $secret);
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
            return $this->redirectToLoginWithError('Please log in to access 2FA settings.');
        }

        // Only allow canceling if 2FA is not yet enabled
        if ($user->passwordSecurity()->exists() && ! $user->passwordSecurity->google2fa_enable) {
            $user->passwordSecurity()->delete();

            return redirect()->to('profileedit#security')->with('success_2fa', '2FA setup has been cancelled.');
        }

        return redirect()->to('profileedit#security')->with('error_2fa', 'Unable to cancel 2FA setup.');
    }

    public function disable2fa(Disable2faPasswordSecurityRequest $request): Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $user = $request->user();

        if (! $user) {
            return $this->redirectToLoginWithError('Please log in to access 2FA settings.');
        }

        if (! (Hash::check($request->get('current-password'), $user->password))) {
            // Password doesn't match - always redirect to profile page with error
            return redirect()->to('profileedit#security')->with('error_2fa', 'Your password does not match with your account password. Please try again.');
        }

        // Turning off active 2FA also needs a current code, so a stolen password and session are not enough.
        if ($user->passwordSecurity?->google2fa_enable) {
            $validCode = $this->checkOneTimePassword($user, (string) $request->input('one_time_password', ''));
            if ($validCode !== true) {
                return redirect()->to('profileedit#security')->with('error_2fa', $validCode === null
                    ? $this->lockedOutMessage($user)
                    : 'Invalid authentication code. Please try again.');
            }
        }

        // Delete the password security record entirely to fully disable 2FA
        $user->passwordSecurity?->delete();
        TrustedDevice::query()->where('user_id', $user->id)->delete();

        // Always redirect to profile page after disabling 2FA
        return redirect()->to('profileedit#security')
            ->with('success_2fa', '2FA is now Disabled.')
            ->withoutCookie('2fa_trusted_device');
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

        $authenticatedUser = $request->user();
        $user = $authenticatedUser ?? User::find($request->session()->get('2fa:user:id'));

        if (! $user || ! $user->passwordSecurity?->google2fa_enable) {
            $request->session()->forget('2fa:user:id');

            return $this->redirectToLoginWithError('User not found or 2FA not configured. Please login again.');
        }

        // Verify the OTP code
        $valid = $this->checkOneTimePassword($user, (string) $request->input('one_time_password'));

        if ($valid === null) {
            return redirect()->route('2fa.verify')->with('error', $this->lockedOutMessage($user));
        }

        if (! $valid) {
            return redirect()->route('2fa.verify')
                ->with('error', 'Invalid authentication code. Please try again.');
        }

        $passwordBreached = false;
        if ($authenticatedUser === null) {
            $rememberMe = (bool) $request->session()->get('2fa:remember', false);
            Auth::login($user, $rememberMe);

            $passwordBreached = (bool) $request->session()->get('2fa:password_breached', false);
            $newSessionToken = Str::random(60);
            $user->forceFill(['session_token' => $newSessionToken])->save();
            $request->session()->put('session_token_web', $newSessionToken);
            event(new OtherDeviceLogout(Auth::getDefaultDriver(), $user));
        }

        $request->session()->regenerate();
        $request->session()->forget('2fa:trusted_device');
        Google2FA::boot($request)->login();

        $request->session()->forget(['2fa:user:id', '2fa:remember', '2fa:password_breached']);

        // Determine where to redirect after successful verification
        $redirectUrl = $request->session()->pull('url.intended', '/');

        // Create the redirect response
        $redirect = redirect()->to($redirectUrl)
            ->with('success', 'Two-factor authentication verified successfully.');

        if ($passwordBreached) {
            $redirect = $redirect->with('warning', 'Security Alert: Your password has been found in a data breach. We strongly recommend changing it immediately in your account settings.');
        }

        // If the user has checked "trust this device", create a trust token
        if ($request->has('trust_device') && $request->input('trust_device') == 1) {
            $trustedDevice = TrustedDevice::issueForUser($user, $request->ip(), $request->userAgent());
            $expiresAt = $trustedDevice['device']->expires_at->getTimestamp();

            $cookieData = [
                'user_id' => $user->id,
                'token' => $trustedDevice['plain'],
                'expires_at' => $expiresAt,
            ];

            $cookieValue = json_encode($cookieData, JSON_UNESCAPED_SLASHES);
            if ($cookieValue !== false) {
                $redirect->withCookie(cookie(
                    '2fa_trusted_device',
                    $cookieValue,
                    60 * 24 * 30,
                    '/',
                    config('session.domain'),
                    config('session.secure'),
                    true,
                    false,
                    config('session.same_site', 'lax')
                ));
            }
        }

        return $redirect;
    }

    /**
     * Display the 2FA verification form for a user who has already authenticated with username/password
     * but needs to enter their 2FA code.
     */
    public function getVerify2fa(Request $request): mixed
    {
        $user = $request->user() ?? User::find($request->session()->get('2fa:user:id'));
        if (! $user || ! $user->passwordSecurity?->google2fa_enable) {
            $request->session()->forget('2fa:user:id');

            return $this->redirectToLoginWithError('User not found. Please login again.');
        }

        return view('auth.2fa_verify', compact('user'));
    }

    /**
     * Show the 2FA enable form on a dedicated page
     */
    public function showEnable2faForm(Request $request): Application|View|Factory|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->redirectToLoginWithError('Please log in to access 2FA settings.');
        }

        $google2fa_url = $this->setupQrCode($user);

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
            return $this->redirectToLoginWithError('Please log in to access 2FA settings.');
        }

        $google2fa_url = $this->setupQrCode($user);

        $data = [
            'user' => $user,
            'google2fa_url' => $google2fa_url,
        ];

        return view('auth.2fa')->with('data', $data);
    }

    /**
     * The setup QR code holds the 2FA secret, so it is only shown while 2FA is being set up.
     */
    private function setupQrCode(User $user): string
    {
        $security = $user->passwordSecurity;
        if ($security === null || $security->google2fa_enable) {
            return '';
        }

        return Google2FA::getQRCodeInline(config('app.name'), $user->email, $security->google2fa_secret);
    }

    /**
     * Check a one-time code against the account's per-account attempt limit.
     *
     * @return bool|null null when the account is locked out after too many wrong codes
     */
    private function checkOneTimePassword(User $user, string $code): ?bool
    {
        $throttleKey = $this->otpThrottleKey($user);
        if (RateLimiter::tooManyAttempts($throttleKey, self::OTP_MAX_ATTEMPTS)) {
            return null;
        }

        $secret = (string) $user->passwordSecurity?->google2fa_secret;
        $valid = $secret !== '' && preg_match('/^\d{6}$/', $code) === 1 && (bool) Google2FA::verifyKey($secret, $code);

        if ($valid) {
            RateLimiter::clear($throttleKey);
        } else {
            RateLimiter::hit($throttleKey, self::OTP_LOCK_SECONDS);
        }

        return $valid;
    }

    private function lockedOutMessage(User $user): string
    {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn($this->otpThrottleKey($user)) / 60));

        return "Too many invalid authentication codes. Please try again in {$minutes} minute(s).";
    }

    private function otpThrottleKey(User $user): string
    {
        return '2fa-otp:'.$user->id;
    }

    private function redirectToLoginWithError(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}
