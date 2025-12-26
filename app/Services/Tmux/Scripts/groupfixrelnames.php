<?php

/**
 * Group Fix Release Names - Utility Script
 *
 * This script is called from multiprocessing to fix release names using various methods.
 *
 * Modernized version - now delegates to Artisan command
 * Original location: misc/update/tmux/bin/groupfixrelnames.php
 * New location: app/Services/Tmux/Scripts/groupfixrelnames.php
 */

require_once __DIR__.'/../../../../bootstrap/autoload.php';

if (! isset($argv[1])) {
    cli()->error('This script is not intended to be run manually, it is called from Multiprocessing.');
    exit(1);
}

// Parse arguments: "type guidChar maxPerRun thread"
[$type, $guidChar, $maxPerRun, $thread] = explode(' ', $argv[1]);

// Build artisan command based on type
$artisan = base_path('artisan');

switch ($type) {
    case 'standard':
        if ($guidChar === null || $maxPerRun === null || ! is_numeric($maxPerRun)) {
            cli()->error('Invalid arguments for standard type');
            exit(1);
        }

        $command = "php {$artisan} releases:fix-names-group standard --guid-char={$guidChar} --limit={$maxPerRun}";
        break;

    case 'predbft':
        if (! isset($maxPerRun) || ! is_numeric($maxPerRun) || ! isset($thread) || ! is_numeric($thread)) {
            cli()->error('Invalid arguments for predbft type');
            exit(1);
        }

        $command = "php {$artisan} releases:fix-names-group predbft --limit={$maxPerRun} --thread={$thread}";
        break;

    default:
        cli()->error("Unknown type: {$type}");
        exit(1);
}

// Execute the command
passthru($command, $exitCode);
exit($exitCode);
