<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Settings;
use App\Models\Invitation;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Blacklight\utility\Utility;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
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
        $user = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => User::hashPassword($data['password']),
            'host' => $data['host'] ?? '',
            'roles_id' => $data['roles_id'],
            'notes' => $data['notes'],
            'invites' => $data['defaultinvites'],
            'api_token' => md5(Password::getRepository()->createNewToken()),
            'userseed' => md5(Str::uuid()->toString()),
        ]);

        $roleName = Role::query()->where('id', $data['roles_id'])->value('name');

        $user->assignRole($roleName);

        return $user;
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Jrean\UserVerification\Exceptions\ModelNotCompliantException
     */
    public function register(Request $request)
    {
        $error = $userName = $password = $confirmPassword = $email = $inviteCode = $inviteCodeQuery = '';
        $showRegister = 1;

        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'min:5', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users', 'indisposable'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/'],
        ]);

        if (env('NOCAPTCHA_ENABLED') === true && (! empty(env('NOCAPTCHA_SECRET')) && ! empty(env('NOCAPTCHA_SITEKEY')))) {
            $this->validate($request, [
                'g-recaptcha-response' => ['required', 'captcha'],
            ]);
        }

        if ($validator->fails()) {
            $error = implode('', array_collapse($validator->errors()->toArray()));
            return $this->showRegistrationForm($error);
        }

        if (Settings::settingValue('..registerstatus') === Settings::REGISTER_STATUS_INVITE && (! $request->has('invitecode') || empty($request->input('invitecode')))) {
            $error = 'Registrations are currently invite only.';

            return $this->showRegistrationForm($error);
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
                        $userDefault = Role::query()->where('isdefault', '=', 1)->first();

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
                        if (! empty($error)) {
                            return $this->showRegistrationForm($error);
                        }

                        $user = $this->create(
                            [
                                'username' => $userName,
                                'password' => $password,
                                'email' => $email,
                                'host' => $request->ip(),
                                'roles_id' => $userDefault !== null ? $userDefault['id'] : User::ROLE_USER,
                                'notes' => '',
                                'defaultinvites' => $userDefault !== null ? $userDefault['defaultinvites'] : Invitation::DEFAULT_INVITES,
                            ]
                        );

                    event(new Registered($user));

                    UserVerification::generate($user);

                    UserVerification::send($user, 'User email verification required');

                    return $this->registered($request, $user) ?: redirect($this->redirectPath());

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
            ]
        );

        return $this->showRegistrationForm($error);
    }

    /**
     * @param string $error
     */
    public function showRegistrationForm($error = '')
    {
        $showRegister = 1;
        if ((int) Settings::settingValue('..registerstatus') === Settings::REGISTER_STATUS_CLOSED) {
            $error = 'Registrations are currently disabled.';
            $showRegister = 0;
        }
        if ((int) Settings::settingValue('..registerstatus') === Settings::REGISTER_STATUS_INVITE) {
            $error = 'Registrations are currently invite only.';
            $showRegister = 0;
        }
        app('smarty.view')->assign('showregister', $showRegister);
        app('smarty.view')->assign('error', $error);
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
