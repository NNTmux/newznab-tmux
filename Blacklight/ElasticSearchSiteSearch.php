<?php

namespace Blacklight;

use App\Models\Release;
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
 * Elasticsearch site search service for releases and predb data.
 *
 * Provides search functionality for the releases and predb indices with
 * caching, connection pooling, and automatic reconnection.
 */
class ElasticSearchSiteSearch
{
    private const CACHE_TTL_MINUTES = 5;

    private const SCROLL_TIMEOUT = '30s';

    private const MAX_RESULTS = 10000;

    private const AVAILABILITY_CHECK_CACHE_TTL = 30; // seconds

    private const DEFAULT_TIMEOUT = 10;

    private const DEFAULT_CONNECT_TIMEOUT = 5;

    private const INDEX_RELEASES = 'releases';

    private const INDEX_PREDB = 'predb';

    private static ?Client $client = null;

    private static ?bool $availabilityCache = null;

    private static ?int $availabilityCacheTime = null;

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

                $config = config('elasticsearch.connections.default');

                if (empty($config)) {
                    throw new RuntimeException('Elasticsearch configuration not found');
                }

                $clientBuilder = ClientBuilder::create();
                $hosts = $this->buildHostsArray($config['hosts'] ?? []);

                if (empty($hosts)) {
                    throw new RuntimeException('No Elasticsearch hosts configured');
                }

                Log::debug('Elasticsearch client initializing', [
                    'hosts' => $hosts,
                ]);

                $clientBuilder->setHosts($hosts);
                $clientBuilder->setHandler(new CurlHandler);
                $clientBuilder->setConnectionParams([
                    'timeout' => config('elasticsearch.connections.default.timeout', self::DEFAULT_TIMEOUT),
                    'connect_timeout' => config('elasticsearch.connections.default.connect_timeout', self::DEFAULT_CONNECT_TIMEOUT),
                ]);

                // Enable retries for better resilience
                $clientBuilder->setRetries(2);

                self::$client = $clientBuilder->build();

                Log::debug('Elasticsearch client initialized successfully');

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

