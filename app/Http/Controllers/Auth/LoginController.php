<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserLoggedIn;
use App\Http\Controllers\Controller;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
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
     * @param Request $request
     * @return RedirectResponse
     * @throws AuthenticationException
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
            Session::flash('message', 'You have failed to login too many times.Try again in '.$this->decayMinutes().' minutes.');

            return $this->showLoginForm();
        }

        if ($validator->passes()) {
            $user = User::query()->orWhere(['username' => $request->input('username'), 'email' => $request->input('username')])->first();
            if ($user !== null) {
                if (config('captcha.enabled') === true && (! empty(config('captcha.secret')) && ! empty(config('captcha.sitekey')))) {
                    $this->validate($request, [
                        'g-recaptcha-response' => ['required', 'captcha'],
                    ]);
                }

                $rememberMe = $request->has('rememberme') && $request->input('rememberme') === 'on';

                if (! $user->isVerified() || $user->isPendingVerification()) {
                    Session::flash('message', 'You have not verified your email address!');

                    return $this->showLoginForm();
                }

                if (Auth::attempt($request->only($login_type, 'password'), $rememberMe)) {
                    $userIp = (int) Settings::settingValue('..storeuserips') === 1 ? (request()->ip() ?? request()->getClientIp()) : '';
                    event(new UserLoggedIn($user, $userIp));

                    Auth::logoutOtherDevices($request->input('password'));
                    $this->clearLoginAttempts($request);

                    return redirect()->intended($this->redirectPath())->with('info', 'You have been logged in');
                }

                $this->incrementLoginAttempts($request);
                Session::flash('message', 'Username or email and password combination used does not match our records!');
            } else {
                $this->incrementLoginAttempts($request);
                Session::flash('message', 'Username or email used do not match our records!');
            }

            return $this->showLoginForm();
        }

        $this->incrementLoginAttempts($request);
        Session::flash('message', implode('', Arr::collapse($validator->errors()->toArray())));

        return $this->showLoginForm();
    }

    public function showLoginForm()
    {
        $theme = Settings::settingValue('site.main.style');

        $meta_title = 'Login';
        $meta_keywords = 'Login';
        $meta_description = 'Login';
        $content = app('smarty.view')->fetch($theme.'/login.tpl');
        app('smarty.view')->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));
        return app('smarty.view')->display($theme.'/basepage.tpl');
    }


    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('login')->with('message', 'You have been logged out successfully');
    }
}
