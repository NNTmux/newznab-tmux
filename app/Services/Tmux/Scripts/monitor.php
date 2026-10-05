#!/usr/bin/env php
<?php

/**
 * Tmux Monitor Script
 *
 * This script runs in tmux pane 0.0 and continuously monitors the system,
 * collecting statistics and spawning tasks in other panes.
 *
 * This is a simple wrapper that calls the tmux:monitor artisan command.
 */
$artisan = dirname(__DIR__, 4).'/artisan';

// Pass any arguments to the artisan command
$args = array_slice($argv ?? [], 1);
$command = [PHP_BINARY, $artisan, 'tmux:monitor', ...$args];

if (function_exists('pcntl_exec')) {
    pcntl_exec(PHP_BINARY, array_slice($command, 1));
    fwrite(STDERR, "Unable to execute Artisan worker.\n");
    exit(1);
}

$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
$exitCode = is_resource($process) ? proc_close($process) : 1;
exit($exitCode);
