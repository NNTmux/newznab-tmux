<?php

namespace App\Console\Commands;

use App\Facades\Elasticsearch;
use App\Facades\Search;
use App\Models\Predb;
use App\Models\Release;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NntmuxOffsetWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:offset-worker
                                       {--manticore : Use ManticoreSearch}
                                       {--elastic : Use ElasticSearch}
                                       {--releases : Populates the releases index}
                                       {--predb : Populates the predb index}
                                       {--offset=0 : Start offset}
                                       {--limit=0 : Number of records to process}
                                       {--worker-id=0 : Worker ID}
                                       {--batch-size=10000 : Batch size for bulk operations}
                                       {--disable-keys : Disable database keys}
                                       {--memory-limit=4G : Memory limit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Offset-based worker process for parallel index population';

    private const GROUP_CONCAT_MAX_LEN = 65535;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $workerId = (int) $this->option('worker-id');
        $offset = (int) $this->option('offset');
        $limit = (int) $this->option('limit');

        $this->info("Worker {$workerId} starting: processing {$limit} records from offset {$offset}");

        try {
            $this->optimizeWorkerSettings();

            $engine = $this->getSelectedEngine();
            $index = $this->getSelectedIndex();

            if (! $engine || ! $index) {
                $this->error('Engine and index must be specified');

                return Command::FAILURE;
            }

            $result = $this->processOffset($engine, $index, $offset, $limit, $workerId);

            if ($result === Command::SUCCESS) {
                $this->info("Worker {$workerId} completed successfully");
            }

            return $result;

        } catch (Exception $e) {
            $this->error("Worker {$workerId} failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Process a specific offset range of records
     */
    private function processOffset(string $engine, string $index, int $offset, int $limit, int $workerId): int
    {
        $batchSize = (int) $this->option('batch-size');
        $query = $this->buildOffsetQuery($index, $offset, $limit);
        $transformer = $this->getTransformer($engine, $index);

        $indexName = $this->getIndexName($engine, $index);
        $processed = 0;
        $errors = 0;

        $this->info("Worker {$workerId}: Processing {$limit} records with batch size {$batchSize}");
        $startTime = microtime(true);

        if ($engine === 'manticore') {
            $batchData = [];

            $query->chunk($batchSize, function ($items) use ($transformer, &$processed, &$errors, &$batchData, $batchSize, $workerId) {
                $this->info("Worker {$workerId}: Processing chunk of {$items->count()} items");

                foreach ($items as $item) {
                    try {
                        $batchData[] = $transformer($item);
                        $processed++;

                        if (count($batchData) >= $batchSize) {
                            $this->processSearchBatch($batchData, $workerId); // @phpstan-ignore argument.type
                            $this->info("Worker {$workerId}: Inserted batch of ".count($batchData).' records');
                            $batchData = [];
                        }
                    } catch (Exception $e) {
                        $errors++;
                        $this->error("Worker {$workerId}: Error processing item {$item->id}: {$e->getMessage()}");
                    }
                }
            });

            // Process remaining items
            if (! empty($batchData)) {
                $this->processSearchBatch($batchData, $workerId); // @phpstan-ignore argument.type
                $this->info("Worker {$workerId}: Inserted final batch of ".count($batchData).' records');
            }

        } else { // ElasticSearch
            $query->chunk($batchSize, function ($items) use ($indexName, $transformer, &$processed, &$errors, $workerId) {
                $this->info("Worker {$workerId}: Processing chunk of {$items->count()} items");
                $data = ['body' => []];

                foreach ($items as $item) {
                    try {
                        $transformedData = $transformer($item);

                        $data['body'][] = [
                            'index' => [
                                '_index' => $indexName,
                                '_id' => $item->id,
                            ],
                        ];
                        $data['body'][] = $transformedData;

                        $processed++;
                    } catch (Exception $e) {
                        $errors++;
                        $this->error("Worker {$workerId}: Error processing item {$item->id}: {$e->getMessage()}");
                    }
                }

                if (! empty($data['body'])) {
                    $this->processElasticBatch($data, $workerId);
                    $this->info("Worker {$workerId}: Inserted batch of ".(count($data['body']) / 2).' records');
                }
            });
        }

        $executionTime = round(microtime(true) - $startTime, 2);
        $this->info("Worker {$workerId}: Completed processing {$processed} records with {$errors} errors in {$executionTime} seconds");

        return Command::SUCCESS;
    }

    /**
     * Build offset-based query
     */
    private function buildOffsetQuery(string $index, int $offset, int $limit): mixed
    {
        if ($index === 'releases') {
            return Release::query()
                ->select([
                    'releases.id',
                    'releases.name',
                    'releases.searchname',
                    'releases.fromname',
                    'releases.categories_id',
                    'releases.postdate',
                ])
                ->selectRaw('(SELECT GROUP_CONCAT(rf.name SEPARATOR " ") FROM release_files rf WHERE rf.releases_id = releases.id) AS filename')
                ->orderBy('releases.id')
                ->offset($offset)
                ->limit($limit);
        } else {
            return Predb::query()
                ->select(['id', 'title', 'filename', 'source'])
                ->orderBy('id')
                ->offset($offset)
                ->limit($limit);
        }
    }

    /**
     * Get transformer function
     */
    private function getTransformer(string $engine, string $index): callable
    {
        if ($index === 'releases') {
            if ($engine === 'manticore') {
                return function ($item) {
                    return [
                        'id' => (string) $item->id,
                        'name' => (string) ($item->name ?: ''),
                        'searchname' => (string) ($item->searchname ?: ''),
                        'fromname' => (string) ($item->fromname ?: ''),
                        'categories_id' => (string) ($item->categories_id ?: '0'),
                        'filename' => (string) ($item->filename ?: ''),
                        'dummy' => '1',
                    ];
                };
            } else {
                return function ($item) {
                    $searchName = str_replace(['.', '-'], ' ', $item->searchname ?? '');

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'searchname' => $item->searchname,
                        'plainsearchname' => $searchName,
                        'fromname' => $item->fromname,
                        'categories_id' => $item->categories_id,
                        'filename' => $item->filename ?? '',
                        'postdate' => $item->postdate,
                    ];
                };
            }
        } else { // predb
            return function ($item) {
                return [
                    'id' => $item->id,
                    'title' => (string) ($item->title ?? ''),
                    'filename' => (string) ($item->filename ?? ''),
                    'source' => (string) ($item->source ?? ''),
                    'dummy' => 1,
                ];
            };
        }
    }

    /**
     * Get index name
     */
    private function getIndexName(string $engine, string $index): string
    {
        if ($engine === 'manticore') {
            return $index === 'releases' ? 'releases_rt' : 'predb_rt';
        } else {
            return $index;
        }
    }

    /**
     * Process search batch
     *
     * @param  array<string, mixed>  $data
     */
    private function processSearchBatch(array $data, int $workerId): void
    {
        $retries = 3;
        $attempt = 0;

        while ($attempt < $retries) {
            try {
                Search::bulkInsertReleases($data);
                break;
            } catch (Exception $e) {
                $attempt++;
                if ($attempt >= $retries) {
                    throw new Exception("Worker {$workerId}: Failed to process search batch after {$retries} attempts: {$e->getMessage()}");
                }
                usleep(200000); // 200ms delay before retry
            }
        }
    }

    /**
     * Process ElasticSearch batch
     *
     * @param  array<string, mixed>  $data
     */
    private function processElasticBatch(array $data, int $workerId): void
    {
        $retries = 3;
        $attempt = 0;

        while ($attempt < $retries) {
            try {
                $response = Elasticsearch::bulk($data);

                if (isset($response['errors']) && $response['errors']) {
                    $errorCount = 0;
                    foreach ($response['items'] as $item) {
                        if (isset($item['index']['error'])) {
                            $errorCount++;
                        }
                    }
                    if ($errorCount > 0) {
                        $this->warn("Worker {$workerId}: ElasticSearch batch had {$errorCount} errors");
                    }
                }
                break;
            } catch (Exception $e) {
                $attempt++;
                if ($attempt >= $retries) {
                    throw new Exception("Worker {$workerId}: Failed to process ElasticSearch batch after {$retries} attempts: {$e->getMessage()}");
                }
                usleep(200000); // 200ms delay before retry
            }
        }
    }

    /**
     * Optimize worker settings
     */
    private function optimizeWorkerSettings(): void
    {
        // Set memory limit
        ini_set('memory_limit', $this->option('memory-limit'));

        // Database optimizations
        if ($this->option('disable-keys')) {
            try {
                DB::statement('SET SESSION FOREIGN_KEY_CHECKS = 0');
                DB::statement('SET SESSION UNIQUE_CHECKS = 0');
                DB::statement('SET SESSION AUTOCOMMIT = 0');
                DB::statement('SET SESSION read_buffer_size = 2097152');
                DB::statement('SET SESSION sort_buffer_size = 16777216');
            } catch (Exception $e) {
                $this->warn("Could not optimize database settings: {$e->getMessage()}");
            }
        }

        // Set GROUP_CONCAT max length
        DB::statement('SET SESSION group_concat_max_len = ?', [self::GROUP_CONCAT_MAX_LEN]);

        // PHP optimizations
        gc_enable();
    }

    /**
     * Get selected engine
     */
    private function getSelectedEngine(): ?string
    {
        return $this->option('manticore') ? 'manticore' : ($this->option('elastic') ? 'elastic' : null);
    }

    /**
     * Get selected index
     */
    private function getSelectedIndex(): ?string
    {
        return $this->option('releases') ? 'releases' : ($this->option('predb') ? 'predb' : null);
    }
}
