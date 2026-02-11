<?php

namespace App\Services\Search\Drivers;

use App\Models\MovieInfo;
use App\Models\Release;
use App\Models\Video;
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

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * @param  array<string, mixed>  $config
     */
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
     * @param  array<string, mixed>  $configHosts  Configuration hosts array
     * @return array<string, mixed> Formatted hosts array for Elasticsearch client
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
     *
     * @return list<array<string, mixed>>
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
     * Check if fuzzy search is enabled.
     */
    public function isFuzzyEnabled(): bool
    {
        return ($this->config['fuzzy']['enabled'] ?? true) && $this->isElasticsearchAvailable();
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
            'fuzziness' => 'AUTO',
            'prefix_length' => 2,
            'max_expansions' => 50,
        ];
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
     * Get the movies index name.
     */
    public function getMoviesIndex(): string
    {
        return $this->config['indexes']['movies'] ?? 'movies';
    }

    /**
     * Get the TV shows index name.
     */
    public function getTvShowsIndex(): string
    {
        return $this->config['indexes']['tvshows'] ?? 'tvshows';
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
     * @param  array<string, mixed>|string  $phrases  Search phrases - can be a string, indexed array of terms, or associative array with field names
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array of release IDs
     */
    public function searchReleases(array|string $phrases, int $limit = 1000): array
    {
        // Normalize the input to a search string
        if (is_string($phrases)) {
            $searchString = $phrases;
        } else {
            // Check if it's an associative array (has string keys like 'searchname')
            $isAssociative = count(array_filter(array_keys($phrases), 'is_string')) > 0;

            if ($isAssociative) {
                // Extract values from associative array
                $searchString = implode(' ', array_values($phrases));
            } else {
                // Indexed array - combine values
                $searchString = implode(' ', $phrases);
            }
        }

        $result = $this->indexSearch($searchString, $limit);

        return is_array($result) ? $result : $result->toArray();
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
     * Uses Elasticsearch's fuzzy matching to find results with typo tolerance.
     *
     * @param  array<string, mixed>|string  $phrases  Search phrases
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed> Array of release IDs
     */
    public function fuzzySearchReleases(array|string $phrases, int $limit = 1000): array
    {
        if (! $this->isFuzzyEnabled() || ! $this->isElasticsearchAvailable()) {
            return [];
        }

        // Normalize the input to a search string
        if (is_string($phrases)) {
            $searchString = $phrases;
        } else {
            $isAssociative = count(array_filter(array_keys($phrases), 'is_string')) > 0;
            if ($isAssociative) {
                $searchString = implode(' ', array_values($phrases));
            } else {
                $searchString = implode(' ', $phrases);
            }
        }

        $keywords = $this->sanitizeSearchTerms($searchString);
        if (empty($keywords)) {
            return [];
        }

        $fuzzyConfig = $this->getFuzzyConfig();
        $cacheKey = $this->buildCacheKey('fuzzy_search', [$keywords, $limit, $fuzzyConfig]); // @phpstan-ignore argument.type
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $client = $this->getClient();

            // Build fuzzy query
            $searchParams = [
                'index' => $this->getReleasesIndex(),
                'body' => [
                    'query' => [
                        'bool' => [
                            'should' => [
                                // Fuzzy match on searchname
                                [
                                    'match' => [
                                        'searchname' => [
                                            'query' => $keywords,
                                            'fuzziness' => $fuzzyConfig['fuzziness'] ?? 'AUTO',
                                            'prefix_length' => $fuzzyConfig['prefix_length'] ?? 2,
                                            'max_expansions' => $fuzzyConfig['max_expansions'] ?? 50,
                                            'boost' => 2,
                                        ],
                                    ],
                                ],
                                // Fuzzy match on plainsearchname
                                [
                                    'match' => [
                                        'plainsearchname' => [
                                            'query' => $keywords,
                                            'fuzziness' => $fuzzyConfig['fuzziness'] ?? 'AUTO',
                                            'prefix_length' => $fuzzyConfig['prefix_length'] ?? 2,
                                            'max_expansions' => $fuzzyConfig['max_expansions'] ?? 50,
                                            'boost' => 1.5,
                                        ],
                                    ],
                                ],
                                // Fuzzy match on name
                                [
                                    'match' => [
                                        'name' => [
                                            'query' => $keywords,
                                            'fuzziness' => $fuzzyConfig['fuzziness'] ?? 'AUTO',
                                            'prefix_length' => $fuzzyConfig['prefix_length'] ?? 2,
                                            'max_expansions' => $fuzzyConfig['max_expansions'] ?? 50,
                                            'boost' => 1.2,
                                        ],
                                    ],
                                ],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                    'size' => min($limit, self::MAX_RESULTS),
                    '_source' => false,
                    'sort' => [
                        ['_score' => ['order' => 'desc']],
                    ],
                ],
            ];

            $response = $client->search($searchParams);

            $ids = [];
            if (isset($response['hits']['hits'])) {
                foreach ($response['hits']['hits'] as $hit) {
                    $ids[] = (int) $hit['_id'];
                }
            }

            Cache::put($cacheKey, $ids, now()->addMinutes($this->config['cache_minutes'] ?? self::CACHE_TTL_MINUTES));

            return $ids; // @phpstan-ignore return.type

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch fuzzySearchReleases error: '.$e->getMessage(), [
                'keywords' => $keywords,
                'limit' => $limit,
            ]);

            return [];
        } catch (\Throwable $e) {
            Log::error('ElasticSearch fuzzySearchReleases unexpected error: '.$e->getMessage(), [
                'keywords' => $keywords,
            ]);

            return [];
        }
    }

    /**
     * Search the predb index.
     *
     * @param  array<string, mixed>|string  $searchTerm  Search term(s)
     * @return array<string, mixed> Array of predb records
     */
    public function searchPredb(array|string $searchTerm): array
    {
        $result = $this->predbIndexSearch($searchTerm);

        return is_array($result) ? $result : $result->toArray();
    }

    /**
     * Search releases index.
     *
     * @param  array<string, mixed>|string  $phrases  Search phrases
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed>|Collection<int, mixed> Array of release IDs
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

        $cacheKey = $this->buildCacheKey('index_search', [$keywords, $limit]); // @phpstan-ignore argument.type
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $search = $this->buildSearchQuery(
                index: $this->getReleasesIndex(),
                keywords: $keywords,
                fields: ['searchname^2', 'plainsearchname^1.5', 'fromname', 'filename', 'name^1.2'], // @phpstan-ignore argument.type
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
     * @param  array<string, mixed>|string  $searchName  Search name(s)
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed>|Collection<int, mixed> Array of release IDs
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

        $cacheKey = $this->buildCacheKey('api_search', [$keywords, $limit]); // @phpstan-ignore argument.type
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $search = $this->buildSearchQuery(
                index: $this->getReleasesIndex(),
                keywords: $keywords,
                fields: ['searchname^2', 'plainsearchname^1.5', 'fromname', 'filename', 'name^1.2'], // @phpstan-ignore argument.type
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
     * @param  array<string, mixed>|string  $name  Name(s) to search
     * @param  int  $limit  Maximum number of results
     * @return array<string, mixed>|Collection<int, mixed> Array of release IDs
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

        $cacheKey = $this->buildCacheKey('tma_search', [$keywords, $limit]); // @phpstan-ignore argument.type
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $search = $this->buildSearchQuery(
                index: $this->getReleasesIndex(),
                keywords: $keywords,
                fields: ['searchname^2', 'plainsearchname^1.5'], // @phpstan-ignore argument.type
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
     * @param  array<string, mixed>|string  $searchTerm  Search term(s)
     * @return array<string, mixed>|Collection<int, mixed> Array of predb records
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

        $cacheKey = $this->buildCacheKey('predb_search', [$keywords]); // @phpstan-ignore argument.type
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $search = $this->buildSearchQuery(
                index: $this->getPredbIndex(),
                keywords: $keywords,
                fields: ['title^2', 'filename'], // @phpstan-ignore argument.type
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
     * @param  array<string, mixed>  $parameters  Release data with 'id', 'name', 'searchname', etc.
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
                        'filename' => $release->filename, // @phpstan-ignore property.notFound
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
     * @param  array<string, mixed>  $parameters  Predb data with 'id', 'title', 'source', 'filename'
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
     * @param  array<string, mixed>  $parameters  Predb data with 'id', 'title', 'source', 'filename'
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
     * Bulk insert multiple releases into the index.
     *
     * @param  array<string, mixed>  $releases  Array of release data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertReleases(array $releases): array
    {
        if (empty($releases) || ! $this->isElasticsearchAvailable()) {
            return ['success' => 0, 'errors' => 0];
        }

        $success = 0;
        $errors = 0;

        $params = ['body' => []];

        foreach ($releases as $release) {
            if (empty($release['id'])) {
                $errors++;

                continue;
            }

            $searchNameDotless = $this->createPlainSearchName($release['searchname'] ?? '');

            $params['body'][] = [
                'index' => [
                    '_index' => $this->getReleasesIndex(),
                    '_id' => $release['id'],
                ],
            ];

            $params['body'][] = [
                'id' => $release['id'],
                'name' => (string) ($release['name'] ?? ''),
                'searchname' => (string) ($release['searchname'] ?? ''),
                'plainsearchname' => $searchNameDotless,
                'fromname' => (string) ($release['fromname'] ?? ''),
                'categories_id' => (int) ($release['categories_id'] ?? 0),
                'filename' => (string) ($release['filename'] ?? ''),
            ];

            $success++;
        }

        if (! empty($params['body'])) {
            try {
                $client = $this->getClient();
                $response = $client->bulk($params);

                if (isset($response['errors']) && $response['errors']) {
                    foreach ($response['items'] as $item) {
                        if (isset($item['index']['error'])) {
                            $errors++;
                            $success--;
                            if (config('app.debug')) {
                                Log::error('ElasticSearch bulkInsertReleases error: '.json_encode($item['index']['error']));
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('ElasticSearch bulkInsertReleases error: '.$e->getMessage());
                $errors += $success;
                $success = 0;
            }
        }

        return ['success' => $success, 'errors' => $errors];
    }

    /**
     * Bulk insert multiple predb records into the index.
     *
     * @param  array<string, mixed>  $predbRecords  Array of predb data arrays
     * @return array<string, mixed> Results with 'success' and 'errors' counts
     */
    public function bulkInsertPredb(array $predbRecords): array
    {
        if (empty($predbRecords) || ! $this->isElasticsearchAvailable()) {
            return ['success' => 0, 'errors' => 0];
        }

        $success = 0;
        $errors = 0;

        $params = ['body' => []];

        foreach ($predbRecords as $predb) {
            if (empty($predb['id'])) {
                $errors++;

                continue;
            }

            $params['body'][] = [
                'index' => [
                    '_index' => $this->getPredbIndex(),
                    '_id' => $predb['id'],
                ],
            ];

            $params['body'][] = [
                'id' => $predb['id'],
                'title' => (string) ($predb['title'] ?? ''),
                'filename' => (string) ($predb['filename'] ?? ''),
                'source' => (string) ($predb['source'] ?? ''),
            ];

            $success++;
        }

        if (! empty($params['body'])) {
            try {
                $client = $this->getClient();
                $response = $client->bulk($params);

                if (isset($response['errors']) && $response['errors']) {
                    foreach ($response['items'] as $item) {
                        if (isset($item['index']['error'])) {
                            $errors++;
                            $success--;
                            if (config('app.debug')) {
                                Log::error('ElasticSearch bulkInsertPredb error: '.json_encode($item['index']['error']));
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('ElasticSearch bulkInsertPredb error: '.$e->getMessage());
                $errors += $success;
                $success = 0;
            }
        }

        return ['success' => $success, 'errors' => $errors];
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
     * @param  array<string, mixed>|string  $indexes  Index name(s) to truncate
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
     * @return array<string, mixed> Health information or empty array on failure
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
     * @return array<string, mixed> Statistics or empty array on failure
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
     * @param  array<string, mixed>|string  $terms  Search terms
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
     * @param  array<string, mixed>  $params  Parameters to include in key
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
     * @param  array<string, mixed>  $fields  Fields to search with boosts
     * @param  int  $limit  Maximum results
     * @param  array<string, mixed>  $options  Additional query_string options
     * @param  bool  $includeDateSort  Include date sorting
     * @return array<string, mixed>
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
     * @param  array<string, mixed>  $parameters  Release parameters
     * @return array<string, mixed>
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
     * @param  array<string, mixed>  $search  Search query
     * @param  bool  $fullResults  Return full source documents instead of just IDs
     * @return array<string, mixed>
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

            return $searchResult; // @phpstan-ignore return.type

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
     * @param  array<string, mixed>  $params  Bulk operation parameters
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

    /**
     * Insert a movie into the movies search index.
     *
     * @param  array<string, mixed>  $parameters  Movie data
     */
    public function insertMovie(array $parameters): void
    {
        if (empty($parameters['id'])) {
            Log::warning('ElasticSearch: Cannot insert movie without ID');

            return;
        }

        try {
            $client = $this->getClient();

            $document = [
                'id' => $parameters['id'],
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

            $client->index([
                'index' => $this->getMoviesIndex(),
                'id' => $parameters['id'],
                'body' => $document,
            ]);

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch insertMovie error: '.$e->getMessage(), [
                'movie_id' => $parameters['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch insertMovie unexpected error: '.$e->getMessage(), [
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
            Log::warning('ElasticSearch: Cannot update movie without ID');

            return;
        }

        try {
            $movie = MovieInfo::find($movieId);

            if ($movie !== null) {
                $this->insertMovie($movie->toArray());
            } else {
                Log::warning('ElasticSearch: Movie not found for update', ['id' => $movieId]);
            }
        } catch (\Throwable $e) {
            Log::error('ElasticSearch updateMovie error: '.$e->getMessage(), [
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
            Log::warning('ElasticSearch: Cannot delete movie without ID');

            return;
        }

        try {
            $client = $this->getClient();
            $client->delete([
                'index' => $this->getMoviesIndex(),
                'id' => $id,
            ]);
        } catch (Missing404Exception $e) {
            // Document doesn't exist, that's fine
        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch deleteMovie error: '.$e->getMessage(), [
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

        $params = ['body' => []];

        foreach ($movies as $movie) {
            if (empty($movie['id'])) {
                $errors++;

                continue;
            }

            $params['body'][] = [
                'index' => [
                    '_index' => $this->getMoviesIndex(),
                    '_id' => $movie['id'],
                ],
            ];

            $params['body'][] = [
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

            $success++;
        }

        if (! empty($params['body'])) {
            try {
                $this->executeBulk($params);
            } catch (\Throwable $e) {
                Log::error('ElasticSearch bulkInsertMovies error: '.$e->getMessage());
                $errors += $success;
                $success = 0;
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
        $escapedSearch = self::escapeString($searchString);

        if (empty($escapedSearch)) {
            return ['id' => [], 'data' => []];
        }

        $cacheKey = 'es:movies:'.md5($escapedSearch.$limit);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $client = $this->getClient();

            $searchParams = [
                'index' => $this->getMoviesIndex(),
                'body' => [
                    'query' => [
                        'multi_match' => [
                            'query' => $escapedSearch,
                            'fields' => ['title^3', 'actors', 'director', 'genre'],
                            'type' => 'best_fields',
                            'fuzziness' => 'AUTO',
                        ],
                    ],
                    'size' => min($limit, self::MAX_RESULTS),
                ],
            ];

            $response = $client->search($searchParams);

            $resultIds = [];
            $resultData = [];

            if (isset($response['hits']['hits'])) {
                foreach ($response['hits']['hits'] as $hit) {
                    $resultIds[] = $hit['_id'];
                    $resultData[] = $hit['_source'];
                }
            }

            $result = ['id' => $resultIds, 'data' => $resultData];

            Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return $result;

        } catch (\Throwable $e) {
            Log::error('ElasticSearch searchMovies error: '.$e->getMessage());

            return ['id' => [], 'data' => []];
        }
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

        $cacheKey = 'es:movie:'.$field.':'.$value;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $client = $this->getClient();

            $searchParams = [
                'index' => $this->getMoviesIndex(),
                'body' => [
                    'query' => [
                        'term' => [
                            $field => (int) $value,
                        ],
                    ],
                    'size' => 1,
                ],
            ];

            $response = $client->search($searchParams);

            if (isset($response['hits']['hits'][0])) {
                $data = $response['hits']['hits'][0]['_source'];
                $data['id'] = $response['hits']['hits'][0]['_id'];
                Cache::put($cacheKey, $data, now()->addMinutes(self::CACHE_TTL_MINUTES));

                return $data;
            }

        } catch (\Throwable $e) {
            Log::error('ElasticSearch searchMovieByExternalId error: '.$e->getMessage(), [
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
            Log::warning('ElasticSearch: Cannot insert TV show without ID');

            return;
        }

        try {
            $client = $this->getClient();

            $document = [
                'id' => $parameters['id'],
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

            $client->index([
                'index' => $this->getTvShowsIndex(),
                'id' => $parameters['id'],
                'body' => $document,
            ]);

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch insertTvShow error: '.$e->getMessage(), [
                'tvshow_id' => $parameters['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch insertTvShow unexpected error: '.$e->getMessage(), [
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
            Log::warning('ElasticSearch: Cannot update TV show without ID');

            return;
        }

        try {
            $video = Video::find($videoId);

            if ($video !== null) {
                $this->insertTvShow($video->toArray());
            } else {
                Log::warning('ElasticSearch: TV show not found for update', ['id' => $videoId]);
            }
        } catch (\Throwable $e) {
            Log::error('ElasticSearch updateTvShow error: '.$e->getMessage(), [
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
            Log::warning('ElasticSearch: Cannot delete TV show without ID');

            return;
        }

        try {
            $client = $this->getClient();
            $client->delete([
                'index' => $this->getTvShowsIndex(),
                'id' => $id,
            ]);
        } catch (Missing404Exception $e) {
            // Document doesn't exist, that's fine
        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch deleteTvShow error: '.$e->getMessage(), [
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

        $params = ['body' => []];

        foreach ($tvShows as $tvShow) {
            if (empty($tvShow['id'])) {
                $errors++;

                continue;
            }

            $params['body'][] = [
                'index' => [
                    '_index' => $this->getTvShowsIndex(),
                    '_id' => $tvShow['id'],
                ],
            ];

            $params['body'][] = [
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

            $success++;
        }

        if (! empty($params['body'])) {
            try {
                $this->executeBulk($params);
            } catch (\Throwable $e) {
                Log::error('ElasticSearch bulkInsertTvShows error: '.$e->getMessage());
                $errors += $success;
                $success = 0;
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
        $escapedSearch = self::escapeString($searchString);

        if (empty($escapedSearch)) {
            return ['id' => [], 'data' => []];
        }

        $cacheKey = 'es:tvshows:'.md5($escapedSearch.$limit);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $client = $this->getClient();

            $searchParams = [
                'index' => $this->getTvShowsIndex(),
                'body' => [
                    'query' => [
                        'match' => [
                            'title' => [
                                'query' => $escapedSearch,
                                'fuzziness' => 'AUTO',
                            ],
                        ],
                    ],
                    'size' => min($limit, self::MAX_RESULTS),
                ],
            ];

            $response = $client->search($searchParams);

            $resultIds = [];
            $resultData = [];

            if (isset($response['hits']['hits'])) {
                foreach ($response['hits']['hits'] as $hit) {
                    $resultIds[] = $hit['_id'];
                    $resultData[] = $hit['_source'];
                }
            }

            $result = ['id' => $resultIds, 'data' => $resultData];

            Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return $result;

        } catch (\Throwable $e) {
            Log::error('ElasticSearch searchTvShows error: '.$e->getMessage());

            return ['id' => [], 'data' => []];
        }
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

        $cacheKey = 'es:tvshow:'.$field.':'.$value;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $client = $this->getClient();

            $searchParams = [
                'index' => $this->getTvShowsIndex(),
                'body' => [
                    'query' => [
                        'term' => [
                            $field => (int) $value,
                        ],
                    ],
                    'size' => 1,
                ],
            ];

            $response = $client->search($searchParams);

            if (isset($response['hits']['hits'][0])) {
                $data = $response['hits']['hits'][0]['_source'];
                $data['id'] = $response['hits']['hits'][0]['_id'];
                Cache::put($cacheKey, $data, now()->addMinutes(self::CACHE_TTL_MINUTES));

                return $data;
            }

        } catch (\Throwable $e) {
            Log::error('ElasticSearch searchTvShowByExternalId error: '.$e->getMessage(), [
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

        $cacheKey = 'es:releases:extid:'.md5(serialize($externalIds));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $client = $this->getClient();

            $shouldClauses = [];
            foreach ($externalIds as $field => $value) {
                if (! empty($value) && in_array($field, ['imdbid', 'tmdbid', 'traktid', 'tvdb', 'tvmaze', 'tvrage'])) {
                    $shouldClauses[] = ['term' => [$field => (int) $value]];
                }
            }

            if (empty($shouldClauses)) {
                return [];
            }

            $searchParams = [
                'index' => $this->getReleasesIndex(),
                'body' => [
                    'query' => [
                        'bool' => [
                            'should' => $shouldClauses,
                            'minimum_should_match' => 1,
                        ],
                    ],
                    'size' => min($limit, self::MAX_RESULTS),
                    '_source' => false,
                ],
            ];

            $response = $client->search($searchParams);

            $resultIds = [];
            if (isset($response['hits']['hits'])) {
                foreach ($response['hits']['hits'] as $hit) {
                    $resultIds[] = $hit['_id'];
                }
            }

            if (! empty($resultIds)) {
                Cache::put($cacheKey, $resultIds, now()->addMinutes(self::CACHE_TTL_MINUTES));
            }

            return $resultIds; // @phpstan-ignore return.type

        } catch (\Throwable $e) {
            Log::error('ElasticSearch searchReleasesByExternalId error: '.$e->getMessage(), [
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

        $cacheKey = 'es:releases:cat:'.md5(serialize($validCategoryIds).':'.$limit);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $client = $this->getClient();

            $searchParams = [
                'index' => $this->getReleasesIndex(),
                'body' => [
                    'query' => [
                        'terms' => [
                            'categories_id' => array_map('intval', $validCategoryIds),
                        ],
                    ],
                    'size' => min($limit, self::MAX_RESULTS),
                    '_source' => false,
                ],
            ];

            $response = $client->search($searchParams);

            $resultIds = [];
            if (isset($response['hits']['hits'])) {
                foreach ($response['hits']['hits'] as $hit) {
                    $resultIds[] = $hit['_id'];
                }
            }

            if (! empty($resultIds)) {
                Cache::put($cacheKey, $resultIds, now()->addMinutes(self::CACHE_TTL_MINUTES));
            }

            return $resultIds; // @phpstan-ignore return.type

        } catch (\Throwable $e) {
            Log::error('ElasticSearch searchReleasesByCategory error: '.$e->getMessage(), [
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
     * @return array<string, mixed>
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

        $cacheKey = 'es:releases:search_cat:'.md5($searchTerm.':'.serialize($validCategoryIds).':'.$limit);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $client = $this->getClient();

            $query = [
                'bool' => [
                    'must' => [
                        [
                            'multi_match' => [
                                'query' => $searchTerm,
                                'fields' => ['searchname^3', 'name^2', 'filename'],
                                'type' => 'best_fields',
                                'fuzziness' => 'AUTO',
                            ],
                        ],
                    ],
                ],
            ];

            // Add category filter if provided
            if (! empty($validCategoryIds)) {
                $query['bool']['filter'] = [
                    'terms' => [
                        'categories_id' => array_map('intval', $validCategoryIds),
                    ],
                ];
            }

            $searchParams = [
                'index' => $this->getReleasesIndex(),
                'body' => [
                    'query' => $query,
                    'size' => min($limit, self::MAX_RESULTS),
                    '_source' => false,
                ],
            ];

            $response = $client->search($searchParams);

            $resultIds = [];
            if (isset($response['hits']['hits'])) {
                foreach ($response['hits']['hits'] as $hit) {
                    $resultIds[] = $hit['_id'];
                }
            }

            if (! empty($resultIds)) {
                Cache::put($cacheKey, $resultIds, now()->addMinutes(self::CACHE_TTL_MINUTES));
            }

            return $resultIds;

        } catch (\Throwable $e) {
            Log::error('ElasticSearch searchReleasesWithCategoryFilter error: '.$e->getMessage(), [
                'searchTerm' => $searchTerm,
                'categoryIds' => $categoryIds,
            ]);
        }

        return [];
    }
}
