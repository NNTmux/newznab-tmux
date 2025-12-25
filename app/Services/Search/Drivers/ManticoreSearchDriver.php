<?php

namespace App\Services\Search\Drivers;

use App\Models\Release;
use App\Services\Search\Contracts\SearchDriverInterface;
use Blacklight\ColorCLI;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Manticoresearch\Client;
use Manticoresearch\Exceptions\ResponseException;
use Manticoresearch\Exceptions\RuntimeException;
use Manticoresearch\Search;

/**
 * ManticoreSearch driver for full-text search functionality.
 */
class ManticoreSearchDriver implements SearchDriverInterface
{
    protected array $config;

    protected array $connection;

    public Client $manticoreSearch;

    public Search $search;

    private ColorCLI $cli;

    /**
     * Establishes a connection to ManticoreSearch HTTP port.
     */
    public function __construct(array $config = [])
    {
        $this->config = ! empty($config) ? $config : config('search.drivers.manticore');
        $this->connection = ['host' => $this->config['host'], 'port' => $this->config['port']];
        $this->manticoreSearch = new Client($this->connection);
        $this->search = new Search($this->manticoreSearch);
        $this->cli = new ColorCLI;
    }

    /**
     * Get the driver name.
     */
    public function getDriverName(): string
    {
        return 'manticore';
    }

