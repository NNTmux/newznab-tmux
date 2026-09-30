<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TrustedDevice;
use PragmaRX\Google2FALaravel\Exceptions\InvalidSecretKey;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class Google2FAAuthenticator extends Authenticator
{
    /**
     * Check if the user is authenticated for 2FA
     */
    public function isAuthenticated()
    {
        // First check - directly check for the cookie before any other logic
        $cookie = request()->cookie('2fa_trusted_device');

        if ($cookie && $this->checkCookieValidity($cookie)) {
            // Force the session to be marked as 2FA authenticated
            $this->loginFromTrustedDevice();

            // Successful authentication with cookie
            return true;
        }

        if ($this->getRequest()->session()->pull('2fa:trusted_device', false)) {
            $this->logout();
        }

        return parent::isAuthenticated();
    }

    public function loginFromTrustedDevice(): void
    {
        $this->login();
        $this->getRequest()->session()->put('2fa:trusted_device', true);
    }

    /**
     * Directly validate the cookie without any output or logging
     */
    private function checkCookieValidity(mixed $cookie): bool
    {
        try {
            return $this->trustedDeviceCookieIsValid($cookie);
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function canPassWithoutCheckingOTP(): bool
    {
        if (! $this->getUser()->passwordSecurity) {
            return true;
        }

        return
            ! $this->getUser()->passwordSecurity->google2fa_enable ||
            ! $this->isEnabled() ||
            $this->noUserIsAuthenticated() ||
            $this->twoFactorAuthStillValid() ||
            $this->isDeviceTrusted();
    }

    /**
     * Check if current device is trusted
     */
    protected function isDeviceTrusted(): bool
    {
        try {
            return $this->trustedDeviceCookieIsValid(request()->cookie('2fa_trusted_device'));
        } catch (\Exception $e) {
            // Silently handle any exceptions
            return false;
        }
    }

    /**
     * @return mixed
     *
     * @throws InvalidSecretKey
     */
    protected function getGoogle2FASecretKey()
    {
        $secret = $this->getUser()->passwordSecurity->{$this->config('otp_secret_column')};

        if (empty($secret)) {
            throw new InvalidSecretKey('Secret key cannot be empty.');
        }

        return $secret;
    }

    /**
     * Override the parent isEnabled method to force-disable 2FA when a trusted device is detected
     */
    public function isEnabled()
    {
        // Check for trusted device cookie
        $trustedCookie = request()->cookie('2fa_trusted_device');

        if ($trustedCookie && auth()->check()) {
            try {
                if ($this->trustedDeviceCookieIsValid($trustedCookie)) {
                    $this->loginFromTrustedDevice();

                    return false;
                }
            } catch (\Exception $e) {
                // Silently handle any exceptions
            }
        }

        // Otherwise, use the parent implementation
        return parent::isEnabled();
    }

    private function trustedDeviceCookieIsValid(mixed $cookie): bool
    {
        return TrustedDevice::cookieIsValidForUser($cookie, (int) $this->getUser()->id);
    }
}
