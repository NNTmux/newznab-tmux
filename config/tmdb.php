<?php

/**
 * TMDB API Configuration
 *
 * This configuration is used by the custom TmdbClient service.
 */
return [
    /*
     * API key for The Movie Database (TMDB)
     * Get your key at: https://www.themoviedb.org/settings/api
     */
    'api_key' => env('TMDB_APIKEY', ''),

    /*
     * Request timeout in seconds
     */
    'timeout' => env('TMDB_TIMEOUT', 30),

    /*
     * Number of retry attempts for failed requests
     */
    'retry_times' => env('TMDB_RETRY_TIMES', 3),

    /*
     * Delay between retry attempts in milliseconds
     */
    'retry_delay' => env('TMDB_RETRY_DELAY', 100),
];
