<?php

namespace App\Support;

use App\Services\TurnstileService;
use Illuminate\Support\Facades\Log;

class CaptchaHelper
{
    /**
     * Check if captcha is enabled
     */
    public static function isEnabled(): bool
    {
        $provider = config('captcha.provider', 'recaptcha');

        // Enforce that both providers cannot be enabled at the same time
        $recaptchaEnabled = config('captcha.recaptcha.enabled') === true
            && ! empty(config('captcha.recaptcha.sitekey'))
            && ! empty(config('captcha.recaptcha.secret'));

        $turnstileEnabled = config('captcha.turnstile.enabled') === true
            && ! empty(config('captcha.turnstile.sitekey'))
            && ! empty(config('captcha.turnstile.secret'));

        // If both are configured as enabled, log a warning and use the configured provider
        if ($recaptchaEnabled && $turnstileEnabled) {
            Log::warning('Both reCAPTCHA and Turnstile are enabled. Only one can be active at a time. Using provider: '.$provider);
        }

        if ($provider === 'turnstile') {
            return $turnstileEnabled;
        }

        // Default to reCAPTCHA
        return $recaptchaEnabled || config('captcha.enabled') === true
            && ! empty(config('captcha.sitekey'))
            && ! empty(config('captcha.secret'));
    }

    /**
     * Get the current captcha provider
     */
    public static function getProvider(): string
    {
        return config('captcha.provider', 'recaptcha');
    }

    /**
     * Display the captcha widget
     */
    public static function display(array $attributes = []): string
    {
        if (! self::isEnabled()) {
            return '';
        }

        $provider = self::getProvider();

        if ($provider === 'turnstile') {
            return TurnstileService::display($attributes);
        }

        // Default to reCAPTCHA
        return app('captcha')->display($attributes);
    }

    /**
     * Render the captcha JavaScript
     */
    public static function renderJs(): string
    {
        if (! self::isEnabled()) {
            return '';
        }

        $provider = self::getProvider();

        if ($provider === 'turnstile') {
            return TurnstileService::renderJs();
        }

        // Default to reCAPTCHA
        return app('captcha')->renderJs();
    }

    /**
     * Get the captcha response field name
     */
    public static function getResponseFieldName(): string
    {
        $provider = self::getProvider();

        if ($provider === 'turnstile') {
            return 'cf-turnstile-response';
        }

        return 'g-recaptcha-response';
    }

    /**
     * Get validation rules for captcha
     */
    public static function getValidationRules(): array
    {
        if (! self::isEnabled()) {
            return [];
        }

        $provider = self::getProvider();
        $fieldName = self::getResponseFieldName();

        if ($provider === 'turnstile') {
            return [
                $fieldName => [
                    'required',
                    new \App\Rules\TurnstileRule,
                ],
            ];
        }

        // Default to reCAPTCHA
        return [
            $fieldName => [
                'required',
                'captcha',
            ],
        ];
    }
}
