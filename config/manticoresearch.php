<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ManticoreSearch Connection
    |--------------------------------------------------------------------------
    |
    | Configure the connection to your ManticoreSearch server.
    | For high availability, you can configure multiple hosts.
    |
    */
    'host' => env('MANTICORESEARCH_HOST', '127.0.0.1'),
    'port' => env('MANTICORESEARCH_PORT', 9308),

    /*
    |--------------------------------------------------------------------------
    | Multiple Connections (High Availability)
    |--------------------------------------------------------------------------
    |
    | Configure multiple ManticoreSearch nodes for failover.
    | Set MANTICORESEARCH_HOSTS as comma-separated list: "host1:9308,host2:9308"
    |
    */
    'hosts' => env('MANTICORESEARCH_HOSTS', ''),
    'retries' => env('MANTICORESEARCH_RETRIES', 2),

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
        'movies' => env('MANTICORESEARCH_INDEX_MOVIES', 'movies_rt'),
        'tvshows' => env('MANTICORESEARCH_INDEX_TVSHOWS', 'tvshows_rt'),
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
    | Autocomplete Settings
    |--------------------------------------------------------------------------
    |
    | Configure autocomplete/suggest behavior.
    |
    */
    'autocomplete' => [
        'enabled' => env('MANTICORESEARCH_AUTOCOMPLETE_ENABLED', true),
        'min_length' => (int) env('MANTICORESEARCH_AUTOCOMPLETE_MIN_LENGTH', 2),
        'max_results' => (int) env('MANTICORESEARCH_AUTOCOMPLETE_MAX_RESULTS', 10),
        'fuzziness' => (int) env('MANTICORESEARCH_AUTOCOMPLETE_FUZZINESS', 1),
        'cache_minutes' => (int) env('MANTICORESEARCH_AUTOCOMPLETE_CACHE', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Suggest/Spell Correction Settings
    |--------------------------------------------------------------------------
    |
    | Configure "Did you mean?" spell correction.
    |
    */
    'suggest' => [
        'enabled' => env('MANTICORESEARCH_SUGGEST_ENABLED', true),
        'max_edits' => (int) env('MANTICORESEARCH_SUGGEST_MAX_EDITS', 4),
    ],

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
