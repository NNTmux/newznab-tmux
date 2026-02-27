<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRegisterRequest;
use App\Models\Invitation;
use App\Models\Settings;
use App\Models\User;
use App\Rules\ValidEmailDomain;
use App\Support\Auth\RegistersUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Jrean\UserVerification\Traits\VerifiesUsers;
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

    /**
     * @param  array<string, mixed>  $data
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
            'api_token' => md5(Str::random(40)),
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

        // Handle invitation token from URL (for email links)
        if ($request->has('token')) {
            $inviteCode = $request->input('token');
            $this->inviteCodeQuery = '&token='.$inviteCode;
        }

        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'min:5', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users', new ValidEmailDomain],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
        ], [
            'password.min' => 'The password must be at least 8 characters.',
            'password.letters' => 'The password must contain letters.',
            'password.mixed_case' => 'The password must contain both uppercase and lowercase letters.',
            'password.numbers' => 'The password must contain at least one number.',
            'password.symbols' => 'The password must contain at least one symbol.',
            'password.uncompromised' => 'The password appears in a data breach and should not be used.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->except('password', 'password_confirmation'));
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

                // Check invitation validity using custom system
                $invitationValid = $this->isInvitationValid($inviteCode, $email);
                $registrationOpen = Settings::settingValue('registerstatus') === Settings::REGISTER_STATUS_OPEN;

                if ($invitationValid || $registrationOpen) {
                    // Get invited_by from invitation if available
                    $invitedBy = 0;
                    $invitation = null;

                    if (! empty($inviteCode)) {
                        $invitation = Invitation::findValidByToken($inviteCode);
                        if ($invitation) {
                            $invitedBy = $invitation->invited_by;

                            // Validate email matches invitation
                            if (! empty($invitation->email) && $invitation->email !== $email) {
                                $error = 'Email address does not match the invitation.';

                                return $this->showRegistrationForm($request, $error);
                            }
                        }
                    }

                    $userData = [
                        'username' => $userName,
                        'password' => $password,
                        'email' => $email,
                        'host' => $request->ip(),
                        'roles_id' => $userDefault !== null ? $userDefault['id'] : User::ROLE_USER,
                        'notes' => '',
                        'defaultinvites' => $userDefault !== null ? $userDefault['defaultinvites'] : Invitation::DEFAULT_INVITES,
                    ];

                    // Apply invitation metadata if available
                    if ($invitation && $invitation->metadata) {
                        if (isset($invitation->metadata['role'])) {
                            $userData['roles_id'] = $invitation->metadata['role'];
                        }
                    }

                    $user = $this->create($userData);

                    // Mark invitation as used
                    if ($invitation) {
                        $invitation->markAsUsed($user->id);
                    }

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

                $error = 'Invalid or expired invitation token!';
                break;

            case 'view':
                // Don't set showRegister here - let showRegistrationForm handle it
                // Only validate invite code if present
                if (($inviteCode !== null) && ! $this->isInvitationTokenValid($inviteCode)) {
                    $error = 'Invalid invitation token!';
                }
                break;
        }

        return $this->showRegistrationForm($request, $error, $showRegister);
    }

    public function showRegistrationForm(Request $request, string $error = '', int $showRegister = 0): mixed
    {
        $inviteCode = '';
        if ($request->has('invitecode')) {
            $inviteCode = $request->input('invitecode');
            $this->inviteCodeQuery = '&invitecode='.$inviteCode;
        }

        // Handle invitation token from URL (for email links)
        if ($request->has('token')) {
            $inviteCode = $request->input('token');
            $this->inviteCodeQuery = '&token='.$inviteCode;
        }

        $emailFromInvite = '';

        if ((int) Settings::settingValue('registerstatus') === Settings::REGISTER_STATUS_INVITE) {
            if (! empty($inviteCode)) {
                if ($this->isInvitationTokenValid($inviteCode)) {
                    $error = '';
                    $showRegister = 1;

                    // Pre-fill email if invitation has one
                    $invitation = Invitation::findValidByToken($inviteCode);
                    if ($invitation && ! empty($invitation->email)) {
                        $emailFromInvite = $invitation->email;
                    }
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
        } elseif ($request->has('invitecode') || $request->has('token')) {
            $error = 'Registration is open, you don\'t need the invite code to register.';
            $showRegister = 0;
        } else {
            $showRegister = 1;
        }

        return view('auth.register')->with([
            'showregister' => $showRegister,
            'error' => $error,
            'email' => $emailFromInvite,
            'invite_code_query' => $this->inviteCodeQuery,
            'invitecode' => $inviteCode,
        ]);
    }

    /**
     * Check if invitation is valid for given email
     */
    private function isInvitationValid(string $token, string $email): bool
    {
        if (empty($token)) {
            return false;
        }

        $invitation = Invitation::findValidByToken($token);

        if (! $invitation) {
            return false;
        }

        // If invitation has specific email, validate it matches
        if (! empty($invitation->email) && $invitation->email !== $email) {
            return false;
        }

        return true;
    }

    /**
     * Check if invitation token is valid (without email check)
     */
    private function isInvitationTokenValid(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $invitation = Invitation::findValidByToken($token);

        return $invitation !== null;
    }
}
