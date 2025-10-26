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
        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => 'critical',
        ],
        'papertrail' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],
        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],
        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],
        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],
        'zipped' => [
            'driver' => 'daily',
            'path' => storage_path('logs/zipped.log'),
            'level' => 'debug',
            'days' => 7,
            'bubble' => true,
            'permission' => 0775,
            'locking' => false,
        ],
        'scrapers' => [
            'driver' => 'daily',
            'path' => storage_path('logs/scrapers.log'),
            'level' => 'debug',
            'days' => 7,
            'bubble' => true,
            'permission' => 0775,
            'locking' => false,
        ],
        'failed_login' => [
            'driver' => 'daily',
            'path' => storage_path('logs/failed_login.log'),
            'level' => 'debug',
            'days' => 7,
            'bubble' => true,
            'permission' => 0775,
            'locking' => false,
        ],
        'crc_oso' => [
            'driver' => 'daily',
            'path' => storage_path('logs/crc_oso.log'),
            'level' => 'debug',
            'days' => 7,
            'bubble' => true,
            'permission' => 0775,
            'locking' => false,
        ],
        'btc_payment' => [
            'driver' => 'daily',
            'path' => storage_path('logs/btc_payment.log'),
            'level' => 'debug',
            'days' => 7,
            'bubble' => true,
            'permission' => 0775,
            'locking' => false,
        ],
        'nzb_upload' => [
            'driver' => 'daily',
            'path' => storage_path('logs/nzb_upload.log'),
            'level' => 'debug',
            'days' => 7,
            'bubble' => true,
            'permission' => 0775,
            'locking' => false,
        ],
        'filename_rename' => [
            'driver' => 'daily',
            'path' => storage_path('logs/filename_rename.log'),
            'level' => 'debug',
            'days' => 7,
            'bubble' => true,
            'permission' => 0775,
            'locking' => false,
        ],
        'nzb_import' => [
            'driver' => 'daily',
            'path' => storage_path('logs/nzb_import.log'),
            'level' => 'debug',
            'days' => 7,
            'bubble' => true,
            'permission' => 0775,
            'locking' => false,
        ],

        'user_login' => [
            'driver' => 'daily',
            'path' => storage_path('logs/user_login.log'),
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
