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

passthru("php {$artisan} groups:update", $exitCode);
exit($exitCode);
