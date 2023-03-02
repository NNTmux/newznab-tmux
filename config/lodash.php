<?php

declare(strict_types=1);

return [
    'debug' => [
        'ips' => explode(',', env('DEBUG_IP_LIST', '')), // IP list for enabling debug mode
    ],

    'cors' => [
        'allow_origins' => explode(',', env('CORS_ALLOW_ORIGINS', '')), // Allowed domains
        'allow_methods' => [ // Allowed request methods
            'HEAD',
            'GET',
            'POST',
            'OPTIONS',
            'PUT',
            'PATCH',
            'DELETE',
        ],
        'allow_headers' => [ // Allowed headers
            'Origin',
            'X-Requested-With',
            'Content-Type',
            'Accept',
            'Authorization',
        ],
    ],

    'xss' => [
        'exclude_uris' => [], // Excluded URI's for Xss middleware
        'x_frame_options' => 'DENY', // X-Frame-Options header value
        'x_content_type_options' => 'nosniff', // X-Content-Type-Options header value
        'x_xss_protection' => '1; mode=block', // X-XSS-Protection header value
    ],
];
