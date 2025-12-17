<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ManticoreSearch Connection
    |--------------------------------------------------------------------------
    |
    | Configure the connection to your ManticoreSearch server.
    |
    */
    'host' => env('MANTICORESEARCH_HOST', '127.0.0.1'),
    'port' => env('MANTICORESEARCH_PORT', 9308),

    /*
    |--------------------------------------------------------------------------
    | Index Names
    |--------------------------------------------------------------------------
    |
    | Define the names of the realtime indexes used for full-text search.
    |
    */
    'indexes' => [
        'releases' => env('MANTICORESEARCH_INDEX_RELEASES', 'releases_rt'),
        'predb' => env('MANTICORESEARCH_INDEX_PREDB', 'predb_rt'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Settings
    |--------------------------------------------------------------------------
    |
    | Configure search behavior and limits.
    |
    */
    'max_matches' => env('MANTICORESEARCH_MAX_MATCHES', 10000),
    'cache_minutes' => env('MANTICORESEARCH_CACHE_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Batch Processing
    |--------------------------------------------------------------------------
    |
    | Configure batch insert/update operations for better performance.
    |
    */
    'batch_size' => env('MANTICORESEARCH_BATCH_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Connection Retry Settings
    |--------------------------------------------------------------------------
    |
    | Configure retry behavior for transient connection failures.
    |
    */
    'retry_attempts' => env('MANTICORESEARCH_RETRY_ATTEMPTS', 3),
    'retry_delay_ms' => env('MANTICORESEARCH_RETRY_DELAY_MS', 100),
];
