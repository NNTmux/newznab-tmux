<?php

return [
    /*
     * Api key
     */
    'api_key' => env('TVDB_APIKEY', ''),
    'user_pin' => env('TVDB_PIN', null),
    'timeout' => env('TVDB_TIMEOUT', 30),
    'retry_times' => env('TVDB_RETRY_TIMES', 2),
];
