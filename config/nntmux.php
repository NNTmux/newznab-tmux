<?php

return [
    'db_name' => env('DB_DATABASE', 'nntmux'),
    'items_per_page' => env('ITEMS_PER_PAGE', 50),
    'items_per_cover_page' => env('ITEMS_PER_COVER_PAGE', 20),
    'max_pager_results' => env('MAX_PAGER_RESULTS', 125000),
    'echocli' => env('ECHOCLI', true),
    'rename_par2' => env('RENAME_PAR2', true),
    'rename_music_mediainfo' => env('RENAME_MUSIC_MEDIAINFO', true),
    'cache_expiry_short' => (int) env('CACHE_EXPIRY_SHORT', 5),
    'cache_expiry_medium' => (int) env('CACHE_EXPIRY_MEDIUM', 10),
    'cache_expiry_long' => (int) env('CACHE_EXPIRY_LONG', 15),
    'admin_username' => env('ADMIN_USER', 'admin'),
    'admin_password' => env('ADMIN_PASS', 'admin'),
    'admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),
    'multiprocessing_max_child_time' => env('NN_MULTIPROCESSING_MAX_CHILD_TIME', 1800),
    'purge_inactive_users' => env('PURGE_INACTIVE_USERS', false),
    'purge_inactive_users_days' => env('PURGE_INACTIVE_USERS_DAYS', 180),
    'elasticsearch_enabled' => env('ELASTICSEARCH_ENABLED', false),
    'btcpay_webhook_secret' => env('BTCPAY_SECRET'),
    'tmp_unrar_path' => env('TEMP_UNRAR_PATH', storage_path('tmp/unrar/')),
    'tmp_unzip_path' => env('TEMP_UNZIP_PATH', storage_path('tmp/unzip/')),
];
