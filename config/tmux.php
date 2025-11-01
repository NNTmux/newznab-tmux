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
        'bg_color' => 'colour235',  // Dark grey
        'fg_color' => 'colour250',  // Light grey
        'active_bg' => 'colour39',  // Bright blue
        'active_fg' => 'colour234', // Almost black
        'show_system_info' => true,
        'use_powerline' => env('TMUX_USE_POWERLINE', true),
        'left_length' => 40,
        'right_length' => 150,
    ],

    'fonts' => [
        // Popular Nerd Fonts / Powerline fonts
        // Install with: sudo apt-get install fonts-powerline
        'use_nerd_fonts' => env('TMUX_USE_NERD_FONTS', true),
        'symbols' => [
            'separator_left' => '',   // Powerline arrow
            'separator_right' => '',  // Powerline arrow
            'branch' => '',           // Git branch
            'lock' => '',             // Lock symbol
            'cpu' => '',              // CPU symbol
            'ram' => '',              // RAM symbol
            'clock' => '',            // Clock symbol
        ],
    ],

    'panes' => [
        'history_limit' => 6000,
        'remain_on_exit' => true,
        'aggressive_resize' => true,
        'monitor_activity' => true,
        'border_style' => 'rounded',  // rounded, heavy, double, simple
        'active_border_color' => 'colour39',  // Bright blue
        'inactive_border_color' => 'colour238',  // Dark grey
    ],

    'colors' => [
        // Modern color scheme (Dracula-inspired)
        'background' => 'colour235',
        'foreground' => 'colour250',
        'selection' => 'colour238',
        'comment' => 'colour244',
        'cyan' => 'colour117',
        'green' => 'colour114',
        'orange' => 'colour215',
        'pink' => 'colour212',
        'purple' => 'colour141',
        'red' => 'colour210',
        'yellow' => 'colour228',
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
