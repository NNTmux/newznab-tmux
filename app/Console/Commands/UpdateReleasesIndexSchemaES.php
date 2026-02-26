<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Elasticsearch;
use App\Models\Release;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateReleasesIndexSchemaES extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:update-releases-index-es
                                        {--add-fields : Add new media-related fields to releases index}
                                        {--update-media-ids : Update existing indexed releases with media IDs from database}
                                        {--batch-size=1000 : Batch size for bulk update operations}
                                        {--movies-only : Only update releases with movie info}
                                        {--tv-only : Only update releases with TV show info}
                                        {--missing-only : Only update releases that have zero media IDs in index}
                                        {--force : Force schema update even if fields exist}
                                        {--recreate-index : Recreate the index with new schema (will delete existing data)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update ElasticSearch releases index schema with new media fields and/or populate media IDs for existing releases';

    /**
     * The media fields that should exist in the releases index
     *
     * @var array<string, mixed>
     */
    private array $mediaFields = [
        'imdbid' => ['type' => 'integer'],
        'tmdbid' => ['type' => 'integer'],
        'traktid' => ['type' => 'integer'],
        'tvdb' => ['type' => 'integer'],
        'tvmaze' => ['type' => 'integer'],
        'tvrage' => ['type' => 'integer'],
        'videos_id' => ['type' => 'integer'],
        'movieinfo_id' => ['type' => 'integer'],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $driver = config('search.default', 'manticore');

        if ($driver !== 'elasticsearch') {
            $this->warn("Current search driver is '{$driver}'. This command is for ElasticSearch.");
            if (! $this->confirm('Do you want to continue anyway?', false)) {
                return Command::SUCCESS;
            }
        }

        $this->info('ElasticSearch releases index schema update utility');
        $this->newLine();

        // Test connection
        try {
            $health = Elasticsearch::cluster()->health();
            $this->info("Connected to ElasticSearch. Cluster status: {$health['status']}");
        } catch (\Exception $e) {
            $this->error('Failed to connect to ElasticSearch: '.$e->getMessage());

            return Command::FAILURE;
        }

        $result = Command::SUCCESS;

        // Handle recreate-index option
        if ($this->option('recreate-index')) {
            $result = $this->recreateIndexWithNewSchema();
            if ($result !== Command::SUCCESS) {
                return $result;
            }
        }

        // Handle add-fields option
        if ($this->option('add-fields')) {
            $result = $this->addNewFields();
            if ($result !== Command::SUCCESS) {
                return $result;
            }
        }

        // Handle update-media-ids option
        if ($this->option('update-media-ids')) {
            $result = $this->updateMediaIds();
        }

        // If no options specified, show current schema info
        if (! $this->option('add-fields') && ! $this->option('update-media-ids') && ! $this->option('recreate-index')) {
            $this->showSchemaInfo();
        }

        return $result;
    }

    /**
     * Show current index schema information
     */
    private function showSchemaInfo(): void
    {
        $this->info('Current releases index schema:');
        $this->newLine();

        try {
            $indexName = config('search.drivers.elasticsearch.indexes.releases', 'releases');

            if (! Elasticsearch::indices()->exists(['index' => $indexName])) {
                $this->warn("Index '{$indexName}' does not exist.");
                $this->info('Run `php artisan nntmux:create-es-indexes` to create the index first.');

                return;
            }

            $mapping = Elasticsearch::indices()->getMapping(['index' => $indexName]);
            $properties = $mapping[$indexName]['mappings']['properties'] ?? [];

            if (empty($properties)) {
                $this->warn('Index has no mapped properties.');

                return;
            }

            $headers = ['Field', 'Type', 'Properties'];
            $rows = [];
            $existingFields = [];

            foreach ($properties as $field => $config) {
                $type = $config['type'] ?? 'unknown';
                $props = [];
                if (isset($config['analyzer'])) {
                    $props[] = "analyzer: {$config['analyzer']}";
                }
                if (isset($config['index']) && ! $config['index']) {
                    $props[] = 'not indexed';
                }
                if (isset($config['fields'])) {
                    $props[] = 'multi-field';
                }
                $rows[] = [$field, $type, implode(', ', $props)];
                $existingFields[$field] = $type;
            }

            $this->table($headers, $rows);
            $this->newLine();

            // Check for missing media fields
            $missingFields = [];
            foreach ($this->mediaFields as $field => $config) {
                if (! isset($existingFields[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (! empty($missingFields)) {
                $this->warn('Missing media fields that should be added:');
                foreach ($missingFields as $field) {
                    $this->line("  - {$field} ({$this->mediaFields[$field]['type']})");
                }
                $this->newLine();
                $this->info('Run with --add-fields to add missing fields, or --recreate-index to rebuild with new schema.');
            } else {
                $this->info('All expected media fields are present in the index.');
            }

            // Show usage hints
            $this->newLine();
            $this->info('Available options:');
            $this->line('  --add-fields         Add missing media-related fields to the index');
            $this->line('  --recreate-index     Recreate index with new schema (data will be lost)');
            $this->line('  --update-media-ids   Update indexed releases with media IDs from database');
            $this->line('  --movies-only        Only update releases with movie info');
            $this->line('  --tv-only            Only update releases with TV show info');
            $this->line('  --missing-only       Only update releases with zero media IDs');
            $this->line('  --batch-size=N       Set batch size for updates (default: 1000)');

        } catch (\Throwable $e) {
            $this->error('Failed to get index mapping: '.$e->getMessage());
            $this->info('The index may not exist. Run `php artisan nntmux:create-es-indexes` first.');
        }
    }

    /**
     * Add new fields to the releases index using mapping update
     *
     * Note: Elasticsearch only allows adding new fields, not modifying existing ones
     */
    private function addNewFields(): int
    {
        $this->info('Checking for missing fields in releases index...');

        try {
            $indexName = config('search.drivers.elasticsearch.indexes.releases', 'releases');

            if (! Elasticsearch::indices()->exists(['index' => $indexName])) {
                $this->error("Index '{$indexName}' does not exist. Create it first with: php artisan nntmux:create-es-indexes");

                return Command::FAILURE;
            }

            // Get current mapping
            $mapping = Elasticsearch::indices()->getMapping(['index' => $indexName]);
            $existingProperties = $mapping[$indexName]['mappings']['properties'] ?? [];

            // Find fields to add
            $fieldsToAdd = [];
            foreach ($this->mediaFields as $field => $config) {
                if (! isset($existingProperties[$field]) || $this->option('force')) {
                    $fieldsToAdd[$field] = $config;
                }
            }

            if (empty($fieldsToAdd)) {
                $this->info('All media fields already exist in the index.');

                return Command::SUCCESS;
            }

            $this->warn('The following fields will be added:');
            foreach ($fieldsToAdd as $field => $config) {
                $this->line("  - {$field} ({$config['type']})");
            }

            if (! $this->confirm('Do you want to proceed with adding these fields?', true)) {
                $this->info('Operation cancelled.');

                return Command::SUCCESS;
            }

            // Add new fields via PUT mapping
            $newProperties = [];
            foreach ($fieldsToAdd as $field => $config) {
                $newProperties[$field] = $config;
            }

            Elasticsearch::indices()->putMapping([
                'index' => $indexName,
                'body' => [
                    'properties' => $newProperties,
                ],
            ]);

            $this->info('Schema update completed successfully!');
            $this->newLine();
            $this->info('Now you can update existing releases with media IDs using:');
            $this->line('  php artisan nntmux:update-releases-index-es --update-media-ids');

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Failed to update schema: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Recreate the index with the new schema including media fields
     * WARNING: This will delete all existing data in the index
     */
    private function recreateIndexWithNewSchema(): int
    {
        $indexName = config('search.drivers.elasticsearch.indexes.releases', 'releases');

        $this->warn("WARNING: This will DELETE all data in the '{$indexName}' index and recreate it with the new schema!");
        $this->warn('You will need to re-populate the index after this operation.');

        if (! $this->confirm('Are you sure you want to proceed?', false)) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        try {
            // Delete existing index if it exists
            if (Elasticsearch::indices()->exists(['index' => $indexName])) {
                $this->info("Deleting existing '{$indexName}' index...");
                Elasticsearch::indices()->delete(['index' => $indexName]);
            }

            // Create new index with updated schema
            $this->info("Creating '{$indexName}' index with new schema...");

            $releasesIndex = [
                'index' => $indexName,
                'body' => [
                    'settings' => [
                        'number_of_shards' => 2,
                        'number_of_replicas' => 0,
                        'analysis' => [
                            'analyzer' => [
                                'release_analyzer' => [
                                    'type' => 'custom',
                                    'tokenizer' => 'standard',
                                    'filter' => ['lowercase', 'asciifolding'],
                                ],
                            ],
                        ],
                    ],
                    'mappings' => [
                        'properties' => [
                            'id' => [
                                'type' => 'long',
                                'index' => false,
                            ],
                            'name' => [
                                'type' => 'text',
                                'analyzer' => 'release_analyzer',
                            ],
                            'searchname' => [
                                'type' => 'text',
                                'analyzer' => 'release_analyzer',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                        'ignore_above' => 256,
                                    ],
                                    'sort' => [
                                        'type' => 'keyword',
                                    ],
                                ],
                            ],
                            'plainsearchname' => [
                                'type' => 'text',
                                'analyzer' => 'release_analyzer',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                        'ignore_above' => 256,
                                    ],
                                    'sort' => [
                                        'type' => 'keyword',
                                    ],
                                ],
                            ],
                            'categories_id' => [
                                'type' => 'integer',
                            ],
                            'fromname' => [
                                'type' => 'text',
                                'analyzer' => 'release_analyzer',
                            ],
                            'filename' => [
                                'type' => 'text',
                                'analyzer' => 'release_analyzer',
                            ],
                            'add_date' => [
                                'type' => 'date',
                                'format' => 'yyyy-MM-dd HH:mm:ss',
                            ],
                            'post_date' => [
                                'type' => 'date',
                                'format' => 'yyyy-MM-dd HH:mm:ss',
                            ],
                            // New media-related fields
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
                ],
            ];

            Elasticsearch::indices()->create($releasesIndex);

            $this->info("Index '{$indexName}' created successfully with new schema!");
            $this->newLine();
            $this->warn('Remember to re-populate the index:');
            $this->line('  php artisan nntmux:populate-search-indexes --elastic --releases');

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Failed to recreate index: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Update existing indexed releases with media IDs from database
     */
    private function updateMediaIds(): int
    {
        $this->info('Updating indexed releases with media IDs from database...');

        $batchSize = (int) $this->option('batch-size');
        $moviesOnly = $this->option('movies-only');
        $tvOnly = $this->option('tv-only');
        $missingOnly = $this->option('missing-only');

        $indexName = config('search.drivers.elasticsearch.indexes.releases', 'releases');

        // Check if index exists
        if (! Elasticsearch::indices()->exists(['index' => $indexName])) {
            $this->error("Index '{$indexName}' does not exist. Create it first.");

            return Command::FAILURE;
        }

        // Build query based on options
        $query = Release::query()
            ->leftJoin('movieinfo', 'releases.movieinfo_id', '=', 'movieinfo.id')
            ->leftJoin('videos', 'releases.videos_id', '=', 'videos.id')
            ->select([
                'releases.id',
                'releases.videos_id',
                'releases.movieinfo_id',
                // Movie external IDs
                'movieinfo.imdbid',
                'movieinfo.tmdbid',
                'movieinfo.traktid',
                // TV show external IDs
                'videos.tvdb',
                'videos.tvmaze',
                'videos.tvrage',
                DB::raw('videos.trakt as video_trakt'),
                DB::raw('videos.imdb as video_imdb'),
                DB::raw('videos.tmdb as video_tmdb'),
            ]);

        // Apply filters based on options
        if ($moviesOnly) {
            $query->whereNotNull('releases.movieinfo_id')
                ->where('releases.movieinfo_id', '>', 0);
            $this->info('Filtering: Movies only (releases with movieinfo_id)');
        } elseif ($tvOnly) {
            $query->whereNotNull('releases.videos_id')
                ->where('releases.videos_id', '>', 0);
            $this->info('Filtering: TV shows only (releases with videos_id)');
        } else {
            // Get releases that have either movie or TV info
            $query->where(function ($q) {
                $q->where(function ($subq) {
                    $subq->whereNotNull('releases.movieinfo_id')
                        ->where('releases.movieinfo_id', '>', 0);
                })->orWhere(function ($subq) {
                    $subq->whereNotNull('releases.videos_id')
                        ->where('releases.videos_id', '>', 0);
                });
            });
            $this->info('Filtering: Releases with either movie or TV info');
        }

        $total = $query->count();

        if ($total === 0) {
            $this->warn('No releases found matching the criteria.');

            return Command::SUCCESS;
        }

        $this->info("Found {$total} releases to update.");

        if (! $this->confirm('Do you want to proceed with the update?', true)) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        $updated = 0;
        $errors = 0;
        $skipped = 0;

        // Process in batches using chunk
        $query->orderBy('releases.id')
            ->chunk($batchSize, function ($releases) use (&$updated, &$errors, &$skipped, $indexName, $bar, $missingOnly) {
                $bulkParams = ['body' => []];

                foreach ($releases as $release) {
                    // Prepare the update data
                    $mediaData = $this->prepareMediaData($release);

                    // Skip if all media IDs are zero
                    $hasMediaIds = array_filter($mediaData, fn ($v) => $v > 0);
                    if (empty($hasMediaIds)) {
                        $skipped++;
                        $bar->advance();

                        continue;
                    }

                    // If missing-only, check if the document already has media IDs
                    if ($missingOnly && $this->documentHasMediaIds($indexName, $release->id)) {
                        $skipped++;
                        $bar->advance();

                        continue;
                    }

                    // Add to bulk update
                    $bulkParams['body'][] = [
                        'update' => [
                            '_index' => $indexName,
                            '_id' => $release->id,
                        ],
                    ];
                    $bulkParams['body'][] = [
                        'doc' => $mediaData,
                        'doc_as_upsert' => false, // Don't create if doesn't exist
                    ];

                    $bar->advance();
                }

                // Execute bulk update
                if (! empty($bulkParams['body'])) {
                    try {
                        $response = Elasticsearch::bulk($bulkParams);

                        if (isset($response['errors']) && $response['errors']) {
                            foreach ($response['items'] as $item) {
                                if (isset($item['update']['error'])) {
                                    $errors++;
                                    if ($errors <= 5) {
                                        Log::warning('Failed to update release in ES: '.json_encode($item['update']['error']));
                                    }
                                } else {
                                    $updated++;
                                }
                            }
                        } else {
                            $updated += count($response['items']);
                        }
                    } catch (\Throwable $e) {
                        $errors += count($bulkParams['body']) / 2;
                        if ($errors <= 5) {
                            Log::error('Bulk update failed: '.$e->getMessage());
                        }
                    }
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info('Update completed!');
        $this->line("  - Updated: {$updated}");
        $this->line("  - Skipped: {$skipped}");
        if ($errors > 0) {
            $this->warn("  - Errors: {$errors}");
        }

        // Refresh index to make changes visible
        $this->info('Refreshing index...');
        Elasticsearch::indices()->refresh(['index' => $indexName]);
        $this->info('Done!');

        return Command::SUCCESS;
    }

    /**
     * Prepare media data for a release
     *
     * @return array<string, mixed>
     */
    private function prepareMediaData(mixed $release): array
    {
        return [
            'imdbid' => (int) ($release->imdbid ?: 0),
            'tmdbid' => (int) ($release->tmdbid ?: ($release->video_tmdb ?: 0)),
            'traktid' => (int) ($release->traktid ?: ($release->video_trakt ?: 0)),
            'tvdb' => (int) ($release->tvdb ?: 0),
            'tvmaze' => (int) ($release->tvmaze ?: 0),
            'tvrage' => (int) ($release->tvrage ?: 0),
            'videos_id' => (int) ($release->videos_id ?: 0),
            'movieinfo_id' => (int) ($release->movieinfo_id ?: 0),
        ];
    }

    /**
     * Check if a document already has media IDs in the index
     */
    private function documentHasMediaIds(string $indexName, int $id): bool
    {
        try {
            $doc = Elasticsearch::get([
                'index' => $indexName,
                'id' => $id,
                '_source' => ['imdbid', 'tmdbid', 'traktid', 'tvdb', 'tvmaze', 'tvrage'],
            ]);

            $source = $doc['_source'] ?? [];

            // Check if any media ID is non-zero
            return ($source['imdbid'] ?? 0) > 0
                || ($source['tmdbid'] ?? 0) > 0
                || ($source['traktid'] ?? 0) > 0
                || ($source['tvdb'] ?? 0) > 0
                || ($source['tvmaze'] ?? 0) > 0
                || ($source['tvrage'] ?? 0) > 0;

        } catch (\Throwable $e) {
            // Other error, assume no media IDs
            return false;
        }
    }
}
