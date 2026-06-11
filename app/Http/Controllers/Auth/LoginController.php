<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Events\UserLoggedIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginLoginRequest;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\PasswordBreachService;
use App\Support\Auth\AuthenticatesUsers;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    protected int $maxAttempts = 3; // Default is 5

    protected int $decayMinutes = 2; // Default is 1

    /**
     * Where to redirect users after login.
     */
    protected string $redirectTo = '/';

    private const string GENERIC_LOGIN_FAILURE = 'Username or email and password combination used does not match our records!';

    /**
     * Get the login username to be used (form field name; value may be email or username).
     */
    public function username(): string
    {
        return 'username';
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * @throws AuthenticationException
     */
    public function login(LoginLoginRequest $request): \Illuminate\Foundation\Application|Redirector|Application|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required'],
            'password' => ['required'],
        ]);

        $login_type = filter_var($request->input('username'), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $request->merge([
            $login_type => $request->input('username'),
        ]);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $request->session()->flash('warning', 'You have failed to log in too many times. Try again in '.$this->decayMinutes().' minutes.');

            return redirect()->to('login');
        }

        if ($validator->passes()) {
            $rememberMe = $request->has('rememberme') && $request->input('rememberme') === 'on';

            if (! Auth::attempt($request->only($login_type, 'password'), $rememberMe)) {
                $this->incrementLoginAttempts($request);
                Log::channel('failed_login')->error('Failed login attempt by user: '.$request->input('username').' from IP address: '.$request->ip());
                $request->session()->flash('error', self::GENERIC_LOGIN_FAILURE);

                return redirect()->to('login');
            }

            /** @var User $user */
            $user = Auth::user();

            if ($user->is_disabled || ! $user->hasVerifiedEmail()) {
                Auth::logout();
                $this->incrementLoginAttempts($request);
                Log::channel('failed_login')->error('Failed login attempt by user: '.$request->input('username').' from IP address: '.$request->ip());
                $request->session()->flash('error', self::GENERIC_LOGIN_FAILURE);

                return redirect()->to('login');
            }

            $request->session()->regenerate();

            $userIp = config('nntmux:settings.store_user_ip') ? ($request->ip() ?? $request->getClientIp()) : '';
            event(new UserLoggedIn($user, $userIp));

            $passwordBreached = $this->isPasswordBreached((string) $request->input('password'));

            if ($user->passwordSecurity && $user->passwordSecurity->google2fa_enable) {
                if ($this->trustedDeviceCookieIsValid($request, $user)) {
                    session([config('google2fa.session_var') => true]);
                    session([config('google2fa.session_var').'.auth.passed_at' => time()]);

                    Auth::logoutOtherDevices((string) $request->input('password'));
                    $this->rotateSessionTokenForCurrentSession($request, $user);
                    $this->clearLoginAttempts($request);

                    return $this->appendPasswordBreachWarning(
                        redirect()->intended($this->redirectPath())->with('info', 'You have been logged in'),
                        $passwordBreached
                    );
                }

                Auth::logoutOtherDevices((string) $request->input('password'));
                $request->session()->put('url.intended', $this->redirectPath());
                $request->session()->put('2fa:remember', $rememberMe);
                $request->session()->put('2fa:password_breached', $passwordBreached);

                Auth::logout();
                $request->session()->put('2fa:user:id', $user->id);

                return redirect()->route('2fa.verify');
            }

            Auth::logoutOtherDevices((string) $request->input('password'));
            $this->rotateSessionTokenForCurrentSession($request, $user);
            $this->clearLoginAttempts($request);

            return $this->appendPasswordBreachWarning(
                redirect()->intended($this->redirectPath())->with('info', 'You have been logged in'),
                $passwordBreached
            );

        }

        $this->incrementLoginAttempts($request);
        $request->session()->flash('error', implode('', Arr::collapse($validator->errors()->toArray())));
        Log::channel('failed_login')->error('Failed login attempt by user: '.$request->input('username').' from IP address: '.$request->ip());

        return redirect()->to('login');
    }

    public function showLoginForm(): mixed
    {
        return view('auth.login');
    }

    public function logout(Request $request): Redirector|RedirectResponse
    {
        // Save 2FA trusted device cookie value before logout
        $trustedDeviceCookie = $request->cookie('2fa_trusted_device');

        // Perform standard logout
        $this->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerate();

        if ($trustedDeviceCookie) {
            try {
                $cookieData = json_decode($trustedDeviceCookie, true);

                if (isset($cookieData['expires_at'])) {
                    // Calculate remaining minutes until expiration
                    $remainingSeconds = max(0, $cookieData['expires_at'] - time());
                    $remainingMinutes = (int) ceil($remainingSeconds / 60);

                    Log::info('Logout - Cookie Expiration', [
                        'expires_at' => $cookieData['expires_at'],
                        'current_time' => time(),
                        'remaining_seconds' => $remainingSeconds,
                        'remaining_minutes' => $remainingMinutes,
                    ]);

                    // Only preserve the cookie if it hasn't expired yet
                    if ($remainingMinutes > 0) {
                        $cookie = cookie(
                            '2fa_trusted_device',
                            $trustedDeviceCookie,
                            $remainingMinutes,
                            '/',
                            config('session.domain'), // use session domain config
                            config('session.secure'), // use session secure config
                            true,
                            false,                   // raw
                            config('session.same_site', 'lax') // use session same_site config
                        );

                        // Queue the cookie to be sent with the response
                        cookie()->queue($cookie);
                    } else {
                        Log::warning('Logout - Cookie Already Expired');
                    }
                } else {
                    Log::warning('Logout - Cookie Missing Expiration Data');
                }
            } catch (\Exception $e) {
                Log::error('Logout - Error Processing Cookie', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->to('login')->with('success', 'You have been logged out successfully');
    }

    /**
     * Check if the password has been compromised in a data breach and add a warning if so.
     */
    protected function checkPasswordBreachAndRedirect(string $password, RedirectResponse $redirect): RedirectResponse
    {
        return $this->appendPasswordBreachWarning($redirect, $this->isPasswordBreached($password));
    }

    protected function isPasswordBreached(string $password): bool
    {
        try {
            $breachService = app(PasswordBreachService::class);

            if ($breachService->isPasswordBreached($password)) {
                return true;
            }
        } catch (\Exception $e) {
            Log::error('Password breach check failed during login', [
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    protected function appendPasswordBreachWarning(RedirectResponse $redirect, bool $passwordBreached): RedirectResponse
    {
        if ($passwordBreached) {
            return $redirect->with('warning', 'Security Alert: Your password has been found in a data breach. We strongly recommend changing it immediately in your account settings.');
        }

        return $redirect;
    }

    private function trustedDeviceCookieIsValid(Request $request, User $user): bool
    {
        $trustedCookie = $request->cookie('2fa_trusted_device');
        if (! is_string($trustedCookie) || $trustedCookie === '') {
            return false;
        }

        try {
            $cookieData = json_decode($trustedCookie, true);

            return json_last_error() === JSON_ERROR_NONE
                && isset($cookieData['user_id'], $cookieData['token'], $cookieData['expires_at'])
                && (int) $cookieData['user_id'] === (int) $user->id
                && time() <= (int) $cookieData['expires_at']
                && TrustedDevice::findValidForUser((int) $user->id, (string) $cookieData['token']) !== null;
        } catch (\Exception $e) {
            Log::error('Login - Error processing trusted device cookie', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function rotateSessionTokenForCurrentSession(Request $request, User $user): void
    {
        $newSessionToken = Str::random(60);

        $user->forceFill([
            'session_token' => $newSessionToken,
        ])->save();

        $request->session()->put('session_token_web', $newSessionToken);
        event(new OtherDeviceLogout(Auth::getDefaultDriver(), $user));
    }
}
