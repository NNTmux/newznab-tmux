<?php

namespace App\Console\Commands;

use App\Models\Settings;
use App\Services\Tmux\TmuxSessionManager;
use Illuminate\Console\Command;
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
            // Get session name from option, database setting, or config
            $this->sessionName = $this->option('session')
                ?? Settings::settingValue('tmux_session')
                ?? config('tmux.session.default_name', 'nntmux');

            $this->sessionManager = new TmuxSessionManager($this->sessionName);

            // Use Laravel's built-in quiet mode check
            $quiet = $this->output->isQuiet();

            // Step 1: Check if tmux session exists
            if (! $this->sessionManager->sessionExists()) {
                if (! $quiet) {
                    $this->warn("⚠️  Tmux session '{$this->sessionName}' does not exist.");
                }


                return Command::FAILURE;
            }

            if (! $quiet) {
                $this->info("✅ Tmux session '{$this->sessionName}' exists.");
            }

            // Step 2: Check if monitor pane (0.0) is dead
            $monitorPaneDead = $this->isMonitorPaneDead();

            if ($monitorPaneDead) {
                if (! $quiet) {
                    $this->warn('⚠️  Monitor pane (0.0) is dead.');
                }

                if ($this->option('auto-restart')) {
                    return $this->restartTmux();
                }

                return Command::FAILURE;
            }

            if (! $quiet) {
                $this->info('✅ Monitor pane (0.0) is alive and running.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Health check failed: '.$e->getMessage());
            logger()->error('Tmux health check error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Check if the monitor pane (0.0) is dead.
     */
    private function isMonitorPaneDead(): bool
    {
        // Use tmux display-message to check pane_dead flag
        $result = Process::timeout(10)->run(
            "tmux display-message -p -t {$this->sessionName}:0.0 '#{pane_dead}'"
        );

        if (! $result->successful()) {
            // If we can't even query the pane, consider it dead
            return true;
        }

        $paneDeadFlag = trim($result->output());

        // tmux returns '1' if pane is dead, '0' if alive
        if ($paneDeadFlag === '1') {
            return true;
        }

        // Additional check: verify the pane is actually running a process
        $commandResult = Process::timeout(10)->run(
            "tmux display-message -p -t {$this->sessionName}:0.0 '#{pane_current_command}'"
        );

        if (! $commandResult->successful()) {
            return true;
        }

        $currentCommand = trim($commandResult->output());

        // If the pane is just showing a shell with no process, it might be considered "idle"
        // but not necessarily dead. The pane_dead flag is the authoritative check.
        return false;
    }

    /**
     * Restart the tmux session.
     */
    private function restartTmux(): int
    {
        $this->info('🔄 Restarting tmux session...');

        // First, stop the existing session if it exists
        if ($this->sessionManager->sessionExists()) {
            $this->info('⏹️  Stopping existing session...');
            $this->call('tmux:stop', [
                '--session' => $this->sessionName,
                '--force' => true,
            ]);

            // Give it a moment to clean up
            sleep(2);
        }

        // Start a new session
        $this->info('▶️  Starting new tmux session...');
        $exitCode = $this->call('tmux:start', [
            '--session' => $this->sessionName,
        ]);

        if ($exitCode === Command::SUCCESS) {
            $this->info("✅ Tmux session '{$this->sessionName}' restarted successfully.");
        } else {
            $this->error("❌ Failed to restart tmux session '{$this->sessionName}'.");
        }

        return $exitCode;
    }
}
