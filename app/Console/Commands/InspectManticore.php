<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Search\Support\ManticoreClientFactory;
use App\Services\Search\Support\ManticoreIndexRegistry;
use App\Services\Search\Support\ManticoreSchemaInspector;
use Illuminate\Console\Command;

final class InspectManticore extends Command
{
    protected $signature = 'manticore:inspect {--json : Emit machine-readable JSON}';

    protected $description = 'Report Manticore version and table schema compatibility';

    public function handle(): int
    {
        try {
            $client = ManticoreClientFactory::make(config('search.drivers.manticore', []));
            $versionResponse = $client->sql('SELECT VERSION() AS version', true);
        } catch (\Throwable $e) {
            $this->error('Unable to connect to Manticore: '.$e->getMessage());

            return self::FAILURE;
        }

        $report = ['version' => self::extractVersion($versionResponse), 'compatible' => true, 'tables' => []];
        $configured = config('search.drivers.manticore.indexes', []);

        foreach (ManticoreIndexRegistry::definitions() as $logical => $definition) {
            $table = (string) ($configured[$logical] ?? $logical.'_rt');
            try {
                if (preg_match('/^[a-zA-Z0-9_]+$/', $table) !== 1) {
                    throw new \RuntimeException('Unsafe configured table name');
                }
                $actual = $client->table($table)->describe();
                $comparison = ManticoreSchemaInspector::compareColumns(is_array($actual) ? $actual : [], $definition['columns']);
                $actualSettings = $client->tables()->settings(['table' => $table]);
                $missingSettings = ManticoreSchemaInspector::missingSettings($actualSettings, ManticoreIndexRegistry::inspectableSettings());
                $needsRebuild = $comparison['missing'] !== [] || $comparison['incompatible'] !== [] || $missingSettings !== [];
                $report['tables'][$table] = [...$comparison, 'missing_settings' => $missingSettings, 'needs_rebuild' => $needsRebuild];
                $report['compatible'] = $report['compatible'] && ! $needsRebuild;
            } catch (\Throwable $e) {
                $report['tables'][$table] = ['missing_table' => true, 'needs_rebuild' => true, 'error' => $e->getMessage()];
                $report['compatible'] = false;
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Manticore version: '.$report['version']);
            foreach ($report['tables'] as $table => $status) {
                $this->line(sprintf('%-20s %s', $table, $status['needs_rebuild'] ? 'REBUILD REQUIRED' : 'compatible'));
            }
            if (! $report['compatible']) {
                $this->warn('Run manticore:create-indexes --drop --index=releases during a maintenance window for release-only schema changes, then repopulate the affected index.');
            }
        }

        return $report['compatible'] ? self::SUCCESS : self::FAILURE;
    }

    private static function extractVersion(mixed $response): string
    {
        $encoded = json_encode($response);
        if (is_string($encoded) && preg_match('/\d+\.\d+(?:\.\d+)?/', $encoded, $match) === 1) {
            return $match[0];
        }

        return 'unknown';
    }
}
