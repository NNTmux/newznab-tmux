<?php

use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

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
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'days' => 7,
            'bubble' => true,
            'permission' => 0775,
            'locking' => false,
        ],

        'flare' => [
            'driver' => 'flare',
        ],
    ],

];
