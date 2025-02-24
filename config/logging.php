<?php

return [

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'name' => 'NNTmux',
            'channels' => ['daily', 'flare'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'bubble' => true,
            'permission' => 0775,
            'locking' => false,
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'days' => 7,
            'bubble' => true,
            'permission' => 0775,
            'locking' => false,
            'replace_placeholders' => true,
        ],

        'flare' => [
            'driver' => 'flare',
        ],
    ],

];
