<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class WebLoginSessionPolicy
{
    public const SESSION_TOKEN_KEY = 'session_token_web';

    public const REMEMBERED_LOGIN_KEY = 'remembered_login';

    private const ADMIN_EXPIRY_PREFIX = 'a.';

    private const CONCURRENT_SESSION_PREFIX = 'c.';

    private const SINGLE_SESSION_PREFIX = 's.';

    public function completePasswordLogin(
        Request $request,
        User $user,
        bool $remembered,
        string $password,
    ): void {
        if ($this->singleActiveSessionEnabled()) {
            Auth::logoutOtherDevices($password);
        }

        $this->completeAuthenticatedSession($request, $user, $remembered, dispatchOtherDeviceLogout: false);
    }

    public function loginWithoutPassword(Request $request, User $user, bool $remembered): void
    {
        if ($this->singleActiveSessionEnabled()) {
            $user->setRememberToken(Str::random(60));
            $user->save();
        }

        Auth::login($user, $remembered);
        $request->session()->regenerate();

        $this->completeAuthenticatedSession($request, $user, $remembered, dispatchOtherDeviceLogout: true);
    }

    public function singleActiveSessionEnabled(): bool
    {
        return (bool) Settings::settingValue('single_active_session');
    }

    public function isAdminExpiryToken(string $token): bool
    {
        return str_starts_with($token, self::ADMIN_EXPIRY_PREFIX);
    }

    public function newAdminExpiryToken(): string
    {
        return $this->newToken(self::ADMIN_EXPIRY_PREFIX);
    }

    private function completeAuthenticatedSession(
        Request $request,
        User $user,
        bool $remembered,
        bool $dispatchOtherDeviceLogout,
    ): void {
        if ($this->singleActiveSessionEnabled()) {
            $token = $this->newToken(self::SINGLE_SESSION_PREFIX);
            $user->forceFill(['session_token' => $token])->save();

            if ($dispatchOtherDeviceLogout) {
                event(new OtherDeviceLogout(Auth::getDefaultDriver(), $user));
            }
        } else {
            $token = (string) ($user->session_token ?? '');

            if ($token === '') {
                $token = $this->newToken(self::CONCURRENT_SESSION_PREFIX);
                $user->forceFill(['session_token' => $token])->save();
            }
        }

        $request->session()->put(self::SESSION_TOKEN_KEY, $token);
        $request->session()->put(self::REMEMBERED_LOGIN_KEY, $remembered);
    }

    private function newToken(string $prefix): string
    {
        return $prefix.Str::random(60 - strlen($prefix));
    }
}
