<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Manually Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of proxies that should always be trusted in addition
    | to the Cloudflare ranges fetched by the cloudflare:reload command.
    |
    */
    'proxies' => env('TRUSTED_PROXIES'),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Trusted Proxy Settings
    |--------------------------------------------------------------------------
    */
    'cloudflare' => [
        'enabled' => env('TRUST_CLOUDFLARE', true),
        'ipv4_url' => env('CLOUDFLARE_IPS_V4_URL', 'https://www.cloudflare.com/ips-v4'),
        'ipv6_url' => env('CLOUDFLARE_IPS_V6_URL', 'https://www.cloudflare.com/ips-v6'),
        'timeout' => env('CLOUDFLARE_IPS_TIMEOUT', 10),
        'connect_timeout' => env('CLOUDFLARE_IPS_CONNECT_TIMEOUT', 5),
        'retry_times' => env('CLOUDFLARE_IPS_RETRY_TIMES', 2),
        'retry_sleep_ms' => env('CLOUDFLARE_IPS_RETRY_SLEEP_MS', 250),
        'storage_path' => env('CLOUDFLARE_IPS_STORAGE_PATH', storage_path('app/cloudflare/trusted-proxies.json')),
        'fallback_to_remote_addr' => env('CLOUDFLARE_TRUST_REMOTE_ADDR_FALLBACK', true),
    ],
];
