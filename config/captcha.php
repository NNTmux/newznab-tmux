<?php

return [
    'secret' => env('NOCAPTCHA_SECRET', ''),
    'sitekey' => env('NOCAPTCHA_SITEKEY', ''),
    'enabled' => env('NOCAPTCHA_ENABLED', false),
    'options' => [
        'timeout' => 30,
    ],
];
