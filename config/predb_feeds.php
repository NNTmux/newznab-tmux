<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PreDB Feed Importer
    |--------------------------------------------------------------------------
    |
    | Polls public PreDB JSON APIs / RSS feeds and upserts entries into the
    | predb table (same semantics as the IRC scraper). Scheduled every five
    | minutes when enabled; run manually with `php artisan predb:import-feed`.
    |
    */

    'enabled' => (bool) env('PREDB_FEEDS_ENABLED', false),

    'sources' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PREDB_FEED_SOURCES', 'predb_club,predb_net,predb_me,predatabase,xrel,xrel_p2p'))
    ))),

    'page_size' => (int) env('PREDB_FEED_PAGE_SIZE', 100),

    'timeout' => (int) env('PREDB_FEED_TIMEOUT', 15),

    'user_agent' => (string) env('PREDB_FEED_USER_AGENT', 'NNTmux-PreDB-Importer/1.0'),

    'endpoints' => [
        'predb_club' => 'https://predb.club/api/v1/',
        'predb_net' => 'https://api.predb.net/',
        'predb_me' => (string) env('PREDB_FEED_RSS_URL', 'https://predb.me/?rss=1'),
        'predb_org' => 'https://predb.org/api/search',
        'predatabase' => 'https://predataba.se/api/search',
        'srrdb' => 'https://api.srrdb.com/v1/search/order:date-desc',
        'xrel' => 'https://api.xrel.to/v2/release/latest.json',
        'xrel_p2p' => 'https://api.xrel.to/v2/p2p/releases.json',
    ],

    // Sources whose terms forbid scraping: never paged beyond the first page (srrDB: "Use but don't
    // scrape"). srrDB is also left out of the default source list; add it explicitly to opt in.
    'single_page_sources' => ['srrdb'],

    // predataba.se: anonymous clients read 50 entries (5 requests) per run; a key lifts the depth limit.
    'predatabase_api_key' => (string) env('PREDB_FEED_PREDATABASE_API_KEY', ''),

    // srrDB reports release dates in its own local time.
    'srrdb_timezone' => (string) env('PREDB_FEED_SRRDB_TIMEZONE', 'Europe/Brussels'),

    // Pause between consecutive pages of one source (history imports page deeply).
    'request_delay_ms' => (int) env('PREDB_FEED_REQUEST_DELAY_MS', 1000),

    // On HTTP 429: wait (Retry-After when sent, else this many seconds) and retry the page.
    'rate_limit_wait_seconds' => (int) env('PREDB_FEED_RATE_LIMIT_WAIT', 60),
    'rate_limit_retries' => (int) env('PREDB_FEED_RATE_LIMIT_RETRIES', 5),
    // If the server asks to wait longer than this, the source fails for this run instead of blocking.
    'rate_limit_max_wait_seconds' => (int) env('PREDB_FEED_RATE_LIMIT_MAX_WAIT', 900),

    // Safety cap on pages per source for --since history imports.
    'max_pages' => (int) env('PREDB_FEED_MAX_PAGES', 2000),
];
