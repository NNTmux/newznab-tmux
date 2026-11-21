<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LoginAttempt;
use App\Services\Security\IpBlockService;
use App\Services\Security\LoginAbuseDetector;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'ip-block:scan')]
class ScanLoginAbuse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ip-block:scan
                            {--dry-run : List the addresses that would be blocked without blocking them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Block addresses whose recorded sign-in attempts target several accounts that do not exist';

    public function __construct(
        private readonly LoginAbuseDetector $detector,
        private readonly IpBlockService $blocks,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = LoginAttempt::query()
            ->where('successful', false)
            ->whereNull('authenticatable_id')
            ->whereNotNull('ip_address')
            ->where('created_at', '>', now()->subHours($this->detector->windowHours()))
            ->toBase()
            ->distinct()
            ->get(['ip_address', 'device_fingerprint']);

        /** @var array<string, array{string, int, string}> $rows */
        $rows = [];

        foreach ($candidates as $candidate) {
            $ip = (string) $candidate->ip_address;
            $finding = $this->detector->inspect($ip, $candidate->device_fingerprint);
            if ($finding === null) {
                continue;
            }

            $created = $dryRun ? [] : array_map(
                static fn ($block): string => $block->ip_address,
                $this->detector->evaluate($ip, $candidate->device_fingerprint),
            );

            foreach ($finding['ips'] as $address) {
                $address = $this->blocks->normalize($address) ?? $address;
                $rows[$address] ??= [$address, count($finding['identifiers']), match (true) {
                    in_array($address, $created, true) => 'blocked',
                    $this->blocks->isProtected($address) => 'protected, never blocked',
                    $this->blocks->isBlocked($address) => 'already blocked',
                    default => 'would be blocked',
                }];
            }
        }

        if ($rows === []) {
            $this->info('No address crossed the threshold.');

            return Command::SUCCESS;
        }

        $this->table(['IP address', 'Non-existent accounts tried', 'Result'], array_values($rows));

        return Command::SUCCESS;
    }
}
