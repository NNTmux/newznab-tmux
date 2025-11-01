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

require_once __DIR__.'/../../../../bootstrap/autoload.php';

$artisan = base_path('artisan');
$command = "php {$artisan} groups:update";

passthru($command, $exitCode);
exit($exitCode);
