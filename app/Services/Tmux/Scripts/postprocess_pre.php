#!/usr/bin/env php
<?php

/**
 * Postprocess PreDB - Utility Script
 *
 * This script checks releases against PreDB for matches.
 *
 * Modernized version - now delegates to Artisan command
 * Original location: misc/update/tmux/bin/postprocess_pre.php
 * New location: app/Services/Tmux/Scripts/postprocess_pre.php
 */
$limit = isset($argv[1]) && is_numeric($argv[1]) ? $argv[1] : '';
$artisan = dirname(__DIR__, 4).'/artisan';

$command = [PHP_BINARY, $artisan, 'predb:check', ...($limit !== '' ? [$limit] : [])];

if (function_exists('pcntl_exec')) {
    pcntl_exec(PHP_BINARY, array_slice($command, 1));
    fwrite(STDERR, "Unable to execute Artisan worker.\n");
    exit(1);
}

$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
$exitCode = is_resource($process) ? proc_close($process) : 1;
exit($exitCode);
