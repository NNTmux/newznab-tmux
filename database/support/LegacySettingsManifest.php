<?php

declare(strict_types=1);

namespace Database\Support;

final class LegacySettingsManifest
{
    /** Every key shipped by the final generic settings seeder. */
    public const array LEGACY_SEEDED_KEYS = [
        'addpar2', 'alternate_nntp', 'backfillthreads', 'binarythreads', 'checkpasswordedrar',
        'completionpercent', 'crossposttime', 'currentppticket', 'debuginfo', 'delaytime',
        'disablebackfillgroup', 'ffmpeg_duration', 'ffmpeg_image_time', 'fixnamesperrun',
        'fixnamethreads', 'grabstatus', 'lastpretime', 'lookupanidb', 'lookupbooks', 'lookupgames',
        'lookupimdb', 'lookupmusic', 'lookupnfo', 'lookuptv', 'lookupxxx', 'max_headers_iteration',
        'maxaddprocessed', 'maxanidbprocessed', 'maxbooksprocessed', 'maxgamesprocessed',
        'maximdbprocessed', 'maxmssgs', 'maxmusicprocessed', 'maxnestedlevels', 'maxnfoprocessed',
        'maxnforetries', 'maxnzbsprocessed', 'maxpartrepair', 'maxpartsprocessed', 'maxrageprocessed',
        'maxsizetopostprocess', 'maxsizetoprocessnfo', 'maxxxxprocessed', 'minsizetopostprocess',
        'minsizetoprocessnfo', 'mischashedretentionhours', 'miscotherretentionhours',
        'newgroupdaystoscan', 'newgroupmsgstoscan', 'newgroupscanmethod', 'nextppticket',
        'nfothreads', 'nntpretries', 'nzbpath', 'nzbsplitlevel', 'nzbthreads', 'partrepair',
        'partrepairmaxtries', 'partretentionhours', 'passchkattempts', 'postdelay', 'postthreads',
        'postthreadsamazon', 'postthreadsnon', 'processjpg', 'processthumbnails', 'processvideos',
        'registerstatus', 'releaseretentiondays', 'releasethreads', 'safebackfilldate',
        'safepartrepair', 'saveaudiopreview', 'segmentstodownload', 'showdroppedyencparts',
        'showpasswordedrelease', 'timeoutseconds', 'userhostexclusion', 'maxsizetoformrelease',
        'minfilestoformrelease', 'minsizetoformrelease', 'banned', 'end', 'categorizeforeign',
        'catwebdl', 'innerfileblacklist', 'collection_timeout', 'last_run_time', 'code',
        'dereferrer_link', 'footer', 'home_link', 'site_logo', 'metadescription', 'metakeywords',
        'metatitle', 'strapline', 'tandc', 'back_timer', 'backfill', 'backfill_days',
        'backfill_groups', 'backfill_order', 'backfill_qty', 'binaries', 'bins_kill_timer',
        'bins_timer', 'bwmng', 'collections_kill', 'colors', 'colors_end', 'colors_exc',
        'colors_start', 'console', 'crap_timer', 'fix_crap', 'fix_crap_opt', 'fix_names',
        'fix_timer', 'htop', 'monitor_delay', 'monitor_path', 'monitor_path_a', 'monitor_path_b',
        'mytop', 'nfos', 'niceness', 'nmon', 'post', 'post_amazon', 'post_kill_timer',
        'post_non', 'post_timer', 'post_timer_amazon', 'post_timer_non', 'postprocess_kill',
        'processupdate', 'progressive', 'redis', 'redis_args', 'rel_timer', 'releases',
        'run_ircscraper', 'running', 'seq_timer', 'sequential', 'showprocesslist', 'showquery',
        'sorter', 'sorter_timer', 'tcptrack', 'tcptrack_args', 'tmux_session', 'vnstat',
        'vnstat_args', 'trailers_display', 'trailers_size_x', 'trailers_size_y', 'exit',
        'releaseprocessingtimeout', 'maxpptimeoutcount',
    ];

