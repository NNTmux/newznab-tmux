<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AdditionalProcessing\AdditionalProcessingDiagnostics;
use Illuminate\Console\Command;

class AdditionalProcessingDiagnose extends Command
{
    protected $signature = 'nntmux:additional-diagnose
                                {--json : Output machine-readable JSON}';

    protected $description = 'Inspect additional post-processing backlog, claims, capacity, indexes, and temp-path health';

    public function __construct(private readonly AdditionalProcessingDiagnostics $diagnostics)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->diagnostics->inspect();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->table(['Backlog', 'Count'], [
            ['Total', (string) $report['backlog']['total']],
            ['Available', (string) $report['backlog']['available']],
            ['Active claims', (string) $report['backlog']['active_claims']],
            ['Stale claims', (string) $report['backlog']['stale_claims']],
        ]);
        $this->table(['Capacity', 'Value'], [
            ['Pipeline', (string) $report['settings']['pipeline']],
            ['Threads', (string) $report['capacity']['threads']],
            ['Releases per batch', (string) $report['capacity']['releases_per_batch']],
            ['Maximum in flight', (string) $report['capacity']['max_in_flight']],
            ['Claim TTL', $report['claims']['ttl_seconds'].' seconds'],
            ['Child timeout', $report['settings']['multiprocessing_child_timeout'].' seconds'],
        ]);
        $this->table(['Preflight', 'Value'], [
            ['Database driver', (string) $report['database_driver']],
            ['Covering claim index', (string) ($report['indexes']['covering_index'] ?? 'missing')],
            ['Temp path', (string) $report['temp_path']['path']],
            ['Temp path writable', $report['temp_path']['writable'] ? 'yes' : 'no'],
        ]);

        if ($report['warnings'] === []) {
            $this->info('No additional-processing diagnostic warnings were found.');

            return self::SUCCESS;
        }

        $this->newLine();
        foreach ($report['warnings'] as $warning) {
            $this->warn('['.$warning['code'].'] '.$warning['message']);
        }

        return self::SUCCESS;
    }
}
