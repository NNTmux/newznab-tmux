<?php

namespace Blacklight;

use App\Models\Release;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Manticoresearch\Client;
use Manticoresearch\Exceptions\ResponseException;
use Manticoresearch\Exceptions\RuntimeException;
use Manticoresearch\Search;

/**
 * ManticoreSearch integration for release and predb full-text search.
 */
class ManticoreSearch
{
    /**
     * Default configuration values.
     */
    private const DEFAULT_HOST = '127.0.0.1';
    private const DEFAULT_PORT = 9308;
    private const DEFAULT_CACHE_MINUTES = 5;
    private const DEFAULT_MAX_MATCHES = 10000;
    private const DEFAULT_BATCH_SIZE = 1000;
    private const DEFAULT_RETRY_ATTEMPTS = 3;
    private const DEFAULT_RETRY_DELAY_MS = 100;

    /**
     * Index names.
     */
    public const INDEX_RELEASES = 'releases_rt';
    public const INDEX_PREDB = 'predb_rt';

    /**
     * Index schema definitions.
     */
    private const INDEX_SCHEMAS = [
        self::INDEX_RELEASES => [
            'name' => ['type' => 'text'],
            'searchname' => ['type' => 'text'],
            'fromname' => ['type' => 'text'],
            'filename' => ['type' => 'text'],
            'categories_id' => ['type' => 'integer'],
        ],
        self::INDEX_PREDB => [
            'title' => ['type' => 'text'],
            'filename' => ['type' => 'text'],
            'source' => ['type' => 'text'],
        ],
    ];

    protected array $config;

    protected array $connection;

    public Client $manticoreSearch;

    private ColorCLI $cli;

    private int $retryAttempts;

    private int $retryDelayMs;

    private int $cacheMinutes;

    /**
     * Establishes a connection to ManticoreSearch HTTP port.
     */
    public function __construct(?Client $client = null)
    {
        $this->config = $this->loadConfig();
        $this->connection = [
            'host' => $this->config['host'],
            'port' => $this->config['port'],
        ];

        $this->manticoreSearch = $client ?? new Client($this->connection);
        $this->cli = new ColorCLI;

        $this->retryAttempts = $this->config['retry_attempts'] ?? self::DEFAULT_RETRY_ATTEMPTS;
        $this->retryDelayMs = $this->config['retry_delay_ms'] ?? self::DEFAULT_RETRY_DELAY_MS;
        $this->cacheMinutes = $this->config['cache_minutes'] ?? self::DEFAULT_CACHE_MINUTES;
    }

    /**
     * Load and validate configuration.
     */
    private function loadConfig(): array
    {
        $config = config('manticoresearch') ?? [];

        return [
            'host' => $config['host'] ?? self::DEFAULT_HOST,
            'port' => $config['port'] ?? self::DEFAULT_PORT,
            'indexes' => $config['indexes'] ?? [
                'releases' => self::INDEX_RELEASES,
                'predb' => self::INDEX_PREDB,
            ],
            'retry_attempts' => $config['retry_attempts'] ?? self::DEFAULT_RETRY_ATTEMPTS,
            'retry_delay_ms' => $config['retry_delay_ms'] ?? self::DEFAULT_RETRY_DELAY_MS,
            'cache_minutes' => $config['cache_minutes'] ?? self::DEFAULT_CACHE_MINUTES,
            'max_matches' => $config['max_matches'] ?? self::DEFAULT_MAX_MATCHES,
            'batch_size' => $config['batch_size'] ?? self::DEFAULT_BATCH_SIZE,
        ];
    }

    /**
     * Get the releases index name.
     */
    public function getReleasesIndex(): string
    {
        return $this->config['indexes']['releases'] ?? self::INDEX_RELEASES;
    }

    /**
     * Get the predb index name.
     */
    public function getPredbIndex(): string
    {
        return $this->config['indexes']['predb'] ?? self::INDEX_PREDB;
    }

