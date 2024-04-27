<?php

return [

    'guards' => [
        'api' => [
            'driver' => 'token',
            'provider' => 'users',
            'hash' => false,
        ],

        'rss' => [
            'driver' => 'token',
            'provider' => 'users',
        ],
    ],

];
