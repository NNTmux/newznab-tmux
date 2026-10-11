<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TmuxMode;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\Configuration\ProcessingRuntimeStateRepository;
use App\Services\Monitoring\TmuxMetricsSnapshot;
use App\Services\Tmux\CollectionDowntimeCompensator;
use App\Services\Tmux\TmuxCommand;
use App\Services\Tmux\TmuxMonitorService;
use App\Services\Tmux\TmuxOutput;
use App\Services\Tmux\TmuxPaneManager;
use App\Services\Tmux\TmuxSessionManager;
use App\Services\Tmux\TmuxTaskRunner;
use App\Support\MetadataSources;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class TmuxMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux:monitor
                            {--session= : Tmux session name}
                            {--reset-collections : Kept for compatibility; collection delays are always compensated on start}
                            {--ready-channel= : Internal startup acknowledgement channel}';

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
            // Initialize services
            $sessionName = $this->option('session')
                ?? app(ConfigurationProvider::class)->tmux()->sessionName;

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

            // Give collections back the time lost while nothing was running. Always, not
            // only with --reset-collections: a monitor recovered by tmux:health-check would
            // otherwise overwrite the activity marker without compensating. Only once the
            // session is known to exist, so a failed launch can't consume the outage.
            $this->resetOldCollections();

            cli()->header('Starting Tmux Monitor');
            $this->info("📊 Monitoring session: {$sessionName}");

            // Initialize monitor
            $runVar = $this->monitor->initializeMonitor();
            $panes = new TmuxPaneManager($sessionName);
            if (! $panes->installExitHook() || ! $panes->heartbeat()) {
                throw new \RuntimeException('Unable to initialize tmux monitor signals.');
            }
            if ($channel = $this->option('ready-channel')) {
                $result = Process::timeout(5)->run(TmuxCommand::arguments(['wait-for', '-S', $channel]));
                if (! $result->successful()) {
                    throw new \RuntimeException('Unable to acknowledge tmux monitor readiness.');
                }
            }

            // Main monitoring loop
            $iteration = 0;
            while ($this->monitor->shouldContinue()) {
                $iteration++;
                if (! $this->sessionManager->sessionExists() || ! $panes->heartbeat()) {
                    throw new \RuntimeException('Tmux monitor session disappeared.');
                }
                $this->recordIngestionActivity();

                MetadataSources::logDailySummary();

                // Collect statistics
                $runVar = $this->monitor->collectStatistics();

                // Update display
                $this->tmuxOutput->updateMonitorPane($runVar);

                // Run pane tasks if tmux is running
                $runVar['settings']['is_running'] = (int) app(ProcessingRuntimeStateRepository::class)->isTmuxRunning();
                $this->publishMetricsSnapshot($runVar);
                if ((int) ($runVar['settings']['is_running'] ?? 0) === 1) {
                    $this->runPaneTasks($runVar);
                } else {
                    if ($iteration % 60 === 0) { // Log every 10 minutes
                        $this->info('⏸️  Tmux is not running. Waiting...');
                    }
                }

                // Increment iteration and sleep
                $this->monitor->incrementIteration();
                $panes->waitForExit(max(1, (int) config('tmux.monitor.delay', 10)));
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
    /**
     * Mark ingestion as running, so a later start can tell how long it was down.
     */
    private function recordIngestionActivity(): void
    {
        try {
            app(CollectionDowntimeCompensator::class)->recordActivity();
        } catch (\Throwable $e) {
            logger()->warning('Unable to record tmux ingestion activity: '.$e->getMessage());
        }
    }

    private function resetOldCollections(): void
    {
        $delayTime = app(ConfigurationProvider::class)->ingestion()->collectionDelayHours;

        cli()->header('Adjusting collection delays...');

        $count = app(CollectionDowntimeCompensator::class)->compensate($delayTime);
        $this->info($count > 0
            ? "✅ Gave {$count} waiting collections back the time lost while the indexer was down"
            : '✅ No collections needed more time');
    }

    /**
     * Hand the latest statistics to the Prometheus exporter. Monitoring must
     * never interrupt processing, so failures are only reported.
     *
     * @param  array<string, mixed>  $runVar
     */
    private function publishMetricsSnapshot(array $runVar): void
    {
        if (! config('monitoring.enabled')) {
            return;
        }

        try {
            app(TmuxMetricsSnapshot::class)->publish($runVar);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Run tasks in appropriate panes
     *
     * @param  array<string, mixed>  $runVar
     */
    private function runPaneTasks(array $runVar): void
    {
        $this->taskRunner->beginCycle();
        $mode = TmuxMode::tryFrom((int) ($runVar['constants']['sequential'] ?? 0)) ?? TmuxMode::Full;
        foreach ($mode->tasks() as $task => $role) {
            $this->taskRunner->runPaneTask($task, ['role' => $role], $runVar);
        }
    }
}
