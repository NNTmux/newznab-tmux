<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\Settings;
use App\Services\Tmux\TmuxMonitorService;
use App\Services\Tmux\TmuxTaskRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TmuxMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux:monitor
                            {--session= : Tmux session name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor and manage tmux processing panes';

    private TmuxMonitorService $monitor;

    private TmuxTaskRunner $taskRunner;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Reset old collections
            $this->resetOldCollections();

            $sessionName = $this->option('session') ?? Settings::settingValue('tmux_session') ?? 'newznab';

            // Initialize services
            $this->monitor = new TmuxMonitorService;
            $this->taskRunner = new TmuxTaskRunner($sessionName);

            // Initialize monitor
            $runVar = $this->monitor->initializeMonitor();

            cli()->header('Starting Tmux Monitor');
            $this->info("Monitoring session: {$sessionName}");

            // Main monitoring loop
            while ($this->monitor->shouldContinue()) {
                // Collect statistics
                $runVar = $this->monitor->collectStatistics();

                // Update display
                $this->monitor->updateDisplay();

                // Run pane tasks if tmux is running
                if ((int) ($runVar['settings']['is_running'] ?? 0) === 1) {
                    $this->runPaneTasks($runVar);
                } else {
                    $this->info('Tmux is not running. Waiting...');
                }

                // Increment iteration and sleep
                $this->monitor->incrementIteration();
                sleep(10);
            }

            $this->info('Monitor stopped by exit flag');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Monitor failed: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Reset old collections based on delay time
     */
    private function resetOldCollections(): void
    {
        $delayTime = Settings::settingValue('delaytime') ?? 2;

        cli()->header('Resetting expired collections. This may take some time...');

        try {
            DB::transaction(function () use ($delayTime) {
                Collection::query()
                    ->where('dateadded', '<', now()->subHours($delayTime))
                    ->update(['dateadded' => now()]);
            }, 10);

            $this->info('Collections reset complete');
        } catch (\Exception $e) {
            $this->error('Failed to reset collections: '.$e->getMessage());
        }
    }

    /**
     * Run tasks in panes based on configuration
     */
    private function runPaneTasks(array $runVar): void
    {
        $sequential = (int) ($runVar['constants']['sequential'] ?? 0);

        // Define pane tasks based on sequential mode
        $paneTasks = $this->getPaneTasks($sequential);

        foreach ($paneTasks as $taskName => $config) {
            try {
                $this->taskRunner->runPaneTask($taskName, $config, $runVar);
            } catch (\Exception $e) {
                $this->error("Failed to run {$taskName}: ".$e->getMessage());
            }
        }
    }

    /**
     * Get pane tasks based on sequential mode
     */
    private function getPaneTasks(int $sequential): array
    {
        return match ($sequential) {
            1 => $this->getSequentialBasicTasks(),
            2 => $this->getSequentialFullTasks(),
            default => $this->getStandardTasks(),
        };
    }

    /**
     * Get standard (non-sequential) tasks
     */
    private function getStandardTasks(): array
    {
        return [
            'main' => ['target' => '0.1'], // Handles binaries, backfill, and releases
            'fixnames' => ['target' => '1.0'],
            'removecrap' => ['target' => '1.1'],
            'ppadditional' => ['target' => '2.0'],
            'tv' => ['target' => '2.1'],
            'movies' => ['target' => '2.2'],
            'amazon' => ['target' => '2.3'],
            'scraper' => ['target' => '3.0'],
        ];
    }

    /**
     * Get sequential basic tasks
     */
    private function getSequentialBasicTasks(): array
    {
        return [
            'main' => ['target' => '0.1'],
            'amazon' => ['target' => '2.3'],
            'scraper' => ['target' => '3.0'],
            'fixnames' => ['target' => '1.0'],
            'removecrap' => ['target' => '1.1'],
            'ppadditional' => ['target' => '2.0'],
            'tv' => ['target' => '2.1'],
            'movies' => ['target' => '2.2'],
        ];
    }

    /**
     * Get sequential full tasks
     */
    private function getSequentialFullTasks(): array
    {
        return [
            'main' => ['target' => '0.1'],
            'amazon' => ['target' => '0.2'],
            'scraper' => ['target' => '3.0'],
        ];
    }
}
