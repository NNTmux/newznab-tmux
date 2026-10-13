<?php

return [
    /*
     * Api key
     */
    'api_key' => env('TVDB_APIKEY', ''),
    'user_pin' => env('TVDB_PIN', null),
    'timeout' => env('TVDB_TIMEOUT', 30),
];
