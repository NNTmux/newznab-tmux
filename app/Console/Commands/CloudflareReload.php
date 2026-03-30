<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Cloudflare\CloudflareIpRangeService;
use Illuminate\Console\Command;
use Throwable;

class CloudflareReload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cloudflare:reload
                            {--dry-run : Fetch and validate the ranges without updating the stored manifest}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the Cloudflare IP ranges used by the trusted proxy middleware';

    public function __construct(
        private readonly CloudflareIpRangeService $cloudflareIpRangeService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'Fetching and validating Cloudflare trusted proxy IP ranges (dry run)...'
            : 'Refreshing Cloudflare trusted proxy IP ranges...');

        try {
            $manifest = $this->cloudflareIpRangeService->refresh(! $dryRun);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Failed to refresh Cloudflare IP ranges: '.$exception->getMessage());

            return Command::FAILURE;
        }

        $manualProxies = $this->cloudflareIpRangeService->manualTrustedProxies();
        $totalTrustedProxies = array_values(array_unique([
            ...$manualProxies,
            ...($manifest['proxies'] ?? []),
        ]));

        $this->line('Cloudflare IPv4 ranges: '.count($manifest['ipv4'] ?? []));
        $this->line('Cloudflare IPv6 ranges: '.count($manifest['ipv6'] ?? []));
        $this->line('Combined Cloudflare proxy count: '.count($manifest['proxies'] ?? []));
        $this->line('Manual proxy count: '.count($manualProxies));
        $this->line('Effective trusted proxy count: '.count($totalTrustedProxies));

        if ($dryRun) {
            $this->info('Dry run complete; manifest file was not updated.');

            return Command::SUCCESS;
        }

        $this->info('Stored Cloudflare manifest: '.$this->cloudflareIpRangeService->manifestPath());
        $this->info('Last updated at: '.($manifest['updated_at'] ?? 'unknown'));

        return Command::SUCCESS;
    }
}
