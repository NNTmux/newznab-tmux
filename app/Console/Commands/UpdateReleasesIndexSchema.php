<?php

namespace App\Console\Commands;

use App\Facades\Search;
use App\Models\Release;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Manticoresearch\Client;
use Manticoresearch\Exceptions\ResponseException;

class UpdateReleasesIndexSchema extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:update-releases-index
                                        {--add-fields : Add new media-related fields to releases_rt index}
                                        {--update-media-ids : Update existing indexed releases with media IDs from database}
                                        {--batch-size=5000 : Batch size for bulk update operations}
                                        {--movies-only : Only update releases with movie info}
                                        {--tv-only : Only update releases with TV show info}
                                        {--missing-only : Only update releases that have zero media IDs in index}
                                        {--force : Force schema update even if fields exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update releases_rt search index schema with new media fields and/or populate media IDs for existing releases';

    /**
     * The expected schema fields for releases_rt
     */
    private array $expectedFields = [
        'name' => ['type' => 'text'],
        'searchname' => ['type' => 'text'],
        'fromname' => ['type' => 'text'],
        'filename' => ['type' => 'text'],
        'categories_id' => ['type' => 'int'],
        // External media IDs for efficient searching
        'imdbid' => ['type' => 'int'],
        'tmdbid' => ['type' => 'int'],
        'traktid' => ['type' => 'int'],
        'tvdb' => ['type' => 'int'],
        'tvmaze' => ['type' => 'int'],
        'tvrage' => ['type' => 'int'],
        'videos_id' => ['type' => 'int'],
        'movieinfo_id' => ['type' => 'int'],
    ];

    protected Client $client;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $driver = config('search.default', 'manticore');

        if ($driver !== 'manticore') {
            $this->error("This command currently only supports ManticoreSearch. Current driver: {$driver}");

            return Command::FAILURE;
        }

        $this->info('Releases index schema update utility');
        $this->newLine();

        // Initialize ManticoreSearch client
        $host = config('search.drivers.manticore.host', '127.0.0.1');
        $port = config('search.drivers.manticore.port', 9308);

        $this->client = new Client([
            'host' => $host,
            'port' => $port,
        ]);

        // Test connection
        try {
            $this->client->nodes()->status();
            $this->info('Connected to ManticoreSearch successfully.');
        } catch (\Exception $e) {
            $this->error('Failed to connect to ManticoreSearch: '.$e->getMessage());

            return Command::FAILURE;
        }

        $result = Command::SUCCESS;

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
        if (! $this->option('add-fields') && ! $this->option('update-media-ids')) {
            $this->showSchemaInfo();
        }

        return $result;
    }

    /**
     * Show current index schema information
     */
    private function showSchemaInfo(): void
    {
        $this->info('Current releases_rt index schema:');
        $this->newLine();

        try {
            // Use the table describe method instead of raw SQL
            $columns = $this->client->table('releases_rt')->describe();

            if (empty($columns)) {
                $this->warn('Index releases_rt does not exist or has no columns.');
                $this->info('Run `php artisan manticore:create-indexes` to create the index first.');

                return;
            }

            $headers = ['Field', 'Type', 'Properties'];
            $rows = [];
            $existingFields = [];

            foreach ($columns as $field => $props) {
                $type = $props['Type'] ?? 'unknown';
                $properties = isset($props['Properties']) ? implode(', ', (array) $props['Properties']) : '';
                $rows[] = [$field, $type, $properties];
                $existingFields[$field] = strtolower($type);
            }

            $this->table($headers, $rows);
            $this->newLine();

            // Check for missing fields
            $missingFields = [];
            foreach ($this->expectedFields as $field => $config) {
                if (! isset($existingFields[$field]) && $field !== 'id') {
                    $missingFields[] = $field;
                }
            }

            if (! empty($missingFields)) {
                $this->warn('Missing fields that should be added:');
                foreach ($missingFields as $field) {
                    $this->line("  - {$field} ({$this->expectedFields[$field]['type']})");
                }
                $this->newLine();
                $this->info('Run with --add-fields to add missing fields.');
            } else {
                $this->info('All expected fields are present in the index.');
            }

            // Show usage hints
            $this->newLine();
            $this->info('Available options:');
            $this->line('  --add-fields         Add missing media-related fields to the index');
            $this->line('  --update-media-ids   Update indexed releases with media IDs from database');
            $this->line('  --movies-only        Only update releases with movie info');
            $this->line('  --tv-only            Only update releases with TV show info');
            $this->line('  --missing-only       Only update releases with zero media IDs');
            $this->line('  --batch-size=N       Set batch size for updates (default: 5000)');

        } catch (\Throwable $e) {
            $this->error('Failed to describe index: '.$e->getMessage());
            $this->info('The index may not exist. Run `php artisan manticore:create-indexes` first.');
        }
    }

    /**
     * Add new fields to the releases_rt index
     *
     * Note: ManticoreSearch RT indexes support ALTER TABLE for adding columns
     */
    private function addNewFields(): int
    {
        $this->info('Checking for missing fields in releases_rt index...');

        try {
            // Get current schema using table describe method
            $columns = $this->client->table('releases_rt')->describe();

            if (empty($columns)) {
                $this->error('Index releases_rt does not exist. Create it first with: php artisan manticore:create-indexes');

                return Command::FAILURE;
            }

            $existingFields = [];
            foreach ($columns as $field => $props) {
                $existingFields[$field] = strtolower($props['Type'] ?? 'unknown');
            }

            // Media fields that should exist
            $mediaFields = [
                'imdbid' => 'int',
                'tmdbid' => 'int',
                'traktid' => 'int',
                'tvdb' => 'int',
                'tvmaze' => 'int',
                'tvrage' => 'int',
                'videos_id' => 'int',
                'movieinfo_id' => 'int',
            ];

            $fieldsToAdd = [];
            foreach ($mediaFields as $field => $type) {
                if (! isset($existingFields[$field]) || $this->option('force')) {
                    $fieldsToAdd[$field] = $type;
                }
            }

            if (empty($fieldsToAdd)) {
                $this->info('All media fields already exist in the index.');

                return Command::SUCCESS;
            }

            $this->warn('The following fields will be added:');
            foreach ($fieldsToAdd as $field => $type) {
                $this->line("  - {$field} ({$type})");
            }

            if (! $this->confirm('Do you want to proceed with adding these fields?', true)) {
                $this->info('Operation cancelled.');

                return Command::SUCCESS;
            }

            // Add each field using the ManticoreSearch PHP client alter method
            foreach ($fieldsToAdd as $field => $type) {
                try {
                    $this->client->table('releases_rt')->alter('add', $field, $type);
                    $this->info("Added field: {$field}");
                } catch (ResponseException $e) {
                    if (str_contains($e->getMessage(), 'already exists')) {
                        $this->warn("Field {$field} already exists, skipping.");
                    } else {
                        $this->error("Failed to add field {$field}: ".$e->getMessage());

                        return Command::FAILURE;
                    }
                }
            }

            $this->info('Schema update completed successfully!');
            $this->newLine();
            $this->info('Now you can update existing releases with media IDs using:');
            $this->line('  php artisan nntmux:update-releases-index --update-media-ids');

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Failed to update schema: '.$e->getMessage());

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

        // Build query based on options
        $query = Release::query()
            ->leftJoin('movieinfo', 'releases.movieinfo_id', '=', 'movieinfo.id')
            ->leftJoin('videos', 'releases.videos_id', '=', 'videos.id')
            ->select([
                'releases.id',
                'releases.name',
                'releases.searchname',
                'releases.fromname',
                'releases.categories_id',
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
        $indexName = config('search.drivers.manticore.indexes.releases', 'releases_rt');

        // Process in batches using chunk
        $query->orderBy('releases.id')
            ->chunk($batchSize, function ($releases) use (&$updated, &$errors, $indexName, $bar, $missingOnly) {
                $batch = [];

                foreach ($releases as $release) {
                    // Prepare the update data
                    $mediaData = $this->prepareMediaData($release);

                    // Skip if all media IDs are zero and we're not forcing update
                    $hasMediaIds = array_filter($mediaData, fn ($v) => $v > 0);
                    if (empty($hasMediaIds)) {
                        $bar->advance();

                        continue;
                    }

                    // If missing-only, check if the document already has media IDs
                    if ($missingOnly && $this->documentHasMediaIds($indexName, $release->id)) {
                        $bar->advance();

                        continue;
                    }

                    $batch[] = [
                        'id' => $release->id,
                        'data' => $mediaData,
                    ];

                    // Process batch when it reaches the threshold
                    if (count($batch) >= 1000) {
                        $result = $this->processBatch($indexName, $batch);
                        $updated += $result['updated'];
                        $errors += $result['errors'];
                        $batch = [];
                    }

                    $bar->advance();
                }

                // Process remaining batch
                if (! empty($batch)) {
                    $result = $this->processBatch($indexName, $batch);
                    $updated += $result['updated'];
                    $errors += $result['errors'];
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info('Update completed!');
        $this->line("  - Updated: {$updated}");
        if ($errors > 0) {
            $this->warn("  - Errors: {$errors}");
        }

        return Command::SUCCESS;
    }

    /**
     * Prepare media data for a release
     */
    private function prepareMediaData($release): array
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
            $doc = $this->client->table($indexName)->getDocumentById($id);

            if (! $doc) {
                return false;
            }

            $data = $doc->getData();

            // Check if any media ID is non-zero
            return ($data['imdbid'] ?? 0) > 0
                || ($data['tmdbid'] ?? 0) > 0
                || ($data['traktid'] ?? 0) > 0
                || ($data['tvdb'] ?? 0) > 0
                || ($data['tvmaze'] ?? 0) > 0
                || ($data['tvrage'] ?? 0) > 0;

        } catch (\Throwable $e) {
            // Document might not exist
            return false;
        }
    }

    /**
     * Process a batch of updates
     */
    private function processBatch(string $indexName, array $batch): array
    {
        $updated = 0;
        $errors = 0;

        foreach ($batch as $item) {
            try {
                // Use updateDocument to modify existing documents
                $this->client->table($indexName)->updateDocument($item['data'], $item['id']);
                $updated++;
            } catch (\Throwable $e) {
                // If update fails, try replace (document might not exist in index yet)
                try {
                    $this->insertOrReplaceDocument($indexName, $item['id'], $item['data']);
                    $updated++;
                } catch (\Throwable $e2) {
                    $errors++;
                    if ($errors <= 5) {
                        // Log first few errors
                        Log::warning("Failed to update release {$item['id']} in search index: ".$e2->getMessage());
                    }
                }
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    /**
     * Insert or replace a document in the index
     * This is used as a fallback when UPDATE fails
     */
    private function insertOrReplaceDocument(string $indexName, int $id, array $mediaData): void
    {
        // First, try to get the existing document
        try {
            $doc = $this->client->table($indexName)->getDocumentById($id);

            if ($doc) {
                // Merge existing data with new media data
                $existingData = $doc->getData();
                $document = array_merge($existingData, $mediaData);
                unset($document['id']); // ID is passed separately

                $this->client->table($indexName)->replaceDocument($document, $id);
            } else {
                // Document doesn't exist in index, we need full data from database
                $release = Release::with(['movieinfo', 'video'])->find($id);
                if ($release) {
                    Search::insertRelease([
                        'id' => $release->id,
                        'name' => $release->name ?? '',
                        'searchname' => $release->searchname ?? '',
                        'fromname' => $release->fromname ?? '',
                        'categories_id' => $release->categories_id ?? 0,
                        'filename' => '',
                        'imdbid' => $mediaData['imdbid'],
                        'tmdbid' => $mediaData['tmdbid'],
                        'traktid' => $mediaData['traktid'],
                        'tvdb' => $mediaData['tvdb'],
                        'tvmaze' => $mediaData['tvmaze'],
                        'tvrage' => $mediaData['tvrage'],
                        'videos_id' => $mediaData['videos_id'],
                        'movieinfo_id' => $mediaData['movieinfo_id'],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
