<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Auth\LoginLoginRequest;
use App\Events\UserLoggedIn;
use App\Http\Controllers\Controller;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
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
     *
     * @throws \Illuminate\Auth\AuthenticationException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(LoginLoginRequest $request): RedirectResponse
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

            return $this->showLoginForm();
        }

        if ($validator->passes()) {
            $user = User::getByUsername($request->input('username'));
            if ($user === null) {
                $user = User::getByEmail($request->input('username'));
            }

            if ($user !== null) {
                if (config('captcha.enabled') === true && (! empty(config('captcha.secret')) && ! empty(config('captcha.sitekey')))) {
                }

                $rememberMe = $request->has('rememberme') && $request->input('rememberme') === 'on';

                if (! $user->isVerified() || $user->isPendingVerification()) {
                    $request->session()->flash('message', 'You have not verified your email address!');

                    return $this->showLoginForm();
                }

                if (Auth::attempt($request->only($login_type, 'password'), $rememberMe)) {
                    $userIp = (int) Settings::settingValue('..storeuserips') === 1 ? ($request->ip() ?? $request->getClientIp()) : '';
                    event(new UserLoggedIn($user, $userIp));

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

            return $this->showLoginForm();
        }

        $this->incrementLoginAttempts($request);
        $request->session()->flash('message', implode('', Arr::collapse($validator->errors()->toArray())));

        return $this->showLoginForm();
    }

    public function showLoginForm(): void
    {
        $theme = Settings::settingValue('site.main.style');

        $meta_title = 'Login';
        $meta_keywords = 'Login';
        $meta_description = 'Login';
        $content = app('smarty.view')->fetch($theme.'/login.tpl');
        app('smarty.view')->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));
        app('smarty.view')->display($theme.'/basepage.tpl');
    }

    public function logout(Request $request): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        $this->guard()->logout();

        $request->session()->flush();
        $request->session()->regenerate();

        return redirect()->to('login')->with('message', 'You have been logged out successfully');
    }
}
