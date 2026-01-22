<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    /**
     * Verify Cloudflare Turnstile token
     */
    public static function verify(string $token, ?string $remoteIp = null): bool
    {
        $secret = config('captcha.turnstile.secret');

        if (empty($secret)) {
            return false;
        }

        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $remoteIp ?? request()->ip(),
            ]);

            $result = $response->json();

            return isset($result['success']) && $result['success'] === true;
        } catch (\Exception $e) {
            Log::error('Turnstile verification failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get the Turnstile HTML widget
     */
    public static function display(array $attributes = []): string
    {
        $sitekey = config('captcha.turnstile.sitekey');

        if (empty($sitekey)) {
            return '';
        }

        $defaultAttributes = [
            'class' => 'cf-turnstile',
            'data-sitekey' => $sitekey,
            'data-theme' => 'auto',
            'data-size' => 'normal',
        ];

        $attributes = array_merge($defaultAttributes, $attributes);

        $attributesString = '';
        foreach ($attributes as $key => $value) {
            $attributesString .= sprintf('%s="%s" ', $key, htmlspecialchars($value));
        }

        return sprintf('<div %s></div>', trim($attributesString));
    }

    /**
     * Get the Turnstile JavaScript
     */
    public static function renderJs(): string
    {
        return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }
}
