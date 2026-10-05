<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TmuxMode;
use App\Enums\TmuxPaneRole;
use App\Models\Settings;
use App\Services\Tmux\TmuxPaneManager;
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
                            {--force : Stop without confirmation}
                            {--timeout=5 : Maximum seconds for interrupted workers to clean up before closing the session}';

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
                ?? config('tmux.session.name')
                ?? config('tmux.session.default_name', 'nntmux');

            $this->sessionManager = new TmuxSessionManager($sessionName);

            // Check if session exists
            if (! $this->sessionManager->sessionExists()) {
                Settings::query()->where('name', 'running')->update(['value' => 0]);
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
            Settings::query()->updateOrCreate(['name' => 'running'], ['value' => 0]);
            $this->info('✅ Running flag cleared');

            Settings::query()->updateOrCreate(['name' => 'exit'], ['value' => 1]);
            $panes = new TmuxPaneManager($sessionName);
            $workerRoles = array_merge(array_values(TmuxMode::Full->tasks()), [TmuxPaneRole::Sequential, TmuxPaneRole::Monitor]);
            $timeout = max(0, (int) $this->option('timeout'));
            $deadline = microtime(true) + $timeout;
            $interrupted = [];
            $this->info('Interrupting processing and cleaning up workers...');
            do {
                $panes->refresh();
                $active = array_filter($panes->paneSnapshot(), static fn (array $state): bool => ! $state['dead']
                    && in_array(TmuxPaneRole::tryFrom($state['role']), $workerRoles, true));
                if ($active === []) {
                    break;
                }
                foreach (array_keys($active) as $pane) {
                    if (! isset($interrupted[$pane])) {
                        $panes->sendKeys($pane, 'C-c', enter: false);
                        $interrupted[$pane] = true;
                    }
                }
                if (microtime(true) >= $deadline) {
                    $this->warn('Cleanup timeout reached; closing the processing session.');

                    break;
                }
                usleep(250000);
            } while (true);

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
