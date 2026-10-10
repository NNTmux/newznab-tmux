<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TmuxPaneRole;
use App\Models\Collection;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\Configuration\ProcessingRuntimeStateRepository;
use App\Services\Tmux\TmuxCommand;
use App\Services\Tmux\TmuxLayoutBuilder;
use App\Services\Tmux\TmuxPaneManager;
use App\Services\Tmux\TmuxSessionManager;
use App\Support\MetadataSources;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

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

    private bool $sessionCreated = false;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            cli()->header('Starting Tmux Processing');

            // Get session name
            $sessionName = $this->option('session')
                ?? app(ConfigurationProvider::class)->tmux()->sessionName;

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
                    if (! $this->confirm('Would you like to restart it?', false)) {
                        return Command::FAILURE;
                    }
                }
                if ($this->call('tmux:stop', ['--session' => $sessionName, '--force' => true]) !== Command::SUCCESS) {
                    $this->error('❌ Existing session could not stop safely. Restart cancelled.');

                    return Command::FAILURE;
                }
            }

            // Reset old collections
            $this->info('🔄 Resetting old collections...');
            $this->resetOldCollections();

            // Get sequential mode
            $sequential = app(ConfigurationProvider::class)->tmux()->sequentialMode;
            $this->info("📐 Building layout (mode: {$sequential})");

            // Build the tmux layout
            if (! $this->layoutBuilder->buildLayout($sequential)) {
                $this->error('❌ Failed to build tmux layout: '.($this->layoutBuilder->lastError() ?? 'unknown error'));

                return Command::FAILURE;
            }

            $this->info('✅ Tmux layout created');
            $this->sessionCreated = true;

            // Set running flag
            $runtimeState = app(ProcessingRuntimeStateRepository::class);
            $runtimeState->requestStop(false);
            $runtimeState->setTmuxRunning(true);
            $this->info('✅ Running flag set');

            foreach (MetadataSources::logSummary() as $line) {
                $this->warn('  ⚠ '.$line);
            }

            // Start monitor in background
            $this->info('🚀 Starting monitor...');
            $this->startMonitor($sessionName);

            // Select monitor pane so attach lands there
            $paneManager = new TmuxPaneManager($sessionName);
            if (! $paneManager->selectWindow(0)
                || ! $paneManager->selectPane($paneManager->paneForRole(TmuxPaneRole::Monitor, '0.0'))) {
                throw new \RuntimeException($paneManager->lastError() ?? 'Unable to select the tmux monitor pane.');
            }

            $this->info("✅ Tmux session '{$sessionName}' started successfully");

            // Attach if requested
            if ($this->option('attach')) {
                $this->info('📎 Attaching to session...');
                if (! $this->sessionManager->attachSession()) {
                    $this->error('❌ Unable to attach to the tmux session.');

                    return Command::FAILURE;
                }
            } else {
                $this->info('💡 To attach, run: php artisan tmux:attach --session='.escapeshellarg($sessionName));
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            if ($this->sessionCreated && isset($this->sessionManager)) {
                $this->sessionManager->killSession();
                app(ProcessingRuntimeStateRepository::class)->setTmuxRunning(false);
            }

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
        $result = Process::timeout(5)
            ->run('which tmux 2>/dev/null');

        return $result->successful() && str_contains($result->output(), 'tmux');
    }

    /**
     * Reset old collections
     */
    private function resetOldCollections(): void
    {
        $delayTime = app(ConfigurationProvider::class)->ingestion()->collectionDelayHours;

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
        $paneManager = new TmuxPaneManager($sessionName);

        $command = TmuxCommand::monitor($sessionName);
        $channel = 'nntmux-ready-'.bin2hex(random_bytes(12));
        $command[] = '--ready-channel='.$channel;

        $monitorPane = $paneManager->paneForRole(TmuxPaneRole::Monitor, '0.0');
        if (! $paneManager->respawnPane($monitorPane, $command)) {
            throw new \RuntimeException('Unable to start the tmux monitor pane.');
        }
        $ready = Process::timeout(30)->run(TmuxCommand::arguments(['wait-for', $channel]));
        if (! $ready->successful()) {
            throw new \RuntimeException('Tmux monitor did not acknowledge readiness.');
        }
    }
}
