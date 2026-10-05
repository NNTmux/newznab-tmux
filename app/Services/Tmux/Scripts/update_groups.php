#!/usr/bin/env php
<?php

/**
 * Update Groups - Utility Script
 *
 * This script updates first/last article numbers for all active groups.
 *
 * Modernized version - now delegates to Artisan command
 * Original location: misc/update/tmux/bin/update_groups.php
 * New location: app/Services/Tmux/Scripts/update_groups.php
 */
$artisan = dirname(__DIR__, 4).'/artisan';

$command = [PHP_BINARY, $artisan, 'groups:update'];
if (function_exists('pcntl_exec')) {
    pcntl_exec(PHP_BINARY, array_slice($command, 1));
    fwrite(STDERR, "Unable to execute Artisan worker.\n");
    exit(1);
}

$process = proc_open([PHP_BINARY, $artisan, 'groups:update'], [STDIN, STDOUT, STDERR], $pipes);
$exitCode = is_resource($process) ? proc_close($process) : 1;
exit($exitCode);
