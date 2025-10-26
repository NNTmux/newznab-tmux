<?php

namespace App\Console\Commands;

use App\Services\SystemMetricsService;
use Illuminate\Console\Command;

class CollectSystemMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:collect {--cleanup : Clean up old metrics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect and store current system metrics (CPU and RAM usage)';

    protected SystemMetricsService $metricsService;

    /**
     * Create a new command instance.
     */
    public function __construct(SystemMetricsService $metricsService)
    {
        parent::__construct();
        $this->metricsService = $metricsService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            if ($this->option('cleanup')) {
                $this->info('Cleaning up old metrics...');
                $deleted = $this->metricsService->cleanupOldMetrics();
                $this->info("Deleted {$deleted} old metric records.");
            }

            $this->info('Collecting system metrics...');
            $this->metricsService->collectMetrics();
            $this->info('System metrics collected successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to collect system metrics: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
