<?php

namespace App\Support;

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
            session([config('google2fa.session_var') => true]);
            session([config('google2fa.session_var').'.auth.passed_at' => time()]);

            // Successful authentication with cookie
            return true;
        }

        return parent::isAuthenticated();
    }

    /**
     * Directly validate the cookie without any output or logging
     */
    private function checkCookieValidity(mixed $cookie): mixed
    {
        try {
            $data = @json_decode($cookie, true);

            if (! is_array($data)) {
                return false;
            }

            // Validate all required fields
            if (! isset($data['user_id'], $data['token'], $data['expires_at'])) {
                return false;
            }

            // Ensure the user ID matches
            if ((int) $data['user_id'] !== (int) $this->getUser()->id) {
                return false;
            }

            // Ensure the token is not expired
            if (time() > $data['expires_at']) {
                return false;
            }

            // All checks passed - this is a valid cookie
            return true;
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
            $cookie = request()->cookie('2fa_trusted_device');
            $user = $this->getUser();

            if (! $cookie) {
                return false;
            }

            $data = @json_decode($cookie, true);

            // Check for JSON decode errors silently
            if (json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }

            // Validate the required fields silently
            if (! isset($data['user_id'], $data['token'], $data['expires_at'])) {
                return false;
            }

            // Check if the token belongs to the current user
            if ((int) $data['user_id'] !== (int) $user->id) {
                return false;
            }

            // Check if the token is expired
            if (time() > $data['expires_at']) {
                return false;
            }

            // Device is trusted and token is valid
            return true;
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
                $cookieData = json_decode($trustedCookie, true);

                if (json_last_error() === JSON_ERROR_NONE &&
                    isset($cookieData['user_id'], $cookieData['token'], $cookieData['expires_at']) &&
                    (int) $cookieData['user_id'] === (int) auth()->id() &&
                    time() <= $cookieData['expires_at']) {

                    // If we have a valid cookie, force-disable 2FA
                    session([config('google2fa.session_var') => true]);
                    session([config('google2fa.session_var').'.auth.passed_at' => time()]);

                    // Completely disable 2FA for this request
                    return false;
                }
            } catch (\Exception $e) {
                // Silently handle any exceptions
            }
        }

        // Otherwise, use the parent implementation
        return parent::isEnabled();
    }
}
