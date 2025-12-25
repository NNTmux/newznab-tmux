<?php

namespace App\Services\Search\Drivers;

use App\Models\Release;
use App\Services\Search\Contracts\SearchDriverInterface;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use GuzzleHttp\Ring\Client\CurlHandler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Elasticsearch driver for full-text search functionality.
 *
 * Provides search functionality for the releases and predb indices with
 * caching, connection pooling, and automatic reconnection.
 */
class ElasticSearchDriver implements SearchDriverInterface
{
    private const CACHE_TTL_MINUTES = 5;

    private const SCROLL_TIMEOUT = '30s';

    private const MAX_RESULTS = 10000;

    private const AVAILABILITY_CHECK_CACHE_TTL = 30; // seconds

    private const DEFAULT_TIMEOUT = 10;

    private const DEFAULT_CONNECT_TIMEOUT = 5;

    private const AUTOCOMPLETE_CACHE_MINUTES = 10;

    private const AUTOCOMPLETE_MAX_RESULTS = 10;

    private const AUTOCOMPLETE_MIN_LENGTH = 2;

    private static ?Client $client = null;

    private static ?bool $availabilityCache = null;

    private static ?int $availabilityCacheTime = null;

    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = ! empty($config) ? $config : config('search.drivers.elasticsearch');
    }

    /**
     * Get the driver name.
     */
    public function getDriverName(): string
    {
        return 'elasticsearch';
    }

    /**
     * Check if Elasticsearch is available.
     */
    public function isAvailable(): bool
    {
        return $this->isElasticsearchAvailable();
    }

    /**
     * Get or create an Elasticsearch client with proper cURL configuration.
     *
     * Uses a singleton pattern to reuse the client connection across requests.
     *
     * @throws RuntimeException When client initialization fails
     */
    private function getClient(): Client
    {
        if (self::$client === null) {
            try {
                if (! extension_loaded('curl')) {
                    throw new RuntimeException('cURL extension is not loaded');
                }

                if (empty($this->config)) {
                    throw new RuntimeException('Elasticsearch configuration not found');
                }

                $clientBuilder = ClientBuilder::create();
                $hosts = $this->buildHostsArray($this->config['hosts'] ?? []);

                if (empty($hosts)) {
                    throw new RuntimeException('No Elasticsearch hosts configured');
                }

                if (config('app.debug')) {
                    Log::debug('Elasticsearch client initializing', [
                        'hosts' => $hosts,
                    ]);
                }

                $clientBuilder->setHosts($hosts);
                $clientBuilder->setHandler(new CurlHandler);
                $clientBuilder->setConnectionParams([
                    'timeout' => $this->config['timeout'] ?? self::DEFAULT_TIMEOUT,
                    'connect_timeout' => $this->config['connect_timeout'] ?? self::DEFAULT_CONNECT_TIMEOUT,
                ]);

                // Enable retries for better resilience
                $clientBuilder->setRetries($this->config['retries'] ?? 2);

                self::$client = $clientBuilder->build();

                if (config('app.debug')) {
                    Log::debug('Elasticsearch client initialized successfully');
                }

            } catch (\Throwable $e) {
                Log::error('Failed to initialize Elasticsearch client: '.$e->getMessage(), [
                    'exception_class' => get_class($e),
                ]);
                throw new RuntimeException('Elasticsearch client initialization failed: '.$e->getMessage());
            }
        }

        return self::$client;
    }

    /**
     * Build hosts array from configuration.
     *
     * @param  array  $configHosts  Configuration hosts array
     * @return array Formatted hosts array for Elasticsearch client
     */
    private function buildHostsArray(array $configHosts): array
    {
        $hosts = [];

        foreach ($configHosts as $host) {
            $hostConfig = [
                'host' => $host['host'] ?? 'localhost',
                'port' => $host['port'] ?? 9200,
            ];

            if (! empty($host['scheme'])) {
                $hostConfig['scheme'] = $host['scheme'];
            }

            if (! empty($host['user']) && ! empty($host['pass'])) {
                $hostConfig['user'] = $host['user'];
                $hostConfig['pass'] = $host['pass'];
            }

            $hosts[] = $hostConfig;
        }

        return $hosts;
    }

    /**
     * Check if Elasticsearch is available.
     *
     * Caches the availability status to avoid frequent ping requests.
     */
    private function isElasticsearchAvailable(): bool
    {
        $now = time();

        // Return cached result if still valid
        if (self::$availabilityCache !== null
            && self::$availabilityCacheTime !== null
            && ($now - self::$availabilityCacheTime) < self::AVAILABILITY_CHECK_CACHE_TTL) {
            return self::$availabilityCache;
        }

        try {
            $client = $this->getClient();
            $result = $client->ping();

            if (config('app.debug')) {
                Log::debug('Elasticsearch ping result', ['available' => $result]);
            }

            self::$availabilityCache = $result;
            self::$availabilityCacheTime = $now;

            return $result;
        } catch (\Throwable $e) {
            Log::warning('Elasticsearch is not available: '.$e->getMessage(), [
                'exception_class' => get_class($e),
            ]);

            self::$availabilityCache = false;
            self::$availabilityCacheTime = $now;

            return false;
        }
    }

    /**
     * Reset the client connection (useful for testing or reconnection).
     */
    public function resetConnection(): void
    {
        self::$client = null;
        self::$availabilityCache = null;
        self::$availabilityCacheTime = null;
    }

    /**
     * Check if autocomplete is enabled.
     */
    public function isAutocompleteEnabled(): bool
    {
        return ($this->config['autocomplete']['enabled'] ?? true) && $this->isElasticsearchAvailable();
    }

    /**
     * Check if suggest is enabled.
     */
    public function isSuggestEnabled(): bool
    {
        return ($this->config['suggest']['enabled'] ?? true) && $this->isElasticsearchAvailable();
    }

    /**
     * Get the releases index name.
     */
    public function getReleasesIndex(): string
    {
        return $this->config['indexes']['releases'] ?? 'releases';
    }

    /**
     * Get the predb index name.
     */
    public function getPredbIndex(): string
    {
        return $this->config['indexes']['predb'] ?? 'predb';
    }

    /**
     * Escape special characters for Elasticsearch.
     */
    public static function escapeString(string $string): string
    {
        if (empty($string) || $string === '*') {
            return '';
        }

        // Replace dots with spaces for release name searches
        $string = str_replace('.', ' ', $string);

        // Remove multiple consecutive spaces
        $string = preg_replace('/\s+/', ' ', trim($string));

        return $string;
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
        if (! $this->isAutocompleteEnabled()) {
            return [];
        }

        $query = trim($query);
        $minLength = (int) ($this->config['autocomplete']['min_length'] ?? self::AUTOCOMPLETE_MIN_LENGTH);
        if (strlen($query) < $minLength) {
            return [];
        }

        $index = $index ?? $this->getReleasesIndex();
        $cacheKey = 'es:autocomplete:'.md5($index.$query);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $suggestions = [];
        $maxResults = (int) ($this->config['autocomplete']['max_results'] ?? self::AUTOCOMPLETE_MAX_RESULTS);

        try {
            $client = $this->getClient();

            // Use a prefix/match query on searchname field
            $searchParams = [
                'index' => $index,
                'body' => [
                    'query' => [
                        'bool' => [
                            'should' => [
                                // Prefix match for autocomplete-like behavior
                                [
                                    'match_phrase_prefix' => [
                                        'searchname' => [
                                            'query' => $query,
                                            'max_expansions' => 50,
                                        ],
                                    ],
                                ],
                                // Also include regular match for better results
                                [
                                    'match' => [
                                        'searchname' => [
                                            'query' => $query,
                                            'fuzziness' => 'AUTO',
                                        ],
                                    ],
                                ],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                    'size' => $maxResults * 3,
                    '_source' => ['searchname'],
                    'sort' => [
                        // Sort by date first to get latest results, then by score
                        ['add_date' => ['order' => 'desc', 'unmapped_type' => 'date', 'missing' => '_last']],
                        ['_score' => ['order' => 'desc']],
                    ],
                ],
            ];

            $response = $client->search($searchParams);

            $seen = [];
            if (isset($response['hits']['hits'])) {
                foreach ($response['hits']['hits'] as $hit) {
                    $searchname = $hit['_source']['searchname'] ?? '';

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
            }
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                Log::warning('ElasticSearch autocomplete error: '.$e->getMessage());
            }
        }

        if (! empty($suggestions)) {
            $cacheMinutes = (int) ($this->config['autocomplete']['cache_minutes'] ?? self::AUTOCOMPLETE_CACHE_MINUTES);
            Cache::put($cacheKey, $suggestions, now()->addMinutes($cacheMinutes));
        }

        return $suggestions;
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
        if (! $this->isSuggestEnabled()) {
            return [];
        }

        $query = trim($query);
        if (empty($query)) {
            return [];
        }

        $index = $index ?? $this->getReleasesIndex();
        $cacheKey = 'es:suggest:'.md5($index.$query);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $suggestions = [];

        try {
            $client = $this->getClient();

            // Use Elasticsearch suggest API with phrase suggester
            $searchParams = [
                'index' => $index,
                'body' => [
                    'suggest' => [
                        'text' => $query,
                        'searchname_suggest' => [
                            'phrase' => [
                                'field' => 'searchname',
                                'size' => 5,
                                'gram_size' => 3,
                                'direct_generator' => [
                                    [
                                        'field' => 'searchname',
                                        'suggest_mode' => 'popular',
                                    ],
                                ],
                                'highlight' => [
                                    'pre_tag' => '',
                                    'post_tag' => '',
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $response = $client->search($searchParams);

            if (isset($response['suggest']['searchname_suggest'][0]['options'])) {
                foreach ($response['suggest']['searchname_suggest'][0]['options'] as $option) {
                    $suggestedText = $option['text'] ?? '';
                    if (! empty($suggestedText) && strtolower($suggestedText) !== strtolower($query)) {
                        $suggestions[] = [
                            'suggest' => $suggestedText,
                            'distance' => 1,
                            'docs' => (int) ($option['freq'] ?? 1),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                Log::debug('ElasticSearch native suggest failed: '.$e->getMessage());
            }
        }

        // Fallback: if native suggest didn't work, use fuzzy search
        if (empty($suggestions)) {
            $suggestions = $this->suggestFallback($query, $index);
        }

        if (! empty($suggestions)) {
            Cache::put($cacheKey, $suggestions, now()->addMinutes($this->config['cache_minutes'] ?? self::CACHE_TTL_MINUTES));
        }

        return $suggestions;
    }

    /**
     * Fallback suggest using fuzzy search on searchnames.
     *
     * @param  string  $query  The search query
     * @param  string  $index  Index to search
     * @return array<array{suggest: string, distance: int, docs: int}>
     */
    private function suggestFallback(string $query, string $index): array
    {
        try {
            $client = $this->getClient();

            // Use fuzzy match to find similar terms
            $searchParams = [
                'index' => $index,
                'body' => [
                    'query' => [
                        'match' => [
                            'searchname' => [
                                'query' => $query,
                                'fuzziness' => 'AUTO',
                            ],
                        ],
                    ],
                    'size' => 20,
                    '_source' => ['searchname'],
                ],
            ];

            $response = $client->search($searchParams);

            // Extract common terms from results that differ from the query
            $termCounts = [];
            if (isset($response['hits']['hits'])) {
                foreach ($response['hits']['hits'] as $hit) {
                    $searchname = $hit['_source']['searchname'] ?? '';
                    $words = preg_split('/[\s.\-_]+/', strtolower($searchname));

                    foreach ($words as $word) {
                        if (strlen($word) >= 3 && $word !== strtolower($query)) {
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
            }

            // Sort by count
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
                Log::warning('ElasticSearch suggest fallback error: '.$e->getMessage());
            }

            return [];
        }
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
        // Clean up the searchname - remove file extensions
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
        $pos = stripos($clean, $query);
        if ($pos !== false) {
            $start = max(0, $pos - 10);
            $extracted = substr($clean, $start, 80);

            if ($start > 0) {
                $extracted = preg_replace('/^\S*\s/', '', $extracted);
            }
            $extracted = preg_replace('/\s\S*$/', '', $extracted);

            return trim($extracted);
        }

        return substr($clean, 0, 80);
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
        // Normalize the input to a search string
        if (is_string($phrases)) {
            $searchString = $phrases;
        } elseif (is_array($phrases)) {
            // Check if it's an associative array (has string keys like 'searchname')
            $isAssociative = count(array_filter(array_keys($phrases), 'is_string')) > 0;

            if ($isAssociative) {
                // Extract values from associative array
                $searchString = implode(' ', array_values($phrases));
            } else {
                // Indexed array - combine values
                $searchString = implode(' ', $phrases);
            }
        } else {
            return [];
        }

        $result = $this->indexSearch($searchString, $limit);

        return is_array($result) ? $result : $result->toArray();
    }

    /**
     * Search the predb index.
     *
     * @param  array|string  $searchTerm  Search term(s)
     * @return array Array of predb records
     */
    public function searchPredb(array|string $searchTerm): array
    {
        $result = $this->predbIndexSearch($searchTerm);

        return is_array($result) ? $result : $result->toArray();
    }

    /**
     * Search releases index.
     *
     * @param  array|string  $phrases  Search phrases
     * @param  int  $limit  Maximum number of results
     * @return array|Collection Array of release IDs
     */
    public function indexSearch(array|string $phrases, int $limit): array|Collection
    {
        if (empty($phrases) || ! $this->isElasticsearchAvailable()) {
            if (config('app.debug')) {
                Log::debug('ElasticSearch indexSearch: empty phrases or ES not available', [
                    'phrases_empty' => empty($phrases),
                    'es_available' => $this->isElasticsearchAvailable(),
                ]);
            }

            return [];
        }

        $keywords = $this->sanitizeSearchTerms($phrases);

        if (config('app.debug')) {
            Log::debug('ElasticSearch indexSearch: sanitized keywords', [
                'original' => is_array($phrases) ? implode(' ', $phrases) : $phrases,
                'sanitized' => $keywords,
            ]);
        }

        if (empty($keywords)) {
            if (config('app.debug')) {
                Log::debug('ElasticSearch indexSearch: keywords empty after sanitization');
            }

            return [];
        }

        $cacheKey = $this->buildCacheKey('index_search', [$keywords, $limit]);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $search = $this->buildSearchQuery(
                index: $this->getReleasesIndex(),
                keywords: $keywords,
                fields: ['searchname^2', 'plainsearchname^1.5', 'fromname', 'filename', 'name^1.2'],
                limit: $limit
            );

            $result = $this->executeSearch($search);
            Cache::put($cacheKey, $result, now()->addMinutes($this->config['cache_minutes'] ?? self::CACHE_TTL_MINUTES));

            return $result;

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch indexSearch error: '.$e->getMessage(), [
                'keywords' => $keywords,
                'limit' => $limit,
            ]);

            return [];
        }
    }

    /**
     * Search releases for API requests.
     *
     * @param  array|string  $searchName  Search name(s)
     * @param  int  $limit  Maximum number of results
     * @return array|Collection Array of release IDs
     */
    public function indexSearchApi(array|string $searchName, int $limit): array|Collection
    {
        if (empty($searchName) || ! $this->isElasticsearchAvailable()) {
            return [];
        }

        $keywords = $this->sanitizeSearchTerms($searchName);
        if (empty($keywords)) {
            return [];
        }

        $cacheKey = $this->buildCacheKey('api_search', [$keywords, $limit]);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $search = $this->buildSearchQuery(
                index: $this->getReleasesIndex(),
                keywords: $keywords,
                fields: ['searchname^2', 'plainsearchname^1.5', 'fromname', 'filename', 'name^1.2'],
                limit: $limit
            );

            $result = $this->executeSearch($search);
            Cache::put($cacheKey, $result, now()->addMinutes($this->config['cache_minutes'] ?? self::CACHE_TTL_MINUTES));

            return $result;

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch indexSearchApi error: '.$e->getMessage(), [
                'keywords' => $keywords,
                'limit' => $limit,
            ]);

            return [];
        }
    }

    /**
     * Search releases for TV/Movie/Audio (TMA) matching.
     *
     * @param  array|string  $name  Name(s) to search
     * @param  int  $limit  Maximum number of results
     * @return array|Collection Array of release IDs
     */
    public function indexSearchTMA(array|string $name, int $limit): array|Collection
    {
        if (empty($name) || ! $this->isElasticsearchAvailable()) {
            return [];
        }

        $keywords = $this->sanitizeSearchTerms($name);
        if (empty($keywords)) {
            return [];
        }

        $cacheKey = $this->buildCacheKey('tma_search', [$keywords, $limit]);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $search = $this->buildSearchQuery(
                index: $this->getReleasesIndex(),
                keywords: $keywords,
                fields: ['searchname^2', 'plainsearchname^1.5'],
                limit: $limit,
                options: [
                    'boost' => 1.2,
                ]
            );

            $result = $this->executeSearch($search);
            Cache::put($cacheKey, $result, now()->addMinutes($this->config['cache_minutes'] ?? self::CACHE_TTL_MINUTES));

            return $result;

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch indexSearchTMA error: '.$e->getMessage(), [
                'keywords' => $keywords,
                'limit' => $limit,
            ]);

            return [];
        }
    }

    /**
     * Search predb index.
     *
     * @param  array|string  $searchTerm  Search term(s)
     * @return array|Collection Array of predb records
     */
    public function predbIndexSearch(array|string $searchTerm): array|Collection
    {
        if (empty($searchTerm) || ! $this->isElasticsearchAvailable()) {
            return [];
        }

        $keywords = $this->sanitizeSearchTerms($searchTerm);
        if (empty($keywords)) {
            return [];
        }

        $cacheKey = $this->buildCacheKey('predb_search', [$keywords]);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $search = $this->buildSearchQuery(
                index: $this->getPredbIndex(),
                keywords: $keywords,
                fields: ['title^2', 'filename'],
                limit: 1000,
                options: [
                    'fuzziness' => 'AUTO',
                ],
                includeDateSort: false
            );

            $result = $this->executeSearch($search, fullResults: true);
            Cache::put($cacheKey, $result, now()->addMinutes($this->config['cache_minutes'] ?? self::CACHE_TTL_MINUTES));

            return $result;

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch predbIndexSearch error: '.$e->getMessage(), [
                'keywords' => $keywords,
            ]);

            return [];
        }
    }

    /**
     * Insert a release into the index.
     *
     * @param  array  $parameters  Release data with 'id', 'name', 'searchname', etc.
     */
    public function insertRelease(array $parameters): void
    {
        if (empty($parameters['id']) || ! $this->isElasticsearchAvailable()) {
            if (empty($parameters['id'])) {
                Log::warning('ElasticSearch: Cannot insert release without ID');
            }

            return;
        }

        try {
            $client = $this->getClient();
            $client->index($this->buildReleaseDocument($parameters));

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch insertRelease error: '.$e->getMessage(), [
                'release_id' => $parameters['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch insertRelease unexpected error: '.$e->getMessage(), [
                'release_id' => $parameters['id'],
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
        if (empty($releases) || ! $this->isElasticsearchAvailable()) {
            return ['success' => 0, 'errors' => 0];
        }

        $params = ['body' => []];
        $validReleases = 0;

        foreach ($releases as $release) {
            if (empty($release['id'])) {
                continue;
            }

            $params['body'][] = [
                'index' => [
                    '_index' => $this->getReleasesIndex(),
                    '_id' => $release['id'],
                ],
            ];

            $document = $this->buildReleaseDocument($release);
            $params['body'][] = $document['body'];
            $validReleases++;

            // Send batch when reaching 500 documents
            if ($validReleases % 500 === 0) {
                $this->executeBulk($params);
                $params = ['body' => []];
            }
        }

        // Send remaining documents
        if (! empty($params['body'])) {
            $this->executeBulk($params);
        }

        return ['success' => $validReleases, 'errors' => 0];
    }

    /**
     * Update a release in the index.
     *
     * @param  int|string  $releaseID  Release ID
     */
    public function updateRelease(int|string $releaseID): void
    {
        if (empty($releaseID)) {
            Log::warning('ElasticSearch: Cannot update release without ID');

            return;
        }

        if (! $this->isElasticsearchAvailable()) {
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

            if ($release === null) {
                Log::warning('ElasticSearch: Release not found for update', ['id' => $releaseID]);

                return;
            }

            $searchNameDotless = $this->createPlainSearchName($release->searchname);
            $data = [
                'body' => [
                    'doc' => [
                        'id' => $release->id,
                        'name' => $release->name,
                        'searchname' => $release->searchname,
                        'plainsearchname' => $searchNameDotless,
                        'fromname' => $release->fromname,
                        'categories_id' => $release->categories_id,
                        'filename' => $release->filename,
                    ],
                    'doc_as_upsert' => true,
                ],
                'index' => $this->getReleasesIndex(),
                'id' => $release->id,
            ];

            $client = $this->getClient();
            $client->update($data);

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch updateRelease error: '.$e->getMessage(), [
                'release_id' => $releaseID,
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch updateRelease unexpected error: '.$e->getMessage(), [
                'release_id' => $releaseID,
            ]);
        }
    }


    /**
     * Insert a predb record into the index.
     *
     * @param  array  $parameters  Predb data with 'id', 'title', 'source', 'filename'
     */
    public function insertPredb(array $parameters): void
    {
        if (empty($parameters['id'])) {
            Log::warning('ElasticSearch: Cannot insert predb without ID');

            return;
        }

        if (! $this->isElasticsearchAvailable()) {
            return;
        }

        try {
            $data = [
                'body' => [
                    'id' => $parameters['id'],
                    'title' => $parameters['title'] ?? '',
                    'source' => $parameters['source'] ?? '',
                    'filename' => $parameters['filename'] ?? '',
                ],
                'index' => $this->getPredbIndex(),
                'id' => $parameters['id'],
            ];

            $client = $this->getClient();
            $client->index($data);

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch insertPreDb error: '.$e->getMessage(), [
                'predb_id' => $parameters['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch insertPreDb unexpected error: '.$e->getMessage(), [
                'predb_id' => $parameters['id'],
            ]);
        }
    }

    /**
     * Update a predb record in the index.
     *
     * @param  array  $parameters  Predb data with 'id', 'title', 'source', 'filename'
     */
    public function updatePreDb(array $parameters): void
    {
        if (empty($parameters['id'])) {
            Log::warning('ElasticSearch: Cannot update predb without ID');

            return;
        }

        if (! $this->isElasticsearchAvailable()) {
            return;
        }

        try {
            $data = [
                'body' => [
                    'doc' => [
                        'id' => $parameters['id'],
                        'title' => $parameters['title'] ?? '',
                        'filename' => $parameters['filename'] ?? '',
                        'source' => $parameters['source'] ?? '',
                    ],
                    'doc_as_upsert' => true,
                ],
                'index' => $this->getPredbIndex(),
                'id' => $parameters['id'],
            ];

            $client = $this->getClient();
            $client->update($data);

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch updatePreDb error: '.$e->getMessage(), [
                'predb_id' => $parameters['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch updatePreDb unexpected error: '.$e->getMessage(), [
                'predb_id' => $parameters['id'],
            ]);
        }
    }

    /**
     * Delete a release from the index.
     *
     * @param  int  $id  Release ID
     */
    public function deleteRelease(int $id): void
    {
        if (empty($id) || ! $this->isElasticsearchAvailable()) {
            if (empty($id)) {
                Log::warning('ElasticSearch: Cannot delete release without ID');
            }

            return;
        }

        try {
            $client = $this->getClient();
            $client->delete([
                'index' => $this->getReleasesIndex(),
                'id' => $id,
            ]);

        } catch (Missing404Exception $e) {
            // Document already deleted, not an error
            if (config('app.debug')) {
                Log::debug('ElasticSearch deleteRelease: document not found', ['release_id' => $id]);
            }
        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch deleteRelease error: '.$e->getMessage(), [
                'release_id' => $id,
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch deleteRelease unexpected error: '.$e->getMessage(), [
                'release_id' => $id,
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
        if (empty($id) || ! $this->isElasticsearchAvailable()) {
            if (empty($id)) {
                Log::warning('ElasticSearch: Cannot delete predb without ID');
            }

            return;
        }

        try {
            $client = $this->getClient();
            $client->delete([
                'index' => $this->getPredbIndex(),
                'id' => $id,
            ]);

        } catch (Missing404Exception $e) {
            if (config('app.debug')) {
                Log::debug('ElasticSearch deletePreDb: document not found', ['predb_id' => $id]);
            }
        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch deletePreDb error: '.$e->getMessage(), [
                'predb_id' => $id,
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch deletePreDb unexpected error: '.$e->getMessage(), [
                'predb_id' => $id,
            ]);
        }
    }

    /**
     * Check if an index exists.
     *
     * @param  string  $index  Index name
     */
    public function indexExists(string $index): bool
    {
        if (! $this->isElasticsearchAvailable()) {
            return false;
        }

        try {
            $client = $this->getClient();

            return $client->indices()->exists(['index' => $index]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch indexExists error: '.$e->getMessage(), ['index' => $index]);

            return false;
        }
    }

    /**
     * Truncate/clear an index (remove all documents).
     * Implements SearchServiceInterface::truncateIndex
     *
     * @param  array|string  $indexes  Index name(s) to truncate
     */
    public function truncateIndex(array|string $indexes): void
    {
        if (! $this->isElasticsearchAvailable()) {
            return;
        }

        $indexArray = is_array($indexes) ? $indexes : [$indexes];

        foreach ($indexArray as $index) {
            try {
                $client = $this->getClient();

                // Check if index exists
                if (! $client->indices()->exists(['index' => $index])) {
                    Log::info("ElasticSearch truncateIndex: index {$index} does not exist, skipping");
                    continue;
                }

                // Delete all documents from the index
                $client->deleteByQuery([
                    'index' => $index,
                    'body' => [
                        'query' => ['match_all' => (object) []],
                    ],
                    'conflicts' => 'proceed',
                ]);

                // Force refresh to ensure deletions are visible
                $client->indices()->refresh(['index' => $index]);

                Log::info("ElasticSearch: Truncated index {$index}");

            } catch (\Throwable $e) {
                Log::error("ElasticSearch truncateIndex error for {$index}: ".$e->getMessage());
            }
        }
    }

    /**
     * Optimize index for better search performance.
     * Implements SearchServiceInterface::optimizeIndex
     */
    public function optimizeIndex(): void
    {
        if (! $this->isElasticsearchAvailable()) {
            return;
        }

        try {
            $client = $this->getClient();

            // Force merge the releases index
            $client->indices()->forcemerge([
                'index' => $this->getReleasesIndex(),
                'max_num_segments' => 1,
            ]);

            // Force merge the predb index
            $client->indices()->forcemerge([
                'index' => $this->getPredbIndex(),
                'max_num_segments' => 1,
            ]);

            // Refresh both indexes
            $client->indices()->refresh(['index' => $this->getReleasesIndex()]);
            $client->indices()->refresh(['index' => $this->getPredbIndex()]);

            Log::info('ElasticSearch: Optimized indexes');

        } catch (\Throwable $e) {
            Log::error('ElasticSearch optimizeIndex error: '.$e->getMessage());
        }
    }

    /**
     * Get cluster health information.
     *
     * @return array Health information or empty array on failure
     */
    public function getClusterHealth(): array
    {
        if (! $this->isElasticsearchAvailable()) {
            return [];
        }

        try {
            $client = $this->getClient();

            return $client->cluster()->health();
        } catch (\Throwable $e) {
            Log::error('ElasticSearch getClusterHealth error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get index statistics.
     *
     * @param  string  $index  Index name
     * @return array Statistics or empty array on failure
     */
    public function getIndexStats(string $index): array
    {
        if (! $this->isElasticsearchAvailable()) {
            return [];
        }

        try {
            $client = $this->getClient();

            return $client->indices()->stats(['index' => $index]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch getIndexStats error: '.$e->getMessage(), ['index' => $index]);

            return [];
        }
    }

    /**
     * Sanitize search terms for Elasticsearch.
     *
     * Uses the global sanitize() helper function which properly escapes
     * Elasticsearch query string special characters.
     *
     * @param  array|string  $terms  Search terms
     * @return string Sanitized search string
     */
    private function sanitizeSearchTerms(array|string $terms): string
    {
        if (is_array($terms)) {
            $terms = implode(' ', array_filter($terms));
        }

        $terms = trim($terms);
        if (empty($terms)) {
            return '';
        }

        // Use the original sanitize() helper function that properly handles
        // Elasticsearch query string escaping
        if (function_exists('sanitize')) {
            return sanitize($terms);
        }

        // Fallback if sanitize function doesn't exist
        // Replace dots with spaces for release name searches
        $terms = str_replace('.', ' ', $terms);

        // Remove multiple consecutive spaces
        $terms = preg_replace('/\s+/', ' ', trim($terms));

        return $terms;
    }

    /**
     * Build a cache key for search results.
     *
     * @param  string  $prefix  Cache key prefix
     * @param  array  $params  Parameters to include in key
     */
    private function buildCacheKey(string $prefix, array $params): string
    {
        return 'es_'.$prefix.'_'.md5(serialize($params));
    }

    /**
     * Build a search query array.
     *
     * @param  string  $index  Index name
     * @param  string  $keywords  Sanitized keywords
     * @param  array  $fields  Fields to search with boosts
     * @param  int  $limit  Maximum results
     * @param  array  $options  Additional query_string options
     * @param  bool  $includeDateSort  Include date sorting
     */
    private function buildSearchQuery(
        string $index,
        string $keywords,
        array $fields,
        int $limit,
        array $options = [],
        bool $includeDateSort = true
    ): array {
        $queryString = array_merge([
            'query' => $keywords,
            'fields' => $fields,
            'analyze_wildcard' => true,
            'default_operator' => 'and',
        ], $options);

        $sort = [['_score' => ['order' => 'desc']]];

        if ($includeDateSort) {
            $sort[] = ['add_date' => ['order' => 'desc', 'unmapped_type' => 'date', 'missing' => '_last']];
            $sort[] = ['post_date' => ['order' => 'desc', 'unmapped_type' => 'date', 'missing' => '_last']];
        }

        return [
            'scroll' => self::SCROLL_TIMEOUT,
            'index' => $index,
            'body' => [
                'query' => [
                    'query_string' => $queryString,
                ],
                'size' => min($limit, self::MAX_RESULTS),
                'sort' => $sort,
                '_source' => ['id'],
                'track_total_hits' => true,
            ],
        ];
    }

    /**
     * Build a release document for indexing.
     *
     * @param  array  $parameters  Release parameters
     */
    private function buildReleaseDocument(array $parameters): array
    {
        $searchNameDotless = $this->createPlainSearchName($parameters['searchname'] ?? '');

        return [
            'body' => [
                'id' => $parameters['id'],
                'name' => $parameters['name'] ?? '',
                'searchname' => $parameters['searchname'] ?? '',
                'plainsearchname' => $searchNameDotless,
                'fromname' => $parameters['fromname'] ?? '',
                'categories_id' => $parameters['categories_id'] ?? 0,
                'filename' => $parameters['filename'] ?? '',
                'add_date' => now()->format('Y-m-d H:i:s'),
                'post_date' => $parameters['postdate'] ?? now()->format('Y-m-d H:i:s'),
            ],
            'index' => $this->getReleasesIndex(),
            'id' => $parameters['id'],
        ];
    }

    /**
     * Create a plain search name by removing dots and dashes.
     *
     * @param  string  $searchName  Original search name
     */
    private function createPlainSearchName(string $searchName): string
    {
        return str_replace(['.', '-'], ' ', $searchName);
    }

    /**
     * Execute a search with scroll support.
     *
     * @param  array  $search  Search query
     * @param  bool  $fullResults  Return full source documents instead of just IDs
     */
    protected function executeSearch(array $search, bool $fullResults = false): array
    {
        if (empty($search) || ! $this->isElasticsearchAvailable()) {
            return [];
        }

        $scrollId = null;

        try {
            $client = $this->getClient();

            // Log the search query for debugging
            if (config('app.debug')) {
                Log::debug('ElasticSearch executing search', [
                    'index' => $search['index'] ?? 'unknown',
                    'query' => $search['body']['query'] ?? [],
                ]);
            }

            $results = $client->search($search);

            // Log the number of hits
            if (config('app.debug')) {
                $totalHits = $results['hits']['total']['value'] ?? $results['hits']['total'] ?? 0;
                Log::debug('ElasticSearch search results', [
                    'total_hits' => $totalHits,
                    'returned_hits' => count($results['hits']['hits'] ?? []),
                ]);
            }

            $searchResult = [];

            while (isset($results['hits']['hits']) && count($results['hits']['hits']) > 0) {
                foreach ($results['hits']['hits'] as $result) {
                    if ($fullResults) {
                        $searchResult[] = $result['_source'];
                    } else {
                        $searchResult[] = $result['_source']['id'] ?? $result['_id'];
                    }
                }

                // Handle scrolling for large result sets
                if (! isset($results['_scroll_id'])) {
                    break;
                }

                $scrollId = $results['_scroll_id'];
                $results = $client->scroll([
                    'scroll_id' => $scrollId,
                    'scroll' => self::SCROLL_TIMEOUT,
                ]);
            }

            return $searchResult;

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch search error: '.$e->getMessage());

            return [];
        } catch (\Throwable $e) {
            Log::error('ElasticSearch search unexpected error: '.$e->getMessage());

            return [];
        } finally {
            // Clean up scroll context
            $this->clearScrollContext($scrollId);
        }
    }

    /**
     * Clear a scroll context to free server resources.
     *
     * @param  string|null  $scrollId  Scroll ID to clear
     */
    private function clearScrollContext(?string $scrollId): void
    {
        if ($scrollId === null) {
            return;
        }

        try {
            $client = $this->getClient();
            $client->clearScroll(['scroll_id' => $scrollId]);
        } catch (\Throwable $e) {
            // Ignore errors when clearing scroll - it's just cleanup
            if (config('app.debug')) {
                Log::debug('Failed to clear scroll context: '.$e->getMessage());
            }
        }
    }

    /**
     * Execute a bulk operation.
     *
     * @param  array  $params  Bulk operation parameters
     */
    private function executeBulk(array $params): void
    {
        if (empty($params['body'])) {
            return;
        }

        try {
            $client = $this->getClient();
            $response = $client->bulk($params);

            if (! empty($response['errors'])) {
                foreach ($response['items'] as $item) {
                    $operation = $item['index'] ?? $item['update'] ?? $item['delete'] ?? [];
                    if (isset($operation['error'])) {
                        Log::error('ElasticSearch bulk operation error', [
                            'id' => $operation['_id'] ?? 'unknown',
                            'error' => $operation['error'],
                        ]);
                    }
                }
            }
        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch bulk error: '.$e->getMessage());
        } catch (\Throwable $e) {
            Log::error('ElasticSearch bulk unexpected error: '.$e->getMessage());
        }
    }
}

