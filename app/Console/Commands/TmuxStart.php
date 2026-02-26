<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\Settings;
use App\Services\Tmux\TmuxLayoutBuilder;
use App\Services\Tmux\TmuxSessionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TmuxStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux:start
                            {--session= : Tmux session name}
                            {--force : Force start even if session exists}
                            {--attach : Attach to session after starting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start tmux processing session (modernized)';

    private TmuxSessionManager $sessionManager;

    private TmuxLayoutBuilder $layoutBuilder;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            cli()->header('Starting Tmux Processing');

            // Get session name
            $sessionName = $this->option('session')
                ?? Settings::settingValue('tmux_session')
                ?? config('tmux.session.default_name', 'nntmux');

            // Initialize services
            $this->sessionManager = new TmuxSessionManager($sessionName);
            $this->layoutBuilder = new TmuxLayoutBuilder($this->sessionManager);

            // Check if tmux is installed
            if (! $this->checkTmuxInstalled()) {
                $this->error('❌ tmux is not installed');

                return Command::FAILURE;
            }

            // Check if session already exists
            if ($this->sessionManager->sessionExists()) {
                if (! $this->option('force')) {
                    $this->error("❌ Session '{$sessionName}' already exists");
                    if ($this->confirm('Would you like to restart it?', false)) {
                        $this->call('tmux:stop', ['--session' => $sessionName]);
                        sleep(2);
                    } else {
                        return Command::FAILURE;
                    }
                } else {
                    $this->call('tmux:stop', ['--session' => $sessionName]);
                    sleep(2);
                }
            }

            // Reset old collections
            $this->info('🔄 Resetting old collections...');
            $this->resetOldCollections();

            // Get sequential mode
            $sequential = (int) (Settings::settingValue('sequential') ?? 0);
            $this->info("📐 Building layout (mode: {$sequential})");

            // Build the tmux layout
            if (! $this->layoutBuilder->buildLayout($sequential)) {
                $this->error('❌ Failed to build tmux layout');

                return Command::FAILURE;
            }

            $this->info('✅ Tmux layout created');

            // Set running flag
            Settings::query()->where('name', 'running')->update(['value' => 1]);
            $this->info('✅ Running flag set');

            // Start monitor in background
            $this->info('🚀 Starting monitor...');
            $this->startMonitor($sessionName);

            // Select monitor pane (0.0) so attach lands there
            $paneManager = new \App\Services\Tmux\TmuxPaneManager($sessionName);
            $paneManager->selectWindow(0);
            $paneManager->selectPane('0.0');

            $this->info("✅ Tmux session '{$sessionName}' started successfully");

            // Attach if requested
            if ($this->option('attach')) {
                $this->info('📎 Attaching to session...');
                $this->sessionManager->attachSession();
            } else {
                $this->info("💡 To attach to the session, run: tmux attach -t {$sessionName}");
                $this->info('💡 Or use: php artisan tmux:attach');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to start tmux: '.$e->getMessage());
            logger()->error('Tmux start error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Check if tmux is installed
     */
    private function checkTmuxInstalled(): bool
    {
        $result = \Illuminate\Support\Facades\Process::timeout(5)
            ->run('which tmux 2>/dev/null');

        return $result->successful() && str_contains($result->output(), 'tmux');
    }

    /**
     * Reset old collections
     */
    private function resetOldCollections(): void
    {
        $delayTime = (int) (Settings::settingValue('delaytime') ?? 2);

        try {
            DB::transaction(function () use ($delayTime) {
                $count = Collection::query()
                    ->where('dateadded', '<', now()->subHours($delayTime))
                    ->update(['dateadded' => now()]);

                if ($count > 0) {
                    $this->info("  ✓ Reset {$count} expired collections");
                } else {
                    $this->info('  ✓ No collections needed resetting');
                }
            }, 10);

        } catch (\Exception $e) {
            $this->warn('  ⚠ Failed to reset collections: '.$e->getMessage());
        }
    }

    /**
     * Start the monitor process
     */
    private function startMonitor(string $sessionName): void
    {
        $paneManager = new \App\Services\Tmux\TmuxPaneManager($sessionName);

        // Priority: new monitor > old monitor > artisan command
        $newMonitor = base_path('app/Services/Tmux/Scripts/monitor.php');
        $oldMonitor = base_path('misc/update/tmux/monitor.php');

        if (file_exists($newMonitor)) {
            // Use the new modernized monitor script
            $command = "php {$newMonitor}";
            $this->info('  ✓ Using modernized monitor script');
        } elseif (file_exists($oldMonitor)) {
            // Fall back to original monitor script
            $command = "php {$oldMonitor}";
            $this->warn('  ⚠ Using legacy monitor.php (consider updating)');
        } else {
            // Last resort: use artisan command (not ideal for pane)
            $artisan = base_path('artisan');
            $command = "php {$artisan} tmux:monitor --session={$sessionName}";
            $this->warn('  ⚠ Using artisan command (not recommended for pane)');
        }

        // Spawn monitor in pane 0.0
        $paneManager->respawnPane('0.0', $command);
    }
}