    /**
     * Check if ManticoreSearch is available.
     */
    public function isAvailable(): bool
    {
        try {
            $status = $this->manticoreSearch->nodes()->status();

            return ! empty($status);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                Log::debug('ManticoreSearch not available: '.$e->getMessage());
            }

            return false;
        }
    }

    /**
     * Check if autocomplete is enabled.
     */
    public function isAutocompleteEnabled(): bool
    {
        return ($this->config['autocomplete']['enabled'] ?? true) === true;
    }

    /**
     * Check if suggest is enabled.
     */
    public function isSuggestEnabled(): bool
    {
        return ($this->config['suggest']['enabled'] ?? true) === true;
    }

    /**
     * Get the releases index name.
     */
    public function getReleasesIndex(): string
    {
        return $this->config['indexes']['releases'] ?? 'releases_rt';
    }

    /**
     * Get the predb index name.
     */
    public function getPredbIndex(): string
    {
        return $this->config['indexes']['predb'] ?? 'predb_rt';
    }

    /**
     * Insert release into ManticoreSearch releases_rt realtime index
     */
    public function insertRelease(array $parameters): void
    {
        if (empty($parameters['id'])) {
            Log::warning('ManticoreSearch: Cannot insert release without ID');

            return;
        }

        try {
            $document = [
                'name' => $parameters['name'] ?? '',
                'searchname' => $parameters['searchname'] ?? '',
                'fromname' => $parameters['fromname'] ?? '',
                'categories_id' => (string) ($parameters['categories_id'] ?? ''),
                'filename' => $parameters['filename'] ?? '',
            ];

            $this->manticoreSearch->table($this->config['indexes']['releases'])
                ->replaceDocument($document, $parameters['id']);

        } catch (ResponseException $e) {
            Log::error('ManticoreSearch insertRelease ResponseException: '.$e->getMessage(), [
                'release_id' => $parameters['id'],
                'index' => $this->config['indexes']['releases'],
            ]);
        } catch (RuntimeException $e) {
            Log::error('ManticoreSearch insertRelease RuntimeException: '.$e->getMessage(), [
                'release_id' => $parameters['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch insertRelease unexpected error: '.$e->getMessage(), [
                'release_id' => $parameters['id'],
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Insert release into Manticore RT table.
     */
    public function insertPredb(array $parameters): void
    {
        if (empty($parameters['id'])) {
            Log::warning('ManticoreSearch: Cannot insert predb without ID');

            return;
        }

        try {
            $document = [
                'title' => $parameters['title'] ?? '',
                'filename' => $parameters['filename'] ?? '',
                'source' => $parameters['source'] ?? '',
            ];

            $this->manticoreSearch->table($this->config['indexes']['predb'])
                ->replaceDocument($document, $parameters['id']);

        } catch (ResponseException $e) {
            Log::error('ManticoreSearch insertPredb ResponseException: '.$e->getMessage(), [
                'predb_id' => $parameters['id'],
            ]);
        } catch (RuntimeException $e) {
            Log::error('ManticoreSearch insertPredb RuntimeException: '.$e->getMessage(), [
                'predb_id' => $parameters['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch insertPredb unexpected error: '.$e->getMessage(), [
                'predb_id' => $parameters['id'],
            ]);
        }
    }

    /**
     * Delete release from Manticore RT tables.
     *
     * @param  int  $id  Release ID
     */
    public function deleteRelease(int $id): void
    {
        if (empty($id)) {
            Log::warning('ManticoreSearch: Cannot delete release without ID');

            return;
        }

        try {
            $this->manticoreSearch->table($this->config['indexes']['releases'])
                ->deleteDocument($id);
        } catch (ResponseException $e) {
            Log::error('ManticoreSearch deleteRelease error: '.$e->getMessage(), [
                'id' => $id,
            ]);
        }
    }

    /**
     * Delete a predb record from the index.
     *
     * @param  int  $id  Predb ID
     */
    public function deletePreDb(int $id): void
    {
        if (empty($id)) {
            Log::warning('ManticoreSearch: Cannot delete predb without ID');

            return;
        }

        try {
            $this->manticoreSearch->table($this->config['indexes']['predb'])
                ->deleteDocument($id);
        } catch (ResponseException $e) {
            Log::error('ManticoreSearch deletePreDb error: '.$e->getMessage(), [
                'id' => $id,
            ]);
        }
    }

    /**
     * Bulk insert multiple releases into the index.
     *
     * @param  array  $releases  Array of release data arrays
     * @return array Results with 'success' and 'errors' counts
     */
    public function bulkInsertReleases(array $releases): array
    {
        if (empty($releases)) {
            return ['success' => 0, 'errors' => 0];
        }

        $success = 0;
        $errors = 0;

        $documents = [];
        foreach ($releases as $release) {
            if (empty($release['id'])) {
                $errors++;
                continue;
            }

            $documents[] = [
                'id' => $release['id'],
                'name' => $release['name'] ?? '',
                'searchname' => $release['searchname'] ?? '',
                'fromname' => $release['fromname'] ?? '',
                'categories_id' => (string) ($release['categories_id'] ?? ''),
                'filename' => $release['filename'] ?? '',
            ];
        }

        if (! empty($documents)) {
            try {
                $this->manticoreSearch->table($this->config['indexes']['releases'])
                    ->replaceDocuments($documents);
                $success = count($documents);
            } catch (\Throwable $e) {
                Log::error('ManticoreSearch bulkInsertReleases error: '.$e->getMessage());
                $errors += count($documents);
            }
        }

        return ['success' => $success, 'errors' => $errors];
    }

    /**
     * Delete release from Manticore RT tables by GUID.
     *
     * @param  array  $identifiers  ['g' => Release GUID(mandatory), 'id' => ReleaseID(optional, pass false)]
     */
    public function deleteReleaseByGuid(array $identifiers): void
    {
        if (empty($identifiers['g'])) {
            Log::warning('ManticoreSearch: Cannot delete release without GUID');

            return;
        }

        try {
            if ($identifiers['i'] === false || empty($identifiers['i'])) {
                $release = Release::query()->where('guid', $identifiers['g'])->first(['id']);
                $identifiers['i'] = $release?->id;
            }

            if (! empty($identifiers['i'])) {
                $this->manticoreSearch->table($this->config['indexes']['releases'])
                    ->deleteDocument($identifiers['i']);
            } else {
                Log::warning('ManticoreSearch: Could not find release ID for deletion', [
                    'guid' => $identifiers['g'],
                ]);
            }
        } catch (ResponseException $e) {
            Log::error('ManticoreSearch deleteRelease error: '.$e->getMessage(), [
                'guid' => $identifiers['g'],
                'id' => $identifiers['i'] ?? null,
            ]);
        }
    }

    /**
     * Escapes characters that are treated as special operators by the query language parser.
     */
    public static function escapeString(string $string): string
    {
        if ($string === '*' || empty($string)) {
            return '';
        }

        $from = ['\\', '(', ')', '@', '~', '"', '&', '/', '$', '=', "'", '--', '[', ']', '!', '-'];
        $to = ['\\\\', '\(', '\)', '\@', '\~', '\"', '\&', '\/', '\$', '\=', "\'", '\--', '\[', '\]', '\!', '\-'];

        $string = str_replace($from, $to, $string);

        // Clean up trailing special characters
        $string = rtrim($string, '-!');

        return trim($string);
    }

    public function updateRelease(int|string $releaseID): void
    {
        if (empty($releaseID)) {
            Log::warning('ManticoreSearch: Cannot update release without ID');

            return;
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
                    DB::raw('IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename'),
                ])
                ->groupBy('releases.id')
                ->first();

            if ($release !== null) {
                $this->insertRelease($release->toArray());
            } else {
                Log::warning('ManticoreSearch: Release not found for update', ['id' => $releaseID]);
            }
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch updateRelease error: '.$e->getMessage(), [
                'release_id' => $releaseID,
            ]);
        }
    }

    /**
     * Update Manticore Predb index for given predb_id.
     */
    public function updatePreDb(array $parameters): void
    {
        if (empty($parameters)) {
            Log::warning('ManticoreSearch: Cannot update predb with empty parameters');

            return;
        }

        $this->insertPredb($parameters);
    }

    public function truncateRTIndex(array $indexes = []): bool
    {
        if (empty($indexes)) {
            $this->cli->error('You need to provide index name to truncate');

            return false;
        }

        $success = true;
        foreach ($indexes as $index) {
            if (! \in_array($index, $this->config['indexes'], true)) {
                $this->cli->error('Unsupported index: '.$index);
                $success = false;

                continue;
            }

            try {
                $this->manticoreSearch->table($index)->truncate();
                $this->cli->info('Truncating index '.$index.' finished.');
            } catch (ResponseException $e) {
                if ($e->getMessage() === 'Invalid index') {
                    $this->createIndexIfNotExists($index);
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
     * Truncate/clear an index (remove all documents).
     * Implements SearchServiceInterface::truncateIndex
     *
     * @param  array|string  $indexes  Index name(s) to truncate
     */
    public function truncateIndex(array|string $indexes): void
    {
        $indexArray = is_array($indexes) ? $indexes : [$indexes];
        $this->truncateRTIndex($indexArray);
    }

    /**
     * Create index if it doesn't exist
     */
    private function createIndexIfNotExists(string $index): void
    {
        try {
            if ($index === 'releases_rt') {
                $this->manticoreSearch->table($index)->create([
                    'name' => ['type' => 'string'],
                    'searchname' => ['type' => 'string'],
                    'fromname' => ['type' => 'string'],
                    'filename' => ['type' => 'string'],
                    'categories_id' => ['type' => 'integer'],
                ]);
                $this->cli->info('Created releases_rt index');
            } elseif ($index === 'predb_rt') {
                $this->manticoreSearch->table($index)->create([
                    'title' => ['type' => 'string'],
                    'filename' => ['type' => 'string'],
                    'source' => ['type' => 'string'],
                ]);
                $this->cli->info('Created predb_rt index');
            }
        } catch (\Throwable $e) {
            $this->cli->error('Error creating index '.$index.': '.$e->getMessage());
        }
    }

    /**
     * Optimize the RT indices.
     */
    public function optimizeRTIndex(): bool
    {
        $success = true;

        foreach ($this->config['indexes'] as $index) {
            try {
                $this->manticoreSearch->table($index)->flush();
                $this->manticoreSearch->table($index)->optimize();
                Log::info("Successfully optimized index: {$index}");
            } catch (ResponseException $e) {
                Log::error('Failed to optimize index '.$index.': '.$e->getMessage());
                $success = false;
            } catch (\Throwable $e) {
                Log::error('Unexpected error optimizing index '.$index.': '.$e->getMessage());
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Optimize index for better search performance.
     * Implements SearchServiceInterface::optimizeIndex
     */
    public function optimizeIndex(): void
    {
        $this->optimizeRTIndex();
    }

    /**
     * Search releases index.
     *
     * @param  array|string  $phrases  Search phrases - can be a string, indexed array of terms, or associative array with field names
     * @param  int  $limit  Maximum number of results
     * @return array Array of release IDs
     */
    public function searchReleases(array|string $phrases, int $limit = 1000): array
    {
        if (is_string($phrases)) {
            // Simple string search - search in searchname field
            $searchArray = ['searchname' => $phrases];
        } elseif (is_array($phrases)) {
            // Check if it's an associative array (has string keys like 'searchname')
            $isAssociative = count(array_filter(array_keys($phrases), 'is_string')) > 0;

            if ($isAssociative) {
                // Already has field names as keys
                $searchArray = $phrases;
            } else {
                // Indexed array - combine values and search in searchname
                $searchArray = ['searchname' => implode(' ', $phrases)];
            }
        } else {
            return [];
        }

        $result = $this->searchIndexes($this->getReleasesIndex(), '', [], $searchArray);

        return ! empty($result) ? ($result['id'] ?? []) : [];
    }

    /**
     * Search predb index.
     *
     * @param  array|string  $searchTerm  Search term(s)
     * @return array Array of predb records
     */
    public function searchPredb(array|string $searchTerm): array
    {
        $searchString = is_array($searchTerm) ? implode(' ', $searchTerm) : $searchTerm;

        $result = $this->searchIndexes($this->getPredbIndex(), $searchString, ['title', 'filename'], []);

        return $result['data'] ?? [];
    }

    public function searchIndexes(string $rt_index, ?string $searchString, array $column = [], array $searchArray = []): array
    {
        if (empty($rt_index)) {
            Log::warning('ManticoreSearch: Index name is required for search');

            return [];
        }

        if (config('app.debug')) {
            Log::debug('ManticoreSearch::searchIndexes called', [
                'rt_index' => $rt_index,
                'searchString' => $searchString,
                'column' => $column,
                'searchArray' => $searchArray,
            ]);
        }

        // Create cache key for search results
        $cacheKey = md5(serialize([
            'index' => $rt_index,
            'search' => $searchString,
            'columns' => $column,
            'array' => $searchArray,
        ]));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            if (config('app.debug')) {
                Log::debug('ManticoreSearch::searchIndexes returning cached result', [
                    'cacheKey' => $cacheKey,
                    'cached_ids_count' => count($cached['id'] ?? []),
                ]);
            }

            return $cached;
        }

        // Build query string once so we can retry if needed
        $searchExpr = null;
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
            if (! empty($terms)) {
                $searchExpr = implode(' ', $terms);
            } else {
                if (config('app.debug')) {
                    Log::debug('ManticoreSearch::searchIndexes no terms after escaping searchArray');
                }

                return [];
            }
        } elseif (! empty($searchString)) {
            $escapedSearch = self::escapeString($searchString);
            if (empty($escapedSearch)) {
                if (config('app.debug')) {
                    Log::debug('ManticoreSearch::searchIndexes escapedSearch is empty');
                }

                return [];
            }

            $searchColumns = '';
            if (! empty($column)) {
                if (count($column) > 1) {
                    $searchColumns = '@('.implode(',', $column).')';
                } else {
                    $searchColumns = '@'.$column[0];
                }
            }

            $searchExpr = '@@relaxed '.$searchColumns.' '.$escapedSearch;
        } else {
            return [];
        }

        // Avoid explicit sort for predb_rt to prevent Manticore's "too many sort-by attributes" error
        $avoidSortForIndex = ($rt_index === 'predb_rt');

        try {
            // Use a fresh Search instance for every query to avoid parameter accumulation across calls
            $query = (new Search($this->manticoreSearch))
                ->setTable($rt_index)
                ->option('ranker', 'sph04')
                ->maxMatches(10000)
                ->limit(10000)
                ->stripBadUtf8(true)
                ->search($searchExpr);

            if (! $avoidSortForIndex) {
                $query->sort('id', 'desc');
            }

            $results = $query->get();
        } catch (ResponseException $e) {
            // If we hit Manticore's "too many sort-by attributes" limit, retry once without explicit sorting
            if (stripos($e->getMessage(), 'too many sort-by attributes') !== false) {
                try {
                    $query = (new Search($this->manticoreSearch))
                        ->setTable($rt_index)
                        ->option('ranker', 'sph04')
                        ->maxMatches(10000)
                        ->limit(10000)
                        ->stripBadUtf8(true)
                        ->search($searchExpr);

                    $results = $query->get();

                    Log::warning('ManticoreSearch: Retried search without sorting due to sort-by attributes limit', [
                        'index' => $rt_index,
                    ]);
                } catch (ResponseException $e2) {
                    Log::error('ManticoreSearch searchIndexes ResponseException after retry: '.$e2->getMessage(), [
                        'index' => $rt_index,
                        'search' => $searchString,
                    ]);

                    return [];
                }
            } else {
                Log::error('ManticoreSearch searchIndexes ResponseException: '.$e->getMessage(), [
                    'index' => $rt_index,
                    'search' => $searchString,
                ]);

                return [];
            }
        } catch (RuntimeException $e) {
            Log::error('ManticoreSearch searchIndexes RuntimeException: '.$e->getMessage(), [
                'index' => $rt_index,
                'search' => $searchString,
            ]);

            return [];
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch searchIndexes unexpected error: '.$e->getMessage(), [
                'index' => $rt_index,
                'search' => $searchString,
            ]);

            return [];
        }

        // Parse results and cache
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

        // Cache results for 5 minutes
        Cache::put($cacheKey, $result, now()->addMinutes($this->config['cache_minutes'] ?? 5));

        return $result;
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
        $autocompleteConfig = $this->config['autocomplete'] ?? [
            'enabled' => true,
            'min_length' => 2,
            'max_results' => 10,
            'cache_minutes' => 10,
        ];

        if (! ($autocompleteConfig['enabled'] ?? true)) {
            return [];
        }

        $query = trim($query);
        $minLength = $autocompleteConfig['min_length'] ?? 2;
        if (strlen($query) < $minLength) {
            return [];
        }

        $index = $index ?? ($this->config['indexes']['releases'] ?? 'releases_rt');
        $cacheKey = 'manticore:autocomplete:'.md5($index.$query);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $suggestions = [];
        $maxResults = $autocompleteConfig['max_results'] ?? 10;

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
                ->sort('id', 'desc')
                ->limit($maxResults * 3)
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

                if (count($suggestions) >= $maxResults) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                Log::warning('ManticoreSearch autocomplete error: '.$e->getMessage());
            }
        }

        if (! empty($suggestions)) {
            $cacheMinutes = (int) ($autocompleteConfig['cache_minutes'] ?? 10);
            Cache::put($cacheKey, $suggestions, now()->addMinutes($cacheMinutes));
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
     *
     * @param  string  $query  The search query to check
     * @param  string|null  $index  Index to use for suggestions
     * @return array<array{suggest: string, distance: int, docs: int}>
     */
    public function suggest(string $query, ?string $index = null): array
    {
        $suggestConfig = $this->config['suggest'] ?? [
            'enabled' => true,
            'max_edits' => 4,
        ];

        if (! ($suggestConfig['enabled'] ?? true)) {
            return [];
        }

        $query = trim($query);
        if (empty($query)) {
            return [];
        }

        $index = $index ?? ($this->config['indexes']['releases'] ?? 'releases_rt');
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
                        'max_edits' => $suggestConfig['max_edits'] ?? 4,
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
            Cache::put($cacheKey, $suggestions, now()->addMinutes($this->config['cache_minutes'] ?? 5));
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
                $words = preg_split('/[\s.\-_]+/', strtolower($searchname));
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
            uasort($termCounts, fn ($a, $b) => $b['count'] - $a['count']);

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
                Log::debug('ManticoreSearch suggest fallback error: '.$e->getMessage());
            }

            return [];
        }
    }
}