    /**
     * Check if ManticoreSearch is available and responding.
     */
    public function isHealthy(): bool
    {
        try {
            $status = $this->manticoreSearch->nodes()->status();

            return ! empty($status);
        } catch (\Throwable $e) {
            Log::warning('ManticoreSearch health check failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get status information about a specific index.
     *
     * @return array{docs: int, size: string, ram: string}|null
     */
    public function getIndexStatus(string $index): ?array
    {
        try {
            $status = $this->manticoreSearch->table($index)->status();

            return [
                'docs' => (int) ($status['indexed_documents'] ?? $status['doc_count'] ?? 0),
                'size' => $status['disk_bytes'] ?? 'unknown',
                'ram' => $status['ram_bytes'] ?? 'unknown',
            ];
        } catch (\Throwable $e) {
            Log::warning("ManticoreSearch: Failed to get status for index {$index}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Check if an index exists.
     */
    public function indexExists(string $index): bool
    {
        try {
            $this->manticoreSearch->table($index)->status();

            return true;
        } catch (ResponseException $e) {
            return false;
        } catch (\Throwable $e) {
            Log::warning("ManticoreSearch: Error checking if index {$index} exists: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Execute an operation with retry logic.
     *
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     *
     * @throws \Throwable
     */
    private function withRetry(callable $operation, string $operationName = 'operation')
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                return $operation();
            } catch (RuntimeException $e) {
                $lastException = $e;

                if ($attempt < $this->retryAttempts) {
                    Log::warning("ManticoreSearch: {$operationName} failed (attempt {$attempt}), retrying...", [
                        'error' => $e->getMessage(),
                    ]);
                    usleep($this->retryDelayMs * 1000 * $attempt);
                }
            }
        }

        throw $lastException ?? new RuntimeException("ManticoreSearch: {$operationName} failed after {$this->retryAttempts} attempts");
    }

    /**
     * Insert release into ManticoreSearch releases_rt realtime index.
     */
    public function insertRelease(array $parameters): bool
    {
        if (empty($parameters['id'])) {
            Log::warning('ManticoreSearch: Cannot insert release without ID');

            return false;
        }

        try {
            return $this->withRetry(function () use ($parameters) {
                $document = [
                    'name' => $this->sanitizeString($parameters['name'] ?? ''),
                    'searchname' => $this->sanitizeString($parameters['searchname'] ?? ''),
                    'fromname' => $this->sanitizeString($parameters['fromname'] ?? ''),
                    'categories_id' => (int) ($parameters['categories_id'] ?? 0),
                    'filename' => $this->sanitizeString($parameters['filename'] ?? ''),
                ];

                $this->manticoreSearch->table($this->getReleasesIndex())
                    ->replaceDocument($document, (int) $parameters['id']);

                return true;
            }, 'insertRelease');
        } catch (ResponseException $e) {
            Log::error('ManticoreSearch insertRelease ResponseException: '.$e->getMessage(), [
                'release_id' => $parameters['id'],
                'index' => $this->getReleasesIndex(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch insertRelease error: '.$e->getMessage(), [
                'release_id' => $parameters['id'],
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return false;
    }

    /**
     * Batch insert releases for better performance.
     *
     * @param  array<array>  $releases  Array of release data with 'id', 'name', 'searchname', 'fromname', 'categories_id', 'filename'
     * @return array{success: int, failed: int}
     */
    public function insertReleasesBatch(array $releases): array
    {
        $success = 0;
        $failed = 0;
        $batchSize = $this->config['batch_size'];
        $batches = array_chunk($releases, $batchSize);

        foreach ($batches as $batch) {
            try {
                $documents = [];
                foreach ($batch as $release) {
                    if (empty($release['id'])) {
                        $failed++;

                        continue;
                    }

                    $documents[] = [
                        'id' => (int) $release['id'],
                        'name' => $this->sanitizeString($release['name'] ?? ''),
                        'searchname' => $this->sanitizeString($release['searchname'] ?? ''),
                        'fromname' => $this->sanitizeString($release['fromname'] ?? ''),
                        'categories_id' => (int) ($release['categories_id'] ?? 0),
                        'filename' => $this->sanitizeString($release['filename'] ?? ''),
                    ];
                }

                if (! empty($documents)) {
                    $this->withRetry(function () use ($documents) {
                        $this->manticoreSearch->table($this->getReleasesIndex())
                            ->replaceDocuments($documents);
                    }, 'insertReleasesBatch');

                    $success += \count($documents);
                }
            } catch (\Throwable $e) {
                Log::error('ManticoreSearch insertReleasesBatch error: '.$e->getMessage(), [
                    'batch_size' => \count($batch),
                ]);
                $failed += \count($batch);
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Insert predb entry into the Manticore RT table.
     */
    public function insertPredb(array $parameters): bool
    {
        if (empty($parameters['id'])) {
            Log::warning('ManticoreSearch: Cannot insert predb without ID');

            return false;
        }

        try {
            return $this->withRetry(function () use ($parameters) {
                $document = [
                    'title' => $this->sanitizeString($parameters['title'] ?? ''),
                    'filename' => $this->sanitizeString($parameters['filename'] ?? ''),
                    'source' => $this->sanitizeString($parameters['source'] ?? ''),
                ];

                $this->manticoreSearch->table($this->getPredbIndex())
                    ->replaceDocument($document, (int) $parameters['id']);

                return true;
            }, 'insertPredb');
        } catch (ResponseException $e) {
            Log::error('ManticoreSearch insertPredb ResponseException: '.$e->getMessage(), [
                'predb_id' => $parameters['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch insertPredb error: '.$e->getMessage(), [
                'predb_id' => $parameters['id'],
            ]);
        }

        return false;
    }

    /**
     * Batch insert predb entries for better performance.
     *
     * @param  array<array>  $entries  Array of predb data with 'id', 'title', 'filename', 'source'
     * @return array{success: int, failed: int}
     */
    public function insertPredbBatch(array $entries): array
    {
        $success = 0;
        $failed = 0;
        $batchSize = $this->config['batch_size'];
        $batches = array_chunk($entries, $batchSize);

        foreach ($batches as $batch) {
            try {
                $documents = [];
                foreach ($batch as $entry) {
                    if (empty($entry['id'])) {
                        $failed++;

                        continue;
                    }

                    $documents[] = [
                        'id' => (int) $entry['id'],
                        'title' => $this->sanitizeString($entry['title'] ?? ''),
                        'filename' => $this->sanitizeString($entry['filename'] ?? ''),
                        'source' => $this->sanitizeString($entry['source'] ?? ''),
                    ];
                }

                if (! empty($documents)) {
                    $this->withRetry(function () use ($documents) {
                        $this->manticoreSearch->table($this->getPredbIndex())
                            ->replaceDocuments($documents);
                    }, 'insertPredbBatch');

                    $success += \count($documents);
                }
            } catch (\Throwable $e) {
                Log::error('ManticoreSearch insertPredbBatch error: '.$e->getMessage(), [
                    'batch_size' => \count($batch),
                ]);
                $failed += \count($batch);
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Delete release from Manticore RT tables.
     *
     * @param  array  $identifiers  ['g' => Release GUID(mandatory), 'i' => ReleaseID(optional, pass false)]
     */
    public function deleteRelease(array $identifiers): bool
    {
        if (empty($identifiers['g'])) {
            Log::warning('ManticoreSearch: Cannot delete release without GUID');

            return false;
        }

        try {
            $releaseId = $identifiers['i'] ?? false;

            if ($releaseId === false || empty($releaseId)) {
                $release = Release::query()->where('guid', $identifiers['g'])->first(['id']);
                $releaseId = $release?->id;
            }

            if (empty($releaseId)) {
                Log::warning('ManticoreSearch: Could not find release ID for deletion', [
                    'guid' => $identifiers['g'],
                ]);

                return false;
            }

            return $this->withRetry(function () use ($releaseId) {
                $this->manticoreSearch->table($this->getReleasesIndex())
                    ->deleteDocument((int) $releaseId);

                return true;
            }, 'deleteRelease');
        } catch (ResponseException $e) {
            // Document not found is not an error
            if (stripos($e->getMessage(), 'not found') !== false) {
                return true;
            }

            Log::error('ManticoreSearch deleteRelease error: '.$e->getMessage(), [
                'guid' => $identifiers['g'],
                'id' => $identifiers['i'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch deleteRelease error: '.$e->getMessage(), [
                'guid' => $identifiers['g'],
            ]);
        }

        return false;
    }

    /**
     * Delete multiple releases by IDs.
     *
     * @param  array<int>  $ids
     */
    public function deleteReleasesByIds(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $deleted = 0;

        try {
            foreach (array_chunk($ids, $this->config['batch_size']) as $batch) {
                $this->withRetry(function () use ($batch) {
                    $this->manticoreSearch->table($this->getReleasesIndex())
                        ->deleteDocuments($batch);
                }, 'deleteReleasesByIds');

                $deleted += \count($batch);
            }
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch deleteReleasesByIds error: '.$e->getMessage(), [
                'count' => \count($ids),
            ]);
        }

        return $deleted;
    }

    /**
     * Sanitize a string for safe storage in Manticore.
     */
    private function sanitizeString(?string $string): string
    {
        if ($string === null || $string === '') {
            return '';
        }

        // Remove null bytes and invalid UTF-8 sequences
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $string) ?? $string;

        // Ensure valid UTF-8
        if (! mb_check_encoding($string, 'UTF-8')) {
            $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        }

        return trim($string);
    }

    /**
     * Escapes characters that are treated as special operators by the query language parser.
     */
    public static function escapeString(string $string): string
    {
        if ($string === '*' || $string === '') {
            return '';
        }

        // Characters that need escaping in Manticore
        $specialChars = [
            '\\' => '\\\\',
            '(' => '\(',
            ')' => '\)',
            '@' => '\@',
            '~' => '\~',
            '"' => '\"',
            '&' => '\&',
            '/' => '\/',
            '$' => '\$',
            '=' => '\=',
            "'" => "\'",
            '-' => '\-',
            '!' => '\!',
            '[' => '\[',
            ']' => '\]',
            '^' => '\^',
            '{' => '\{',
            '}' => '\}',
            '<' => '\<',
            '>' => '\>',
            '|' => '\|',
        ];

        $string = strtr($string, $specialChars);

        // Remove double escaping for already escaped sequences
        $string = preg_replace('/\\\\{3,}/', '\\\\', $string) ?? $string;

        // Clean up trailing special characters that might cause issues
        $string = rtrim($string, '-!\\');

        return trim($string);
    }

    /**
     * Update release in Manticore index.
     */
    public function updateRelease(int|string $releaseID): bool
    {
        if (empty($releaseID)) {
            Log::warning('ManticoreSearch: Cannot update release without ID');

            return false;
        }

        try {
            $release = Release::query()
                ->where('releases.id', $releaseID)
                ->leftJoin('release_files as rf', 'releases.id', '=', 'rf.releases_id')
                ->select([
                    'releases.id',
                    'releases.name',
                    'releases.searchname',
                    'releases.fromname',
                    'releases.categories_id',
                    DB::raw('IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") AS filename'),
                ])
                ->groupBy('releases.id')
                ->first();

            if ($release === null) {
                Log::warning('ManticoreSearch: Release not found for update', ['id' => $releaseID]);

                return false;
            }

            return $this->insertRelease($release->toArray());
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch updateRelease error: '.$e->getMessage(), [
                'release_id' => $releaseID,
            ]);

            return false;
        }
    }

    /**
     * Update Manticore Predb index for given predb_id.
     */
    public function updatePreDb(array $parameters): bool
    {
        if (empty($parameters)) {
            Log::warning('ManticoreSearch: Cannot update predb with empty parameters');

            return false;
        }

        return $this->insertPredb($parameters);
    }

    /**
     * Truncate (clear) an RT index.
     *
     * @param  array<string>  $indexes  Index names to truncate
     */
    public function truncateRTIndex(array $indexes = []): bool
    {
        if (empty($indexes)) {
            $this->cli->error('You need to provide index name to truncate');

            return false;
        }

        $success = true;
        $validIndexes = array_values($this->config['indexes']);

        foreach ($indexes as $index) {
            if (! \in_array($index, $validIndexes, true)) {
                $this->cli->error('Unsupported index: '.$index);
                $success = false;

                continue;
            }

            try {
                $this->manticoreSearch->table($index)->truncate();
                $this->cli->info('Truncating index '.$index.' finished.');
            } catch (ResponseException $e) {
                if (stripos($e->getMessage(), 'index') !== false) {
                    $this->createIndex($index);
                } else {
                    $this->cli->error('Error truncating index '.$index.': '.$e->getMessage());
                    $success = false;
                }
            } catch (\Throwable $e) {
                $this->cli->error('Unexpected error truncating index '.$index.': '.$e->getMessage());
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Create an index with the predefined schema.
     */
    public function createIndex(string $index): bool
    {
        if (! isset(self::INDEX_SCHEMAS[$index])) {
            $this->cli->error("Unknown index schema for: {$index}");

            return false;
        }

        try {
            $this->manticoreSearch->table($index)->create(self::INDEX_SCHEMAS[$index]);
            $this->cli->info("Created index: {$index}");

            return true;
        } catch (\Throwable $e) {
            $this->cli->error("Error creating index {$index}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Create index if it doesn't exist.
     */
    public function createIndexIfNotExists(string $index): bool
    {
        if ($this->indexExists($index)) {
            return true;
        }

        return $this->createIndex($index);
    }

    /**
     * Optimize the RT indices.
     */
    public function optimizeRTIndex(?string $index = null): bool
    {
        $success = true;
        $indexes = $index !== null ? [$index] : $this->config['indexes'];

        foreach ($indexes as $idx) {
            try {
                $this->manticoreSearch->table($idx)->flush();
                $this->manticoreSearch->table($idx)->optimize();
                Log::info("Successfully optimized index: {$idx}");
            } catch (ResponseException $e) {
                Log::error('Failed to optimize index '.$idx.': '.$e->getMessage());
                $success = false;
            } catch (\Throwable $e) {
                Log::error('Unexpected error optimizing index '.$idx.': '.$e->getMessage());
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Search indexes with caching support.
     *
     * @param  string  $rt_index  Index name to search
     * @param  string|null  $searchString  Search string (used when searchArray is empty)
     * @param  array<string>  $column  Columns to search in (for simple search)
     * @param  array<string, string>  $searchArray  Field => value pairs for field-specific search
     * @return array{id: array<int>, data: array<array>}
     */
    public function searchIndexes(string $rt_index, ?string $searchString, array $column = [], array $searchArray = []): array
    {
        if (empty($rt_index)) {
            Log::warning('ManticoreSearch: Index name is required for search');

            return [];
        }

        // Create cache key for search results
        $cacheKey = 'manticore:'.md5(serialize([
            'index' => $rt_index,
            'search' => $searchString,
            'columns' => $column,
            'array' => $searchArray,
        ]));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Build the search expression
        $searchExpr = $this->buildSearchExpression($searchString, $column, $searchArray);
        if ($searchExpr === null) {
            return [];
        }

        $maxMatches = $this->config['max_matches'];

        try {
            $results = $this->executeSearch($rt_index, $searchExpr, $maxMatches);
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch searchIndexes error: '.$e->getMessage(), [
                'index' => $rt_index,
                'search' => $searchString,
            ]);

            return [];
        }

        // Parse results
        $resultIds = [];
        $resultData = [];
        foreach ($results as $doc) {
            $resultIds[] = $doc->getId();
            $resultData[] = $doc->getData();
        }

        $result = [
            'id' => $resultIds,
            'data' => $resultData,
        ];

        // Cache results
        Cache::put($cacheKey, $result, now()->addMinutes($this->cacheMinutes));

        return $result;
    }

    /**
     * Build search expression from parameters.
     */
    private function buildSearchExpression(?string $searchString, array $column, array $searchArray): ?string
    {
        if (! empty($searchArray)) {
            $terms = [];
            foreach ($searchArray as $key => $value) {
                if (! empty($value)) {
                    $escapedValue = self::escapeString($value);
                    if (! empty($escapedValue)) {
                        $terms[] = '@@relaxed @'.$key.' '.$escapedValue;
                    }
                }
            }

            return ! empty($terms) ? implode(' ', $terms) : null;
        }

        if (! empty($searchString)) {
            $escapedSearch = self::escapeString($searchString);
            if (empty($escapedSearch)) {
                return null;
            }

            $searchColumns = '';
            if (! empty($column)) {
                $searchColumns = \count($column) > 1
                    ? '@('.implode(',', $column).')'
                    : '@'.$column[0];
            }

            return '@@relaxed '.$searchColumns.' '.$escapedSearch;
        }

        return null;
    }

    /**
     * Execute the search query with retry on sort error.
     */
    private function executeSearch(string $index, string $searchExpr, int $maxMatches): iterable
    {
        $avoidSort = ($index === self::INDEX_PREDB);

        $buildQuery = function (bool $withSort) use ($index, $searchExpr, $maxMatches) {
            $query = (new Search($this->manticoreSearch))
                ->setTable($index)
                ->option('ranker', 'sph04')
                ->maxMatches($maxMatches)
                ->limit($maxMatches)
                ->stripBadUtf8(true)
                ->search($searchExpr);

            if ($withSort) {
                $query->sort('id', 'desc');
            }

            return $query;
        };

        try {
            return $buildQuery(! $avoidSort)->get();
        } catch (ResponseException $e) {
            // Retry without sorting if we hit the sort-by attributes limit
            if (stripos($e->getMessage(), 'too many sort-by attributes') !== false) {
                Log::warning('ManticoreSearch: Retrying search without sorting', ['index' => $index]);

                return $buildQuery(false)->get();
            }

            throw $e;
        }
    }

    /**
     * Search without caching (useful for real-time needs).
     *
     * @return array{id: array<int>, data: array<array>}
     */
    public function searchIndexesNoCache(string $rt_index, ?string $searchString, array $column = [], array $searchArray = []): array
    {
        if (empty($rt_index)) {
            return [];
        }

        $searchExpr = $this->buildSearchExpression($searchString, $column, $searchArray);
        if ($searchExpr === null) {
            return [];
        }

        try {
            $results = $this->executeSearch($rt_index, $searchExpr, $this->config['max_matches']);

            $resultIds = [];
            $resultData = [];
            foreach ($results as $doc) {
                $resultIds[] = $doc->getId();
                $resultData[] = $doc->getData();
            }

            return [
                'id' => $resultIds,
                'data' => $resultData,
            ];
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch searchIndexesNoCache error: '.$e->getMessage(), [
                'index' => $rt_index,
            ]);

            return [];
        }
    }

    /**
     * Clear search cache for a specific query or all cached searches.
     */
    public function clearSearchCache(?string $rt_index = null, ?string $searchString = null): void
    {
        if ($rt_index !== null && $searchString !== null) {
            $cacheKey = 'manticore:'.md5(serialize([
                'index' => $rt_index,
                'search' => $searchString,
                'columns' => [],
                'array' => [],
            ]));
            Cache::forget($cacheKey);
        }

        // For full cache clear, we'd need to track keys or use tags
        // This is a limitation of the current implementation
    }

    /**
     * Get statistics for all configured indexes.
     *
     * @return array<string, array{docs: int, size: string, ram: string}|null>
     */
    public function getAllIndexStats(): array
    {
        $stats = [];

        foreach ($this->config['indexes'] as $key => $index) {
            $stats[$key] = $this->getIndexStatus($index);
        }

        return $stats;
    }

    /**
     * Get the current configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
