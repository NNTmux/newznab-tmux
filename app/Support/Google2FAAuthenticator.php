<?php

namespace App\Support;

use PragmaRX\Google2FALaravel\Exceptions\InvalidSecretKey;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class Google2FAAuthenticator extends Authenticator
{
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
     *
     * @return bool
     */
    protected function isDeviceTrusted(): bool
    {
        $cookie = request()->cookie('2fa_trusted_device');

        if (!$cookie) {
            return false;
        }

        try {
            $data = json_decode($cookie, true);

            // Check if cookie contains the required data
            if (!isset($data['user_id'], $data['token'], $data['expires_at'])) {
                return false;
            }

            // Check if the token belongs to the current user
            if ((int)$data['user_id'] !== (int)$this->getUser()->id) {
                return false;
            }

            // Check if the token is expired
            if (time() > $data['expires_at']) {
                return false;
            }

            // Device is trusted and token is valid
            return true;
        } catch (\Exception $e) {
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
}
