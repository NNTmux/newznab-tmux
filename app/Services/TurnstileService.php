<?php

declare(strict_types=1);

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
            Log::error('Turnstile verification failed: missing secret.', self::logContext());

            return false;
        }

        try {
            $response = Http::asForm()->connectTimeout(5)->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $remoteIp ?? request()->ip(),
            ]);

            $result = $response->json();

            if ($response->successful() && is_array($result) && ($result['success'] ?? null) === true) {
                return true;
            }

            $errorCodes = is_array($result) ? ($result['error-codes'] ?? []) : [];

            Log::warning('Turnstile verification failed.', self::logContext() + [
                'http_status' => $response->status(),
                'error_codes' => is_array($errorCodes) ? array_values(array_filter($errorCodes, 'is_string')) : [],
                'reason' => ! $response->successful() ? 'http-error' : (is_array($result) ? 'rejected' : 'invalid-response'),
                'siteverify_ray' => $response->header('cf-ray'),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Turnstile verification request failed.', self::logContext() + [
                'exception_class' => $e::class,
            ]);

            return false;
        }
    }

    /**
     * Keep credentials, CAPTCHA tokens, and request bodies out of diagnostics.
     *
     * @return array<string, mixed>
     */
    private static function logContext(): array
    {
        return [
            'route' => request()->route()?->getName(),
            'cf_ray' => request()->header('CF-Ray'),
            'ip' => config('nntmux_settings.store_user_ip') ? request()->ip() : null,
        ];
    }

    /**
     * Get the Turnstile HTML widget
     *
     * @param  array<string, mixed>  $attributes
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
