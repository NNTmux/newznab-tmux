<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    /**
     * Verify a Google reCAPTCHA token.
     */
    public static function verify(string $token, ?string $remoteIp = null): bool
    {
        $secret = config('captcha.recaptcha.secret', config('captcha.secret'));

        if ($secret === null || $secret === '') {
            return false;
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $remoteIp ?? request()->ip(),
            ]);

            $result = $response->json();

            return isset($result['success']) && $result['success'] === true;
        } catch (\Throwable $throwable) {
            Log::error('reCAPTCHA verification failed: '.$throwable->getMessage());

            return false;
        }
    }

    /**
     * Render the Google reCAPTCHA widget.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function display(array $attributes = []): string
    {
        $sitekey = config('captcha.recaptcha.sitekey', config('captcha.sitekey'));

        if ($sitekey === null || $sitekey === '') {
            return '';
        }

        $defaultAttributes = [
            'class' => 'g-recaptcha',
            'data-sitekey' => $sitekey,
        ];

        $attributes = array_merge($defaultAttributes, $attributes);
        $attributesString = '';

        foreach ($attributes as $key => $value) {
            $attributesString .= sprintf('%s="%s" ', $key, htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));
        }

        return sprintf('<div %s></div>', trim($attributesString));
    }

    /**
     * Render the Google reCAPTCHA JavaScript include.
     */
    public static function renderJs(): string
    {
        return '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
    }
}
