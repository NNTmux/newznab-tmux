<?php

return [
    'items_per_page' => env('ITEMS_PER_PAGE',50),
    'items_per_cover_page' => env('ITEMS_PER_COVER_PAGE',20),
    'max_pager_results' => env('MAX_PAGER_RESULTS', 125000),
    'flood_check' => env('FLOOD_CHECK', false),
    'flood_wait_time' => env('FLOOD_WAIT_TIME', 5),
    'flood_max_requests_per_second' => env('FLOOD_MAX_REQUESTS_PER_SECOND', 5),
    'echocli' => env('ECHOCLI', true),
    'rename_par2' => env('RENAME_PAR2', true),
    'rename_music_mediainfo' => env('RENAME_MUSIC_MEDIAINFO', true),
    'cache_expiry_short' => env('CACHE_EXPIRY_SHORT', 300),
    'cache_expiry_medium' =>env('CACHE_EXPIRY_MEDIUM', 600),
    'cache_expiry_long' => env('CACHE_EXPIRY_LONG', 900),
];
