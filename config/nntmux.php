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
    'crc_token' => env('CRC_TOKEN', null),
    'multiprocessing_max_child_time' => env('NN_MULTIPROCESSING_MAX_CHILD_TIME', 1800),
    'concurrency_timeout' => env('NN_CONCURRENCY_TIMEOUT'),
    'stream_fork_output' => env('STREAM_FORK_OUTPUT', false),
    'cbp' => [
        // Bound the amount of header data retained and written in one transaction.
        'header_chunk_size' => (int) env('CBP_HEADER_CHUNK_SIZE', 500),
        // Hard limit for rows represented by a generated raw SQL statement.
        'sql_chunk_size' => (int) env('CBP_SQL_CHUNK_SIZE', 500),
        // Number of stale collections reconciled by one release-processing batch.
        'reconcile_batch_size' => (int) env('CBP_RECONCILE_BATCH_SIZE', 500),
        // Maximum flattened binary/part rows loaded while streaming an NZB.
        'nzb_stream_rows' => (int) env('CBP_NZB_STREAM_ROWS', 5000),
        // Explicit maintenance-window approval for the destructive hash/key migration.
        'storage_migration_execute' => (bool) env('CBP_STORAGE_MIGRATION_EXECUTE', false),
    ],
    'purge_inactive_users' => env('PURGE_INACTIVE_USERS', false),
    'purge_inactive_users_days' => env('PURGE_INACTIVE_USERS_DAYS', 180),
    'mysql_search_fallback' => env('MYSQL_SEARCH_FALLBACK', false), // Disable MySQL LIKE fallback when Manticore/Elasticsearch return no results
    'api' => [
        'release_cache_ttl' => (int) env('API_RELEASE_CACHE_TTL', 600),
        'release_cache_jitter' => (int) env('API_RELEASE_CACHE_JITTER', 60),
        'release_cache_stale_ttl' => (int) env('API_RELEASE_CACHE_STALE_TTL', 900),
        'release_cache_lock_ttl' => (int) env('API_RELEASE_CACHE_LOCK_TTL', 15),
        'async_audit' => (bool) env('API_ASYNC_AUDIT', true),
        'audit_queue' => env('API_AUDIT_QUEUE', 'api-audit'),
        'access_update_interval' => (int) env('API_ACCESS_UPDATE_INTERVAL', 60),
        'metrics_sample_rate' => (float) env('API_METRICS_SAMPLE_RATE', 0.01),
    ],
    'block_proxy_indexer_apps' => (bool) env('BLOCK_PROXY_INDEXER_APPS', false),
    'block_proxy_indexer_app_user_agents' => env('BLOCK_PROXY_INDEXER_APP_USER_AGENTS', 'Prowlarr/,NZBHydra2'),

    // Behavioural detection of direct proxy fetches (NZBHydra2/Prowlarr) that spoof a downloader UA.
    // Combines Referer, UA-pair, download/search ratio, and IP-correlation signals into a score; blocks
    // only when the score meets the threshold, so legitimate redirected grabs are left untouched.
    'proxy_detection_enabled' => (bool) env('PROXY_DETECTION_ENABLED', false),
    'proxy_detection_threshold' => (int) env('PROXY_DETECTION_THRESHOLD', 50),
    'proxy_detection_window_seconds' => (int) env('PROXY_DETECTION_WINDOW_SECONDS', 3600),
    'proxy_detection_ratio_min' => (float) env('PROXY_DETECTION_RATIO_MIN', 0.8),
    'proxy_detection_min_searches' => (int) env('PROXY_DETECTION_MIN_SEARCHES', 20),
    // Only apps that fetch NZBs directly from the indexer belong here. NZBHydra2 and Prowlarr issue their
    // own download requests, so they can proxy an NZB fetch. The *arr suite (Sonarr, Radarr, Lidarr,
    // Readarr, Bazarr) only search — directly or via Prowlarr/NZBHydra2 — and never download from the
    // indexer, so listing them here would only risk false positives. Jackett is torrent-only (Torznab)
    // and cannot query a newznab/Usenet indexer, so it never fetches NZBs here either.
    'proxy_detection_indexer_referer_patterns' => env('PROXY_DETECTION_INDEXER_REFERER_PATTERNS', 'hydra,prowlarr'),

    /*
    |--------------------------------------------------------------------------
    | Release dedupe (import-time)
    |--------------------------------------------------------------------------
    |
    | Size tolerance for matching an existing release when deduping imports
    | (collections / NZB). Default 0.05 = ±5% on total bytes (par2/RAR drift).
    |
    */
    'release_dedupe_size_tolerance' => (float) env('RELEASE_DEDUPE_SIZE_TOLERANCE', 0.05),
    'btcpay_webhook_secret' => env('BTCPAY_SECRET'),
    'tmp_unrar_path' => env('TEMP_UNRAR_PATH', storage_path('tmp/unrar/')),
    'tmp_unzip_path' => env('TEMP_UNZIP_PATH', storage_path('tmp/unzip/')),
    'nzb_import_folder' => env('NZB_IMPORT_FOLDER'),
    'nzb_upload_folder' => env('NZB_UPLOAD_FOLDER'),
    'redis_fast_degrade' => (bool) env('REDIS_FAST_DEGRADE', true),
    'redis_tcp_check_seconds' => (float) env('REDIS_TCP_CHECK_SECONDS', 0.2),
    'categorization' => [
        'log' => (bool) env('NNTMUX_CATEGORIZATION_LOG', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Releases table normalization migration
    |--------------------------------------------------------------------------
    |
    | Knobs for the one-off releases rebuild. Skip the in-migration preflight
    | only after `php artisan releases:optimize-preflight` has passed, since the
    | checks are four full scans of the largest table. The free-space guard
    | aborts before a COPY-algorithm ALTER that would fill the data volume.
    |
    */
    'releases_optimize' => [
        'skip_preflight' => (bool) env('RELEASES_OPTIMIZE_SKIP_PREFLIGHT', false),
        'skip_free_space_check' => (bool) env('RELEASES_OPTIMIZE_SKIP_FREE_SPACE_CHECK', false),
        'chunk_size' => (int) env('RELEASES_OPTIMIZE_CHUNK_SIZE', 5000),
    ],
];
