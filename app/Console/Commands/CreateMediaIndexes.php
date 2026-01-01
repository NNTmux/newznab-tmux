<?php

namespace App\Console\Commands;

use App\Facades\Search;
use Illuminate\Console\Command;
use Manticoresearch\Client;
use Manticoresearch\Exceptions\ResponseException;

class CreateMediaIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:create-media-indexes
                                        {--drop : Drop existing indexes before creating}
                                        {--populate : Populate indexes after creation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create movies and tvshows search indexes for the active search driver';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $driver = config('search.default', 'manticore');
        $this->info("Creating media search indexes for driver: {$driver}");

        if ($driver === 'manticore') {
            return $this->createManticoreIndexes();
        } elseif ($driver === 'elasticsearch') {
            return $this->createElasticsearchIndexes();
        } else {
            $this->error("Unsupported search driver: {$driver}");
            return Command::FAILURE;
        }
    }

    /**
     * Create Manticore indexes for movies and tvshows.
     */
    private function createManticoreIndexes(): int
    {
        $host = config('search.drivers.manticore.host', '127.0.0.1');
        $port = config('search.drivers.manticore.port', 9308);

        $client = new Client([
            'host' => $host,
            'port' => $port,
        ]);

        // Test connection
        try {
            $client->nodes()->status();
            $this->info('Connected to ManticoreSearch successfully.');
        } catch (\Exception $e) {
            $this->error('Failed to connect to ManticoreSearch: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $dropExisting = $this->option('drop');

        // Define movies and tvshows indexes
        $indexes = [
            'movies_rt' => [
                'settings' => [
                    'min_prefix_len' => 0,
                    'min_infix_len' => 2,
                ],
                'columns' => [
                    'imdbid' => ['type' => 'integer'],
                    'tmdbid' => ['type' => 'integer'],
                    'traktid' => ['type' => 'integer'],
                    'title' => ['type' => 'text'],
                    'year' => ['type' => 'text'],
                    'genre' => ['type' => 'text'],
                    'actors' => ['type' => 'text'],
                    'director' => ['type' => 'text'],
                    'rating' => ['type' => 'text'],
                    'plot' => ['type' => 'text'],
                ],
            ],
            'tvshows_rt' => [
                'settings' => [
                    'min_prefix_len' => 0,
                    'min_infix_len' => 2,
                ],
                'columns' => [
                    'title' => ['type' => 'text'],
                    'tvdb' => ['type' => 'integer'],
                    'trakt' => ['type' => 'integer'],
                    'tvmaze' => ['type' => 'integer'],
                    'tvrage' => ['type' => 'integer'],
                    'imdb' => ['type' => 'integer'],
                    'tmdb' => ['type' => 'integer'],
                    'started' => ['type' => 'text'],
                    'type' => ['type' => 'integer'],
                ],
            ],
        ];

        $hasErrors = false;

        foreach ($indexes as $indexName => $schema) {
            if (!$this->createManticoreIndex($client, $indexName, $schema, $dropExisting)) {
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            $this->error('Some errors occurred during index creation.');
            return Command::FAILURE;
        }

        $this->info('Media indexes created successfully!');

        // Optionally populate the indexes
        if ($this->option('populate')) {
            $this->info('Populating indexes...');
            $this->call('nntmux:populate', [
                '--manticore' => true,
                '--movies' => true,
            ]);
            $this->call('nntmux:populate', [
                '--manticore' => true,
                '--tvshows' => true,
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Create a single Manticore index.
     */
    private function createManticoreIndex(Client $client, string $indexName, array $schema, bool $dropExisting): bool
    {
        try {
            // Check if index exists
            $indexExists = false;
            try {
                $client->table($indexName)->describe();
                $indexExists = true;
            } catch (ResponseException $e) {
                // Index doesn't exist
            }

            if ($indexExists) {
                if ($dropExisting) {
                    $this->warn("Dropping existing index: {$indexName}");
                    $client->tables()->drop(['index' => $indexName, 'body' => ['silent' => true]]);
                } else {
                    $this->info("Index {$indexName} already exists. Use --drop to recreate.");
                    return true;
                }
            }

            // Create the index using the ManticoreSearch PHP client
            $this->info("Creating index: {$indexName}");

            // Use the same format as CreateManticoreIndexes - pass schema directly
            $response = $client->tables()->create([
                'index' => $indexName,
                'body' => $schema,
            ]);

            $this->info("Index {$indexName} created successfully.");
            $this->line('Response: ' . json_encode($response, JSON_PRETTY_PRINT));
            return true;

        } catch (ResponseException $e) {
            // Check if the error is because the index already exists
            if (str_contains($e->getMessage(), 'already exists')) {
                $this->warn("Index {$indexName} already exists. Use --drop to recreate.");
                return true;
            }
            $this->error("Failed to create index {$indexName}: " . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            $this->error("Unexpected error creating index {$indexName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create Elasticsearch indexes for movies and tvshows.
     */
    private function createElasticsearchIndexes(): int
    {
        $this->info('Creating Elasticsearch media indexes...');

        $dropExisting = $this->option('drop');

        // Movies index mapping
        $moviesMapping = [
            'mappings' => [
                'properties' => [
                    'imdbid' => ['type' => 'integer'],
                    'tmdbid' => ['type' => 'integer'],
                    'traktid' => ['type' => 'integer'],
                    'title' => ['type' => 'text', 'analyzer' => 'standard'],
                    'year' => ['type' => 'keyword'],
                    'genre' => ['type' => 'text'],
                    'actors' => ['type' => 'text'],
                    'director' => ['type' => 'text'],
                    'rating' => ['type' => 'keyword'],
                    'plot' => ['type' => 'text'],
                ],
            ],
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
        ];

        // TV shows index mapping
        $tvshowsMapping = [
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text', 'analyzer' => 'standard'],
                    'tvdb' => ['type' => 'integer'],
                    'trakt' => ['type' => 'integer'],
                    'tvmaze' => ['type' => 'integer'],
                    'tvrage' => ['type' => 'integer'],
                    'imdb' => ['type' => 'integer'],
                    'tmdb' => ['type' => 'integer'],
                    'started' => ['type' => 'keyword'],
                    'type' => ['type' => 'integer'],
                ],
            ],
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
        ];

        try {
            // Create Elasticsearch client directly
            $esConfig = config('search.drivers.elasticsearch');
            $esClient = \Elasticsearch\ClientBuilder::create()
                ->setHosts($esConfig['hosts'] ?? [['host' => 'localhost', 'port' => 9200]])
                ->build();

            // Create movies index
            $moviesIndex = config('search.drivers.elasticsearch.indexes.movies', 'movies');
            if ($dropExisting && $esClient->indices()->exists(['index' => $moviesIndex])) {
                $this->warn("Dropping existing index: {$moviesIndex}");
                $esClient->indices()->delete(['index' => $moviesIndex]);
            }

            if (!$esClient->indices()->exists(['index' => $moviesIndex])) {
                $this->info("Creating index: {$moviesIndex}");
                $esClient->indices()->create([
                    'index' => $moviesIndex,
                    'body' => $moviesMapping,
                ]);
                $this->info("Index {$moviesIndex} created successfully.");
            } else {
                $this->info("Index {$moviesIndex} already exists. Use --drop to recreate.");
            }

            // Create tvshows index
            $tvshowsIndex = config('search.drivers.elasticsearch.indexes.tvshows', 'tvshows');
            if ($dropExisting && $esClient->indices()->exists(['index' => $tvshowsIndex])) {
                $this->warn("Dropping existing index: {$tvshowsIndex}");
                $esClient->indices()->delete(['index' => $tvshowsIndex]);
            }

            if (!$esClient->indices()->exists(['index' => $tvshowsIndex])) {
                $this->info("Creating index: {$tvshowsIndex}");
                $esClient->indices()->create([
                    'index' => $tvshowsIndex,
                    'body' => $tvshowsMapping,
                ]);
                $this->info("Index {$tvshowsIndex} created successfully.");
            } else {
                $this->info("Index {$tvshowsIndex} already exists. Use --drop to recreate.");
            }

            $this->info('Elasticsearch media indexes created successfully!');

            // Optionally populate the indexes
            if ($this->option('populate')) {
                $this->info('Populating indexes...');
                $this->call('nntmux:populate', [
                    '--elastic' => true,
                    '--movies' => true,
                ]);
                $this->call('nntmux:populate', [
                    '--elastic' => true,
                    '--tvshows' => true,
                ]);
            }

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Failed to create Elasticsearch indexes: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

