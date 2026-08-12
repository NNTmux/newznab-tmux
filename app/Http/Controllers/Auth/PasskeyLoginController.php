<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Events\UserLoggedIn;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\WebLoginSessionPolicy;
use App\Support\CaptchaHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;
use Spatie\LaravelPasskeys\Http\Requests\AuthenticateUsingPasskeysRequest;
use Spatie\LaravelPasskeys\Support\Config;

final class PasskeyLoginController extends Controller
{
    public function __construct(private readonly WebLoginSessionPolicy $webLoginSessionPolicy) {}

    public function __invoke(AuthenticateUsingPasskeysRequest $request): RedirectResponse
    {
        $captchaRules = CaptchaHelper::getValidationRules();
        $captchaField = CaptchaHelper::getResponseFieldName();
        $captchaValue = $request->input($captchaField);

        // Passkey login should not be blocked when captcha widgets rotate/expire
        // during WebAuthn prompts. If a captcha token is present, still validate it.
        if ($captchaRules !== [] && is_string($captchaValue) && trim($captchaValue) !== '') {
            $validator = Validator::make($request->all(), $captchaRules);
            if ($validator->fails()) {
                Log::channel('failed_login')->error(
                    'Failed passkey login captcha check from IP address: '.$request->ip()
                );

                session()->flash('authenticatePasskey::message', $validator->errors()->first());
                session()->flash('authenticatePasskey::reason', 'captcha');

                return back();
            }
        }

        $passkeyOptionsJson = Session::pull('passkey-authentication-options');

        if (! is_string($passkeyOptionsJson) || $passkeyOptionsJson === '') {
            Log::channel('failed_login')->warning(
                'Passkey login submitted without passkey-authentication-options in session from IP address: '.$request->ip()
            );

            session()->flash(
                'authenticatePasskey::message',
                'Your passkey sign-in session expired or was interrupted. Please try signing in with a passkey again.'
            );
            session()->flash('authenticatePasskey::reason', 'missing_auth_options');

            return back();
        }

        $findAuthenticatableUsingPasskey = Config::getAction(
            'find_passkey',
            FindPasskeyToAuthenticateAction::class
        );

        $passkey = $findAuthenticatableUsingPasskey->execute(
            $request->input('start_authentication_response'),
            $passkeyOptionsJson,
        );

        if (! $passkey || ! $passkey->authenticatable instanceof User) {
            Log::channel('failed_login')->error(
                'Failed passkey login attempt from IP address: '.$request->ip()
            );

            session()->flash('authenticatePasskey::message', __('passkeys::passkeys.invalid'));
            session()->flash('authenticatePasskey::reason', 'invalid_passkey');

            return back();
        }

        $user = $passkey->authenticatable;

        if ($user->trashed()) {
            Log::channel('failed_login')->error(
                'Failed passkey login for deactivated user: '.$user->username.' from IP address: '.$request->ip()
            );

            session()->flash(
                'authenticatePasskey::message',
                'This account has been deactivated. Please contact us through contact form to have your account reactivated.'
            );

            return back();
        }

        if (! $user->hasVerifiedEmail()) {
            Log::channel('failed_login')->error(
                'Failed passkey login for unverified user: '.$user->username.' from IP address: '.$request->ip()
            );

            session()->flash('authenticatePasskey::message', 'You have not verified your email address!');

            return back();
        }

        $this->webLoginSessionPolicy->loginWithoutPassword($request, $user, remembered: true);

        // Passkey auth is treated as sufficient MFA, so skip additional OTP gate.
        session([config('google2fa.session_var') => true]);
        session([config('google2fa.session_var').'.auth.passed_at' => time()]);

        $userIp = config('nntmux:settings.store_user_ip') ? ($request->ip() ?? $request->getClientIp()) : '';
        event(new UserLoggedIn($user, $userIp));
        event(new PasskeyUsedToAuthenticateEvent($passkey, $request));

        $url = Session::has('passkeys.redirect')
            ? (string) Session::pull('passkeys.redirect')
            : (string) config('passkeys.redirect_to_after_login', '/');

        return redirect($url)->with('info', 'You have been logged in');
    }
}
