<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Settings;
use App\Models\UserRole;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Blacklight\utility\Utility;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Password;
use Illuminate\Foundation\Auth\RegistersUsers;
use Jrean\UserVerification\Traits\VerifiesUsers;
use Jrean\UserVerification\Facades\UserVerification;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;
    use VerifiesUsers;

    /**
     * Where to redirect users after registration.
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
        $this->middleware('guest', ['except' => ['getVerification', 'getVerificationError']]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data): User
    {
        return User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => User::hashPassword($data['password']),
            'host' => $data['host'] ?? '',
            'user_roles_id' => $data['user_roles_id'],
            'notes' => $data['notes'],
            'invites' => $data['defaultinvites'],
            'api_token' => md5(Password::getRepository()->createNewToken()),
            'userseed' => md5(Utility::generateUuid()),
        ]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function register(Request $request)
    {
        $error = $userName = $password = $confirmPassword = $email = $inviteCode = $inviteCodeQuery = '';
        $showRegister = 1;

        $this->validate($request, [
            'username' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (env('NOCAPTCHA_ENABLED') === true && (! empty(env('NOCAPTCHA_SECRET')) && ! empty(env('NOCAPTCHA_SITEKEY')))) {
            $this->validate($request, [
                'g-recaptcha-response' => 'required|captcha',
            ]);
        }

        if ((int) Settings::settingValue('..registerstatus') === Settings::REGISTER_STATUS_CLOSED) {
            session()->flash('status', 'Registrations are currently disabled.');
            $showRegister = 0;
        } elseif (Settings::settingValue('..registerstatus') === Settings::REGISTER_STATUS_INVITE && (! $request->has('invitecode') || empty($request->input('invitecode')))) {
            session()->flash('status', 'Registrations are currently invite only.');
            $showRegister = 0;
        }

        if ($showRegister === 1) {
            $action = $request->input('action') ?? 'view';

            switch ($action) {
                case 'submit':
                    $userName = $request->input('username');
                    $password = $request->input('password');
                    $confirmPassword = $request->input('password_confirmation');
                    $email = $request->input('email');
                    if ($request->has('invitecode')) {
                        $inviteCode = $request->input('invitecode');
                    }

                        // Get the default user role.
                        $userDefault = UserRole::getDefaultRole();

                        if ((int) Settings::settingValue('..registerstatus') === Settings::REGISTER_STATUS_INVITE) {
                            if ($inviteCode === '') {
                                $error = 'Sorry, the invite code is old or has been used.';
                                break;
                            }

                            $invitedBy = User::checkAndUseInvite($inviteCode);
                            if ($invitedBy < 0) {
                                $error = 'Sorry, the invite code is old or has been used.';
                                break;
                            }
                        }

                        if (! User::isValidUsername($userName)) {
                            $error = 'Your username must be at least five characters.';
                            break;
                        }

                        if (! User::isValidPassword($password)) {
                            $error = 'Your password must be longer than eight characters, have at least 1 number, at least 1 capital and at least one lowercase letter';
                            break;
                        }

                        if (! User::isValidEmail($email)) {
                            $error = 'Your email is not a valid format.';
                            break;
                        }

                        $res = User::getByUsername($userName);
                        if ($res !== null) {
                            $error = 'Sorry, the username is already taken.';
                            break;
                        }

                        $res = User::getByEmail($email);
                        if ($res !== null) {
                            $error = 'Sorry, the email is already in use.';
                            break;
                        }

                        $user = $this->create(
                            [
                                'username' => $userName,
                                'password' => $password,
                                'email' => $email,
                                'host' => $request->ip(),
                                'user_roles_id' => $userDefault['id'],
                                'notes' => '',
                                'defaultinvites' => $userDefault['defaultinvites'],
                            ]
                        );

                    event(new Registered($user));

                    UserVerification::generate($user);

                    UserVerification::send($user, 'User verification required');

                    return $this->registered($request, $user)
                        ?: redirect($this->redirectPath());

                    break;
                case 'view': {
                    $inviteCode = $request->input('invitecode') ?? null;
                    if ($inviteCode !== null) {
                        // See if it is a valid invite.
                        $invite = Invitation::getInvite($inviteCode);
                        if (! $invite) {
                            $error = sprintf('Bad or invite code older than %d days.', Invitation::DEFAULT_INVITE_EXPIRY_DAYS);
                            $showRegister = 0;
                        } else {
                            $inviteCode = $invite['guid'];
                        }
                    }
                    break;
                }
            }
        }
        app('smarty.view')->assign(
            [
                'username'          => Utility::htmlfmt($userName),
                'password'          => Utility::htmlfmt($password),
                'password_confirmation'   => Utility::htmlfmt($confirmPassword),
                'email'             => Utility::htmlfmt($email),
                'invitecode'        => Utility::htmlfmt($inviteCode),
                'invite_code_query' => Utility::htmlfmt($inviteCodeQuery),
                'showregister'      => $showRegister,
                'error'             => $error,
            ]
        );
    }

    /**
     * @throws \Exception
     */
    public function showRegistrationForm()
    {
        $theme = Settings::settingValue('site.main.style');

        $nocaptcha = env('NOCAPTCHA_ENABLED');

        $meta_title = 'Register';
        $meta_keywords = 'register,signup,registration';
        $meta_description = 'Register';

        $content = app('smarty.view')->fetch($theme.'/register.tpl');
        app('smarty.view')->assign(
            [
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
                'nocaptcha' => $nocaptcha,
            ]
        );
        app('smarty.view')->display($theme.'/basepage.tpl');
    }
}