            Log::debug('Elasticsearch ping result', ['available' => $result]);

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
     * Search releases index.
     *
     * @param  array|string  $phrases  Search phrases
     * @param  int  $limit  Maximum number of results
     * @return array|Collection Array of release IDs
     */
    public function indexSearch(array|string $phrases, int $limit): array|Collection
    {
        if (empty($phrases) || ! $this->isElasticsearchAvailable()) {
            Log::debug('ElasticSearch indexSearch: empty phrases or ES not available', [
                'phrases_empty' => empty($phrases),
                'es_available' => $this->isElasticsearchAvailable(),
            ]);
            return [];
        }

        $keywords = $this->sanitizeSearchTerms($phrases);

        Log::debug('ElasticSearch indexSearch: sanitized keywords', [
            'original' => is_array($phrases) ? implode(' ', $phrases) : $phrases,
            'sanitized' => $keywords,
        ]);

        if (empty($keywords)) {
            Log::debug('ElasticSearch indexSearch: keywords empty after sanitization');
            return [];
        }

        $cacheKey = $this->buildCacheKey('index_search', [$keywords, $limit]);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $search = $this->buildSearchQuery(
                index: self::INDEX_RELEASES,
                keywords: $keywords,
                fields: ['searchname^2', 'plainsearchname^1.5', 'fromname', 'filename', 'name^1.2'],
                limit: $limit
            );

            $result = $this->executeSearch($search);
            Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));

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
                index: self::INDEX_RELEASES,
                keywords: $keywords,
                fields: ['searchname^2', 'plainsearchname^1.5', 'fromname', 'filename', 'name^1.2'],
                limit: $limit
            );

            $result = $this->executeSearch($search);
            Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));

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
                index: self::INDEX_RELEASES,
                keywords: $keywords,
                fields: ['searchname^2', 'plainsearchname^1.5'],
                limit: $limit,
                options: [
                    'boost' => 1.2,
                ]
            );

            $result = $this->executeSearch($search);
            Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));

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
                index: self::INDEX_PREDB,
                keywords: $keywords,
                fields: ['title^2', 'filename'],
                limit: 1000,
                options: [
                    'fuzziness' => 'AUTO',
                ],
                includeDateSort: false
            );

            $result = $this->executeSearch($search, fullResults: true);
            Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));

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
                    '_index' => self::INDEX_RELEASES,
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
     * @param  int  $id  Release ID
     */
    public function updateRelease(int $id): void
    {
        if (empty($id)) {
            Log::warning('ElasticSearch: Cannot update release without ID');

            return;
        }

        if (! $this->isElasticsearchAvailable()) {
            return;
        }

        try {
            $release = Release::query()
                ->where('releases.id', $id)
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
                Log::warning('ElasticSearch: Release not found for update', ['id' => $id]);

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
                'index' => self::INDEX_RELEASES,
                'id' => $release->id,
            ];

            $client = $this->getClient();
            $client->update($data);

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch updateRelease error: '.$e->getMessage(), [
                'release_id' => $id,
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch updateRelease unexpected error: '.$e->getMessage(), [
                'release_id' => $id,
            ]);
        }
    }

    /**
     * Search predb (non-scrolling version for direct API use).
     *
     * @param  mixed  $searchTerm  Search term
     * @return array Raw Elasticsearch hits
     */
    public function searchPreDb(mixed $searchTerm): array
    {
        if (empty($searchTerm) || ! $this->isElasticsearchAvailable()) {
            return [];
        }

        $keywords = $this->sanitizeSearchTerms($searchTerm);
        if (empty($keywords)) {
            return [];
        }

        $search = [
            'index' => self::INDEX_PREDB,
            'body' => [
                'query' => [
                    'query_string' => [
                        'query' => $keywords,
                        'fields' => ['title^2', 'filename'],
                        'analyze_wildcard' => true,
                        'default_operator' => 'and',
                        'fuzziness' => 'AUTO',
                    ],
                ],
                'size' => 100,
                'sort' => [
                    ['_score' => ['order' => 'desc']],
                ],
            ],
        ];

        try {
            $client = $this->getClient();
            $results = $client->search($search);

            return $results['hits']['hits'] ?? [];

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch searchPreDb error: '.$e->getMessage(), [
                'search_term' => $searchTerm,
            ]);

            return [];
        }
    }

    /**
     * Insert a predb record into the index.
     *
     * @param  array  $parameters  Predb data with 'id', 'title', 'source', 'filename'
     */
    public function insertPreDb(array $parameters): void
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
                'index' => self::INDEX_PREDB,
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
                'index' => self::INDEX_PREDB,
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
                'index' => self::INDEX_RELEASES,
                'id' => $id,
            ]);

        } catch (Missing404Exception $e) {
            // Document already deleted, not an error
            Log::debug('ElasticSearch deleteRelease: document not found', ['release_id' => $id]);
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
                'index' => self::INDEX_PREDB,
                'id' => $id,
            ]);

        } catch (Missing404Exception $e) {
            Log::debug('ElasticSearch deletePreDb: document not found', ['predb_id' => $id]);
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
            'index' => self::INDEX_RELEASES,
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
            Log::debug('ElasticSearch executing search', [
                'index' => $search['index'] ?? 'unknown',
                'query' => $search['body']['query'] ?? [],
            ]);

            $results = $client->search($search);

            // Log the number of hits
            $totalHits = $results['hits']['total']['value'] ?? $results['hits']['total'] ?? 0;
            Log::debug('ElasticSearch search results', [
                'total_hits' => $totalHits,
                'returned_hits' => count($results['hits']['hits'] ?? []),
            ]);

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
            Log::debug('Failed to clear scroll context: '.$e->getMessage());
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
