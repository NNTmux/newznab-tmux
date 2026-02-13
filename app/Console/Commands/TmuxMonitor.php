<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\Settings;
use App\Services\Tmux\TmuxMonitorService;
use App\Services\Tmux\TmuxOutput;
use App\Services\Tmux\TmuxSessionManager;
use App\Services\Tmux\TmuxTaskRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TmuxMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux:monitor
                            {--session= : Tmux session name}
                            {--reset-collections : Reset old collections before starting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor and manage tmux processing panes (modernized)';

    private TmuxSessionManager $sessionManager;

    private TmuxMonitorService $monitor;

    private TmuxTaskRunner $taskRunner;

    private TmuxOutput $tmuxOutput;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {

        try {
            // Reset old collections if requested
            if ($this->option('reset-collections')) {
                $this->resetOldCollections();
            }

            // Initialize services
            $sessionName = $this->option('session')
                ?? Settings::settingValue('tmux_session')
                ?? config('tmux.session.default_name', 'nntmux');

            $this->sessionManager = new TmuxSessionManager($sessionName);
            $this->monitor = new TmuxMonitorService;
            $this->taskRunner = new TmuxTaskRunner($sessionName);
            $this->tmuxOutput = new TmuxOutput;

            // Verify session exists
            if (! $this->sessionManager->sessionExists()) {
                $this->error("❌ Tmux session '{$sessionName}' does not exist.");
                $this->info("💡 Run 'php artisan tmux:start' to create the session first.");

                return Command::FAILURE;
            }

            cli()->header('Starting Tmux Monitor');
            $this->info("📊 Monitoring session: {$sessionName}");

            // Initialize monitor
            $runVar = $this->monitor->initializeMonitor();

            // Main monitoring loop
            $iteration = 0;
            while ($this->monitor->shouldContinue()) {
                $iteration++;

                // Collect statistics
                $runVar = $this->monitor->collectStatistics();

                // Update display
                $this->tmuxOutput->updateMonitorPane($runVar);

                // Run pane tasks if tmux is running
                if ((int) ($runVar['settings']['is_running'] ?? 0) === 1) {
                    $this->runPaneTasks($runVar);
                } else {
                    if ($iteration % 60 === 0) { // Log every 10 minutes
                        $this->info('⏸️  Tmux is not running. Waiting...');
                    }
                }

                // Increment iteration and sleep
                $this->monitor->incrementIteration();
                sleep(10);
            }

            $this->info('🛑 Monitor stopped by exit flag');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Monitor failed: '.$e->getMessage());
            logger()->error('Tmux monitor error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Reset old collections based on delay time
     */
    private function resetOldCollections(): void
    {
        $delayTime = (int) (Settings::settingValue('delaytime') ?? 2);

        cli()->header('Resetting expired collections...');

        try {
            DB::transaction(function () use ($delayTime) {
                $count = Collection::query()
                    ->where('dateadded', '<', now()->subHours($delayTime))
                    ->update(['dateadded' => now()]);

                if ($count > 0) {
                    $this->info("✅ Reset {$count} collections");
                } else {
                    $this->info('✅ No collections needed resetting');
                }
            }, 10);

        } catch (\Exception $e) {
            $this->error('Failed to reset collections: '.$e->getMessage());
        }
    }

    /**
     * Run tasks in appropriate panes
     *
     * @param  array<string, mixed>  $runVar
     */
    private function runPaneTasks(array $runVar): void
    {
        $sequential = (int) ($runVar['constants']['sequential'] ?? 0);

        // Always run IRC scraper
        $this->runIRCScraper($runVar);

        // Run main tasks based on sequential mode
        if ($sequential === 2) {
            // Stripped mode - only essential tasks
            $this->runSequentialTasks($runVar);
        } elseif ($sequential === 1) {
            // Basic sequential mode
            $this->runBasicTasks($runVar);
        } else {
            // Full non-sequential mode
            $this->runFullTasks($runVar);
        }
    }

    /**
     * Run IRC scraper
     *
     * @param  array<string, mixed>  $runVar
     */
    private function runIRCScraper(array $runVar): void
    {
        $this->taskRunner->runIRCScraper([
            'constants' => $runVar['constants'],
        ]);
    }

    /**
     * Run full non-sequential tasks
     *
     * @param  array<string, mixed>  $runVar
     */
    private function runFullTasks(array $runVar): void
    {
        // Update binaries
        $this->taskRunner->runBinariesUpdate($runVar);

        // Backfill
        $this->taskRunner->runBackfill($runVar);

        // Update releases
        $this->taskRunner->runReleasesUpdate(array_merge($runVar, ['pane' => '0.3']));

        // Post-processing and cleanup tasks
        $this->runPostProcessingTasks($runVar);
    }

    /**
     * Run basic sequential tasks
     *
     * @param  array<string, mixed>  $runVar
     */
    private function runBasicTasks(array $runVar): void
    {
        // Update releases
        $this->taskRunner->runReleasesUpdate(array_merge($runVar, ['pane' => '0.1']));

        // Post-processing and cleanup tasks
        $this->runPostProcessingTasks($runVar);
    }

    /**
     * Run stripped sequential tasks
     *
     * @param  array<string, mixed>  $runVar
     */
    private function runSequentialTasks(array $runVar): void
    {
        // Minimal tasks for complete sequential mode
        // Tasks are handled by the sequential script itself
    }

    /**
     * Run post-processing tasks (common to most modes)
     *
     * @param  array<string, mixed>  $runVar
     */
    private function runPostProcessingTasks(array $runVar): void
    {
        $sequential = (int) ($runVar['constants']['sequential'] ?? 0);

        if ($sequential === 2) {
            // Skip post-processing in complete sequential mode
            return;
        }

        // Run utility tasks (window 1)
        $this->taskRunner->runPaneTask('fixnames', [], $runVar);
        $this->taskRunner->runPaneTask('removecrap', [], $runVar);

        // Run post-processing tasks (window 2)
        $this->taskRunner->runPaneTask('ppadditional', [], $runVar);
        $this->taskRunner->runPaneTask('tv', [], $runVar);
        $this->taskRunner->runPaneTask('movies', [], $runVar);
        $this->taskRunner->runPaneTask('amazon', [], $runVar);
    }
}
