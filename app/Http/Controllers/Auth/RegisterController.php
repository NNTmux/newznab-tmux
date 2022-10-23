<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Settings;
use App\Models\User;
use Blacklight\utility\Utility;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Password as PasswordFacade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Jrean\UserVerification\Traits\VerifiesUsers;
use Junaidnasir\Larainvite\Facades\Invite;
use Spatie\Permission\Models\Role;

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
    protected string $redirectTo = '/';

    /**
     * @var string
     */
    private string $inviteCodeQuery;

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
            'api_token' => md5(PasswordFacade::getRepository()->createNewToken()),
            'userseed' => md5(Str::uuid()->toString()),
        ]);

        $role = Role::query()->where('id', '=', $data['roles_id'])->first();

        if ($role !== null) {
            $user->syncRoles([$role->name]);
            if ($user->can('view console')) {
                $user->givePermissionTo('view console');
            }

            if ($user->can('view movies')) {
                $user->givePermissionTo('view movies');
            }

            if ($user->can('view audio')) {
                $user->givePermissionTo('view audio');
            }

            if ($user->can('view pc')) {
                $user->givePermissionTo('view pc');
            }

            if ($user->can('view tv')) {
                $user->givePermissionTo('view tv');
            }

            if ($user->can('view adult')) {
                $user->givePermissionTo('view adult');
            }

            if ($user->can('view books')) {
                $user->givePermissionTo('view books');
            }

            if ($user->can('view other')) {
                $user->givePermissionTo('view other');
            }
        }

        return $user;
    }

    /**
     * @param  Request  $request
     * @return RedirectResponse|Redirector|void
     *
     * @throws ValidationException
     */
    public function register(Request $request)
    {
        $error = $userName = $password = $confirmPassword = $email = $inviteCode = '';
        $showRegister = 1;

        if ($request->has('invitecode')) {
            $inviteCode = $request->input('invitecode');
            $this->inviteCodeQuery = '&invitecode='.$inviteCode;
        }

        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'min:5', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users', 'indisposable'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
        ]);

        if (config('captcha.enabled') === true && (! empty(config('captcha.secret')) && ! empty(config('captcha.sitekey')))) {
            $this->validate($request, [
                'g-recaptcha-response' => ['required', 'captcha'],
            ]);
        }

        if ($validator->fails()) {
            $error = implode('', Arr::collapse($validator->errors()->toArray()));

            return $this->showRegistrationForm($request, $error);
        }

        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                $userName = $request->input('username');
                $password = $request->input('password');
                $confirmPassword = $request->input('password_confirmation');
                $email = $request->input('email');

                // Get the default user role.
                $userDefault = Role::query()->where('isdefault', '=', 1)->first();

                if (! empty($error)) {
                    return $this->showRegistrationForm($request, $error);
                }

                if (Invite::isAllowed($inviteCode, $email) || Settings::settingValue('..registerstatus') !== Settings::REGISTER_STATUS_INVITE) {
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
                    Invite::consume($inviteCode);

                    return $this->registered($request, $user) ?: redirect($this->redirectPath())->with('info', 'Your Account has been created. You will receive a separate verification email shortly.');
                }
                break;
            case 'view':
                // See if it is a valid invite.
                if (($inviteCode !== null) && ! Invite::isValid($inviteCode)) {
                    $error = 'Invalid invitation token!';
                    $showRegister = 0;
                } else {
                    $showRegister = 1;
                }
                break;

        }
        app('smarty.view')->assign(
            [
                'username'          => Utility::htmlfmt($userName),
                'password'          => Utility::htmlfmt($password),
                'password_confirmation'   => Utility::htmlfmt($confirmPassword),
                'email'             => Utility::htmlfmt($email),
                'invitecode'        => Utility::htmlfmt($inviteCode),
                'invite_code_query' => Utility::htmlfmt($this->inviteCodeQuery),
                'showregister'      => $showRegister,
            ]
        );

        return $this->showRegistrationForm($request, $error, $showRegister);
    }

    /**
     * @param  Request  $request
     * @param string $error
     * @param int $showRegister
     */
    public function showRegistrationForm(Request $request, string $error = '', int $showRegister = 0): void
    {
        $inviteCode = '';
        if ($request->has('invitecode')) {
            $inviteCode = $request->input('invitecode');
            $this->inviteCodeQuery = '&invitecode='.$inviteCode;
        }

        if ((int) Settings::settingValue('..registerstatus') === Settings::REGISTER_STATUS_INVITE) {
            if (! empty($inviteCode)) {
                if (Invite::isValid($inviteCode)) {
                    $error = '';
                    $showRegister = 1;
                } else {
                    $error = 'Invalid or expired invitation token!';
                    $showRegister = 0;
                }
            } else {
                $error = 'Registrations are currently invite only.';
                $showRegister = 0;
            }
        } elseif ((int) Settings::settingValue('..registerstatus') === Settings::REGISTER_STATUS_CLOSED) {
            $error = 'Registrations are currently closed.';
            $showRegister = 0;
        } elseif ($request->has('invitecode')) {
            $error = 'Registration is open, you don\'t need the invite code to register.';
            $showRegister = 0;
        } else {
            $showRegister = 1;
        }

        app('smarty.view')->assign('showregister', $showRegister);
        app('smarty.view')->assign('error', $error);
        app('smarty.view')->assign('invite_code_query', $this->inviteCodeQuery);
        $theme = Settings::settingValue('site.main.style');

        $nocaptcha = env('NOCAPTCHA_ENABLED');

        $meta_title = 'Register';
        $meta_keywords = 'register,signup,registration';
        $meta_description = 'Register';

        $content = app('smarty.view')->fetch($theme.'/register.tpl');
        app('smarty.view')->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description', 'nocaptcha'));
        app('smarty.view')->display($theme.'/basepage.tpl');
    }
}
