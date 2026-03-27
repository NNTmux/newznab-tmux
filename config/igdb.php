<?php

return [
    'base_url' => env('IGDB_BASE_URL', 'https://api.igdb.com/v4'),

    'token_url' => env('TWITCH_TOKEN_URL', 'https://id.twitch.tv/oauth2/token'),

    /*
     * These are the credentials you got from https://dev.twitch.tv/console/apps
     */
    'credentials' => [
        'client_id' => env('TWITCH_CLIENT_ID', ''),
        'client_secret' => env('TWITCH_CLIENT_SECRET', ''),
    ],

    /*
     * The local IGDB client caches query responses for this many seconds.
     *
     * To turn cache off set this value to 0
     *
     * Recommended: 86400 (24 hours) for game data that rarely changes
     */
    'cache_lifetime' => (int) env('IGDB_CACHE_LIFETIME', 86400),
];
