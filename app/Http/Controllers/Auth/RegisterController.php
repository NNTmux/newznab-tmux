<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRegisterRequest;
use App\Models\Invitation;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Password as PasswordFacade;
use Illuminate\Support\Facades\Validator;
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
     */
    protected string $redirectTo = '/';

    private string $inviteCodeQuery = '';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest', ['except' => ['getVerification', 'getVerificationError']]);
    }

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
     * @return RedirectResponse|Redirector|void
     *
     * @throws ValidationException
     */
    public function register(RegisterRegisterRequest $request)
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

                // Check if the email is associated with a soft-deleted user
                $existingUser = User::withTrashed()->where('email', $email)->first();
                if ($existingUser && $existingUser->trashed()) {
                    $error = 'This email address belongs to a deactivated account.';

                    return $this->showRegistrationForm($request, $error);
                }

                // Get the default user role.
                $userDefault = Role::query()->where('isdefault', '=', 1)->first();

                if (! empty($error)) {
                    return $this->showRegistrationForm($request, $error);
                }

                if (Invite::isAllowed($inviteCode, $email) || Settings::settingValue('registerstatus') !== Settings::REGISTER_STATUS_INVITE) {
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

                    // Create verification message that will be shown on login page
                    $notificationMessage = 'Your Account has been created. A verification email has been sent to your email address. You will be able to log in after completing the verification process.';

                    // Store notification in session so it's available after redirect
                    // Use 'message' key to match the key expected by the login template
                    $request->session()->flash('message', $notificationMessage);

                    // Set the message type to info for proper styling
                    $request->session()->flash('message_type', 'info');

                    // First ensure the user is created and verification is sent, then redirect to login
                    $this->registered($request, $user);

                    return redirect('/login');
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
                'username' => e($userName),
                'password' => e($password),
                'password_confirmation' => e($confirmPassword),
                'email' => e($email),
                'invitecode' => e($inviteCode),
                'invite_code_query' => e($this->inviteCodeQuery),
                'showregister' => $showRegister,
            ]
        );

        return $this->showRegistrationForm($request, $error, $showRegister);
    }

    public function showRegistrationForm(Request $request, string $error = '', int $showRegister = 0): void
    {
        $inviteCode = '';
        if ($request->has('invitecode')) {
            $inviteCode = $request->input('invitecode');
            $this->inviteCodeQuery = '&invitecode='.$inviteCode;
        }

        if ((int) Settings::settingValue('registerstatus') === Settings::REGISTER_STATUS_INVITE) {
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
        } elseif ((int) Settings::settingValue('registerstatus') === Settings::REGISTER_STATUS_CLOSED) {
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
        $theme = 'Gentele';

        $nocaptcha = config('settings.nocaptcha_enabled');

        $meta_title = 'Register';
        $meta_keywords = 'register,signup,registration';
        $meta_description = 'Register';

        $content = app('smarty.view')->fetch($theme.'/register.tpl');
        app('smarty.view')->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description', 'nocaptcha'));
        app('smarty.view')->display($theme.'/basepage.tpl');
    }
}
