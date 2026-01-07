#!/usr/bin/env php
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
if (! isset($argv[1])) {
    fwrite(STDERR, "This script is not intended to be run manually, it is called from Multiprocessing.\n");
    exit(1);
}

// Parse arguments: "type guidChar maxPerRun thread"
[$type, $guidChar, $maxPerRun, $thread] = explode(' ', $argv[1]);

// Build artisan command based on type
$artisan = dirname(__DIR__, 4).'/artisan';

switch ($type) {
    case 'standard':
        if ($guidChar === null || $maxPerRun === null || ! is_numeric($maxPerRun)) {
            fwrite(STDERR, "Invalid arguments for standard type\n");
            exit(1);
        }

        $command = "php {$artisan} releases:fix-names-group standard --guid-char={$guidChar} --limit={$maxPerRun}";
        break;

    case 'predbft':
        if (! isset($maxPerRun) || ! is_numeric($maxPerRun) || ! isset($thread) || ! is_numeric($thread)) {
            fwrite(STDERR, "Invalid arguments for predbft type\n");
            exit(1);
        }

        $command = "php {$artisan} releases:fix-names-group predbft --limit={$maxPerRun} --thread={$thread}";
        break;

    default:
        fwrite(STDERR, "Unknown type: {$type}\n");
        exit(1);
}

// Execute the command
passthru($command, $exitCode);
exit($exitCode);
