<?php

return [
    'host' => env('MANTICORESEARCH_HOST', '127.0.0.1'),
    'port' => env('MANTICORESEARCH_PORT', 9308),
    'indexes' => [
        'releases' => 'releases_rt',
        'predb' => 'predb_rt',
    ],
];
