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
$argString = implode(' ', array_map('escapeshellarg', $args));

passthru("php {$artisan} tmux:monitor {$argString}", $exitCode);
exit($exitCode);