    /**
     * Each definition is [legacy keys in precedence order, value type, default, minimum, maximum].
     *
     * @var array<string, array<string, array{keys: list<string>, type: string, default: mixed, min?: int, max?: int}>>
     */
    public const array DOMAINS = [
        'site_configurations' => [
            'title' => ['keys' => ['title', 'code'], 'type' => 'string', 'default' => 'NNTmux'],
            'home_link' => ['keys' => ['home_link'], 'type' => 'string', 'default' => '/'],
            'site_logo' => ['keys' => ['site_logo'], 'type' => 'nullable_string', 'default' => null],
            'strapline' => ['keys' => ['strapline'], 'type' => 'string', 'default' => 'A great usenet indexer'],
            'meta_title' => ['keys' => ['metatitle'], 'type' => 'string', 'default' => 'An indexer'],
            'meta_description' => ['keys' => ['metadescription'], 'type' => 'string', 'default' => 'A usenet indexing website'],
            'meta_keywords' => ['keys' => ['metakeywords'], 'type' => 'string', 'default' => 'usenet,nzbs,cms,community'],
            'footer' => ['keys' => ['footer'], 'type' => 'string', 'default' => 'Usenet binary indexer.'],
            'dereferrer_link' => ['keys' => ['dereferrer_link'], 'type' => 'string', 'default' => ''],
            'terms' => ['keys' => ['tandc'], 'type' => 'string', 'default' => ''],
            'trailers_display' => ['keys' => ['trailers_display'], 'type' => 'bool', 'default' => true],
            'trailers_size_x' => ['keys' => ['trailers_size_x'], 'type' => 'int', 'default' => 480, 'min' => 1],
            'trailers_size_y' => ['keys' => ['trailers_size_y'], 'type' => 'int', 'default' => 345, 'min' => 1],
        ],
        'registration_configurations' => [
            'status' => ['keys' => ['registerstatus'], 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 2],
        ],
        'ingestion_configurations' => [
            'binary_threads' => ['keys' => ['binarythreads'], 'type' => 'int', 'default' => 1, 'min' => 1],
            'backfill_threads' => ['keys' => ['backfillthreads'], 'type' => 'int', 'default' => 1, 'min' => 1],
            'release_threads' => ['keys' => ['releasethreads'], 'type' => 'int', 'default' => 1, 'min' => 1],
            'collection_delay_hours' => ['keys' => ['delaytime'], 'type' => 'int', 'default' => 2, 'min' => 0],
            'collection_timeout_hours' => ['keys' => ['collection_timeout'], 'type' => 'int', 'default' => 48, 'min' => 1],
            'cross_post_hours' => ['keys' => ['crossposttime'], 'type' => 'int', 'default' => 2, 'min' => 0],
            'completion_percent' => ['keys' => ['completionpercent'], 'type' => 'int', 'default' => 95, 'min' => 0, 'max' => 100],
            'grab_status' => ['keys' => ['grabstatus'], 'type' => 'bool', 'default' => true],
            'max_headers_per_iteration' => ['keys' => ['max_headers_iteration'], 'type' => 'int', 'default' => 1000000, 'min' => 1],
            'max_messages' => ['keys' => ['maxmssgs'], 'type' => 'int', 'default' => 20000, 'min' => 1],
            'max_releases_created' => ['keys' => ['maxnzbsprocessed'], 'type' => 'int', 'default' => 1000, 'min' => 1],
            'nntp_retries' => ['keys' => ['nntpretries'], 'type' => 'int', 'default' => 10, 'min' => 0],
            'nzb_split_level' => ['keys' => ['nzbsplitlevel'], 'type' => 'int', 'default' => 4, 'min' => 0],
            'part_retention_hours' => ['keys' => ['partretentionhours'], 'type' => 'int', 'default' => 72, 'min' => 0],
            'release_retention_days' => ['keys' => ['releaseretentiondays'], 'type' => 'int', 'default' => 0, 'min' => 0],
            'misc_other_retention_hours' => ['keys' => ['miscotherretentionhours'], 'type' => 'int', 'default' => 0, 'min' => 0],
            'misc_hashed_retention_hours' => ['keys' => ['mischashedretentionhours'], 'type' => 'int', 'default' => 0, 'min' => 0],
            'min_files_to_form_release' => ['keys' => ['minfilestoformrelease'], 'type' => 'int', 'default' => 1, 'min' => 0],
            'min_size_to_form_release' => ['keys' => ['minsizetoformrelease'], 'type' => 'int', 'default' => 0, 'min' => 0],
            'max_size_to_form_release' => ['keys' => ['maxsizetoformrelease'], 'type' => 'int', 'default' => 0, 'min' => 0],
            'new_group_scan_method' => ['keys' => ['newgroupscanmethod'], 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 1],
            'new_group_days_to_scan' => ['keys' => ['newgroupdaystoscan'], 'type' => 'int', 'default' => 1, 'min' => 0],
            'new_group_messages_to_scan' => ['keys' => ['newgroupmsgstoscan'], 'type' => 'int', 'default' => 100000, 'min' => 0],
            'safe_backfill_date' => ['keys' => ['safebackfilldate'], 'type' => 'date', 'default' => '2012-06-24'],
            'disable_backfill_group' => ['keys' => ['disablebackfillgroup'], 'type' => 'bool', 'default' => false],
            'part_repair' => ['keys' => ['partrepair'], 'type' => 'bool', 'default' => true],
            'safe_part_repair' => ['keys' => ['safepartrepair'], 'type' => 'bool', 'default' => false],
            'max_part_repair' => ['keys' => ['maxpartrepair'], 'type' => 'int', 'default' => 15000, 'min' => 0],
            'part_repair_max_tries' => ['keys' => ['partrepairmaxtries'], 'type' => 'int', 'default' => 3, 'min' => 0],
            'categorize_foreign' => ['keys' => ['categorizeforeign'], 'type' => 'bool', 'default' => true],
            'categorize_web_dl' => ['keys' => ['catwebdl'], 'type' => 'bool', 'default' => false],
            'show_passworded_releases' => ['keys' => ['showpasswordedrelease'], 'type' => 'bool', 'default' => false],
            'delete_passworded_releases' => ['keys' => ['deletepasswordedrelease'], 'type' => 'bool', 'default' => false],
            'backfill_days_mode' => ['keys' => ['backfill_days'], 'type' => 'int', 'default' => 1, 'min' => 1, 'max' => 2],
            'backfill_order' => ['keys' => ['backfill_order'], 'type' => 'int', 'default' => 2, 'min' => 1, 'max' => 6],
            'backfill_quantity' => ['keys' => ['backfill_qty'], 'type' => 'int', 'default' => 100000, 'min' => 1],
        ],
        'post_processing_configurations' => [
            'post_threads' => ['keys' => ['postthreads'], 'type' => 'int', 'default' => 1, 'min' => 1],
            'post_threads_amazon' => ['keys' => ['postthreadsamazon'], 'type' => 'int', 'default' => 1, 'min' => 1],
            'post_threads_non' => ['keys' => ['postthreadsnon'], 'type' => 'int', 'default' => 1, 'min' => 1],
            'nfo_threads' => ['keys' => ['nfothreads'], 'type' => 'int', 'default' => 1, 'min' => 1],
            'fix_name_threads' => ['keys' => ['fixnamethreads'], 'type' => 'int', 'default' => 1, 'min' => 1],
            'timeout_seconds' => ['keys' => ['timeoutseconds'], 'type' => 'int', 'default' => 60, 'min' => 0],
            'release_timeout_seconds' => ['keys' => ['releaseprocessingtimeout'], 'type' => 'int', 'default' => 120, 'min' => 1],
            'max_timeout_count' => ['keys' => ['maxpptimeoutcount'], 'type' => 'int', 'default' => 3, 'min' => 1],
            'max_additional_processed' => ['keys' => ['maxaddprocessed'], 'type' => 'int', 'default' => 25, 'min' => 1],
            'max_parts_processed' => ['keys' => ['maxpartsprocessed'], 'type' => 'int', 'default' => 3, 'min' => 1],
            'password_check_attempts' => ['keys' => ['passchkattempts'], 'type' => 'int', 'default' => 1, 'min' => 1],
            'fix_names_per_run' => ['keys' => ['fixnamesperrun'], 'type' => 'int', 'default' => 10, 'min' => 1],
            'max_nested_levels' => ['keys' => ['maxnestedlevels'], 'type' => 'int', 'default' => 3, 'min' => 1],
            'extract_using_rar_info' => ['keys' => ['extractusingrarinfo'], 'type' => 'bool', 'default' => false],
            'segments_to_download' => ['keys' => ['segmentstodownload'], 'type' => 'int', 'default' => 2, 'min' => 1],
            'ffmpeg_duration' => ['keys' => ['ffmpeg_duration'], 'type' => 'int', 'default' => 5, 'min' => 1],
            'inner_file_blacklist' => ['keys' => ['innerfileblacklist'], 'type' => 'regex', 'default' => '/setup.exe|password.url/i'],
            'process_jpg' => ['keys' => ['processjpg'], 'type' => 'bool', 'default' => false],
            'process_thumbnails' => ['keys' => ['processthumbnails'], 'type' => 'bool', 'default' => false],
            'process_videos' => ['keys' => ['processvideos'], 'type' => 'bool', 'default' => false],
            'save_audio_preview' => ['keys' => ['saveaudiopreview'], 'type' => 'bool', 'default' => false],
            'min_size_to_post_process' => ['keys' => ['minsizetopostprocess'], 'type' => 'int', 'default' => 1048576, 'min' => 0],
            'max_size_to_post_process' => ['keys' => ['maxsizetopostprocess'], 'type' => 'int', 'default' => 107374182400, 'min' => 0],
            'min_size_to_process_nfo' => ['keys' => ['minsizetoprocessnfo'], 'type' => 'int', 'default' => 1048576, 'min' => 0],
            'max_size_to_process_nfo' => ['keys' => ['maxsizetoprocessnfo'], 'type' => 'int', 'default' => 107374182400, 'min' => 0],
            'max_nfo_processed' => ['keys' => ['maxnfoprocessed'], 'type' => 'int', 'default' => 100, 'min' => 1],
            'max_nfo_retries' => ['keys' => ['maxnforetries'], 'type' => 'int', 'default' => 5, 'min' => 0],
            'lookup_nfo' => ['keys' => ['lookupnfo'], 'type' => 'bool', 'default' => true],
            'lookup_par2' => ['keys' => ['lookuppar2'], 'type' => 'bool', 'default' => false],
        ],
        'metadata_configurations' => [
            'anime_lookup' => ['keys' => ['lookupanidb'], 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 2],
            'book_lookup' => ['keys' => ['lookupbooks'], 'type' => 'int', 'default' => 1, 'min' => 0, 'max' => 2],
            'game_lookup' => ['keys' => ['lookupgames'], 'type' => 'int', 'default' => 1, 'min' => 0, 'max' => 2],
            'movie_lookup' => ['keys' => ['lookupimdb'], 'type' => 'int', 'default' => 1, 'min' => 0, 'max' => 2],
            'music_lookup' => ['keys' => ['lookupmusic'], 'type' => 'int', 'default' => 1, 'min' => 0, 'max' => 2],
            'tv_lookup' => ['keys' => ['lookuptv'], 'type' => 'int', 'default' => 1, 'min' => 0, 'max' => 2],
            'movie_language' => ['keys' => ['imdblanguage', 'lookuplanguage'], 'type' => 'string', 'default' => 'en'],
            'imdb_alternate_url' => ['keys' => ['imdburl'], 'type' => 'bool', 'default' => false],
            'max_anime_processed' => ['keys' => ['maxanidbprocessed'], 'type' => 'int', 'default' => 100, 'min' => 1],
            'max_books_processed' => ['keys' => ['maxbooksprocessed'], 'type' => 'int', 'default' => 300, 'min' => 1],
            'max_games_processed' => ['keys' => ['maxgamesprocessed'], 'type' => 'int', 'default' => 150, 'min' => 1],
            'max_movies_processed' => ['keys' => ['maximdbprocessed'], 'type' => 'int', 'default' => 100, 'min' => 1],
            'max_music_processed' => ['keys' => ['maxmusicprocessed'], 'type' => 'int', 'default' => 150, 'min' => 1],
            'max_tv_processed' => ['keys' => ['maxrageprocessed'], 'type' => 'int', 'default' => 75, 'min' => 1],
            'amazon_sleep_milliseconds' => ['keys' => ['amazonsleep'], 'type' => 'int', 'default' => 1000, 'min' => 0],
        ],
        'tmux_configurations' => [
            'session_name' => ['keys' => ['tmux_session'], 'type' => 'string', 'default' => 'nntmux'],
            'monitor_delay' => ['keys' => ['monitor_delay'], 'type' => 'int', 'default' => 30, 'min' => 1],
            'niceness' => ['keys' => ['niceness'], 'type' => 'int', 'default' => 19, 'min' => -20, 'max' => 19],
            'sequential_mode' => ['keys' => ['sequential'], 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 2],
            'sequential_timer' => ['keys' => ['seq_timer'], 'type' => 'int', 'default' => 30, 'min' => 1],
            'binaries_enabled' => ['keys' => ['binaries'], 'type' => 'bool', 'default' => false],
            'binaries_timer' => ['keys' => ['bins_timer'], 'type' => 'int', 'default' => 30, 'min' => 1],
            'binaries_kill_timer' => ['keys' => ['bins_kill_timer'], 'type' => 'int', 'default' => 1, 'min' => 0],
            'backfill_mode' => ['keys' => ['backfill'], 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 4],
            'backfill_groups' => ['keys' => ['backfill_groups'], 'type' => 'int', 'default' => 4, 'min' => 1],
            'backfill_timer' => ['keys' => ['back_timer'], 'type' => 'int', 'default' => 30, 'min' => 1],
            'progressive_backfill' => ['keys' => ['progressive'], 'type' => 'bool', 'default' => false],
            'releases_enabled' => ['keys' => ['releases'], 'type' => 'bool', 'default' => false],
            'release_timer' => ['keys' => ['rel_timer'], 'type' => 'int', 'default' => 30, 'min' => 1],
            'post_mode' => ['keys' => ['post'], 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 3],
            'post_timer' => ['keys' => ['post_timer'], 'type' => 'int', 'default' => 30, 'min' => 1],
            'post_kill_timer' => ['keys' => ['post_kill_timer'], 'type' => 'int', 'default' => 300, 'min' => 0],
            'post_amazon_mode' => ['keys' => ['post_amazon'], 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 3],
            'post_amazon_timer' => ['keys' => ['post_timer_amazon'], 'type' => 'int', 'default' => 30, 'min' => 1],
            'post_non_mode' => ['keys' => ['post_non'], 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 3],
            'post_non_timer' => ['keys' => ['post_timer_non'], 'type' => 'int', 'default' => 30, 'min' => 1],
            'fix_names_enabled' => ['keys' => ['fix_names'], 'type' => 'bool', 'default' => false],
            'fix_timer' => ['keys' => ['fix_timer'], 'type' => 'int', 'default' => 30, 'min' => 1],
            'cleanup_mode' => ['keys' => ['fix_crap_opt'], 'type' => 'string', 'default' => 'Disabled'],
            'cleanup_timer' => ['keys' => ['crap_timer'], 'type' => 'int', 'default' => 30, 'min' => 1],
            'run_irc_scraper' => ['keys' => ['run_ircscraper'], 'type' => 'bool', 'default' => false],
            'console_enabled' => ['keys' => ['console'], 'type' => 'bool', 'default' => false],
            'htop_enabled' => ['keys' => ['htop'], 'type' => 'bool', 'default' => false],
            'mytop_enabled' => ['keys' => ['mytop'], 'type' => 'bool', 'default' => false],
            'nmon_enabled' => ['keys' => ['nmon'], 'type' => 'bool', 'default' => false],
            'vnstat_enabled' => ['keys' => ['vnstat'], 'type' => 'bool', 'default' => false],
            'vnstat_args' => ['keys' => ['vnstat_args'], 'type' => 'nullable_string', 'default' => null],
            'tcp_track_enabled' => ['keys' => ['tcptrack'], 'type' => 'bool', 'default' => false],
            'tcp_track_args' => ['keys' => ['tcptrack_args'], 'type' => 'nullable_string', 'default' => '-i eth0 port 443'],
            'bwmng_enabled' => ['keys' => ['bwmng'], 'type' => 'bool', 'default' => false],
            'redis_enabled' => ['keys' => ['redis'], 'type' => 'bool', 'default' => false],
            'redis_args' => ['keys' => ['redis_args'], 'type' => 'nullable_string', 'default' => null],
            'write_logs' => ['keys' => ['write_logs'], 'type' => 'bool', 'default' => false],
            'collections_kill_threshold' => ['keys' => ['collections_kill'], 'type' => 'int', 'default' => 0, 'min' => 0],
            'post_process_kill_threshold' => ['keys' => ['postprocess_kill'], 'type' => 'int', 'default' => 0, 'min' => 0],
            'colors_start' => ['keys' => ['colors_start'], 'type' => 'int', 'default' => 1, 'min' => 0, 'max' => 255],
            'colors_end' => ['keys' => ['colors_end'], 'type' => 'int', 'default' => 250, 'min' => 0, 'max' => 255],
        ],
    ];

