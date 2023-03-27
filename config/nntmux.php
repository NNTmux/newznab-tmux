<?php

return [
    'db_name' => env('DB_DATABASE', 'nntmux'),
    'items_per_page' => env('ITEMS_PER_PAGE', 50),
    'items_per_cover_page' => env('ITEMS_PER_COVER_PAGE', 20),
    'max_pager_results' => env('MAX_PAGER_RESULTS', 125000),
    'echocli' => env('ECHOCLI', true),
    'rename_par2' => env('RENAME_PAR2', true),
    'rename_music_mediainfo' => env('RENAME_MUSIC_MEDIAINFO', true),
    'cache_expiry_short' => env('CACHE_EXPIRY_SHORT', 5),
    'cache_expiry_medium' => env('CACHE_EXPIRY_MEDIUM', 10),
    'cache_expiry_long' => env('CACHE_EXPIRY_LONG', 15),
    'admin_username' => env('ADMIN_USER', 'admin'),
    'admin_password' => env('ADMIN_PASS', 'admin'),
    'admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),
    'multiprocessing_max_child_time' => env('NN_MULTIPROCESSING_MAX_CHILD_TIME', 1800),
    'purge_inactive_users' => env('PURGE_INACTIVE_USERS', false),
    'elasticsearch_enabled' => env('ELASTICSEARCH_ENABLED', false),
];
