<?php

namespace App\Console\Commands;

use App\Models\Settings;
use App\Services\Tmux\TmuxSessionManager;
use Illuminate\Console\Command;

class TmuxStop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux:stop
                            {--session= : Tmux session name}
                            {--force : Force stop without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stop tmux processing session (modernized)';

    private TmuxSessionManager $sessionManager;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {

        try {
            // Get session name
            $sessionName = $this->option('session')
                ?? Settings::settingValue('tmux_session')
                ?? config('tmux.session.default_name', 'nntmux');

            $this->sessionManager = new TmuxSessionManager($sessionName);

            // Check if session exists
            if (! $this->sessionManager->sessionExists()) {
                $this->warn("⚠️  Session '{$sessionName}' is not running");

                return Command::SUCCESS;
            }

            // Confirm unless forced
            if (! $this->option('force')) {
                if (! $this->confirm("Stop tmux session '{$sessionName}'?", true)) {
                    $this->info('Cancelled');

                    return Command::SUCCESS;
                }
            }

            cli()->header('Stopping Tmux Session');

            // Set running flag to 0
            Settings::query()->where('name', 'running')->update(['value' => 0]);
            $this->info('✅ Running flag cleared');

            // Wait for panes to shut down gracefully
            $delay = (int) (Settings::settingValue('monitor_delay') ?? 10);
            $this->info("⏳ Waiting {$delay} seconds for panes to shut down gracefully...");
            sleep($delay);

            // Kill the session
            if ($this->sessionManager->killSession()) {
                $this->info("✅ Session '{$sessionName}' stopped successfully");

                return Command::SUCCESS;
            } else {
                $this->error("❌ Failed to stop session '{$sessionName}'");

                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('❌ Failed to stop tmux: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
