<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserLoggedIn;
use App\Http\Controllers\Controller;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
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

    protected $maxAttempts = 3; // Default is 5
    protected $decayMinutes = 2; // Default is 1

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

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
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|void
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
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
            $error = 'You have failed to login too many times.Try again in: '.$this->decayMinutes().' minutes.';
            return $this->showLoginForm($error);
        }

        if ($validator->passes()) {
            $user = User::getByUsername($request->input('username'));
            if ($user === null) {
                $user = User::getByEmail($request->input('username'));
            }

            if ($user !== null && ((config('firewall.enabled') === true && ! \Firewall::isBlacklisted($user->host)) || config('firewall.enabled') === false)) {
                if (config('captcha.enabled') === true && (! empty(config('captcha.secret')) && ! empty(config('captcha.sitekey')))) {
                    $this->validate($request, [
                        'g-recaptcha-response' => ['required', 'captcha'],
                    ]);
                }

                $rememberMe = $request->has('rememberme') && $request->input('rememberme') === 'on';

                if (! $user->isVerified() || $user->isPendingVerification()) {
                    return $this->showLoginForm('You have not verified your email address!');
                }

                if (Auth::attempt($request->only($login_type, 'password'), $rememberMe)) {
                    $userIp = (int) Settings::settingValue('..storeuserips') === 1 ? (request()->ip() ?? request()->getClientIp()) : '';
                    event(new UserLoggedIn($user, $userIp));

                    Auth::logoutOtherDevices($request->input('password'));
                    $this->clearLoginAttempts($request);

                    return redirect()->intended($this->redirectPath())->with('info', 'You have been logged in');
                }

                $this->incrementLoginAttempts($request);
                $error = 'Username or email and password combination used does not match our records!';
            } else {
                $this->incrementLoginAttempts($request);
                $error = 'Username or email used do not match our records!';
            }
            return $this->showLoginForm($error);
        }

        $this->incrementLoginAttempts($request);

        $error = implode('', Arr::collapse($validator->errors()->toArray()));

        return $this->showLoginForm($error);
    }

    /**
     * @param string $error
     * @param string $notice
     */
    public function showLoginForm($error = '', $notice = '')
    {
        $theme = Settings::settingValue('site.main.style');
        app('smarty.view')->assign(['error' => $error, 'notice' => $notice, 'username' => '', 'rememberme' => '']);

        $meta_title = 'Login';
        $meta_keywords = 'Login';
        $meta_description = 'Login';
        $content = app('smarty.view')->fetch($theme.'/login.tpl');
        app('smarty.view')->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));
        app('smarty.view')->display($theme.'/basepage.tpl');
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function logout(Request $request)
    {
        $this->guard()->logout();

        $request->session()->flush();
        $request->session()->regenerate();

        return redirect('login')->with('info', 'You have been logged out successfully');
    }
}
