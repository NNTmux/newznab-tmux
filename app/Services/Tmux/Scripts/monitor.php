#!/usr/bin/env php
<?php

/**
 * Tmux Monitor Script
 *
 * This script runs in tmux pane 0.0 and continuously monitors the system,
 * collecting statistics and spawning tasks in other panes.
 *
 * This is the modernized version that uses the new service architecture.
 */

// Bootstrap Laravel
// From: app/Services/Tmux/Scripts/monitor.php
// To:   bootstrap/autoload.php (4 levels up)
require_once __DIR__.'/../../../../bootstrap/autoload.php';

use App\Models\Settings;
use App\Services\Tmux\TmuxMonitorService;
use App\Services\Tmux\TmuxTaskRunner;
use Blacklight\ColorCLI;
use Blacklight\TmuxOutput;

$colorCli = new ColorCLI;

try {
    // Get session name
    $sessionName = Settings::settingValue('tmux_session') ?? 'nntmux';

    // Initialize services
    $monitor = new TmuxMonitorService;
    $taskRunner = new TmuxTaskRunner($sessionName);
    $output = new TmuxOutput;

    // Initialize monitor
    $runVar = $monitor->initializeMonitor();

    // Spawn IRC scraper immediately
    try {
        $taskRunner->runIRCScraper(['constants' => $runVar['constants']]);
    } catch (Exception $e) {
        logger()->error('Failed to spawn IRC scraper: '.$e->getMessage());
    }

    $colorCli->header('Tmux Monitor Started');
    echo "Session: {$sessionName}\n";
    echo "Press Ctrl+C to stop (or set exit flag in settings)\n\n";

    // Main monitoring loop
    while ($monitor->shouldContinue()) {
        // Collect statistics
        $runVar = $monitor->collectStatistics();

        // Update display
        $output->updateMonitorPane($runVar);

        // Run pane tasks if tmux is running
        if ((int) ($runVar['settings']['is_running'] ?? 0) === 1) {
            $sequential = (int) ($runVar['constants']['sequential'] ?? 0);

            // Determine which panes to run based on sequential mode
            $panesToRun = match ($sequential) {
                0 => ['main', 'fixnames', 'removecrap', 'ppadditional', 'nonamazon', 'amazon'],
                1 => ['main', 'fixnames', 'removecrap', 'ppadditional', 'nonamazon', 'amazon'],
                2 => ['main', 'fixnames', 'amazon'],
                default => [],
            };

            // Run each pane task using TmuxTaskRunner
            foreach ($panesToRun as $paneName) {
                try {
                    $taskRunner->runPaneTask($paneName, [], $runVar);
                } catch (Exception $e) {
                    logger()->error("Failed to run pane {$paneName}: ".$e->getMessage());
                }
            }
        }

        // Increment iteration and sleep
        $monitor->incrementIteration();
        sleep(10);
    }

    $colorCli->info('Monitor stopped by exit flag');

} catch (Exception $e) {
    $colorCli->error('Monitor failed: '.$e->getMessage());
    logger()->error('Tmux monitor error', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit(1);
}
