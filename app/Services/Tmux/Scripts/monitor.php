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
use Blacklight\TmuxRun;

$colorCli = new ColorCLI;

try {
    // Get session name
    $sessionName = Settings::settingValue('tmux_session') ?? 'nntmux';

    // Initialize services
    $monitor = new TmuxMonitorService;
    $taskRunner = new TmuxTaskRunner($sessionName);
    $output = new TmuxOutput;

    // For backwards compatibility, also initialize old classes
    $tRun = new TmuxRun;

    // Initialize monitor
    $runVar = $monitor->initializeMonitor();

    // Add paths that TmuxRun expects
    $runVar['paths']['misc'] = base_path().'/misc/';
    $runVar['paths']['cli'] = base_path().'/cli/';

    // Also initialize old-style commands for compatibility
    $tmux_niceness = Settings::settingValue('niceness') ?? 2;
    $runVar['commands']['_php'] = " nice -n{$tmux_niceness} php";
    $runVar['commands']['_phpn'] = "nice -n{$tmux_niceness} php";
    $runVar['commands']['_sleep'] = "{$runVar['commands']['_phpn']} ".base_path('app/Services/Tmux/Scripts/showsleep.php');

    // Add scripts paths for TmuxRun - Using multiprocessing scripts WITH ARGUMENTS
    $runVar['scripts']['binaries'] = "nice -n{$tmux_niceness} php ".base_path('misc/update/multiprocessing/binaries.php').' 0';
    $runVar['scripts']['backfill'] = "nice -n{$tmux_niceness} php ".base_path('misc/update/multiprocessing/backfill.php').' 0';
    $runVar['scripts']['releases'] = "nice -n{$tmux_niceness} php ".base_path('misc/update/multiprocessing/releases.php');

    // Spawn IRC scraper immediately
    try {
        $taskRunner->runIRCScraper(['constants' => $runVar['constants']]);
    } catch (Exception $e) {
        logger()->error('Failed to spawn IRC scraper: '.$e->getMessage());
    }

    // Get list of panes for compatibility
    $runVar['panes'] = $tRun->getListOfPanes($runVar['constants']);

    $colorCli->header('Tmux Monitor Started');
    echo "Session: {$sessionName}\n";
    echo "Press Ctrl+C to stop (or set exit flag in settings)\n\n";

    // Main monitoring loop
    while ($monitor->shouldContinue()) {
        // Collect statistics (but preserve our custom settings)
        $scriptsBackup = $runVar['scripts'] ?? [];
        $commandsBackup = $runVar['commands'] ?? [];
        $pathsBackup = $runVar['paths'] ?? [];

        $runVar = $monitor->collectStatistics();

        // Restore our custom settings that collectStatistics doesn't know about
        $runVar['scripts'] = $scriptsBackup;
        $runVar['commands'] = $commandsBackup;
        $runVar['paths'] = array_merge($runVar['paths'] ?? [], $pathsBackup);

        // Refresh panes list periodically
        $runVar['panes'] = $tRun->getListOfPanes($runVar['constants']);

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

            // Run each pane task
            foreach ($panesToRun as $paneName) {
                try {
                    // Use old TmuxRun for compatibility during transition
                    $tRun->runPane($paneName, $runVar);
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