    /** @var array<string, array{column: string, type: string, default: mixed}> */
    public const array RUNTIME = [
        'running' => ['column' => 'tmux_running', 'type' => 'bool', 'default' => false],
        'exit' => ['column' => 'stop_requested', 'type' => 'bool', 'default' => false],
        'last_run_time' => ['column' => 'last_binary_run_at', 'type' => 'nullable_datetime', 'default' => null],
        'monitor_path' => ['column' => 'monitor_path', 'type' => 'nullable_string', 'default' => null],
        'monitor_path_a' => ['column' => 'monitor_path_a', 'type' => 'nullable_string', 'default' => null],
        'monitor_path_b' => ['column' => 'monitor_path_b', 'type' => 'nullable_string', 'default' => null],
    ];

    /** @var list<string> */
    public const array SET_VALUES = ['fix_crap', 'colors_exc'];

    /** @var list<string> */
    public const array RETIRED = [
        'amazonpubkey', 'amazonprivkey', 'amazonassociatetag',
        'addpar2', 'alternate_nntp', 'banned', 'checkpasswordedrar', 'colors', 'currentppticket',
        'debuginfo', 'end', 'ffmpeg_image_time', 'lastpretime', 'lookupxxx', 'maxxxxprocessed',
        'nextppticket', 'nfos', 'nzbpath', 'nzbthreads', 'partsdeletechunks', 'postdelay', 'processupdate',
        'showdroppedyencparts', 'showprocesslist', 'showquery', 'sorter', 'sorter_timer', 'userdownloadpurgedays',
        'userhostexclusion',
    ];

    /** @return list<string> */
    public static function classifiedKeys(): array
    {
        $keys = self::RUNTIME === [] ? [] : array_keys(self::RUNTIME);
        foreach (self::DOMAINS as $definitions) {
            foreach ($definitions as $definition) {
                array_push($keys, ...$definition['keys']);
            }
        }

        return array_values(array_unique([...$keys, ...self::SET_VALUES, ...self::RETIRED]));
    }
}
