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
        // Install with: ./install-tmux-fonts.sh or see TMUX_FONTS_GUIDE.md
        'use_nerd_fonts' => env('TMUX_USE_NERD_FONTS', true),
        'symbols' => [
            // Powerline arrows (rounded style)
            'separator_left' => '',   // Rounded powerline arrow left
            'separator_right' => '',  // Rounded powerline arrow right
            'separator_thin_left' => '',  // Thin separator
            'separator_thin_right' => '', // Thin separator

            // Status icons
            'session' => '',           // Terminal/session icon
            'window' => '',            // Window icon
            'pane_active' => '●',       // Active pane indicator
            'pane_inactive' => '○',     // Inactive pane indicator

            // System monitoring
            'cpu' => '',              // CPU symbol
            'ram' => '',              // RAM symbol
            'disk' => '󰋊',             // Disk symbol
            'network' => '󰛳',          // Network symbol
            'clock' => '',            // Clock symbol
            'calendar' => '',         // Calendar symbol
            'uptime' => '󰥔',           // Uptime symbol

            // Processing icons
            'download' => '',         // Download/binaries
            'sync' => '󰑓',             // Sync/backfill
            'release' => '',          // Release
            'fix' => '󰯃',              // Fix/wrench
            'trash' => '󰆴',            // Trash/remove
            'tv' => '󰟴',               // TV
            'movie' => '',            // Movie
            'amazon' => '',           // Amazon/shopping
            'irc' => '󰻞',              // Chat/IRC

            // Monitoring tools
            'htop' => '',             // Process monitor
            'database' => '',         // Database
            'redis' => '',            // Redis
            'console' => '',          // Console/terminal
        ],
    ],

    'panes' => [
        'history_limit' => 6000,
        'remain_on_exit' => true,
        'aggressive_resize' => true,
        'monitor_activity' => true,
        'border_style' => 'heavy',    // heavy for bolder lines
        'active_border_color' => 'colour75',   // Sapphire blue (Catppuccin)
        'inactive_border_color' => 'colour238', // Dark grey
    ],

    'colors' => [
        // Modern color scheme (Catppuccin Mocha inspired)
        'base' => 'colour234',        // Darkest background (crust)
        'background' => 'colour235',  // Main background (base)
        'surface' => 'colour237',     // Surface for elevated elements
        'foreground' => 'colour255',  // Main text (text)
        'subtext' => 'colour250',     // Dimmed text (subtext0)
        'overlay' => 'colour238',     // Overlay elements

        // Accent colors (Catppuccin)
        'sapphire' => 'colour75',     // Primary accent
        'teal' => 'colour116',        // Secondary accent
        'green' => 'colour114',       // Success
        'yellow' => 'colour221',      // Warning
        'peach' => 'colour215',       // Attention
        'red' => 'colour210',         // Error/danger
        'pink' => 'colour218',        // Special
        'mauve' => 'colour141',       // Purple
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
