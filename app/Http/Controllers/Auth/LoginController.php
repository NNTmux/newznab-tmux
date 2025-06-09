<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserLoggedIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginLoginRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
    public function login(LoginLoginRequest $request): \Illuminate\Foundation\Application|\Illuminate\Routing\Redirector|Application|RedirectResponse
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
            $request->session()->flash('message', 'You have failed to login too many times.Try again in '.$this->decayMinutes().' minutes.');

            return redirect()->to('login');
        }

        if ($validator->passes()) {
            $user = User::query()->orWhere(['username' => $request->input('username'), 'email' => $request->input('username')])->withTrashed()->first();
            if ($user !== null) {
                // Check if user is soft deleted
                if ($user->trashed()) {
                    $request->session()->flash('message', 'This account has been deactivated. Please contact us through contact form to have your account reactivated.');

                    return redirect()->to('login');
                }

                $rememberMe = $request->has('rememberme') && $request->input('rememberme') === 'on';

                if (! $user->isVerified() || $user->isPendingVerification()) {
                    $request->session()->flash('message', 'You have not verified your email address!');

                    return redirect()->to('login');
                }

                if (Auth::attempt($request->only($login_type, 'password'), $rememberMe)) {
                    $userIp = config('nntmux:settings.store_user_ip') ? ($request->ip() ?? $request->getClientIp()) : '';
                    event(new UserLoggedIn($user, $userIp));

                    // Check if the user has 2FA enabled
                    if ($user->passwordSecurity && $user->passwordSecurity->google2fa_enable) {
                        // Check for trusted device cookie before redirecting to 2FA
                        $trustedCookie = $request->cookie('2fa_trusted_device');
                        if ($trustedCookie) {
                            try {
                                $cookieData = json_decode($trustedCookie, true);

                                // Validate the cookie data
                                if (json_last_error() === JSON_ERROR_NONE &&
                                    isset($cookieData['user_id'], $cookieData['token'], $cookieData['expires_at']) &&
                                    (int) $cookieData['user_id'] === (int) $user->id &&
                                    time() <= $cookieData['expires_at']) {

                                    // Cookie is valid - mark 2FA as passed
                                    session([config('google2fa.session_var') => true]);
                                    session([config('google2fa.session_var').'.auth.passed_at' => time()]);

                                    // Skip 2FA - proceed with login
                                    Auth::logoutOtherDevices($request->input('password'));
                                    $this->clearLoginAttempts($request);

                                    return redirect()->intended($this->redirectPath())->with('info', 'You have been logged in');
                                }
                            } catch (\Exception $e) {
                                \Log::error('Login - Error processing trusted device cookie', [
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                        // No valid trusted device cookie, proceed with 2FA verification
                        // Store intended URL for redirecting after 2FA verification
                        $request->session()->put('url.intended', $this->redirectPath());
                        Auth::logout();

                        // Store user ID in the session for 2FA verification
                        $request->session()->put('2fa:user:id', $user->id);

                        return redirect()->route('2fa.verify');
                    }

                    Auth::logoutOtherDevices($request->input('password'));
                    $this->clearLoginAttempts($request);

                    return redirect()->intended($this->redirectPath())->with('info', 'You have been logged in');
                }

                $this->incrementLoginAttempts($request);
                $request->session()->flash('message', 'Username or email and password combination used does not match our records!');
            } else {
                $this->incrementLoginAttempts($request);
                $request->session()->flash('message', 'Username or email used do not match our records!');
            }

            return redirect()->to('login');
        }

        $this->incrementLoginAttempts($request);
        $request->session()->flash('message', implode('', Arr::collapse($validator->errors()->toArray())));

        return redirect()->to('login');
    }

    public function showLoginForm()
    {
        $theme = 'Gentele';

        $meta_title = 'Login';
        $meta_keywords = 'Login';
        $meta_description = 'Login';
        // $content = app('smarty.view')->fetch($theme.'/login.tpl');
        app('smarty.view')->assign(compact(/* 'content', */ 'meta_title', 'meta_keywords', 'meta_description'));

        return app('smarty.view')->display($theme.'/login.tpl');
    }

    public function logout(Request $request): \Illuminate\Routing\Redirector|RedirectResponse
    {
        // Save 2FA trusted device cookie value before logout
        $trustedDeviceCookie = $request->cookie('2fa_trusted_device');

        // Perform standard logout
        $this->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerate();

        // If there was a trusted device cookie, preserve it by re-creating it
        if ($trustedDeviceCookie) {
            try {
                // Parse the cookie to get the original data including expiration time
                $cookieData = json_decode($trustedDeviceCookie, true);

                if (isset($cookieData['expires_at'])) {
                    // Calculate remaining minutes until expiration
                    $remainingSeconds = max(0, $cookieData['expires_at'] - time());
                    $remainingMinutes = (int) ceil($remainingSeconds / 60);

                    \Log::info('Logout - Cookie Expiration', [
                        'expires_at' => $cookieData['expires_at'],
                        'current_time' => time(),
                        'remaining_seconds' => $remainingSeconds,
                        'remaining_minutes' => $remainingMinutes,
                    ]);

                    // Only preserve the cookie if it hasn't expired yet
                    if ($remainingMinutes > 0) {
                        // Create a cookie with proper settings for persistence
                        $cookie = cookie(
                            '2fa_trusted_device',    // name
                            $trustedDeviceCookie,    // value
                            $remainingMinutes,       // minutes remaining
                            '/',                     // path
                            config('session.domain'), // use session domain config
                            config('session.secure'), // use session secure config
                            false,                   // httpOnly
                            false,                   // raw
                            config('session.same_site', 'lax') // use session same_site config
                        );

                        // Queue the cookie to be sent with the response
                        cookie()->queue($cookie);
                    } else {
                        \Log::warning('Logout - Cookie Already Expired');
                    }
                } else {
                    \Log::warning('Logout - Cookie Missing Expiration Data');
                }
            } catch (\Exception $e) {
                \Log::error('Logout - Error Processing Cookie', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->to('login')->with('message', 'You have been logged out successfully');
    }
}
