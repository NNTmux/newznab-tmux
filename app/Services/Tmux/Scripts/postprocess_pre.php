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

require_once __DIR__.'/../../../../bootstrap/autoload.php';

$limit = isset($argv[1]) && is_numeric($argv[1]) ? $argv[1] : '';
$artisan = base_path('artisan');

$command = "php {$artisan} predb:check".($limit ? " {$limit}" : '');

passthru($command, $exitCode);
exit($exitCode);
