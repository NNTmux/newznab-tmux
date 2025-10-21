<?php

return [
    // Captcha provider: 'recaptcha' or 'turnstile'
    'provider' => env('CAPTCHA_PROVIDER', 'recaptcha'),

    // Google reCAPTCHA settings
    'recaptcha' => [
        'secret' => env('NOCAPTCHA_SECRET', ''),
        'sitekey' => env('NOCAPTCHA_SITEKEY', ''),
        'enabled' => env('NOCAPTCHA_ENABLED', false),
    ],

    // Cloudflare Turnstile settings
    'turnstile' => [
        'secret' => env('TURNSTILE_SECRET', ''),
        'sitekey' => env('TURNSTILE_SITEKEY', ''),
        'enabled' => env('TURNSTILE_ENABLED', false),
    ],

    // Legacy support - keep for backward compatibility
    'secret' => env('NOCAPTCHA_SECRET', ''),
    'sitekey' => env('NOCAPTCHA_SITEKEY', ''),
    'enabled' => env('NOCAPTCHA_ENABLED', false),
    'options' => [
        'timeout' => 30,
    ],
];
