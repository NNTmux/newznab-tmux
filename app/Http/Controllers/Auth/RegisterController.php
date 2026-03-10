<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRegisterRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Rules\ValidEmailDomain;
use App\Services\RegistrationStatusService;
use App\Support\Auth\RegistersUsers;
use App\Support\PermissionSyncHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Jrean\UserVerification\Traits\VerifiesUsers;
use Spatie\Permission\Models\Role;
use Throwable;

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

    public function __construct(
        private readonly RegistrationStatusService $registrationStatusService
    ) {
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
            PermissionSyncHelper::grantInheritedPermissions($user);
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
        $error = '';
        $showRegister = 1;
        $inviteCode = $this->extractInviteCode($request);

        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'min:5', 'max:255', 'unique:users'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where(fn ($query) => $query->whereNull('deleted_at')),
                new ValidEmailDomain,
            ],
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
            $this->logRegistrationFailure(
                $request,
                'validation_failed',
                'The registration request failed validation.',
                'warning',
                ['validation_errors' => $validator->errors()->toArray()]
            );

            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->except('password', 'password_confirmation'));
        }

        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                $userName = (string) $request->input('username');
                $password = (string) $request->input('password');
                $email = (string) $request->input('email');

                // Check if the email is associated with a soft-deleted user
                $existingUser = User::withTrashed()->where('email', $email)->first();
                if ($existingUser && $existingUser->trashed()) {
                    return $this->redirectBackWithRegistrationError(
                        $request,
                        'This email address belongs to a deactivated account. Please contact us if you need help reactivating it.',
                        'deactivated_account'
                    );
                }

                // Get the default user role.
                $userDefault = Role::query()->where('isdefault', '=', 1)->first();

                $status = $this->registrationStatusService->resolve();
                $invitationValid = $this->isInvitationValid($inviteCode, $email);

                if ($status['is_open'] || ($status['is_invite_only'] && $invitationValid)) {
                    $invitation = null;

                    if ($invitationValid) {
                        $invitation = Invitation::findValidByToken($inviteCode);
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

                    try {
                        DB::transaction(function () use ($invitation, $userData): void {
                            $user = $this->create($userData);

                            if ($invitation && ! $invitation->markAsUsed($user->id)) {
                                throw new \RuntimeException('Failed to mark invitation as used.');
                            }
                        });
                    } catch (Throwable $exception) {
                        return $this->redirectBackWithRegistrationError(
                            $request,
                            'We could not complete your registration right now. Please try again in a few minutes. If the problem continues, contact support.',
                            'unexpected_exception',
                            'error',
                            [
                                'error' => $exception->getMessage(),
                                'exception' => $exception::class,
                            ]
                        );
                    }

                    return redirect()
                        ->route('login')
                        ->with('info', 'Your account has been created. You will receive an account confirmation email shortly. Please verify your email address before logging in.');
                }

                return $this->rejectUnavailableRegistration($request, $status, $inviteCode);

            case 'view':
                // Don't set showRegister here - let showRegistrationForm handle it
                // Only validate invite code if present
                if ($inviteCode !== '' && ! $this->isInvitationTokenValid($inviteCode)) {
                    $error = 'Invalid invitation token!';
                }
                break;
        }

        return $this->showRegistrationForm($request, $error, $showRegister);
    }

    public function showRegistrationForm(Request $request, string $error = '', int $showRegister = 0): mixed
    {
        $inviteCode = $this->extractInviteCode($request);

        $emailFromInvite = '';
        $notice = '';
        $status = $this->registrationStatusService->resolve();

        if ($status['is_open']) {
            $showRegister = 1;

            if (! empty($inviteCode)) {
                $invitation = Invitation::findValidByToken($inviteCode);
                if ($invitation && ! empty($invitation->email)) {
                    $emailFromInvite = $invitation->email;
                } elseif (! $this->isInvitationTokenValid($inviteCode)) {
                    $notice = 'Registration is currently open, so you can register without a valid invitation.';
                }
            }

            if ($status['scheduled_override_active']) {
                $notice = $status['message'];
            }
        } elseif ($status['is_invite_only']) {
            if (! empty($inviteCode) && $this->isInvitationTokenValid($inviteCode)) {
                $showRegister = 1;

                $invitation = Invitation::findValidByToken($inviteCode);
                if ($invitation && ! empty($invitation->email)) {
                    $emailFromInvite = $invitation->email;
                }
            } elseif (! empty($inviteCode)) {
                $error = 'Invalid or expired invitation token!';
                $showRegister = 0;
            } else {
                $error = $status['message'];
                $showRegister = 0;
            }
        } else {
            $error = $status['message'];
            $showRegister = 0;
        }

        return view('auth.register')->with([
            'showregister' => $showRegister,
            'error' => $error,
            'notice' => $notice,
            'email' => $emailFromInvite,
            'invite_code_query' => $this->inviteCodeQuery,
            'invitecode' => $inviteCode,
            'registrationStatus' => $status,
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
        if (! empty($invitation->email) && ! $this->emailsMatch($invitation->email, $email)) {
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

    /**
     * @param  array<string, mixed>  $context
     */
    private function redirectBackWithRegistrationError(
        Request $request,
        string $message,
        string $reason = 'registration_failed',
        string $level = 'warning',
        array $context = []
    ): RedirectResponse {
        $this->logRegistrationFailure($request, $reason, $message, $level, $context);

        return redirect()
            ->back()
            ->withErrors(['registration' => $message])
            ->withInput($request->except('password', 'password_confirmation'));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logRegistrationFailure(
        Request $request,
        string $reason,
        string $message,
        string $level = 'warning',
        array $context = []
    ): void {
        $status = $this->registrationStatusService->resolve();

        $logContext = array_merge([
            'reason' => $reason,
            'message' => $message,
            'username' => $request->input('username'),
            'email' => $request->input('email'),
            'ip' => $request->ip(),
            'registration_status' => $status['effective_status'],
            'manual_registration_status' => $status['manual_status'],
            'registration_status_reason' => $status['reason'],
            'has_invite_code' => $request->filled('invitecode') || $request->filled('token') || $request->filled('invitation'),
        ], $context);

        Log::channel('registration')->{$level}("Registration attempt failed: {$reason}", $logContext);
    }

    /**
     * @return RedirectResponse|Redirector
     */
    private function rejectUnavailableRegistration(Request $request, array $status, string $inviteCode)
    {
        if ($status['is_closed']) {
            return $this->redirectBackWithRegistrationError(
                $request,
                $status['message'],
                'registrations_closed'
            );
        }

        if ($status['is_invite_only']) {
            if (! empty($inviteCode) && ! $this->isInvitationTokenValid($inviteCode)) {
                return $this->redirectBackWithRegistrationError(
                    $request,
                    'Invalid or expired invitation token!',
                    'invalid_or_expired_invitation'
                );
            }

            return $this->redirectBackWithRegistrationError(
                $request,
                $status['message'],
                'registrations_invite_only'
            );
        }

        return $this->redirectBackWithRegistrationError(
            $request,
            'Registrations are not currently available.',
            'registrations_unavailable'
        );
    }

    private function extractInviteCode(Request $request): string
    {
        $this->inviteCodeQuery = '';

        foreach (['invitecode', 'token', 'invitation'] as $parameter) {
            if ($request->filled($parameter)) {
                $inviteCode = (string) $request->input($parameter);
                $this->inviteCodeQuery = '&'.$parameter.'='.$inviteCode;

                return $inviteCode;
            }
        }

        return '';
    }

    private function emailsMatch(string $firstEmail, string $secondEmail): bool
    {
        return strcasecmp(trim($firstEmail), trim($secondEmail)) === 0;
    }
}
