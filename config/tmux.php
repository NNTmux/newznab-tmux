<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tmux Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for tmux session management.
    | Previously located in misc/update/tmux/tmux.conf, this has been
    | modernized and integrated into Laravel's config system.
    |
    */

    'config_file' => env('TMUX_CONFIG_FILE', config_path('tmux.conf')),

    'session' => [
        'name' => env('TMUX_SESSION_NAME'),
        'default_name' => 'nntmux',
    ],

    'terminal' => [
        'type' => env('TMUX_TERMINAL', 'xterm-256color'),
        'escape_time' => 0,
    ],

    'status_bar' => [
        'interval' => 5,
        'bg_color' => 'black',
        'fg_color' => 'white',
        'show_system_info' => true,
    ],

    'panes' => [
        'history_limit' => 6000,
        'remain_on_exit' => true,
        'aggressive_resize' => true,
        'monitor_activity' => true,
    ],

    'keys' => [
        'mode' => 'vi',
        'prefix' => 'C-a',
    ],

    'mouse' => [
        'enabled' => true,
    ],

    'paths' => [
        'scripts' => base_path('app/Services/Tmux/Scripts'),
        'logs' => storage_path('logs/tmux'),
    ],

    'monitor' => [
        'delay' => env('TMUX_MONITOR_DELAY', 10),
        'refresh_interval' => env('TMUX_REFRESH_INTERVAL', 60),
    ],

    'performance' => [
        'niceness' => env('TMUX_NICENESS', 2),
        'timeout' => env('TMUX_TIMEOUT', 300),
    ],
];
