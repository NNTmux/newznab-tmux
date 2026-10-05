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

// Parse arguments: "type guidChar maxPerRun worker workers"
[$type, $guidChar, $maxPerRun, $thread, $workers] = array_pad(explode(' ', $argv[1]), 5, '1');

// Build artisan command based on type
$artisan = dirname(__DIR__, 4).'/artisan';

switch ($type) {
    case 'standard':
        if ($guidChar === '' || $maxPerRun === '' || ! is_numeric($maxPerRun)) {
            fwrite(STDERR, "Invalid arguments for standard type\n");
            exit(1);
        }

        $command = [PHP_BINARY, $artisan, 'releases:fix-names-group', 'standard', '--guid-char='.$guidChar, '--limit='.$maxPerRun];
        break;

    case 'predbft':
        if (! is_numeric($maxPerRun) || ! is_numeric($thread)) {
            fwrite(STDERR, "Invalid arguments for predbft type\n");
            exit(1);
        }

        $command = [PHP_BINARY, $artisan, 'releases:fix-names-group', 'predbft', '--limit='.$maxPerRun, '--thread='.$thread, '--workers='.$workers];
        break;

    default:
        fwrite(STDERR, "Unknown type: {$type}\n");
        exit(1);
}

// Execute the command
if (function_exists('pcntl_exec')) {
    pcntl_exec(PHP_BINARY, array_slice($command, 1));
    fwrite(STDERR, "Unable to execute Artisan worker.\n");
    exit(1);
}

$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
$exitCode = is_resource($process) ? proc_close($process) : 1;
exit($exitCode);
