<?php

namespace Blacklight;

use App\Models\Release;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Manticoresearch\Client;
use Manticoresearch\Exceptions\ResponseException;
use Manticoresearch\Exceptions\RuntimeException;
use Manticoresearch\Query\BoolQuery;
use Manticoresearch\Query\In;
use Manticoresearch\Query\MatchQuery;
use Manticoresearch\Query\Range;
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
     * Supports single host or multiple hosts for high availability.
     */
    public function __construct(?Client $client = null)
    {
        $this->config = $this->loadConfig();
        $this->connection = $this->buildConnectionConfig();

        $this->manticoreSearch = $client ?? new Client($this->connection);
        $this->cli = new ColorCLI;

        $this->retryAttempts = $this->config['retry_attempts'] ?? self::DEFAULT_RETRY_ATTEMPTS;
        $this->retryDelayMs = $this->config['retry_delay_ms'] ?? self::DEFAULT_RETRY_DELAY_MS;
        $this->cacheMinutes = $this->config['cache_minutes'] ?? self::DEFAULT_CACHE_MINUTES;
    }

    /**
     * Build connection configuration supporting single or multiple hosts.
     */
    private function buildConnectionConfig(): array
    {
        // Check for multiple hosts configuration
        $hostsString = $this->config['hosts'] ?? '';

        if (! empty($hostsString)) {
            $connections = [];
            $hosts = explode(',', $hostsString);

            foreach ($hosts as $hostEntry) {
                $hostEntry = trim($hostEntry);
                if (empty($hostEntry)) {
                    continue;
                }

                $parts = explode(':', $hostEntry);
                $connections[] = [
                    'host' => $parts[0],
                    'port' => (int) ($parts[1] ?? self::DEFAULT_PORT),
                ];
            }

            if (! empty($connections)) {
                return [
                    'connections' => $connections,
                    'retries' => $this->config['retries'] ?? count($connections),
                ];
            }
        }

        // Single host configuration
        return [
            'host' => $this->config['host'],
            'port' => $this->config['port'],
        ];
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
            'hosts' => $config['hosts'] ?? '',
            'retries' => $config['retries'] ?? 2,
            'indexes' => $config['indexes'] ?? [
                'releases' => self::INDEX_RELEASES,
                'predb' => self::INDEX_PREDB,
            ],
            'retry_attempts' => $config['retry_attempts'] ?? self::DEFAULT_RETRY_ATTEMPTS,
            'retry_delay_ms' => $config['retry_delay_ms'] ?? self::DEFAULT_RETRY_DELAY_MS,
            'cache_minutes' => $config['cache_minutes'] ?? self::DEFAULT_CACHE_MINUTES,
            'max_matches' => $config['max_matches'] ?? self::DEFAULT_MAX_MATCHES,
            'batch_size' => $config['batch_size'] ?? self::DEFAULT_BATCH_SIZE,
            'autocomplete' => $config['autocomplete'] ?? [
                'enabled' => true,
                'min_length' => 2,
                'max_results' => 10,
                'fuzziness' => 1,
                'cache_minutes' => 10,
            ],
            'suggest' => $config['suggest'] ?? [
                'enabled' => true,
                'max_edits' => 4,
            ],
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

        // Characters that need escaping in Manticore full-text search
        $from = ['\\', '(', ')', '|', '-', '!', '@', '~', '"', '&', '/', '^', '$', '=', "'", "\x00", "\n", "\r", "\x1a"];
        $to = ['\\\\', '\(', '\)', '\|', '\-', '\!', '\@', '\~', '\"', '\&', '\/', '\^', '\$', '\=', "\'", '\\0', '\\n', '\\r', '\\Z'];

        return str_replace($from, $to, $string);
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

    /**
     * Get autocomplete suggestions for a search query.
     * Searches the releases index and returns matching searchnames.
     *
     * @param  string  $query  The partial search query
     * @param  string|null  $index  Index to search (defaults to releases index)
     * @return array<array{suggest: string, distance: int, docs: int}>
     */
    public function autocomplete(string $query, ?string $index = null): array
    {
        $autocompleteConfig = $this->config['autocomplete'];

        if (! $autocompleteConfig['enabled']) {
            return [];
        }

        $query = trim($query);
        if (strlen($query) < $autocompleteConfig['min_length']) {
            return [];
        }

        $index = $index ?? $this->getReleasesIndex();
        $cacheKey = 'manticore:autocomplete:'.md5($index.$query);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $suggestions = [];

        try {
            // Search releases index for matching searchnames
            $escapedQuery = self::escapeString($query);
            if (empty($escapedQuery)) {
                return [];
            }

            // Use relaxed search on searchname field
            $searchExpr = '@@relaxed @searchname '.$escapedQuery;

            $search = (new Search($this->manticoreSearch))
                ->setTable($index)
                ->search($searchExpr)
                ->sort('id', 'desc') // Sort by id descending to get latest results first
                ->limit($autocompleteConfig['max_results'] * 3) // Get more to dedupe
                ->stripBadUtf8(true);

            $results = $search->get();

            $seen = [];
            foreach ($results as $doc) {
                $data = $doc->getData();
                $searchname = $data['searchname'] ?? '';

                if (empty($searchname)) {
                    continue;
                }

                // Create a clean suggestion from the searchname
                $suggestion = $this->extractSuggestion($searchname, $query);

                if (! empty($suggestion) && ! isset($seen[strtolower($suggestion)])) {
                    $seen[strtolower($suggestion)] = true;
                    $suggestions[] = [
                        'suggest' => $suggestion,
                        'distance' => 0,
                        'docs' => 1,
                    ];
                }

                if (count($suggestions) >= $autocompleteConfig['max_results']) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                Log::warning('ManticoreSearch autocomplete error: '.$e->getMessage());
            }
        }

        if (! empty($suggestions)) {
            Cache::put($cacheKey, $suggestions, now()->addMinutes((int) $autocompleteConfig['cache_minutes']));
        }

        return $suggestions;
    }

    /**
     * Extract a clean suggestion from a searchname.
     *
     * @param  string  $searchname  The full searchname
     * @param  string  $query  The user's query
     * @return string|null The extracted suggestion
     */
    private function extractSuggestion(string $searchname, string $query): ?string
    {
        // Clean up the searchname - remove file extensions, quality tags at the end
        $clean = preg_replace('/\.(mkv|avi|mp4|wmv|nfo|nzb|par2|rar|zip|r\d+)$/i', '', $searchname);

        // Replace dots and underscores with spaces for readability
        $clean = str_replace(['.', '_'], ' ', $clean);

        // Remove multiple spaces
        $clean = preg_replace('/\s+/', ' ', $clean);
        $clean = trim($clean);

        if (empty($clean)) {
            return null;
        }

        // If the clean name is reasonable length, use it
        if (strlen($clean) <= 80) {
            return $clean;
        }

        // For very long names, try to extract the relevant part
        // Find where the query matches and extract context around it
        $pos = stripos($clean, $query);
        if ($pos !== false) {
            // Get up to 80 chars starting from the match position, or from beginning if match is early
            $start = max(0, $pos - 10);
            $extracted = substr($clean, $start, 80);

            // Clean up - don't cut mid-word
            if ($start > 0) {
                $extracted = preg_replace('/^\S*\s/', '', $extracted);
            }
            $extracted = preg_replace('/\s\S*$/', '', $extracted);

            return trim($extracted);
        }

        // Fallback: just truncate
        return substr($clean, 0, 80);
    }


    /**
     * Get spell correction suggestions ("Did you mean?").
     * Uses CALL SUGGEST or falls back to searching releases for similar terms.
     *
     * @param  string  $query  The search query to check
     * @param  string|null  $index  Index to use for suggestions
     * @return array<array{suggest: string, distance: int, docs: int}>
     */
    public function suggest(string $query, ?string $index = null): array
    {
        $suggestConfig = $this->config['suggest'];

        if (! $suggestConfig['enabled']) {
            return [];
        }

        $query = trim($query);
        if (empty($query)) {
            return [];
        }

        $index = $index ?? $this->getReleasesIndex();
        $cacheKey = 'manticore:suggest:'.md5($index.$query);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $suggestions = [];

        try {
            // Try native CALL SUGGEST first
            $result = $this->manticoreSearch->suggest([
                'table' => $index,
                'body' => [
                    'query' => $query,
                    'options' => [
                        'limit' => 5,
                        'max_edits' => $suggestConfig['max_edits'],
                    ],
                ],
            ]);

            if (! empty($result) && is_array($result)) {
                foreach ($result as $item) {
                    if (isset($item['suggest']) && $item['suggest'] !== $query) {
                        $suggestions[] = [
                            'suggest' => $item['suggest'],
                            'distance' => $item['distance'] ?? 0,
                            'docs' => $item['docs'] ?? 0,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                Log::debug('ManticoreSearch native suggest failed: '.$e->getMessage());
            }
        }

        // If native suggest didn't return results, try a fuzzy search fallback
        if (empty($suggestions)) {
            $suggestions = $this->suggestFallback($query, $index);
        }

        if (! empty($suggestions)) {
            Cache::put($cacheKey, $suggestions, now()->addMinutes((int) $this->cacheMinutes));
        }

        return $suggestions;
    }

    /**
     * Fallback suggest using similar searchname matches.
     *
     * @param  string  $query  The search query
     * @param  string  $index  Index to search
     * @return array<array{suggest: string, distance: int, docs: int}>
     */
    private function suggestFallback(string $query, string $index): array
    {
        try {
            // Try to find releases with similar searchnames
            // This helps when users misspell common terms
            $escapedQuery = self::escapeString($query);
            if (empty($escapedQuery)) {
                return [];
            }

            // Use relaxed search to find partial matches
            $searchExpr = '@@relaxed @searchname '.$escapedQuery;

            $search = (new Search($this->manticoreSearch))
                ->setTable($index)
                ->search($searchExpr)
                ->limit(20)
                ->stripBadUtf8(true);

            $results = $search->get();

            // Extract common terms from the results that differ from the query
            $termCounts = [];
            foreach ($results as $doc) {
                $data = $doc->getData();
                $searchname = $data['searchname'] ?? '';

                // Extract words from searchname
                $words = preg_split('/[\s\.\-\_]+/', strtolower($searchname));
                foreach ($words as $word) {
                    if (strlen($word) >= 3 && $word !== strtolower($query)) {
                        // Check if word is similar to query (within edit distance)
                        $distance = levenshtein(strtolower($query), $word);
                        if ($distance > 0 && $distance <= 3) {
                            if (! isset($termCounts[$word])) {
                                $termCounts[$word] = ['count' => 0, 'distance' => $distance];
                            }
                            $termCounts[$word]['count']++;
                        }
                    }
                }
            }

            // Sort by count (most common first)
            uasort($termCounts, fn($a, $b) => $b['count'] - $a['count']);

            $suggestions = [];
            foreach (array_slice($termCounts, 0, 5, true) as $term => $data) {
                $suggestions[] = [
                    'suggest' => $term,
                    'distance' => $data['distance'],
                    'docs' => $data['count'],
                ];
            }

            return $suggestions;
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                Log::warning('ManticoreSearch suggest fallback error: '.$e->getMessage());
            }
            return [];
        }
    }

    /**
     * Search with result highlighting.
     *
     * @param  string  $index  Index to search
     * @param  string  $query  Search query
     * @param  array<string>  $highlightFields  Fields to highlight
     * @param  int  $limit  Maximum results
     * @return array{results: array, total: int}
     */
    public function searchWithHighlight(
        string $index,
        string $query,
        array $highlightFields = ['searchname', 'name'],
        int $limit = 100
    ): array {
        if (empty($query)) {
            return ['results' => [], 'total' => 0];
        }

        $cacheKey = 'manticore:highlight:'.md5($index.$query.implode(',', $highlightFields).$limit);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $escapedQuery = self::escapeString($query);
            if (empty($escapedQuery)) {
                return ['results' => [], 'total' => 0];
            }

            $search = (new Search($this->manticoreSearch))
                ->setTable($index)
                ->search($escapedQuery)
                ->highlight($highlightFields, [
                    'pre_tags' => '<mark class="search-highlight bg-yellow-200 dark:bg-yellow-700 px-0.5 rounded">',
                    'post_tags' => '</mark>',
                    'limit' => 256,
                    'no_match_size' => 0,
                ])
                ->limit($limit)
                ->maxMatches($this->config['max_matches'])
                ->stripBadUtf8(true);

            $resultSet = $search->get();

            $results = [];
            foreach ($resultSet as $doc) {
                $results[] = [
                    'id' => $doc->getId(),
                    'data' => $doc->getData(),
                    'highlight' => $doc->getHighlight() ?? [],
                ];
            }

            $response = [
                'results' => $results,
                'total' => $resultSet->getTotal() ?? count($results),
            ];

            Cache::put($cacheKey, $response, now()->addMinutes($this->cacheMinutes));

            return $response;
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch searchWithHighlight error: '.$e->getMessage(), [
                'index' => $index,
                'query' => $query,
            ]);

            return ['results' => [], 'total' => 0];
        }
    }

    /**
     * Advanced search using structured BoolQuery with filters.
     *
     * @param  string  $index  Index to search
     * @param  array{
     *     search?: string,
     *     searchname?: string,
     *     name?: string,
     *     fromname?: string,
     *     filename?: string,
     *     categories?: array<int>,
     *     exclude_categories?: array<int>,
     *     min_date?: string,
     *     max_date?: string,
     *     sort_field?: string,
     *     sort_dir?: string,
     *     limit?: int,
     *     offset?: int
     * }  $params  Search parameters
     * @return array{ids: array<int>, total: int}
     */
    public function advancedSearch(string $index, array $params): array
    {
        $cacheKey = 'manticore:advsearch:'.md5($index.serialize($params));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $query = new BoolQuery;
            $hasQuery = false;

            // Full-text search across multiple fields
            if (! empty($params['search'])) {
                $escaped = self::escapeString($params['search']);
                if (! empty($escaped)) {
                    $query->must(new MatchQuery($escaped, 'searchname,name,filename'));
                    $hasQuery = true;
                }
            }

            // Field-specific searches
            $fieldMappings = [
                'searchname' => 'searchname',
                'name' => 'name',
                'fromname' => 'fromname',
                'filename' => 'filename',
            ];

            foreach ($fieldMappings as $paramKey => $field) {
                if (! empty($params[$paramKey])) {
                    $escaped = self::escapeString($params[$paramKey]);
                    if (! empty($escaped)) {
                        $query->must(new MatchQuery($escaped, $field));
                        $hasQuery = true;
                    }
                }
            }

            // Category filter
            if (! empty($params['categories']) && is_array($params['categories'])) {
                $query->must(new In('categories_id', array_map('intval', $params['categories'])));
                $hasQuery = true;
            }

            // Exclude categories
            if (! empty($params['exclude_categories']) && is_array($params['exclude_categories'])) {
                $query->mustNot(new In('categories_id', array_map('intval', $params['exclude_categories'])));
            }

            if (! $hasQuery) {
                return ['ids' => [], 'total' => 0];
            }

            $limit = $params['limit'] ?? $this->config['max_matches'];
            $offset = $params['offset'] ?? 0;

            $search = (new Search($this->manticoreSearch))
                ->setTable($index)
                ->search($query)
                ->limit($limit)
                ->offset($offset)
                ->maxMatches($this->config['max_matches'])
                ->stripBadUtf8(true);

            // Sorting
            $sortField = $params['sort_field'] ?? 'id';
            $sortDir = strtolower($params['sort_dir'] ?? 'desc');
            if (in_array($sortDir, ['asc', 'desc'])) {
                $search->sort($sortField, $sortDir);
            }

            $resultSet = $search->get();

            $ids = [];
            foreach ($resultSet as $doc) {
                $ids[] = $doc->getId();
            }

            $response = [
                'ids' => $ids,
                'total' => $resultSet->getTotal() ?? count($ids),
            ];

            Cache::put($cacheKey, $response, now()->addMinutes($this->cacheMinutes));

            return $response;
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch advancedSearch error: '.$e->getMessage(), [
                'index' => $index,
                'params' => $params,
            ]);

            return ['ids' => [], 'total' => 0];
        }
    }

    /**
     * Check if autocomplete is enabled.
     */
    public function isAutocompleteEnabled(): bool
    {
        return $this->config['autocomplete']['enabled'] ?? false;
    }

    /**
     * Check if suggest is enabled.
     */
    public function isSuggestEnabled(): bool
    {
        return $this->config['suggest']['enabled'] ?? false;
    }
}
