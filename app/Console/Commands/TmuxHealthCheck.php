<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TmuxPaneRole;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\Configuration\ProcessingRuntimeStateRepository;
use App\Services\Tmux\TmuxCommand;
use App\Services\Tmux\TmuxPaneManager;
use App\Services\Tmux\TmuxSessionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TmuxHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux:health-check
                            {--session= : Tmux session name}
                            {--require-session : Check only whether the exact session exists}
                            {--auto-restart : Automatically restart tmux if monitor pane is dead}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if tmux session exists and monitor pane is alive, optionally restart if dead';

    private TmuxSessionManager $sessionManager;

    private string $sessionName;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->sessionName = $this->option('session')
                ?? app(ConfigurationProvider::class)->tmux()->sessionName;

            $this->sessionManager = new TmuxSessionManager($this->sessionName);
            $quiet = $this->output->isQuiet();
            $shouldBeRunning = $this->shouldBeRunning();
            $autoRestart = (bool) $this->option('auto-restart');

            if (! $this->sessionManager->sessionExists()) {
                if (! $quiet) {
                    $this->warn("⚠️  Tmux session '{$this->sessionName}' does not exist.");
                }

                if ($this->option('require-session')) {
                    return Command::FAILURE;
                }

                if (! $shouldBeRunning) {
                    $this->logHealthCheck('notice', 'stopped_intentionally');

                    return Command::SUCCESS;
                }

                if ($autoRestart) {
                    $this->logHealthCheck('warning', 'session_missing_restart_attempted');

                    return $this->restartTmux();
                }

                $this->logHealthCheck('warning', 'session_missing_unrecovered');

                return Command::FAILURE;
            }

            if ($this->option('require-session')) {
                return Command::SUCCESS;
            }

            if (! $quiet) {
                $this->info("✅ Tmux session '{$this->sessionName}' exists.");
            }

            if (! $shouldBeRunning) {
                $this->logHealthCheck('notice', 'stopped_intentionally');

                return Command::SUCCESS;
            }

            $monitorPaneDead = $this->isMonitorPaneDead();

            if ($monitorPaneDead) {
                if (! $quiet) {
                    $this->warn('⚠️  Monitor pane is dead.');
                }

                if ($autoRestart) {
                    $this->logHealthCheck('warning', 'monitor_dead_restart_attempted');

                    return $this->restartMonitor();
                }

                $this->logHealthCheck('warning', 'monitor_dead_unrecovered');

                return Command::FAILURE;
            }

            if (! $quiet) {
                $this->info('✅ Monitor pane is alive and running.');
            }

            $heartbeat = (new TmuxPaneManager($this->sessionName))->heartbeatTime();
            $maximumAge = max(60, 6 * (int) config('tmux.monitor.delay', 10));
            if ($heartbeat !== null && time() - $heartbeat > $maximumAge) {
                $this->logHealthCheck('warning', 'monitor_heartbeat_stale');
                $this->warn('Monitor heartbeat is stale; its active pane was preserved.');

                return Command::FAILURE;
            }

            $this->logHealthCheck('info', 'healthy');

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('❌ Health check failed: '.$e->getMessage());
            logger()->error('Tmux health check error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Check if the monitor pane is dead.
     */
    private function isMonitorPaneDead(): bool
    {
        $paneManager = new TmuxPaneManager($this->sessionName);

        try {
            $monitorPane = $paneManager->paneForRole(TmuxPaneRole::Monitor, '0.0');
        } catch (\RuntimeException) {
            return true;
        }

        return ! $paneManager->isAlive($monitorPane);
    }

    private function restartMonitor(): int
    {
        $panes = new TmuxPaneManager($this->sessionName);
        $pane = $panes->paneForRole(TmuxPaneRole::Monitor, '0.0');
        $successful = $panes->respawnPane($pane, TmuxCommand::monitor($this->sessionName));
        $this->logHealthCheck($successful ? 'notice' : 'error', $successful ? 'monitor_restart_succeeded' : 'monitor_restart_failed');

        return $successful ? Command::SUCCESS : Command::FAILURE;
    }

    private function shouldBeRunning(): bool
    {
        return filter_var(app(ProcessingRuntimeStateRepository::class)->isTmuxRunning(), FILTER_VALIDATE_BOOL);
    }

    /**
     * Restart the tmux session.
     */
    private function restartTmux(): int
    {
        $this->info('🔄 Restarting tmux session...');

        $this->info('▶️  Starting new tmux session...');
        $exitCode = $this->call('tmux:start', [
            '--session' => $this->sessionName,
        ]);

        if ($exitCode === Command::SUCCESS) {
            $this->info("✅ Tmux session '{$this->sessionName}' restarted successfully.");
            $this->logHealthCheck('notice', 'restart_succeeded', $exitCode);
        } else {
            $this->error("❌ Failed to restart tmux session '{$this->sessionName}'.");
            $this->logHealthCheck('error', 'restart_failed', $exitCode);
        }

        return $exitCode;
    }

    private function logHealthCheck(string $level, string $outcome, ?int $exitCode = null): void
    {
        Log::log($level, 'Tmux health check result', [
            'session' => $this->sessionName,
            'auto_restart' => (bool) $this->option('auto-restart'),
            'should_be_running' => $this->shouldBeRunning(),
            'outcome' => $outcome,
            'exit_code' => $exitCode,
        ]);
    }
}
