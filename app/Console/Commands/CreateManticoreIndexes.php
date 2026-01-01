<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Manticoresearch\Client;
use Manticoresearch\Exceptions\ResponseException;
use Manticoresearch\Table;

class CreateManticoreIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'manticore:create-indexes
                                        {--drop : Drop existing indexes before creating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Manticore Search indexes based on configuration';

    protected Client $client;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Creating Manticore Search indexes...');

        $dropExisting = $this->option('drop');

        // Get connection details from config
        $host = config('manticoresearch.host', '127.0.0.1');
        $port = config('manticoresearch.port', 9308);

        // Create client
        $this->client = new Client([
            'host' => $host,
            'port' => $port,
        ]);

        // We'll skip checking for data_dir this way since it may not be accessible via API
        // but instead provide better error handling during index creation

        // If you encounter data_dir errors, ensure it's properly set in config/manticore.conf:
        // data_dir = /path/to/data
        // And make sure the path exists and has proper permissions

        try {
            $this->client->nodes()->status();
        } catch (\Exception $e) {
            $this->error('Failed to connect to Manticore Search: '.$e->getMessage());
            $this->info('Please check if Manticore Search is running and properly configured.');

            return 1;
        }

        // Define indexes and their schema
        $indexes = [
            'releases_rt' => [
                'settings' => [
                    'min_prefix_len' => 0,
                    'min_infix_len' => 2,
                ],
                'columns' => [
                    'name' => ['type' => 'text'],
                    'searchname' => ['type' => 'text'],
                    'fromname' => ['type' => 'text'],
                    'filename' => ['type' => 'text'],
                    'categories_id' => ['type' => 'integer'],
                    // External media IDs for efficient searching
                    'imdbid' => ['type' => 'integer'],
                    'tmdbid' => ['type' => 'integer'],
                    'traktid' => ['type' => 'integer'],
                    'tvdb' => ['type' => 'integer'],
                    'tvmaze' => ['type' => 'integer'],
                    'tvrage' => ['type' => 'integer'],
                    'videos_id' => ['type' => 'integer'],
                    'movieinfo_id' => ['type' => 'integer'],
                ],
            ],
            'predb_rt' => [
                'settings' => [
                    'min_prefix_len' => 0,
                    'min_infix_len' => 2,
                ],
                'columns' => [
                    'title' => ['type' => 'text', 'attribute' => true],
                    'filename' => ['type' => 'text', 'attribute' => true],
                    'source' => ['type' => 'string', 'attribute' => true],
                ],
            ],
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

        // Create each index
        foreach ($indexes as $indexName => $schema) {
            if (! $this->createIndex($indexName, $schema, $dropExisting)) {
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            $this->error('Some errors occurred during index creation.');

            return 1;
        }

        $this->info('All Manticore Search indexes created successfully!');

        return 0;
    }

    /**
     * Create a single index with error handling.
     */
    protected function createIndex(string $indexName, array $schema, bool $dropExisting): bool
    {
        $this->info("Creating {$indexName} index...");
        $indices = $this->client->tables();

        try {
            // Optionally drop existing index
            if ($dropExisting) {
                try {
                    $this->info("Dropping existing {$indexName} index...");
                    $indices->drop(['index' => $indexName, 'body' => ['silent' => true]]);
                    $this->info("Successfully dropped {$indexName} index.");
                } catch (ResponseException $e) {
                    if (! str_contains($e->getMessage(), 'unknown index')) {
                        $this->warn("Warning when dropping {$indexName} index: ".$e->getMessage());
                    }
                }
            }

            // Instead of checking if index exists (which doesn't work),
            // try to create it directly and handle any errors
            // that might occur if it already exists
            $response = $indices->create([
                'index' => $indexName,
                'body' => $schema,
            ]);

            $this->info("Successfully created {$indexName} index.");
            $this->line('Response: '.json_encode($response, JSON_PRETTY_PRINT));

            return true;
        } catch (ResponseException $e) {
            // Check if the error is because the index already exists
            if (str_contains($e->getMessage(), 'already exists')) {
                $this->warn("Index {$indexName} already exists. Use --drop option to recreate it.");

                return true;
            }

            // Check for data_dir configuration error
            if (str_contains($e->getMessage(), 'data_dir')) {
                $this->error("Failed to create {$indexName} index: ".$e->getMessage());
                $this->newLine();
                $this->warn('ManticoreSearch requires data_dir to be set in its configuration file.');
                $this->info('To fix this, edit your ManticoreSearch config file (usually /etc/manticoresearch/manticore.conf):');
                $this->line('  1. Add or uncomment: data_dir = /var/lib/manticore');
                $this->line('  2. Ensure the directory exists and is writable by the manticore user');
                $this->line('  3. Restart ManticoreSearch: sudo systemctl restart manticore');

                return false;
            }

            $this->error("Failed to create {$indexName} index: ".$e->getMessage());

            return false;
        } catch (\Exception $e) {
            $this->error("Failed to create {$indexName} index: ".$e->getMessage());

            return false;
        }
    }
}
