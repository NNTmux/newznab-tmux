<?php

declare(strict_types=1);

namespace App\Services\Search\Drivers;

use App\Enums\SecondarySearchIndex;
use App\Models\BookInfo;
use App\Models\ConsoleInfo;
use App\Models\GamesInfo;
use App\Models\MovieInfo;
use App\Models\MusicInfo;
use App\Models\Release;
use App\Models\SteamApp;
use App\Models\Video;
use App\Services\Search\Contracts\SearchDriverInterface;
use App\Services\Search\Support\ElasticsearchClientFactory;
use App\Services\Search\Support\ElasticsearchResponseHelper;
use App\Support\SecondaryIndexDocuments;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
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
                if (empty($this->config)) {
                    throw new RuntimeException('Elasticsearch configuration not found');
                }

                if (config('app.debug')) {
                    Log::debug('Elasticsearch client initializing', [
                        'hosts' => ElasticsearchClientFactory::buildHosts($this->config['hosts'] ?? []),
                    ]);
                }

                self::$client = ElasticsearchClientFactory::make($this->config);

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
            $result = ElasticsearchResponseHelper::boolResponse($client, fn (Client $elasticClient) => $elasticClient->ping());

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
                ->leftJoin('movieinfo as mi', 'releases.movieinfo_id', '=', 'mi.id')
                ->leftJoin('videos as v', 'releases.videos_id', '=', 'v.id')
                ->select([
                    'releases.id',
                    'releases.name',
                    'releases.searchname',
                    'releases.fromname',
                    'releases.categories_id',
                    'releases.size',
                    'releases.postdate',
                    'releases.adddate',
                    'releases.totalpart',
                    'releases.grabs',
                    'releases.passwordstatus',
                    'releases.groups_id',
                    'releases.nzbstatus',
                    'releases.haspreview',
                    'releases.imdbid',
                    'releases.videos_id',
                    'releases.movieinfo_id',
                    DB::raw('IFNULL(mi.tmdbid, 0) AS tmdbid'),
                    DB::raw('IFNULL(mi.traktid, 0) AS traktid'),
                    DB::raw('IFNULL(v.tvdb, 0) AS tvdb'),
                    DB::raw('IFNULL(v.tvmaze, 0) AS tvmaze'),
                    DB::raw('IFNULL(v.tvrage, 0) AS tvrage'),
                    DB::raw('IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename'),
                ])
                ->groupBy([
                    'releases.id',
                    'releases.name',
                    'releases.searchname',
                    'releases.fromname',
                    'releases.categories_id',
                    'releases.size',
                    'releases.postdate',
                    'releases.adddate',
                    'releases.totalpart',
                    'releases.grabs',
                    'releases.passwordstatus',
                    'releases.groups_id',
                    'releases.nzbstatus',
                    'releases.haspreview',
                    'releases.imdbid',
                    'releases.videos_id',
                    'releases.movieinfo_id',
                    'mi.tmdbid',
                    'mi.traktid',
                    'v.tvdb',
                    'v.tvmaze',
                    'v.tvrage',
                ])
                ->first();

            if ($release === null) {
                Log::warning('ElasticSearch: Release not found for update', ['id' => $releaseID]);

                return;
            }

            $client = $this->getClient();
            $client->index($this->buildReleaseDocument($release->toArray()));

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

        } catch (\Throwable $e) {
            if (ElasticsearchResponseHelper::isNotFound($e)) {
                if (config('app.debug')) {
                    Log::debug('ElasticSearch deleteRelease: document not found', ['release_id' => $id]);
                }

                return;
            }

            if ($e instanceof ElasticsearchException) {
                Log::error('ElasticSearch deleteRelease error: '.$e->getMessage(), [
                    'release_id' => $id,
                ]);

                return;
            }

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

        } catch (\Throwable $e) {
            if (ElasticsearchResponseHelper::isNotFound($e)) {
                if (config('app.debug')) {
                    Log::debug('ElasticSearch deletePreDb: document not found', ['predb_id' => $id]);
                }

                return;
            }

            if ($e instanceof ElasticsearchException) {
                Log::error('ElasticSearch deletePreDb error: '.$e->getMessage(), [
                    'predb_id' => $id,
                ]);

                return;
            }

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

            return ElasticsearchResponseHelper::boolResponse($client, fn (Client $elasticClient) => $elasticClient->indices()->exists(['index' => $index]));
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
                if (! ElasticsearchResponseHelper::boolResponse($client, fn (Client $elasticClient) => $elasticClient->indices()->exists(['index' => $index]))) {
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

            return ElasticsearchResponseHelper::asArray($client->cluster()->health());
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

            return ElasticsearchResponseHelper::asArray($client->indices()->stats(['index' => $index]));
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
        bool $includeDateSort = true,
        bool $useScroll = false
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

        $search = [
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

        if ($useScroll) {
            $search['scroll'] = self::SCROLL_TIMEOUT;
        }

        return $search;
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
                'imdbid' => (string) ($parameters['imdbid'] ?? ''),
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
        $useScroll = isset($search['scroll']) && is_string($search['scroll']) && $search['scroll'] !== '';

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

            foreach ($results['hits']['hits'] ?? [] as $result) {
                if ($fullResults) {
                    $searchResult[] = $result['_source'];
                } else {
                    $searchResult[] = $result['_source']['id'] ?? $result['_id'];
                }
            }

            if (! $useScroll) {
                return $searchResult;
            }

            while (isset($results['hits']['hits']) && count($results['hits']['hits']) > 0) {

                // Handle scrolling for large result sets
                if (! isset($results['_scroll_id'])) {
                    break;
                }

                $scrollId = $results['_scroll_id'];
                $results = $client->scroll([
                    'scroll_id' => $scrollId,
                    'scroll' => self::SCROLL_TIMEOUT,
                ]);

                foreach ($results['hits']['hits'] ?? [] as $result) {
                    if ($fullResults) {
                        $searchResult[] = $result['_source'];
                    } else {
                        $searchResult[] = $result['_source']['id'] ?? $result['_id'];
                    }
                }
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
                'imdbid' => (string) ($parameters['imdbid'] ?? ''),
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
        } catch (\Throwable $e) {
            if (ElasticsearchResponseHelper::isNotFound($e)) {
                return;
            }

            if ($e instanceof ElasticsearchException) {
                Log::error('ElasticSearch deleteMovie error: '.$e->getMessage(), [
                    'id' => $id,
                ]);
            }
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
                'imdbid' => (string) ($movie['imdbid'] ?? ''),
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
     * {@inheritdoc}
     */
    public function searchMoviesByFields(array $fieldTerms, int $limit = 5000): array
    {
        $allowed = ['title' => true, 'director' => true, 'actors' => true, 'genre' => true];
        $filtered = [];
        foreach ($fieldTerms as $key => $value) {
            $k = (string) $key;
            if (isset($allowed[$k]) && is_string($value) && trim($value) !== '') {
                $filtered[$k] = trim($value);
            }
        }

        if ($filtered === [] || ! $this->isElasticsearchAvailable()) {
            return ['imdbids' => [], 'movieinfo_ids' => [], 'data' => []];
        }

        $must = [];
        foreach ($filtered as $field => $value) {
            $must[] = [
                'match' => [
                    $field => [
                        'query' => $value,
                        'operator' => 'and',
                    ],
                ],
            ];
        }

        try {
            $client = $this->getClient();
            $response = $client->search([
                'index' => $this->getMoviesIndex(),
                'body' => [
                    'query' => ['bool' => ['must' => $must]],
                    'size' => min($limit, self::MAX_RESULTS),
                ],
            ]);

            $imdbids = [];
            $movieinfoIds = [];
            $data = [];

            foreach ($response['hits']['hits'] ?? [] as $hit) {
                $movieinfoIds[] = (int) ($hit['_id'] ?? 0);
                $src = $hit['_source'] ?? [];
                $data[] = $src;
                $imdb = (string) ($src['imdbid'] ?? '');
                if ($imdb !== '') {
                    $imdbids[] = $imdb;
                }
            }

            return [
                'imdbids' => array_values(array_unique($imdbids)),
                'movieinfo_ids' => $movieinfoIds,
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('ElasticSearch searchMoviesByFields error: '.$e->getMessage());

            return ['imdbids' => [], 'movieinfo_ids' => [], 'data' => []];
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
        if (empty($value)) {
            return null;
        }

        return $this->searchMovieByExternalIds([$field => $value]);
    }

    /**
     * @param  array<string, mixed>  $externalIds
     * @return array<string, mixed>|null
     */
    public function searchMovieByExternalIds(array $externalIds): ?array
    {
        $allowedFields = ['imdbid', 'tmdbid', 'traktid'];
        $shouldClauses = [];
        $cacheableValues = [];

        foreach ($allowedFields as $field) {
            $value = $externalIds[$field] ?? null;
            if (empty($value)) {
                continue;
            }

            $typedValue = $field === 'imdbid' ? (string) $value : (int) $value;
            if ($typedValue === '' || $typedValue === 0) {
                continue;
            }

            $shouldClauses[] = ['term' => [$field => $typedValue]];
            $cacheableValues[$field] = $typedValue;
        }

        if ($shouldClauses === []) {
            return null;
        }

        $cacheKey = 'es:movie:any:'.md5(serialize($cacheableValues));
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
                        'bool' => [
                            'should' => $shouldClauses,
                            'minimum_should_match' => 1,
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
            Log::error('ElasticSearch searchMovieByExternalIds error: '.$e->getMessage(), [
                'externalIds' => $externalIds,
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
                'imdb' => (string) ($parameters['imdb'] ?? ''),
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
        } catch (\Throwable $e) {
            if (ElasticsearchResponseHelper::isNotFound($e)) {
                return;
            }

            if ($e instanceof ElasticsearchException) {
                Log::error('ElasticSearch deleteTvShow error: '.$e->getMessage(), [
                    'id' => $id,
                ]);
            }
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
                'imdb' => (string) ($tvShow['imdb'] ?? ''),
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
        if (empty($value)) {
            return null;
        }

        return $this->searchTvShowByExternalIds([$field => $value]);
    }

    /**
     * @param  array<string, mixed>  $externalIds
     * @return array<string, mixed>|null
     */
    public function searchTvShowByExternalIds(array $externalIds): ?array
    {
        $allowedFields = ['tvdb', 'trakt', 'tvmaze', 'tvrage', 'imdb', 'tmdb'];
        $shouldClauses = [];
        $cacheableValues = [];

        foreach ($allowedFields as $field) {
            $value = $externalIds[$field] ?? null;
            if (empty($value)) {
                continue;
            }

            $typedValue = $field === 'imdb' ? (string) $value : (int) $value;
            if ($typedValue === '' || $typedValue === 0) {
                continue;
            }

            $shouldClauses[] = ['term' => [$field => $typedValue]];
            $cacheableValues[$field] = $typedValue;
        }

        if ($shouldClauses === []) {
            return null;
        }

        $cacheKey = 'es:tvshow:any:'.md5(serialize($cacheableValues));
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
                        'bool' => [
                            'should' => $shouldClauses,
                            'minimum_should_match' => 1,
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
            Log::error('ElasticSearch searchTvShowByExternalIds error: '.$e->getMessage(), [
                'externalIds' => $externalIds,
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
        return $this->searchReleasesByMultipleExternalIds([$externalIds], $limit);
    }

    /**
     * @param  array<int, array<string, mixed>>  $externalIdSets
     * @return array<string, mixed>
     */
    public function searchReleasesByMultipleExternalIds(array $externalIdSets, int $limit = 1000): array
    {
        if ($externalIdSets === []) {
            return [];
        }

        $shouldClauses = [];
        $cachePayload = [];

        foreach ($externalIdSets as $externalIds) {
            $setClauses = $this->buildReleaseExternalIdShouldClauses($externalIds);
            if ($setClauses === []) {
                continue;
            }

            $shouldClauses[] = [
                'bool' => [
                    'should' => $setClauses,
                    'minimum_should_match' => 1,
                ],
            ];
            $cachePayload[] = $externalIds;
        }

        if ($shouldClauses === []) {
            return [];
        }

        $cacheKey = 'es:releases:extid_sets:'.md5(serialize([$cachePayload, $limit]));
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
            foreach ($response['hits']['hits'] ?? [] as $hit) {
                $resultIds[] = $hit['_id'];
            }

            if ($resultIds !== []) {
                Cache::put($cacheKey, $resultIds, now()->addMinutes(self::CACHE_TTL_MINUTES));
            }

            return $resultIds; // @phpstan-ignore return.type
        } catch (\Throwable $e) {
            Log::error('ElasticSearch searchReleasesByMultipleExternalIds error: '.$e->getMessage(), [
                'externalIdSets' => $externalIdSets,
            ]);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $externalIds
     * @return list<array<string, mixed>>
     */
    private function buildReleaseExternalIdShouldClauses(array $externalIds): array
    {
        $clauses = [];
        foreach ($externalIds as $field => $value) {
            if (empty($value) || ! in_array($field, ['imdbid', 'tmdbid', 'traktid', 'tvdb', 'tvmaze', 'tvrage'], true)) {
                continue;
            }

            $clauses[] = ['term' => [$field => $field === 'imdbid' ? (string) $value : (int) $value]];
        }

        return $clauses;
    }

    /**
     * Search releases by category ID using the search index.
     * This provides a fast way to get release IDs for a specific category without hitting the database.
     *
     * @param  array<string, mixed>  $categoryIds  Array of category IDs to filter by
     * @param  int  $limit  Maximum number of results
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

    /**
     * {@inheritdoc}
     */
    public function searchReleasesFiltered(array $criteria, int $limit, int $offset = 0): array
    {
        if (! $this->isElasticsearchAvailable()) {
            return ['ids' => [], 'total' => 0, 'fuzzy' => false];
        }

        $phrases = $criteria['phrases'] ?? null;
        $hasText = $phrases !== null && $phrases !== '' && $phrases !== -1;
        $tryFuzzy = (bool) ($criteria['try_fuzzy'] ?? true);

        $run = function (bool $useFuzzy) use ($criteria, $limit, $offset, $hasText, $phrases): array {
            $filter = $this->buildElasticsearchReleaseFilters($criteria);
            $must = [];

            if ($hasText) {
                $text = is_string($phrases)
                    ? $phrases
                    : implode(' ', array_values(array_filter(
                        is_array($phrases) ? $phrases : [],
                        static fn ($v): bool => $v !== null && $v !== '' && $v !== -1
                    )));
                if ($text === '') {
                    return ['ids' => [], 'total' => 0, 'fuzzy' => $useFuzzy];
                }
                $multi = [
                    'query' => $text,
                    'fields' => ['searchname^3', 'name^2', 'filename', 'plainsearchname'],
                    'type' => 'best_fields',
                ];
                if ($useFuzzy && $this->isFuzzyEnabled()) {
                    $multi['fuzziness'] = $this->getFuzzyConfig()['fuzziness'] ?? 'AUTO';
                }
                $must[] = ['multi_match' => $multi];
            }

            if ($must !== [] && $filter !== []) {
                $query = ['bool' => ['must' => $must, 'filter' => $filter]];
            } elseif ($must !== []) {
                $query = ['bool' => ['must' => $must]];
            } elseif ($filter !== []) {
                $query = ['bool' => ['filter' => $filter]];
            } else {
                $query = ['match_all' => new \stdClass];
            }

            $sortField = (string) ($criteria['sort_field'] ?? 'postdate_ts');
            $sortDir = strtolower((string) ($criteria['sort_dir'] ?? 'desc'));
            $allowedSort = ['postdate_ts', 'adddate_ts', 'size', 'totalpart', 'grabs', 'categories_id', 'id'];
            if (! in_array($sortField, $allowedSort, true)) {
                $sortField = 'postdate_ts';
            }
            if ($sortField === 'id') {
                $sortField = 'postdate_ts';
            }
            $order = $sortDir === 'asc' ? 'asc' : 'desc';

            try {
                $client = $this->getClient();
                $response = $client->search([
                    'index' => $this->getReleasesIndex(),
                    'body' => [
                        'query' => $query,
                        'sort' => [
                            [$sortField => ['order' => $order]],
                        ],
                        'from' => max(0, $offset),
                        'size' => max(1, min($limit, self::MAX_RESULTS)),
                        'track_total_hits' => true,
                        '_source' => false,
                    ],
                ]);

                $ids = [];
                foreach ($response['hits']['hits'] ?? [] as $hit) {
                    $ids[] = (int) ($hit['_id'] ?? 0);
                }
                $total = (int) ($response['hits']['total']['value'] ?? $response['hits']['total'] ?? 0);

                return ['ids' => $ids, 'total' => $total, 'fuzzy' => $useFuzzy];
            } catch (\Throwable $e) {
                Log::error('ElasticSearch searchReleasesFiltered error: '.$e->getMessage());

                return ['ids' => [], 'total' => 0, 'fuzzy' => $useFuzzy];
            }
        };

        if ($hasText) {
            $first = $run(false);
            if ($first['ids'] !== [] || ! $tryFuzzy || ! $this->isFuzzyEnabled()) {
                return $first;
            }

            return $run(true);
        }

        return $run(false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildElasticsearchReleaseFilters(array $criteria): array
    {
        $filter = [];

        $releaseIds = $criteria['release_ids'] ?? null;
        if (is_array($releaseIds) && $releaseIds !== []) {
            $valid = array_values(array_filter(
                array_map(static fn ($id): int => (int) $id, $releaseIds),
                static fn (int $id): bool => $id > 0
            ));
            if ($valid !== []) {
                $filter[] = ['ids' => ['values' => array_map(static fn (int $id): string => (string) $id, $valid)]];
            }
        }

        $categoryIds = $criteria['category_ids'] ?? null;
        if (is_array($categoryIds) && $categoryIds !== []) {
            $valid = array_values(array_filter($categoryIds, static fn ($id): bool => (int) $id > 0));
            if ($valid !== []) {
                $filter[] = ['terms' => ['categories_id' => array_map(static fn ($id): int => (int) $id, $valid)]];
            }
        }

        $excluded = $criteria['excluded_category_ids'] ?? [];
        if ($excluded !== []) {
            $filter[] = ['bool' => ['must_not' => [
                ['terms' => ['categories_id' => array_map(static fn ($id): int => (int) $id, $excluded)]],
            ]]];
        }

        $minSize = (int) ($criteria['min_size'] ?? 0);
        if ($minSize > 0) {
            $filter[] = ['range' => ['size' => ['gte' => $minSize]]];
        }

        $maxSize = (int) ($criteria['max_size'] ?? 0);
        if ($maxSize > 0) {
            $filter[] = ['range' => ['size' => ['lte' => $maxSize]]];
        }

        $maxAge = (int) ($criteria['max_age_days'] ?? 0);
        if ($maxAge > 0) {
            $cutoff = time() - ($maxAge * 86400);
            $filter[] = ['range' => ['postdate_ts' => ['gte' => $cutoff]]];
        }

        $minDate = (int) ($criteria['min_date'] ?? 0);
        if ($minDate > 0) {
            $filter[] = ['range' => ['postdate_ts' => ['gte' => $minDate]]];
        }

        $maxDate = (int) ($criteria['max_date'] ?? 0);
        if ($maxDate > 0) {
            $filter[] = ['range' => ['postdate_ts' => ['lte' => $maxDate]]];
        }

        $gid = $criteria['groups_id'] ?? null;
        if ($gid !== null && (int) $gid > 0) {
            $filter[] = ['term' => ['groups_id' => (int) $gid]];
        }

        $allowRar = (bool) ($criteria['password_allow_rar'] ?? false);
        if ($allowRar) {
            $filter[] = ['range' => ['passwordstatus' => ['lte' => 1]]];
        } else {
            $filter[] = ['term' => ['passwordstatus' => 0]];
        }

        return $filter;
    }

    public function getSecondaryIndexName(SecondarySearchIndex $index): string
    {
        $configured = $this->config['indexes'][$index->value] ?? null;
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return match ($index) {
            SecondarySearchIndex::Music => 'music',
            SecondarySearchIndex::Books => 'books',
            SecondarySearchIndex::Games => 'games',
            SecondarySearchIndex::Console => 'console',
            SecondarySearchIndex::Steam => 'steam',
            SecondarySearchIndex::Anime => 'anime',
        };
    }

    public function insertSecondary(SecondarySearchIndex $index, int $id, array $document): void
    {
        if ($id <= 0) {
            return;
        }

        try {
            $this->getClient()->index([
                'index' => $this->getSecondaryIndexName($index),
                'id' => (string) $id,
                'body' => $document,
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch insertSecondary error: '.$e->getMessage(), [
                'secondary' => $index->value,
                'id' => $id,
            ]);
        }
    }

    public function updateSecondary(SecondarySearchIndex $index, int $id): void
    {
        if ($id <= 0) {
            return;
        }

        try {
            match ($index) {
                SecondarySearchIndex::Music => $this->insertSecondary($index, $id, SecondaryIndexDocuments::music(MusicInfo::query()->findOrFail($id))),
                SecondarySearchIndex::Books => $this->insertSecondary($index, $id, SecondaryIndexDocuments::book(BookInfo::query()->findOrFail($id))),
                SecondarySearchIndex::Games => $this->insertSecondary($index, $id, SecondaryIndexDocuments::games(GamesInfo::query()->findOrFail($id))),
                SecondarySearchIndex::Console => $this->insertSecondary($index, $id, SecondaryIndexDocuments::console(ConsoleInfo::query()->findOrFail($id))),
                SecondarySearchIndex::Steam => $this->insertSecondary($index, $id, SecondaryIndexDocuments::steam(SteamApp::query()->findOrFail($id))),
                SecondarySearchIndex::Anime => null,
            };
        } catch (\Throwable $e) {
            Log::warning('ElasticSearch updateSecondary skipped: '.$e->getMessage(), [
                'secondary' => $index->value,
                'id' => $id,
            ]);
        }
    }

    public function deleteSecondary(SecondarySearchIndex $index, int $id): void
    {
        if ($id <= 0) {
            return;
        }

        try {
            $this->getClient()->delete([
                'index' => $this->getSecondaryIndexName($index),
                'id' => (string) $id,
            ]);
        } catch (\Throwable $e) {
            Log::debug('ElasticSearch deleteSecondary: '.$e->getMessage(), [
                'secondary' => $index->value,
                'id' => $id,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<string, mixed>
     */
    public function bulkInsertSecondary(SecondarySearchIndex $index, array $documents): array
    {
        if ($documents === []) {
            return ['success' => 0, 'errors' => 0];
        }

        $validDocs = [];
        $errors = 0;
        $indexName = $this->getSecondaryIndexName($index);

        foreach ($documents as $doc) {
            $docId = (int) ($doc['id'] ?? 0);
            if ($docId <= 0) {
                $errors++;

                continue;
            }

            unset($doc['id']);
            $validDocs[] = ['id' => $docId, 'body' => $doc];
        }

        if ($validDocs === []) {
            return ['success' => 0, 'errors' => $errors];
        }

        $params = ['body' => []];
        foreach ($validDocs as $doc) {
            $params['body'][] = [
                'index' => [
                    '_index' => $indexName,
                    '_id' => (string) $doc['id'],
                ],
            ];
            $params['body'][] = $doc['body'];
        }

        $success = count($validDocs);
        try {
            $this->executeBulk($params);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch bulkInsertSecondary error: '.$e->getMessage(), ['secondary' => $index->value]);
            $errors += $success;
            $success = 0;
        }

        return ['success' => $success, 'errors' => $errors];
    }

    /**
     * @return array{id: list<int>, data: list<array<string, mixed>>}
     */
    public function searchSecondary(SecondarySearchIndex $index, string $query, int $limit = 100): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['id' => [], 'data' => []];
        }

        $fields = $index->fulltextFields();
        $escapedSearch = self::escapeString($query);
        if ($escapedSearch === '') {
            return ['id' => [], 'data' => []];
        }

        try {
            $response = $this->getClient()->search([
                'index' => $this->getSecondaryIndexName($index),
                'body' => [
                    'query' => [
                        'multi_match' => [
                            'query' => $escapedSearch,
                            'fields' => $fields,
                            'operator' => 'and',
                        ],
                    ],
                    'size' => max(1, min($limit, self::MAX_RESULTS)),
                    '_source' => true,
                ],
            ]);

            $ids = [];
            $data = [];
            foreach ($response['hits']['hits'] ?? [] as $hit) {
                $ids[] = (int) ($hit['_id'] ?? 0);
                $data[] = (array) ($hit['_source'] ?? []);
            }

            return ['id' => $ids, 'data' => $data];
        } catch (\Throwable $e) {
            Log::error('ElasticSearch searchSecondary error: '.$e->getMessage(), ['secondary' => $index->value]);

            return ['id' => [], 'data' => []];
        }
    }

    /**
     * @return list<int>
     */
    public function searchAnimeTitle(string $query, int $limit = 100): array
    {
        $hits = $this->searchSecondary(SecondarySearchIndex::Anime, $query, $limit);
        $seen = [];
        foreach ($hits['data'] as $row) {
            $aid = (int) ($row['anidbid'] ?? 0);
            if ($aid > 0) {
                $seen[$aid] = true;
            }
        }

        return array_keys($seen);
    }
}
