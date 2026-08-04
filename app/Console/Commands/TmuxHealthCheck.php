<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TmuxPaneRole;
use App\Models\Settings;
use App\Services\Tmux\TmuxPaneManager;
use App\Services\Tmux\TmuxSessionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class TmuxHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux:health-check
                            {--session= : Tmux session name}
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
                ?? Settings::settingValue('tmux_session')
                ?? config('tmux.session.default_name', 'nntmux');

            $this->sessionManager = new TmuxSessionManager($this->sessionName);
            $quiet = $this->output->isQuiet();
            $shouldBeRunning = $this->shouldBeRunning();
            $autoRestart = (bool) $this->option('auto-restart');

            if (! $this->sessionManager->sessionExists()) {
                if (! $quiet) {
                    $this->warn("⚠️  Tmux session '{$this->sessionName}' does not exist.");
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

            if (! $quiet) {
                $this->info("✅ Tmux session '{$this->sessionName}' exists.");
            }

            $monitorPaneDead = $this->isMonitorPaneDead();

            if ($monitorPaneDead) {
                if (! $quiet) {
                    $this->warn('⚠️  Monitor pane is dead.');
                }

                if ($autoRestart) {
                    $this->logHealthCheck('warning', 'monitor_dead_restart_attempted');

                    return $this->restartTmux();
                }

                $this->logHealthCheck('warning', 'monitor_dead_unrecovered');

                return Command::FAILURE;
            }

            if (! $quiet) {
                $this->info('✅ Monitor pane is alive and running.');
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

        $result = Process::timeout(10)->run(
            ['tmux', 'display-message', '-p', '-t', $monitorPane, '#{pane_dead}']
        );

        if (! $result->successful()) {
            return true;
        }

        $paneDeadFlag = trim($result->output());

        if ($paneDeadFlag === '1') {
            return true;
        }

        $commandResult = Process::timeout(10)->run(
            ['tmux', 'display-message', '-p', '-t', $monitorPane, '#{pane_current_command}']
        );

        if (! $commandResult->successful()) {
            return true;
        }

        return false;
    }

    private function shouldBeRunning(): bool
    {
        return filter_var(Settings::settingValue('running'), FILTER_VALIDATE_BOOL);
    }

    /**
     * Restart the tmux session.
     */
    private function restartTmux(): int
    {
        $this->info('🔄 Restarting tmux session...');

        if ($this->sessionManager->sessionExists()) {
            $this->info('⏹️  Stopping existing session...');
            $this->call('tmux:stop', [
                '--session' => $this->sessionName,
                '--force' => true,
            ]);

            sleep(2);
        }

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
