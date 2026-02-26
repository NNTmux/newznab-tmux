<?php

declare(strict_types=1);

namespace App\Services\Search\Drivers;

use App\Models\MovieInfo;
use App\Models\Release;
use App\Models\Video;
use App\Services\Search\Contracts\SearchDriverInterface;
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
    /**
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * @var array<string, mixed>
     */
    protected array $connection;

    public Client $manticoreSearch;

    public Search $search;

    /**
     * Establishes a connection to ManticoreSearch HTTP port.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = ! empty($config) ? $config : config('search.drivers.manticore');
        $this->connection = ['host' => $this->config['host'], 'port' => $this->config['port']];
        $this->manticoreSearch = new Client($this->connection);
        $this->search = new Search($this->manticoreSearch);
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
     * Check if fuzzy search is enabled.
     */
    public function isFuzzyEnabled(): bool
    {
        return ($this->config['fuzzy']['enabled'] ?? true) === true;
    }

    /**
     * Get fuzzy search configuration.
     *
     * @return array<string, mixed>
     */
    public function getFuzzyConfig(): array
    {
        return $this->config['fuzzy'] ?? [
            'enabled' => true,
            'max_distance' => 2,
            'layouts' => 'us',
        ];
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
     * Get the movies index name.
     */
    public function getMoviesIndex(): string
    {
        return $this->config['indexes']['movies'] ?? 'movies_rt';
    }

    /**
     * Get the TV shows index name.
     */
    public function getTvShowsIndex(): string
    {
        return $this->config['indexes']['tvshows'] ?? 'tvshows_rt';
    }

    /**
     * Insert release into ManticoreSearch releases_rt realtime index
     *
     * @param  array<string, mixed>  $parameters
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
                'categories_id' => (int) ($parameters['categories_id'] ?? 0),
                'filename' => $parameters['filename'] ?? '',
                // External media IDs for efficient searching
                'imdbid' => (int) ($parameters['imdbid'] ?? 0),
                'tmdbid' => (int) ($parameters['tmdbid'] ?? 0),
                'traktid' => (int) ($parameters['traktid'] ?? 0),
                'tvdb' => (int) ($parameters['tvdb'] ?? 0),
                'tvmaze' => (int) ($parameters['tvmaze'] ?? 0),
                'tvrage' => (int) ($parameters['tvrage'] ?? 0),
                'videos_id' => (int) ($parameters['videos_id'] ?? 0),
                'movieinfo_id' => (int) ($parameters['movieinfo_id'] ?? 0),
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
     *
     * @param  array<string, mixed>  $parameters
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
     * @param  array<string, mixed>  $releases  Array of release data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertReleases(array $releases): array
    {
        if (empty($releases)) {
            return ['success' => 0, 'errors' => 0];
        }

        $documents = [];
        foreach ($releases as $release) {
            if (empty($release['id'])) {
                continue;
            }

            $documents[] = [
                'id' => $release['id'],
                'name' => (string) ($release['name'] ?? ''),
                'searchname' => (string) ($release['searchname'] ?? ''),
                'fromname' => (string) ($release['fromname'] ?? ''),
                'categories_id' => (int) ($release['categories_id'] ?? 0),
                'filename' => (string) ($release['filename'] ?? ''),
                'imdbid' => (int) ($release['imdbid'] ?? 0),
                'tmdbid' => (int) ($release['tmdbid'] ?? 0),
                'traktid' => (int) ($release['traktid'] ?? 0),
                'tvdb' => (int) ($release['tvdb'] ?? 0),
                'tvmaze' => (int) ($release['tvmaze'] ?? 0),
                'tvrage' => (int) ($release['tvrage'] ?? 0),
                'videos_id' => (int) ($release['videos_id'] ?? 0),
                'movieinfo_id' => (int) ($release['movieinfo_id'] ?? 0),
            ];
        }

        if (empty($documents)) {
            return ['success' => 0, 'errors' => 0];
        }

        try {
            $this->manticoreSearch->table($this->config['indexes']['releases'])
                ->replaceDocuments($documents);

            return ['success' => count($documents), 'errors' => 0];
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch bulkInsertReleases error: '.$e->getMessage());

            return ['success' => 0, 'errors' => count($documents)];
        }
    }

    /**
     * Bulk insert multiple predb records into the index.
     *
     * @param  array<string, mixed>  $predbRecords  Array of predb data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertPredb(array $predbRecords): array
    {
        if (empty($predbRecords)) {
            return ['success' => 0, 'errors' => 0];
        }

        $documents = [];
        foreach ($predbRecords as $predb) {
            if (empty($predb['id'])) {
                continue;
            }

            $documents[] = [
                'id' => $predb['id'],
                'title' => (string) ($predb['title'] ?? ''),
                'filename' => (string) ($predb['filename'] ?? ''),
                'source' => (string) ($predb['source'] ?? ''),
            ];
        }

        if (empty($documents)) {
            return ['success' => 0, 'errors' => 0];
        }

        try {
            $this->manticoreSearch->table($this->config['indexes']['predb'])
                ->replaceDocuments($documents);

            return ['success' => count($documents), 'errors' => 0];
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch bulkInsertPredb error: '.$e->getMessage());

            return ['success' => 0, 'errors' => count($documents)];
        }
    }

    /**
     * Delete release from Manticore RT tables by GUID.
     *
     * @param  array<string, mixed>  $identifiers  ['g' => Release GUID(mandatory), 'id' => ReleaseID(optional, pass false)]
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
     *
     * This method escapes ALL special characters, including search operators.
     * Use prepareUserSearchQuery() instead for user-facing search queries
     * where operators like negation (!) should be preserved.
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

    /**
     * Prepares a user search query for ManticoreSearch, preserving search operators.
     *
     * Unlike escapeString() which escapes ALL special characters, this method
     * recognizes and preserves user-facing search operators:
     *
     * - Negation: !word or -word (excludes results containing "word")
     * - Phrase search: "exact phrase"
     * - Negated phrase: !"exact phrase" or -"exact phrase"
     * - OR operator: word1 | word2
     * - Wildcards: word*, *word, *word*
     * - Grouping: (word1 | word2) -word3
     *
     * Characters that are not useful as user operators are still escaped:
     * \, @, ~, &, /, $, =, ', [, ]
     */
    public static function prepareUserSearchQuery(string $query): string
    {
        $query = trim($query);
        if ($query === '' || $query === '*') {
            return '';
        }

        // Tokenize while preserving quoted phrases intact
        // Matches: optional negation prefix (! or -) + "quoted strings", OR non-whitespace sequences
        preg_match_all('/[-!]?"[^"]*"|\S+/', $query, $matches);
        $tokens = $matches[0];

        if (empty($tokens)) {
            return '';
        }

        // Characters that should always be escaped (not meaningful as user-facing search operators)
        // Includes " for unmatched quotes that appear in non-quoted tokens
        $escapeFrom = ['\\', '@', '~', '&', '/', '$', '=', "'", '[', ']', '"'];
        $escapeTo = ['\\\\', '\@', '\~', '\&', '\/', '\$', '\=', "\'", '\[', '\]', '\"'];

        $processed = [];
        foreach ($tokens as $token) {
            // Preserve OR operator
            if ($token === '|') {
                $processed[] = '|';

                continue;
            }

            // Extract leading/trailing parentheses for grouping: (word) or ((word))
            $leadingParens = '';
            $trailingParens = '';
            while (str_starts_with($token, '(')) {
                $leadingParens .= '(';
                $token = substr($token, 1);
            }
            while (str_ends_with($token, ')') && ! str_starts_with($token, '"')) {
                $trailingParens = ')'.$trailingParens;
                $token = substr($token, 0, -1);
            }

            if ($token === '') {
                // Only parens, no word content
                if ($leadingParens !== '' || $trailingParens !== '') {
                    $processed[] = $leadingParens.$trailingParens;
                }

                continue;
            }

            // Detect negation prefix (! or -) at the start of a word
            $negation = '';
            if (strlen($token) > 1 && ($token[0] === '!' || $token[0] === '-')) {
                $negation = $token[0];
                $token = substr($token, 1);
            }

            // Handle quoted phrases: "exact phrase" (possibly with negation prefix)
            if (str_starts_with($token, '"') && str_ends_with($token, '"') && strlen($token) > 1) {
                $inner = substr($token, 1, -1);
                $inner = str_replace($escapeFrom, $escapeTo, $inner);
                // Escape ! and - inside phrases (they're literal text, not operators)
                $inner = str_replace(['!', '-'], ['\!', '\-'], $inner);
                $processed[] = $leadingParens.$negation.'"'.$inner.'"'.$trailingParens;

                continue;
            }

            // Detect wildcard prefix/suffix on non-quoted tokens
            $wildcardPrefix = '';
            $wildcardSuffix = '';
            if (str_starts_with($token, '*')) {
                $wildcardPrefix = '*';
                $token = ltrim($token, '*');
            }
            if (str_ends_with($token, '*')) {
                $wildcardSuffix = '*';
                $token = rtrim($token, '*');
            }

            // Escape non-operator special characters within the word
            $token = str_replace($escapeFrom, $escapeTo, $token);

            // Escape ! and - that appear INSIDE a word (not at the start as operators)
            // e.g., "spider-man" → "spider\-man", but "-circus" keeps the leading -
            $token = str_replace(['!', '-'], ['\!', '\-'], $token);

            if ($token !== '' || $wildcardPrefix !== '' || $wildcardSuffix !== '') {
                $processed[] = $leadingParens.$negation.$wildcardPrefix.$token.$wildcardSuffix.$trailingParens;
            }
        }

        $result = implode(' ', $processed);

        return trim($result);
    }

    /**
     * Check if a search query contains negation operators (! or - prefix on words).
     *
     * Used to prevent fuzzy fallback from reversing the user's negation intent.
     * For example, if the user searches "!harry", fuzzy should not strip the !
     * and return "harry" results.
     *
     * @param  array<string, mixed>  $phrases
     */
    public static function queryHasNegation(array|string $phrases): bool
    {
        $values = [];
        if (is_string($phrases)) {
            $values[] = $phrases;
        } elseif (is_array($phrases)) {
            foreach ($phrases as $value) {
                if (is_string($value) && $value !== '' && $value !== '-1') {
                    $values[] = $value;
                }
            }
        }

        foreach ($values as $value) {
            // Check if any token starts with ! or - (negation operator)
            if (preg_match('/(?:^|\s)[!-]\S/', $value)) {
                return true;
            }
        }

        return false;
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
     *
     * @param  array<string, mixed>  $parameters
     */
    public function updatePreDb(array $parameters): void
    {
        if (empty($parameters)) {
            Log::warning('ManticoreSearch: Cannot update predb with empty parameters');

            return;
        }

        $this->insertPredb($parameters);
    }

    /**
     * @param  array<string, mixed>  $indexes
     */
    public function truncateRTIndex(array $indexes = []): bool
    {
        if (empty($indexes)) {
            cli()->error('You need to provide index name to truncate');

            return false;
        }

        $success = true;
        foreach ($indexes as $index) {
            if (! \in_array($index, $this->config['indexes'], true)) {
                cli()->error('Unsupported index: '.$index);
                $success = false;

                continue;
            }

            try {
                $this->manticoreSearch->table($index)->truncate();
                cli()->info('Truncating index '.$index.' finished.');
            } catch (ResponseException $e) {
                // Handle case where index doesn't exist - create it
                $message = $e->getMessage();
                if (str_contains($message, 'does not exist') || $message === 'Invalid index') {
                    cli()->info('Index '.$index.' does not exist, creating it...');
                    $this->createIndexIfNotExists($index);
                } else {
                    cli()->error('Error truncating index '.$index.': '.$message);
                    $success = false;
                }
            } catch (\Throwable $e) {
                // Also handle generic exceptions for non-existent tables
                $message = $e->getMessage();
                if (str_contains($message, 'does not exist')) {
                    cli()->info('Index '.$index.' does not exist, creating it...');
                    $this->createIndexIfNotExists($index);
                } else {
                    cli()->error('Unexpected error truncating index '.$index.': '.$message);
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Truncate/clear an index (remove all documents).
     * Implements SearchServiceInterface::truncateIndex
     *
     * @param  array<string, mixed>|string  $indexes  Index name(s) to truncate
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
            // Use the tables() API which properly handles settings
            $indices = $this->manticoreSearch->tables();

            if ($index === 'releases_rt') {
                $indices->create([
                    'index' => $index,
                    'body' => [
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
                ]);
                cli()->info('Created releases_rt index with external ID fields and infix search support');
            } elseif ($index === 'predb_rt') {
                $indices->create([
                    'index' => $index,
                    'body' => [
                        'settings' => [
                            'min_prefix_len' => 0,
                            'min_infix_len' => 2,
                        ],
                        'columns' => [
                            'title' => ['type' => 'text'],
                            'filename' => ['type' => 'text'],
                            'source' => ['type' => 'text'],
                        ],
                    ],
                ]);
                cli()->info('Created predb_rt index with infix search support');
            } elseif ($index === 'movies_rt') {
                $indices->create([
                    'index' => $index,
                    'body' => [
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
                ]);
                cli()->info('Created movies_rt index with infix search support');
            } elseif ($index === 'tvshows_rt') {
                $indices->create([
                    'index' => $index,
                    'body' => [
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
                ]);
                cli()->info('Created tvshows_rt index with infix search support');
            }
        } catch (\Throwable $e) {
            cli()->error('Error creating index '.$index.': '.$e->getMessage());
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
     * @param  array<string, mixed>|string  $phrases  Search phrases - can be a string, indexed array of terms, or associative array with field names
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array of release IDs
     */
    public function searchReleases(array|string $phrases, int $limit = 1000): array
    {
        if (is_string($phrases)) {
            // Simple string search - search in searchname field
            $searchArray = ['searchname' => $phrases];
        } else {
            // Check if it's an associative array (has string keys like 'searchname')
            $isAssociative = count(array_filter(array_keys($phrases), 'is_string')) > 0;

            if ($isAssociative) {
                // Already has field names as keys
                $searchArray = $phrases;
            } else {
                // Indexed array - combine values and search in searchname
                $searchArray = ['searchname' => implode(' ', $phrases)];
            }
        }

        $result = $this->searchIndexes($this->getReleasesIndex(), '', [], $searchArray);

        return ! empty($result) ? ($result['id'] ?? []) : [];
    }

    /**
     * Search releases with fuzzy fallback.
     *
     * If exact search returns no results and fuzzy is enabled, this method
     * will automatically try a fuzzy search as a fallback.
     *
     * @param  array<string, mixed>|string  $phrases  Search phrases
     * @param  int  $limit  Maximum number of results
     * @param  bool  $forceFuzzy  Force fuzzy search regardless of exact results
     * @return array<string, mixed> Array with 'ids' (release IDs) and 'fuzzy' (bool indicating if fuzzy was used)
     */
    public function searchReleasesWithFuzzy(array|string $phrases, int $limit = 1000, bool $forceFuzzy = false): array
    {
        // First try exact search unless forcing fuzzy
        if (! $forceFuzzy) {
            $exactResults = $this->searchReleases($phrases, $limit);
            if (! empty($exactResults)) {
                return [
                    'ids' => $exactResults,
                    'fuzzy' => false,
                ];
            }
        }

        // Skip fuzzy fallback when the query contains negation operators (! or -).
        // Fuzzy search strips all special characters, which would reverse the user's
        // intent: "!harry" would become "harry" and return exactly what they wanted to exclude.
        if (self::queryHasNegation($phrases)) {
            if (config('app.debug')) {
                Log::debug('ManticoreSearch::searchReleasesWithFuzzy skipping fuzzy - query contains negation operators', [
                    'phrases' => $phrases,
                ]);
            }

            return [
                'ids' => [],
                'fuzzy' => false,
            ];
        }

        // If exact search returned nothing (or forcing fuzzy) and fuzzy is enabled, try fuzzy search
        if ($this->isFuzzyEnabled()) {
            $fuzzyResults = $this->fuzzySearchReleases($phrases, $limit);
            if (! empty($fuzzyResults)) {
                return [
                    'ids' => $fuzzyResults,
                    'fuzzy' => true,
                ];
            }
        }

        return [
            'ids' => [],
            'fuzzy' => false,
        ];
    }

    /**
     * Perform fuzzy search on releases index.
     *
     * Uses Manticore's native fuzzy search with Levenshtein distance algorithm.
     *
     * @param  array<string, mixed>|string  $phrases  Search phrases
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array of release IDs
     */
    public function fuzzySearchReleases(array|string $phrases, int $limit = 1000): array
    {
        if (! $this->isFuzzyEnabled()) {
            return [];
        }

        if (is_string($phrases)) {
            $searchArray = ['searchname' => $phrases];
        } else {
            $isAssociative = count(array_filter(array_keys($phrases), 'is_string')) > 0;
            if ($isAssociative) {
                $searchArray = $phrases;
            } else {
                $searchArray = ['searchname' => implode(' ', $phrases)];
            }
        }

        $result = $this->fuzzySearchIndexes($this->getReleasesIndex(), $searchArray, $limit);

        return ! empty($result) ? ($result['id'] ?? []) : [];
    }

    /**
     * Perform fuzzy search on an index using Manticore's native fuzzy search.
     *
     * Uses Levenshtein distance algorithm to find matches with typo tolerance.
     * Supports:
     * - Missing characters: "laptp" → "laptop"
     * - Extra characters: "laptopp" → "laptop"
     * - Transposed characters: "lpatop" → "laptop"
     * - Wrong characters: "laptip" → "laptop"
     *
     * @param  string  $index  Index to search
     * @param  array<string, mixed>  $searchArray  Associative array of field => value to search
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array with 'id' and 'data' keys
     */
    public function fuzzySearchIndexes(string $index, array $searchArray, int $limit = 1000): array
    {
        if (empty($index) || empty($searchArray)) {
            return [];
        }

        $fuzzyConfig = $this->getFuzzyConfig();
        $distance = $fuzzyConfig['max_distance'] ?? 2;

        // Create cache key for fuzzy search results
        $cacheKey = 'manticore:fuzzy:'.md5(serialize([
            'index' => $index,
            'array' => $searchArray,
            'limit' => $limit,
            'distance' => $distance,
        ]));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            if (config('app.debug')) {
                Log::debug('ManticoreSearch::fuzzySearchIndexes returning cached result', [
                    'cacheKey' => $cacheKey,
                ]);
            }

            return $cached;
        }

        // For fuzzy search, we need to use a simple query string without the @@relaxed @field syntax
        // The fuzzy option only works with plain query_string queries
        $searchTerms = [];
        foreach ($searchArray as $field => $value) {
            if (! empty($value)) {
                // Clean value for fuzzy search - remove special characters but keep words
                $cleanValue = preg_replace('/[^\w\s]/', ' ', $value);
                $cleanValue = preg_replace('/\s+/', ' ', trim($cleanValue));
                if (! empty($cleanValue)) {
                    $searchTerms[] = $cleanValue;
                }
            }
        }

        if (empty($searchTerms)) {
            return [];
        }

        $searchExpr = implode(' ', $searchTerms);

        if (config('app.debug')) {
            Log::debug('ManticoreSearch::fuzzySearchIndexes query', [
                'index' => $index,
                'searchExpr' => $searchExpr,
                'fuzzy' => true,
                'distance' => $distance,
            ]);
        }

        try {
            // Use Manticore's native fuzzy search with Levenshtein distance
            // Important: use search() with plain query string for fuzzy to work
            // Note: Keep options minimal - layouts, stripBadUtf8, and sort can interfere with fuzzy
            $query = (new Search($this->manticoreSearch))
                ->setTable($index)
                ->search($searchExpr)
                ->option('fuzzy', true)
                ->option('distance', $distance)
                ->limit(min($limit, 10000));

            $results = $query->get();
        } catch (ResponseException $e) {
            $message = $e->getMessage();

            // Check if fuzzy search failed due to missing min_infix_len
            // This happens when index was created without proper settings
            if (str_contains($message, 'min_infix_len')) {
                Log::warning('ManticoreSearch fuzzySearchIndexes: Fuzzy search unavailable - index missing min_infix_len setting. Please recreate the index with: php artisan manticore:create-indexes --drop', [
                    'index' => $index,
                ]);

                // Fall back to regular search without fuzzy
                return $this->searchIndexes($index, '', [], $searchArray);
            }

            Log::error('ManticoreSearch fuzzySearchIndexes ResponseException: '.$message, [
                'index' => $index,
                'searchArray' => $searchArray,
            ]);

            return [];
        } catch (RuntimeException $e) {
            Log::error('ManticoreSearch fuzzySearchIndexes RuntimeException: '.$e->getMessage(), [
                'index' => $index,
            ]);

            return [];
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch fuzzySearchIndexes unexpected error: '.$e->getMessage(), [
                'index' => $index,
            ]);

            return [];
        }

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

        if (config('app.debug')) {
            Log::debug('ManticoreSearch::fuzzySearchIndexes results', [
                'index' => $index,
                'total_results' => count($resultIds),
            ]);
        }

        // Cache fuzzy results for 5 minutes
        if (! empty($resultIds)) {
            Cache::put($cacheKey, $result, now()->addMinutes($this->config['cache_minutes'] ?? 5));
        }

        return $result;
    }

    /**
     * Search predb index.
     *
     * @param  array<string, mixed>|string  $searchTerm  Search term(s)
     * @return array<string, mixed> Array of predb records
     */
    public function searchPredb(array|string $searchTerm): array
    {
        $searchString = is_array($searchTerm) ? implode(' ', $searchTerm) : $searchTerm;

        $result = $this->searchIndexes($this->getPredbIndex(), $searchString, ['title', 'filename'], []); // @phpstan-ignore argument.type

        return $result['data'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $column
     * @param  array<string, mixed>  $searchArray
     * @return array<string, mixed>
     */
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
        // Use prepareUserSearchQuery() to preserve search operators (!, -, "", |, *)
        $searchExpr = null;
        if (! empty($searchArray)) {
            $terms = [];
            foreach ($searchArray as $key => $value) {
                if (! empty($value)) {
                    $preparedValue = self::prepareUserSearchQuery($value);
                    if (! empty($preparedValue)) {
                        $terms[] = '@@relaxed @'.$key.' '.$preparedValue;
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
            $preparedSearch = self::prepareUserSearchQuery($searchString);
            if (empty($preparedSearch)) {
                if (config('app.debug')) {
                    Log::debug('ManticoreSearch::searchIndexes preparedSearch is empty');
                }

                return [];
            }

            $searchColumns = '';
            if (! empty($column)) {
                if (count($column) > 1) {
                    $searchColumns = '@('.implode(',', $column).')';
                } else {
                    $searchColumns = '@'.$column[0]; // @phpstan-ignore offsetAccess.notFound
                }
            }

            $searchExpr = '@@relaxed '.$searchColumns.' '.$preparedSearch;
        } else {
            return [];
        }

        // Avoid explicit sort for predb_rt to prevent Manticore's "too many sort-by attributes" error
        $avoidSortForIndex = ($rt_index === 'predb_rt');

        if (config('app.debug')) {
            Log::debug('ManticoreSearch::searchIndexes executing query', [
                'rt_index' => $rt_index,
                'searchExpr' => $searchExpr,
            ]);
        }

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

        // Only cache non-empty results to avoid caching temporary failures or empty index states
        if (! empty($resultIds)) {
            Cache::put($cacheKey, $result, now()->addMinutes($this->config['cache_minutes'] ?? 5));
        }

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

        // Don't suggest spelling corrections for queries with negation operators.
        // Suggesting "harry" for "!harry" would be misleading since the user
        // intentionally wants to exclude that term.
        if (self::queryHasNegation($query)) {
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

    /**
     * Insert a movie into the movies search index.
     *
     * @param  array<string, mixed>  $parameters  Movie data
     */
    public function insertMovie(array $parameters): void
    {
        if (empty($parameters['id'])) {
            Log::warning('ManticoreSearch: Cannot insert movie without ID');

            return;
        }

        try {
            $document = [
                'imdbid' => (int) ($parameters['imdbid'] ?? 0),
                'tmdbid' => (int) ($parameters['tmdbid'] ?? 0),
                'traktid' => (int) ($parameters['traktid'] ?? 0),
                'title' => (string) ($parameters['title'] ?? ''),
                'year' => (string) ($parameters['year'] ?? ''),
                'genre' => (string) ($parameters['genre'] ?? ''),
                'actors' => (string) ($parameters['actors'] ?? ''),
                'director' => (string) ($parameters['director'] ?? ''),
                'rating' => (string) ($parameters['rating'] ?? ''),
                'plot' => (string) ($parameters['plot'] ?? ''),
            ];

            $this->manticoreSearch->table($this->getMoviesIndex())
                ->replaceDocument($document, $parameters['id']);

        } catch (ResponseException $e) {
            Log::error('ManticoreSearch insertMovie ResponseException: '.$e->getMessage(), [
                'movie_id' => $parameters['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch insertMovie unexpected error: '.$e->getMessage(), [
                'movie_id' => $parameters['id'],
            ]);
        }
    }

    /**
     * Update a movie in the search index.
     *
     * @param  int  $movieId  Movie ID
     */
    public function updateMovie(int $movieId): void
    {
        if (empty($movieId)) {
            Log::warning('ManticoreSearch: Cannot update movie without ID');

            return;
        }

        try {
            $movie = MovieInfo::find($movieId);

            if ($movie !== null) {
                $this->insertMovie($movie->toArray());
            } else {
                Log::warning('ManticoreSearch: Movie not found for update', ['id' => $movieId]);
            }
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch updateMovie error: '.$e->getMessage(), [
                'movie_id' => $movieId,
            ]);
        }
    }

    /**
     * Delete a movie from the search index.
     *
     * @param  int  $id  Movie ID
     */
    public function deleteMovie(int $id): void
    {
        if (empty($id)) {
            Log::warning('ManticoreSearch: Cannot delete movie without ID');

            return;
        }

        try {
            $this->manticoreSearch->table($this->getMoviesIndex())
                ->deleteDocument($id);
        } catch (ResponseException $e) {
            Log::error('ManticoreSearch deleteMovie error: '.$e->getMessage(), [
                'id' => $id,
            ]);
        }
    }

    /**
     * Bulk insert multiple movies into the index.
     *
     * @param  array<string, mixed>  $movies  Array of movie data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertMovies(array $movies): array
    {
        if (empty($movies)) {
            return ['success' => 0, 'errors' => 0];
        }

        $success = 0;
        $errors = 0;

        $documents = [];
        foreach ($movies as $movie) {
            if (empty($movie['id'])) {
                $errors++;

                continue;
            }

            $documents[] = [
                'id' => $movie['id'],
                'imdbid' => (int) ($movie['imdbid'] ?? 0),
                'tmdbid' => (int) ($movie['tmdbid'] ?? 0),
                'traktid' => (int) ($movie['traktid'] ?? 0),
                'title' => (string) ($movie['title'] ?? ''),
                'year' => (string) ($movie['year'] ?? ''),
                'genre' => (string) ($movie['genre'] ?? ''),
                'actors' => (string) ($movie['actors'] ?? ''),
                'director' => (string) ($movie['director'] ?? ''),
                'rating' => (string) ($movie['rating'] ?? ''),
                'plot' => (string) ($movie['plot'] ?? ''),
            ];
        }

        if (! empty($documents)) {
            try {
                $this->manticoreSearch->table($this->getMoviesIndex())
                    ->replaceDocuments($documents);
                $success = count($documents);
            } catch (\Throwable $e) {
                Log::error('ManticoreSearch bulkInsertMovies error: '.$e->getMessage());
                $errors += count($documents);
            }
        }

        return ['success' => $success, 'errors' => $errors];
    }

    /**
     * Search the movies index.
     *
     * @param  array<string, mixed>|string  $searchTerm  Search term(s)
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array with 'id' (movie IDs) and 'data' (movie data)
     */
    public function searchMovies(array|string $searchTerm, int $limit = 1000): array
    {
        $searchString = is_array($searchTerm) ? implode(' ', $searchTerm) : $searchTerm;

        return $this->searchIndexes($this->getMoviesIndex(), $searchString, ['title', 'actors', 'director'], []); // @phpstan-ignore argument.type
    }

    /**
     * Search movies by external ID (IMDB, TMDB, Trakt).
     *
     * @param  string  $field  Field name (imdbid, tmdbid, traktid)
     * @param  int|string  $value  The external ID value
     * @return array<string, mixed>|null Movie data or null if not found
     */
    public function searchMovieByExternalId(string $field, int|string $value): ?array
    {
        if (empty($value) || ! in_array($field, ['imdbid', 'tmdbid', 'traktid'])) {
            return null;
        }

        $cacheKey = 'manticore:movie:'.$field.':'.$value;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $query = (new Search($this->manticoreSearch))
                ->setTable($this->getMoviesIndex())
                ->filter($field, '=', (int) $value)
                ->limit(1);

            $results = $query->get();

            foreach ($results as $doc) {
                $data = $doc->getData();
                $data['id'] = $doc->getId();
                Cache::put($cacheKey, $data, now()->addMinutes($this->config['cache_minutes'] ?? 5));

                return $data;
            }
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch searchMovieByExternalId error: '.$e->getMessage(), [
                'field' => $field,
                'value' => $value,
            ]);
        }

        return null;
    }

    /**
     * Insert a TV show into the tvshows search index.
     *
     * @param  array<string, mixed>  $parameters  TV show data
     */
    public function insertTvShow(array $parameters): void
    {
        if (empty($parameters['id'])) {
            Log::warning('ManticoreSearch: Cannot insert TV show without ID');

            return;
        }

        try {
            $document = [
                'title' => (string) ($parameters['title'] ?? ''),
                'tvdb' => (int) ($parameters['tvdb'] ?? 0),
                'trakt' => (int) ($parameters['trakt'] ?? 0),
                'tvmaze' => (int) ($parameters['tvmaze'] ?? 0),
                'tvrage' => (int) ($parameters['tvrage'] ?? 0),
                'imdb' => (int) ($parameters['imdb'] ?? 0),
                'tmdb' => (int) ($parameters['tmdb'] ?? 0),
                'started' => (string) ($parameters['started'] ?? ''),
                'type' => (int) ($parameters['type'] ?? 0),
            ];

            $this->manticoreSearch->table($this->getTvShowsIndex())
                ->replaceDocument($document, $parameters['id']);

        } catch (ResponseException $e) {
            Log::error('ManticoreSearch insertTvShow ResponseException: '.$e->getMessage(), [
                'tvshow_id' => $parameters['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch insertTvShow unexpected error: '.$e->getMessage(), [
                'tvshow_id' => $parameters['id'],
            ]);
        }
    }

    /**
     * Update a TV show in the search index.
     *
     * @param  int  $videoId  Video/TV show ID
     */
    public function updateTvShow(int $videoId): void
    {
        if (empty($videoId)) {
            Log::warning('ManticoreSearch: Cannot update TV show without ID');

            return;
        }

        try {
            $video = Video::find($videoId);

            if ($video !== null) {
                $this->insertTvShow($video->toArray());
            } else {
                Log::warning('ManticoreSearch: TV show not found for update', ['id' => $videoId]);
            }
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch updateTvShow error: '.$e->getMessage(), [
                'tvshow_id' => $videoId,
            ]);
        }
    }

    /**
     * Delete a TV show from the search index.
     *
     * @param  int  $id  TV show ID
     */
    public function deleteTvShow(int $id): void
    {
        if (empty($id)) {
            Log::warning('ManticoreSearch: Cannot delete TV show without ID');

            return;
        }

        try {
            $this->manticoreSearch->table($this->getTvShowsIndex())
                ->deleteDocument($id);
        } catch (ResponseException $e) {
            Log::error('ManticoreSearch deleteTvShow error: '.$e->getMessage(), [
                'id' => $id,
            ]);
        }
    }

    /**
     * Bulk insert multiple TV shows into the index.
     *
     * @param  array<string, mixed>  $tvShows  Array of TV show data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertTvShows(array $tvShows): array
    {
        if (empty($tvShows)) {
            return ['success' => 0, 'errors' => 0];
        }

        $success = 0;
        $errors = 0;

        $documents = [];
        foreach ($tvShows as $tvShow) {
            if (empty($tvShow['id'])) {
                $errors++;

                continue;
            }

            $documents[] = [
                'id' => $tvShow['id'],
                'title' => (string) ($tvShow['title'] ?? ''),
                'tvdb' => (int) ($tvShow['tvdb'] ?? 0),
                'trakt' => (int) ($tvShow['trakt'] ?? 0),
                'tvmaze' => (int) ($tvShow['tvmaze'] ?? 0),
                'tvrage' => (int) ($tvShow['tvrage'] ?? 0),
                'imdb' => (int) ($tvShow['imdb'] ?? 0),
                'tmdb' => (int) ($tvShow['tmdb'] ?? 0),
                'started' => (string) ($tvShow['started'] ?? ''),
                'type' => (int) ($tvShow['type'] ?? 0),
            ];
        }

        if (! empty($documents)) {
            try {
                $this->manticoreSearch->table($this->getTvShowsIndex())
                    ->replaceDocuments($documents);
                $success = count($documents);
            } catch (\Throwable $e) {
                Log::error('ManticoreSearch bulkInsertTvShows error: '.$e->getMessage());
                $errors += count($documents);
            }
        }

        return ['success' => $success, 'errors' => $errors];
    }

    /**
     * Search the TV shows index.
     *
     * @param  array<string, mixed>|string  $searchTerm  Search term(s)
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array with 'id' (TV show IDs) and 'data' (TV show data)
     */
    public function searchTvShows(array|string $searchTerm, int $limit = 1000): array
    {
        $searchString = is_array($searchTerm) ? implode(' ', $searchTerm) : $searchTerm;

        return $this->searchIndexes($this->getTvShowsIndex(), $searchString, ['title'], []); // @phpstan-ignore argument.type
    }

    /**
     * Search TV shows by external ID (TVDB, Trakt, TVMaze, TVRage, IMDB, TMDB).
     *
     * @param  string  $field  Field name (tvdb, trakt, tvmaze, tvrage, imdb, tmdb)
     * @param  int|string  $value  The external ID value
     * @return array<string, mixed>|null TV show data or null if not found
     */
    public function searchTvShowByExternalId(string $field, int|string $value): ?array
    {
        if (empty($value) || ! in_array($field, ['tvdb', 'trakt', 'tvmaze', 'tvrage', 'imdb', 'tmdb'])) {
            return null;
        }

        $cacheKey = 'manticore:tvshow:'.$field.':'.$value;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $query = (new Search($this->manticoreSearch))
                ->setTable($this->getTvShowsIndex())
                ->filter($field, '=', (int) $value)
                ->limit(1);

            $results = $query->get();

            foreach ($results as $doc) {
                $data = $doc->getData();
                $data['id'] = $doc->getId();
                Cache::put($cacheKey, $data, now()->addMinutes($this->config['cache_minutes'] ?? 5));

                return $data;
            }
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch searchTvShowByExternalId error: '.$e->getMessage(), [
                'field' => $field,
                'value' => $value,
            ]);
        }

        return null;
    }

    /**
     * Search releases by external media IDs.
     * Used to find releases associated with a specific movie or TV show.
     *
     * @param  array<string, mixed>  $externalIds  Associative array of external IDs
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array of release IDs
     */
    public function searchReleasesByExternalId(array $externalIds, int $limit = 1000): array
    {
        if (empty($externalIds)) {
            return [];
        }

        $cacheKey = 'manticore:releases:extid:'.md5(serialize($externalIds));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $query = (new Search($this->manticoreSearch))
                ->setTable($this->getReleasesIndex())
                ->limit(min($limit, 10000));

            // Add filters for each external ID provided
            foreach ($externalIds as $field => $value) {
                if (! empty($value) && in_array($field, ['imdbid', 'tmdbid', 'traktid', 'tvdb', 'tvmaze', 'tvrage'])) {
                    $query->filter($field, '=', (int) $value);
                }
            }

            $results = $query->get();

            $resultIds = [];
            foreach ($results as $doc) {
                $resultIds[] = $doc->getId();
            }

            if (! empty($resultIds)) {
                Cache::put($cacheKey, $resultIds, now()->addMinutes($this->config['cache_minutes'] ?? 5));
            }

            return $resultIds;
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch searchReleasesByExternalId error: '.$e->getMessage(), [
                'externalIds' => $externalIds,
            ]);
        }

        return [];
    }

    /**
     * Search releases by category ID using the search index.
     * This provides a fast way to get release IDs for a specific category without hitting the database.
     *
     * @param  array<string, mixed>  $categoryIds  Array of category IDs to filter by
     * @param  int  $limit  Maximum number of results
     * @return list
     * @return array<string, mixed>
     */
    public function searchReleasesByCategory(array $categoryIds, int $limit = 1000): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        // Filter out invalid category IDs (-1 means "all categories")
        $validCategoryIds = array_filter($categoryIds, fn ($id) => $id > 0);
        if (empty($validCategoryIds)) {
            return [];
        }

        $cacheKey = 'manticore:releases:cat:'.md5(serialize($validCategoryIds).':'.$limit);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $query = (new Search($this->manticoreSearch))
                ->setTable($this->getReleasesIndex())
                ->limit(min($limit, 10000));

            // Use IN filter for multiple category IDs
            if (count($validCategoryIds) === 1) {
                $query->filter('categories_id', '=', (int) $validCategoryIds[0]); // @phpstan-ignore offsetAccess.notFound
            } else {
                $query->filter('categories_id', 'in', array_map('intval', $validCategoryIds));
            }

            $results = $query->get();

            $resultIds = [];
            foreach ($results as $doc) {
                $resultIds[] = $doc->getId();
            }

            if (! empty($resultIds)) {
                Cache::put($cacheKey, $resultIds, now()->addMinutes($this->config['cache_minutes'] ?? 5));
            }

            return $resultIds; // @phpstan-ignore return.type
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch searchReleasesByCategory error: '.$e->getMessage(), [
                'categoryIds' => $categoryIds,
            ]);
        }

        return [];
    }

    /**
     * Combined search: text search with category filtering.
     * First searches by text, then filters by category IDs using the search index.
     *
     * @param  string  $searchTerm  Search text
     * @param  array<string, mixed>  $categoryIds  Array of category IDs to filter by (empty for all categories)
     * @param  int  $limit  Maximum number of results
     * @return list
     * @return array<string, mixed>
     */
    public function searchReleasesWithCategoryFilter(string $searchTerm, array $categoryIds = [], int $limit = 1000): array
    {
        if (empty($searchTerm)) {
            // If no search term, just filter by category
            return $this->searchReleasesByCategory($categoryIds, $limit);
        }

        // Filter out invalid category IDs
        $validCategoryIds = array_filter($categoryIds, fn ($id) => $id > 0);

        $cacheKey = 'manticore:releases:search_cat:'.md5($searchTerm.':'.serialize($validCategoryIds).':'.$limit);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $preparedSearch = self::prepareUserSearchQuery($searchTerm);
            if (empty($preparedSearch)) {
                return $this->searchReleasesByCategory($categoryIds, $limit);
            }

            $searchExpr = '@@relaxed @searchname '.$preparedSearch;

            $query = (new Search($this->manticoreSearch))
                ->setTable($this->getReleasesIndex())
                ->search($searchExpr)
                ->option('ranker', 'sph04')
                ->stripBadUtf8(true)
                ->limit(min($limit, 10000));

            // Add category filter if provided
            if (! empty($validCategoryIds)) {
                if (count($validCategoryIds) === 1) {
                    $query->filter('categories_id', '=', (int) $validCategoryIds[0]); // @phpstan-ignore offsetAccess.notFound
                } else {
                    $query->filter('categories_id', 'in', array_map('intval', $validCategoryIds));
                }
            }

            $results = $query->get();

            $resultIds = [];
            foreach ($results as $doc) {
                $resultIds[] = $doc->getId();
            }

            if (! empty($resultIds)) {
                Cache::put($cacheKey, $resultIds, now()->addMinutes($this->config['cache_minutes'] ?? 5));
            }

            return $resultIds; // @phpstan-ignore return.type
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch searchReleasesWithCategoryFilter error: '.$e->getMessage(), [
                'searchTerm' => $searchTerm,
                'categoryIds' => $categoryIds,
            ]);
        }

        return [];
    }
}
