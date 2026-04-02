<?php

return [
    'anidb_api_key' => env('ANIDB_APIKEY', ''),
    'fanarttv_api_key' => env('FANARTTV_APIKEY', ''),
    'imdbapi_dev_enabled' => env('IMDBAPI_DEV_ENABLED', true),
    'imdbapi_dev_base_url' => env('IMDBAPI_DEV_BASE_URL', 'https://api.imdbapi.dev'),
    'imdbapi_dev_min_interval_seconds' => env('IMDBAPI_DEV_MIN_INTERVAL_SECONDS', 15),
    'imdbapi_dev_cooldown_seconds' => env('IMDBAPI_DEV_COOLDOWN_SECONDS', 300),
    'omdb_api_key' => env('OMDB_APIKEY', ''),
    'trakttv_api_key' => env('TRAKTTV_APIKEY', ''),
];
