<?php

return [
    'host' => env('SPHINX_HOST', '127.0.0.1'),
    'port' => env('SPHINX_PORT', 9306),
    'indexes' => [
        'releases' => 'releases_rt',
        'predb' => 'predb_rt',
    ],
];
