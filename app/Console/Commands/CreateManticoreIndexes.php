<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Search\Support\ManticoreClientFactory;
use App\Services\Search\Support\ManticoreIndexRegistry;
use Illuminate\Console\Command;
use Manticoresearch\Client;
use Manticoresearch\Exceptions\ResponseException;

class CreateManticoreIndexes extends Command
{
    protected $signature = 'manticore:create-indexes
                            {--drop : Drop selected indexes before creating}
                            {--index=* : Logical index name(s) to create; defaults to all indexes}';

    protected $description = 'Create Manticore Search indexes based on configuration';

    protected Client $client;

    public function handle(): int
    {
        $this->info('Creating Manticore Search indexes...');
        $this->client = ManticoreClientFactory::make(config('search.drivers.manticore', []));

        try {
            $this->client->nodes()->status();
        } catch (\Throwable $e) {
            $this->error('Failed to connect to Manticore Search: '.$e->getMessage());
            $this->info('Check the configured host, authentication, and whether Manticore is running.');

            return self::FAILURE;
        }

        $configuredNames = config('search.drivers.manticore.indexes', []);
        $selected = array_values(array_filter(array_map('strval', (array) $this->option('index'))));
        $definitions = ManticoreIndexRegistry::definitions();
        if ($selected !== []) {
            $unknown = array_diff($selected, array_keys($definitions));
            if ($unknown !== []) {
                $this->error('Unknown logical index: '.implode(', ', $unknown));

                return self::FAILURE;
            }
            $definitions = array_intersect_key($definitions, array_flip($selected));
        }
        $hasErrors = false;
        foreach ($definitions as $logical => $schema) {
            $indexName = (string) ($configuredNames[$logical] ?? $logical.'_rt');
            if (! $this->createIndex($indexName, $schema, (bool) $this->option('drop'))) {
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            $this->error('Some Manticore tables could not be created.');

            return self::FAILURE;
        }

        $this->info('All Manticore Search tables are ready.');

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $schema */
    protected function createIndex(string $indexName, array $schema, bool $dropExisting): bool
    {
        $tables = $this->client->tables();

        try {
            if ($dropExisting) {
                $this->info("Dropping {$indexName}...");
                $tables->drop(['index' => $indexName, 'body' => ['silent' => true]]);
            }

            $tables->create(['index' => $indexName, 'body' => $schema]);
            $this->info("Created {$indexName}.");

            return true;
        } catch (ResponseException $e) {
            if (str_contains($e->getMessage(), 'already exists')) {
                $this->warn("{$indexName} already exists; use --drop during a maintenance rebuild.");

                return true;
            }

            if (str_contains($e->getMessage(), 'data_dir')) {
                $this->error("Failed to create {$indexName}: configure a writable Manticore data_dir.");
            } else {
                $this->error("Failed to create {$indexName}: ".$e->getMessage());
            }

            return false;
        } catch (\Throwable $e) {
            $this->error("Failed to create {$indexName}: ".$e->getMessage());

            return false;
        }
    }
}
