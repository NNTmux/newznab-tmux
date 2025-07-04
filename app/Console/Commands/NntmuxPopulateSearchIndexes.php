<?php

namespace App\Console\Commands;

use App\Models\Predb;
use App\Models\Release;
use Blacklight\ManticoreSearch;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class NntmuxPopulateSearchIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:populate
                                       {--manticore : Use ManticoreSearch}
                                       {--elastic : Use ElasticSearch}
                                       {--releases : Populates the releases index}
                                       {--predb : Populates the predb index}
                                       {--count=20000 : Sets the chunk size}
                                       {--optimize : Optimize ManticoreSearch indexes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate Manticore/Elasticsearch indexes with either releases or predb';

    private const SUPPORTED_ENGINES = ['manticore', 'elastic'];

    private const SUPPORTED_INDEXES = ['releases', 'predb'];

    private const GROUP_CONCAT_MAX_LEN = 16384;

    private const DEFAULT_CHUNK_SIZE = 20000;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            if ($this->option('optimize')) {
                return $this->handleOptimize();
            }

            $engine = $this->getSelectedEngine();
            $index = $this->getSelectedIndex();

            if (! $engine || ! $index) {
                $this->error('You must specify both an engine (--manticore or --elastic) and an index (--releases or --predb).');
                $this->info('Use --help to see all available options.');

                return Command::FAILURE;
            }

            return $this->populateIndex($engine, $index);

        } catch (Exception $e) {
            $this->error("An error occurred: {$e->getMessage()}");

            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Get the selected search engine from options
     */
    private function getSelectedEngine(): ?string
    {
        foreach (self::SUPPORTED_ENGINES as $engine) {
            if ($this->option($engine)) {
                return $engine;
            }
        }

        return null;
    }

    /**
     * Get the selected index from options
     */
    private function getSelectedIndex(): ?string
    {
        foreach (self::SUPPORTED_INDEXES as $index) {
            if ($this->option($index)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Handle the optimize command
     */
    private function handleOptimize(): int
    {
        $this->info('Optimizing ManticoreSearch indexes...');

        try {
            (new ManticoreSearch)->optimizeRTIndex();
            $this->info('Optimization completed successfully!');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Optimization failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Populate the specified index with the specified engine
     */
    private function populateIndex(string $engine, string $index): int
    {
        $methodName = "{$engine}".ucfirst($index);

        if (! method_exists($this, $methodName)) {
            $this->error("Method {$methodName} not implemented.");

            return Command::FAILURE;
        }

        $this->info("Starting {$engine} {$index} population...");

        $startTime = microtime(true);
        $result = $this->{$methodName}();
        $executionTime = round(microtime(true) - $startTime, 2);

        if ($result === Command::SUCCESS) {
            $this->info("Population completed in {$executionTime} seconds.");
        }

        return $result;
    }

    private function manticoreReleases(): int
    {
        $manticore = new ManticoreSearch;
        $indexName = 'releases_rt';

        $manticore->truncateRTIndex(Arr::wrap($indexName));

        $total = Release::count();
        if (! $total) {
            $this->warn('Releases table is empty. Nothing to do.');

            return Command::SUCCESS;
        }

        $query = Release::query()
            ->orderByDesc('releases.id')
            ->leftJoin('release_files', 'releases.id', '=', 'release_files.releases_id')
            ->select([
                'releases.id',
                'releases.name',
                'releases.searchname',
                'releases.fromname',
                'releases.categories_id',
            ])
            ->selectRaw('IFNULL(GROUP_CONCAT(release_files.name SEPARATOR " "),"") AS filename')
            ->groupBy([
                'releases.id',
                'releases.name',
                'releases.searchname',
                'releases.fromname',
                'releases.categories_id',
            ]);

        return $this->processManticoreData(
            $indexName,
            $total,
            $query,
            function ($item) {
                return [
                    'id' => (string) $item->id,
                    'name' => (string) ($item->name ?: ''),
                    'searchname' => (string) ($item->searchname ?: ''),
                    'fromname' => (string) ($item->fromname ?: ''),
                    'categories_id' => (string) ($item->categories_id ?: '0'),
                    'filename' => (string) ($item->filename ?: ''),
                    'dummy' => '1',
                ];
            }
        );
    }

    /**
     * Populate ManticoreSearch predb index
     */
    private function manticorePredb(): int
    {
        $manticore = new ManticoreSearch;
        $indexName = 'predb_rt';

        $manticore->truncateRTIndex([$indexName]);

        $total = Predb::count();
        if (! $total) {
            $this->warn('PreDB table is empty. Nothing to do.');

            return Command::SUCCESS;
        }

        $query = Predb::query()
            ->select(['id', 'title', 'filename', 'source'])
            ->orderBy('id');

        return $this->processManticoreData(
            $indexName,
            $total,
            $query,
            function ($item) {
                return [
                    'id' => $item->id,
                    'title' => (string) ($item->title ?? ''),
                    'filename' => (string) ($item->filename ?? ''),
                    'source' => (string) ($item->source ?? ''),
                    'dummy' => 1,
                ];
            }
        );
    }

    /**
     * Process data for ManticoreSearch
     */
    private function processManticoreData(string $indexName, int $total, $query, callable $transformer): int
    {
        $manticore = new ManticoreSearch;
        $chunkSize = $this->getChunkSize();

        $this->setGroupConcatMaxLen();

        $this->info(sprintf(
            "Populating ManticoreSearch index '%s' with %s rows using chunks of %s.",
            $indexName,
            number_format($total),
            number_format($chunkSize)
        ));

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('verbose');
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;

        try {
            $query->chunk($chunkSize, function ($items) use ($manticore, $indexName, $transformer, $bar, &$processedCount, &$errorCount) {
                $data = [];

                foreach ($items as $item) {
                    try {
                        $data[] = $transformer($item);
                        $processedCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                        if ($this->output->isVerbose()) {
                            $this->error("Error processing item {$item->id}: {$e->getMessage()}");
                        }
                    }
                    $bar->advance();
                }

                if (! empty($data)) {
                    $manticore->manticoreSearch->table($indexName)->replaceDocuments($data);
                }
            });

            $bar->finish();
            $this->newLine();

            if ($errorCount > 0) {
                $this->warn("Completed with {$errorCount} errors out of {$processedCount} processed items.");
            } else {
                $this->info('ManticoreSearch population completed successfully!');
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $bar->finish();
            $this->newLine();
            $this->error("Failed to populate ManticoreSearch: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Populate ElasticSearch releases index
     */
    private function elasticReleases(): int
    {
        $total = Release::count();
        if (! $total) {
            $this->warn('Releases table is empty. Nothing to do.');

            return Command::SUCCESS;
        }

        $query = Release::query()
            ->orderByDesc('releases.id')
            ->leftJoin('release_files', 'releases.id', '=', 'release_files.releases_id')
            ->select([
                'releases.id',
                'releases.name',
                'releases.searchname',
                'releases.fromname',
                'releases.categories_id',
                'releases.postdate',
            ])
            ->selectRaw('IFNULL(GROUP_CONCAT(release_files.name SEPARATOR " "),"") AS filename')
            ->groupBy([
                'releases.id',
                'releases.name',
                'releases.searchname',
                'releases.fromname',
                'releases.categories_id',
                'releases.postdate',
            ]);

        return $this->processElasticData(
            'releases',
            $total,
            $query,
            function ($item) {
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
            }
        );
    }

    /**
     * Populate ElasticSearch predb index
     */
    private function elasticPredb(): int
    {
        $total = Predb::count();
        if (! $total) {
            $this->warn('PreDB table is empty. Nothing to do.');

            return Command::SUCCESS;
        }

        $query = Predb::query()
            ->select(['id', 'title', 'filename', 'source'])
            ->orderBy('id');

        return $this->processElasticData(
            'predb',
            $total,
            $query,
            function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'filename' => $item->filename,
                    'source' => $item->source,
                ];
            }
        );
    }

    /**
     * Process data for ElasticSearch
     */
    private function processElasticData(string $indexName, int $total, $query, callable $transformer): int
    {
        $chunkSize = $this->getChunkSize();

        $this->setGroupConcatMaxLen();

        $this->info(sprintf(
            "Populating ElasticSearch index '%s' with %s rows using chunks of %s.",
            $indexName,
            number_format($total),
            number_format($chunkSize)
        ));

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('verbose');
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;
        $batchSize = min($chunkSize, 1000); // ElasticSearch performs better with smaller bulk sizes

        try {
            $query->chunk($chunkSize, function ($items) use ($indexName, $transformer, $bar, &$processedCount, &$errorCount, $batchSize) {
                // Process in smaller batches for ElasticSearch
                foreach ($items->chunk($batchSize) as $batch) {
                    $data = ['body' => []];

                    foreach ($batch as $item) {
                        try {
                            $transformedData = $transformer($item);

                            $data['body'][] = [
                                'index' => [
                                    '_index' => $indexName,
                                    '_id' => $item->id,
                                ],
                            ];
                            $data['body'][] = $transformedData;

                            $processedCount++;
                        } catch (Exception $e) {
                            $errorCount++;
                            if ($this->output->isVerbose()) {
                                $this->error("Error processing item {$item->id}: {$e->getMessage()}");
                            }
                        }

                        $bar->advance();
                    }

                    if (! empty($data['body'])) {
                        $response = \Elasticsearch::bulk($data);

                        // Check for errors in bulk response
                        if (isset($response['errors']) && $response['errors']) {
                            foreach ($response['items'] as $item) {
                                if (isset($item['index']['error'])) {
                                    $errorCount++;
                                    if ($this->output->isVerbose()) {
                                        $this->error('ElasticSearch error: '.json_encode($item['index']['error']));
                                    }
                                }
                            }
                        }
                    }
                }
            });

            $bar->finish();
            $this->newLine();

            if ($errorCount > 0) {
                $this->warn("Completed with {$errorCount} errors out of {$processedCount} processed items.");
            } else {
                $this->info('ElasticSearch population completed successfully!');
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $bar->finish();
            $this->newLine();
            $this->error("Failed to populate ElasticSearch: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Get the chunk size from options
     */
    private function getChunkSize(): int
    {
        $chunkSize = (int) $this->option('count');

        return $chunkSize > 0 ? $chunkSize : self::DEFAULT_CHUNK_SIZE;
    }

    /**
     * Set the GROUP_CONCAT max length for the session
     */
    private function setGroupConcatMaxLen(): void
    {
        DB::statement('SET SESSION group_concat_max_len = ?', [self::GROUP_CONCAT_MAX_LEN]);
    }
}
