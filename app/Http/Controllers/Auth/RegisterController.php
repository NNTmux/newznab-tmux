<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Settings;
use App\Models\Invitation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Blacklight\utility\Utility;
use Illuminate\Routing\Redirector;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Junaidnasir\Larainvite\Facades\Invite;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Validation\ValidationException;
use Jrean\UserVerification\Traits\VerifiesUsers;

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
     * @var string
     */
    private $inviteCodeQuery;

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
     * @param array $data
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

        return $user;
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse|Redirector|void
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
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/'],
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

                    return $this->registered($request, $user) ?: redirect($this->redirectPath());
                }
                break;
            case 'view': {
                // See if it is a valid invite.
                if (($inviteCode !== null) && ! Invite::isValid($inviteCode)) {
                    $error = 'Invalid invitation token!';
                    $showRegister = 0;
                } else {
                    $showRegister = 1;
                }
                break;
            }
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
     * @param Request $request
     * @param string $error
     * @param int $showRegister
     */
    public function showRegistrationForm(Request $request, $error = '', $showRegister = 0)
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
