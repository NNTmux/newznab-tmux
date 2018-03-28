<?php

namespace App\Http\Controllers\Auth;

use App\Models\Invitation;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserRole;
use Blacklight\http\BasePage;
use Illuminate\Http\Request;
use Blacklight\utility\Utility;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;

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
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'username' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'g-recaptcha-response' => 'required|captcha'
        ]);
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
            'rsstoken' => md5(Password::getRepository()->createNewToken()),
            'userseed' => md5(Utility::generateUuid()),
        ]);
    }

    public function register(Request $request)
    {
        $userName = $password = $confirmPassword = $email = $inviteCode = $inviteCodeQuery = '';
        $showRegister = 1;

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
                    $confirmPassword = $request->input('confirmpassword');
                    $email = $request->input('email');
                    if (! empty($request->input('invitecode'))) {
                        $inviteCode = $request->input('invitecode');
                    }

                    // Check uname/email isn't in use, password valid. If all good create new user account and redirect back to home page.
                    if ($password !== $confirmPassword) {
                        session()->flash('status', 'Password Mismatch');
                    } else {
                        // Get the default user role.
                        $userDefault = UserRole::getDefaultRole();

                        if ((int) Settings::settingValue('..registerstatus') === Settings::REGISTER_STATUS_INVITE) {
                            if ($inviteCode === '') {
                                echo 'Sorry, the invite code is old or has been used.';
                                break;
                            }

                            $invitedBy = User::checkAndUseInvite($inviteCode);
                            if ($invitedBy < 0) {
                                echo 'Sorry, the invite code is old or has been used.';
                                break;
                            }
                        }

                        if (! User::isValidUsername($userName)) {
                            echo 'Your username must be at least five characters.';
                            break;
                        }

                        if (! User::isValidPassword($password)) {
                            echo 'Your password must be longer than eight characters, have at least 1 number, at least 1 capital and at least one lowercase letter';
                            break;
                        }

                        if (! User::isValidEmail($email)) {
                            echo 'Your email is not a valid format.';
                            break;
                        }

                        $res = User::getByUsername($userName);
                        if ($res) {
                            echo 'Sorry, the username is already taken.';
                            break;
                        }

                        $res = User::getByEmail($email);
                        if ($res) {
                            echo 'Sorry, the email is already in use.';
                            break;
                        }

                        $ret = $this->create(
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

                        if ($ret->id > 0) {
                            Auth::loginUsingId($ret->id);
                            return redirect()->intended($this->redirectPath());
                        }
                    }
                    break;
                case 'view': {
                    $inviteCode = $request->input('invitecode') ?? null;
                    if ($inviteCode !== null) {
                        // See if it is a valid invite.
                        $invite = Invitation::getInvite($inviteCode);
                        if (! $invite) {
                            echo sprintf('Bad or invite code older than %d days.', Invitation::DEFAULT_INVITE_EXPIRY_DAYS);
                            $showRegister = 0;
                        } else {
                            $inviteCode = $invite['guid'];
                        }
                    }
                    break;
                }
            }
        }
    }
}
