<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default search driver that will be used to
    | perform full-text search operations. Supported: "manticore", "elasticsearch"
    |
    */
    'default' => env('SEARCH_DRIVER', 'manticore'),

    /*
    |--------------------------------------------------------------------------
    | Search Driver Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure each search driver as needed.
    |
    */
    'drivers' => [

        'manticore' => [
            'driver' => 'manticore',
            'host' => env('MANTICORESEARCH_HOST', '127.0.0.1'),
            'port' => env('MANTICORESEARCH_PORT', 9308),
            'hosts' => env('MANTICORESEARCH_HOSTS', ''),
            'retries' => env('MANTICORESEARCH_RETRIES', 2),
            'indexes' => [
                'releases' => env('MANTICORESEARCH_INDEX_RELEASES', 'releases_rt'),
                'predb' => env('MANTICORESEARCH_INDEX_PREDB', 'predb_rt'),
                'movies' => env('MANTICORESEARCH_INDEX_MOVIES', 'movies_rt'),
                'tvshows' => env('MANTICORESEARCH_INDEX_TVSHOWS', 'tvshows_rt'),
            ],
            'max_matches' => env('MANTICORESEARCH_MAX_MATCHES', 10000),
            'cache_minutes' => env('MANTICORESEARCH_CACHE_MINUTES', 5),
            'autocomplete' => [
                'enabled' => env('MANTICORESEARCH_AUTOCOMPLETE_ENABLED', true),
                'min_length' => env('MANTICORESEARCH_AUTOCOMPLETE_MIN_LENGTH', 2),
                'max_results' => env('MANTICORESEARCH_AUTOCOMPLETE_MAX_RESULTS', 10),
                'cache_minutes' => env('MANTICORESEARCH_AUTOCOMPLETE_CACHE_MINUTES', 10),
            ],
            'suggest' => [
                'enabled' => env('MANTICORESEARCH_SUGGEST_ENABLED', true),
                'max_edits' => env('MANTICORESEARCH_SUGGEST_MAX_EDITS', 4),
            ],
            'fuzzy' => [
                'enabled' => env('MANTICORESEARCH_FUZZY_ENABLED', true),
                'max_distance' => env('MANTICORESEARCH_FUZZY_MAX_DISTANCE', 2),
                'layouts' => env('MANTICORESEARCH_FUZZY_LAYOUTS', 'us'),
            ],
        ],

        'elasticsearch' => [
            'driver' => 'elasticsearch',
            'hosts' => [
                [
                    'host' => env('ELASTICSEARCH_HOST', 'localhost'),
                    'port' => env('ELASTICSEARCH_PORT', 9200),
                    'scheme' => env('ELASTICSEARCH_SCHEME', null),
                    'user' => env('ELASTICSEARCH_USER', null),
                    'pass' => env('ELASTICSEARCH_PASS', null),
                ],
            ],
            'timeout' => env('ELASTICSEARCH_TIMEOUT', 10),
            'connect_timeout' => env('ELASTICSEARCH_CONNECT_TIMEOUT', 5),
            'retries' => env('ELASTICSEARCH_RETRIES', 2),
            'indexes' => [
                'releases' => 'releases',
                'predb' => 'predb',
                'movies' => 'movies',
                'tvshows' => 'tvshows',
            ],
            'cache_minutes' => env('ELASTICSEARCH_CACHE_MINUTES', 5),
            'autocomplete' => [
                'enabled' => env('ELASTICSEARCH_AUTOCOMPLETE_ENABLED', true),
                'min_length' => env('ELASTICSEARCH_AUTOCOMPLETE_MIN_LENGTH', 2),
                'max_results' => env('ELASTICSEARCH_AUTOCOMPLETE_MAX_RESULTS', 10),
                'cache_minutes' => env('ELASTICSEARCH_AUTOCOMPLETE_CACHE_MINUTES', 10),
            ],
            'suggest' => [
                'enabled' => env('ELASTICSEARCH_SUGGEST_ENABLED', true),
            ],
            'fuzzy' => [
                'enabled' => env('ELASTICSEARCH_FUZZY_ENABLED', true),
                'fuzziness' => env('ELASTICSEARCH_FUZZY_FUZZINESS', 'AUTO'),
                'prefix_length' => env('ELASTICSEARCH_FUZZY_PREFIX_LENGTH', 2),
                'max_expansions' => env('ELASTICSEARCH_FUZZY_MAX_EXPANSIONS', 50),
            ],
        ],

    ],

];
