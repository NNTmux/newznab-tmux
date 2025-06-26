<?php

namespace Blacklight;

use App\Models\Release;
use Elasticsearch;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use GuzzleHttp\Ring\Client\CurlHandler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ElasticSearchSiteSearch
{
    private const CACHE_TTL_MINUTES = 5;
    private const SCROLL_TIMEOUT = '30s';
    private const MAX_RESULTS = 10000;

    private static $client = null;

    /**
     * Get or create Elasticsearch client with proper cURL configuration
     */
    private function getClient()
    {
        if (self::$client === null) {
            try {
                // Check if cURL extension is loaded
                if (!extension_loaded('curl')) {
                    throw new \RuntimeException('cURL extension is not loaded');
                }

                $config = config('elasticsearch.connections.default');

                $clientBuilder = ClientBuilder::create();

                // Build hosts array
                $hosts = [];
                foreach ($config['hosts'] as $host) {
                    $hostConfig = [
                        'host' => $host['host'],
                        'port' => $host['port'] ?? 9200,
                    ];

                    if (!empty($host['scheme'])) {
                        $hostConfig['scheme'] = $host['scheme'];
                    }

                    if (!empty($host['user']) && !empty($host['pass'])) {
                        $hostConfig['user'] = $host['user'];
                        $hostConfig['pass'] = $host['pass'];
                    }

                    $hosts[] = $hostConfig;
                }

                $clientBuilder->setHosts($hosts);

                // Explicitly set cURL handler
                $clientBuilder->setHandler(new CurlHandler());

                // Set connection timeout and other options
                $clientBuilder->setConnectionParams([
                    'timeout' => 10,
                    'connect_timeout' => 5,
                ]);

                self::$client = $clientBuilder->build();

            } catch (\Throwable $e) {
                Log::error('Failed to initialize Elasticsearch client: ' . $e->getMessage());
                throw new \RuntimeException('Elasticsearch client initialization failed: ' . $e->getMessage());
            }
        }

        return self::$client;
    }

    /**
     * Check if Elasticsearch is available
     */
    private function isElasticsearchAvailable(): bool
    {
        try {
            $client = $this->getClient();
            $client->ping();
            return true;
        } catch (\Throwable $e) {
            Log::warning('Elasticsearch is not available: ' . $e->getMessage());
            return false;
        }
    }

    public function indexSearch(array|string $phrases, int $limit): array|Collection
    {
        if (empty($phrases) || !$this->isElasticsearchAvailable()) {
            return [];
        }

        $keywords = $this->sanitizeSearchTerms($phrases);
        if (empty($keywords)) {
            return [];
        }

        // Create cache key
        $cacheKey = md5('es_index_search_' . serialize([$keywords, $limit]));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $search = [
                'scroll' => self::SCROLL_TIMEOUT,
                'index' => 'releases',
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => $keywords,
                            'fields' => ['searchname^2', 'plainsearchname^1.5', 'fromname', 'filename', 'name^1.2', 'categories_id'],
                            'analyze_wildcard' => true,
                            'default_operator' => 'and',
                            'fuzziness' => 'AUTO',
                            'minimum_should_match' => '75%'
                        ],
                    ],
                    'size' => min($limit, self::MAX_RESULTS),
                    'sort' => [
                        ['_score' => ['order' => 'desc']],
                        ['add_date' => ['order' => 'desc']],
                        ['post_date' => ['order' => 'desc']]
                    ],
                    '_source' => ['id']
                ],
            ];

            $result = $this->search($search);
            Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));
            return $result;

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch indexSearch error: ' . $e->getMessage(), [
                'keywords' => $keywords,
                'limit' => $limit
            ]);
            return [];
        }
    }

    public function indexSearchApi(array|string $searchName, int $limit): array|Collection
    {
        if (empty($searchName) || !$this->isElasticsearchAvailable()) {
            return [];
        }

        $keywords = $this->sanitizeSearchTerms($searchName);
        if (empty($keywords)) {
            return [];
        }

        $cacheKey = md5('es_api_search_' . serialize([$keywords, $limit]));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $search = [
                'scroll' => self::SCROLL_TIMEOUT,
                'index' => 'releases',
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => $keywords,
                            'fields' => ['searchname^2', 'plainsearchname^1.5', 'fromname', 'filename', 'name^1.2'],
                            'analyze_wildcard' => true,
                            'default_operator' => 'and',
                            'fuzziness' => 'AUTO'
                        ],
                    ],
                    'size' => min($limit, self::MAX_RESULTS),
                    'sort' => [
                        ['_score' => ['order' => 'desc']],
                        ['add_date' => ['order' => 'desc']],
                        ['post_date' => ['order' => 'desc']]
                    ],
                    '_source' => ['id']
                ],
            ];

            $result = $this->search($search);
            Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));
            return $result;

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch indexSearchApi error: ' . $e->getMessage(), [
                'keywords' => $keywords,
                'limit' => $limit
            ]);
            return [];
        }
    }

    public function indexSearchTMA(array|string $name, int $limit): array|Collection
    {
        if (empty($name) || !$this->isElasticsearchAvailable()) {
            return [];
        }

        $keywords = $this->sanitizeSearchTerms($name);
        if (empty($keywords)) {
            return [];
        }

        $cacheKey = md5('es_tma_search_' . serialize([$keywords, $limit]));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $search = [
                'scroll' => self::SCROLL_TIMEOUT,
                'index' => 'releases',
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => $keywords,
                            'fields' => ['searchname^2', 'plainsearchname^1.5'],
                            'analyze_wildcard' => true,
                            'default_operator' => 'and',
                            'boost' => 1.2
                        ],
                    ],
                    'size' => min($limit, self::MAX_RESULTS),
                    'sort' => [
                        ['_score' => ['order' => 'desc']],
                        ['add_date' => ['order' => 'desc']],
                        ['post_date' => ['order' => 'desc']]
                    ],
                    '_source' => ['id']
                ],
            ];

            $result = $this->search($search);
            Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));
            return $result;

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch indexSearchTMA error: ' . $e->getMessage(), [
                'keywords' => $keywords,
                'limit' => $limit
            ]);
            return [];
        }
    }

    public function predbIndexSearch(array|string $searchTerm): array|Collection
    {
        if (empty($searchTerm) || !$this->isElasticsearchAvailable()) {
            return [];
        }

        $keywords = $this->sanitizeSearchTerms($searchTerm);
        if (empty($keywords)) {
            return [];
        }

        $cacheKey = md5('es_predb_search_' . $keywords);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $search = [
                'scroll' => self::SCROLL_TIMEOUT,
                'index' => 'predb',
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => $keywords,
                            'fields' => ['title^2', 'filename'],
                            'analyze_wildcard' => true,
                            'default_operator' => 'and',
                            'fuzziness' => 'AUTO'
                        ],
                    ],
                    'size' => 1000,
                    'sort' => [
                        ['_score' => ['order' => 'desc']]
                    ]
                ],
            ];

            $result = $this->search($search, true);
            Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));
            return $result;

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch predbIndexSearch error: ' . $e->getMessage(), [
                'keywords' => $keywords
            ]);
            return [];
        }
    }

    public function insertRelease(array $parameters): void
    {
        if (empty($parameters['id']) || !$this->isElasticsearchAvailable()) {
            if (empty($parameters['id'])) {
                Log::warning('ElasticSearch: Cannot insert release without ID');
            }
            return;
        }

        try {
            $searchNameDotless = str_replace(['.', '-'], ' ', $parameters['searchname'] ?? '');
            $data = [
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
                'index' => 'releases',
                'id' => $parameters['id'],
            ];

            $client = $this->getClient();
            $client->index($data);

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch insertRelease error: ' . $e->getMessage(), [
                'release_id' => $parameters['id']
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch insertRelease unexpected error: ' . $e->getMessage(), [
                'release_id' => $parameters['id']
            ]);
        }
    }

    public function updateRelease(int $id): void
    {
        if (empty($id)) {
            Log::warning('ElasticSearch: Cannot update release without ID');
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
                    DB::raw('IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename')
                ])
                ->groupBy('releases.id')
                ->first();

            if ($release === null) {
                Log::warning('ElasticSearch: Release not found for update', ['id' => $id]);
                return;
            }

            $searchNameDotless = str_replace(['.', '-'], ' ', $release->searchname);
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
                'index' => 'releases',
                'id' => $release->id,
            ];

            Elasticsearch::update($data);

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch updateRelease error: ' . $e->getMessage(), [
                'release_id' => $id
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch updateRelease unexpected error: ' . $e->getMessage(), [
                'release_id' => $id
            ]);
        }
    }

    public function searchPreDb($searchTerm): array
    {
        if (empty($searchTerm)) {
            return [];
        }

        $keywords = $this->sanitizeSearchTerms($searchTerm);
        if (empty($keywords)) {
            return [];
        }

        $search = [
            'index' => 'predb',
            'body' => [
                'query' => [
                    'query_string' => [
                        'query' => $keywords,
                        'fields' => ['title^2', 'filename'],
                        'analyze_wildcard' => true,
                        'default_operator' => 'and',
                        'fuzziness' => 'AUTO'
                    ],
                ],
                'size' => 100,
                'sort' => [
                    ['_score' => ['order' => 'desc']]
                ]
            ],
        ];

        try {
            $primaryResults = Elasticsearch::search($search);
            return $primaryResults['hits']['hits'] ?? [];

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch searchPreDb error: ' . $e->getMessage(), [
                'search_term' => $searchTerm
            ]);
            return [];
        }
    }

    public function insertPreDb(array $parameters): void
    {
        if (empty($parameters['id'])) {
            Log::warning('ElasticSearch: Cannot insert predb without ID');
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
                'index' => 'predb',
                'id' => $parameters['id'],
            ];

            Elasticsearch::index($data);

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch insertPreDb error: ' . $e->getMessage(), [
                'predb_id' => $parameters['id']
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch insertPreDb unexpected error: ' . $e->getMessage(), [
                'predb_id' => $parameters['id']
            ]);
        }
    }

    public function updatePreDb(array $parameters): void
    {
        if (empty($parameters['id'])) {
            Log::warning('ElasticSearch: Cannot update predb without ID');
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
                'index' => 'predb',
                'id' => $parameters['id'],
            ];

            Elasticsearch::update($data);

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch updatePreDb error: ' . $e->getMessage(), [
                'predb_id' => $parameters['id']
            ]);
        } catch (\Throwable $e) {
            Log::error('ElasticSearch updatePreDb unexpected error: ' . $e->getMessage(), [
                'predb_id' => $parameters['id']
            ]);
        }
    }

    /**
     * Sanitize search terms for Elasticsearch
     */
    private function sanitizeSearchTerms(array|string $terms): string
    {
        if (is_array($terms)) {
            $terms = implode(' ', array_filter($terms));
        }

        if (function_exists('sanitize')) {
            return sanitize($terms);
        }

        // Fallback sanitization if sanitize function doesn't exist
        $terms = trim($terms);
        if (empty($terms)) {
            return '';
        }

        // Remove dangerous characters but keep wildcards
        $terms = preg_replace('/[<>"\'\x00-\x1f\x7f]/', '', $terms);

        // Escape special Elasticsearch characters except wildcards
        $terms = preg_replace('/([+\-={}[\]^~():"\\\\])/', '\\\\$1', $terms);

        return $terms;
    }

    protected function search(array $search, bool $fullResults = false): array
    {
        if (empty($search) || !$this->isElasticsearchAvailable()) {
            return [];
        }

        try {
            $client = $this->getClient();
            $results = $client->search($search);
            $searchResult = [];

            while (isset($results['hits']['hits']) && count($results['hits']['hits']) > 0) {
                foreach ($results['hits']['hits'] as $result) {
                    if ($fullResults === true) {
                        $searchResult[] = $result['_source'];
                    } else {
                        $searchResult[] = $result['_source']['id'] ?? $result['_id'];
                    }
                }

                // Handle scrolling for large result sets
                if (!isset($results['_scroll_id'])) {
                    break;
                }

                $scroll_id = $results['_scroll_id'];
                $results = $client->scroll([
                    'scroll_id' => $scroll_id,
                    'scroll' => self::SCROLL_TIMEOUT,
                ]);
            }

            return $searchResult;

        } catch (ElasticsearchException $e) {
            Log::error('ElasticSearch search error: ' . $e->getMessage());
            return [];
        } catch (\Throwable $e) {
            Log::error('ElasticSearch search unexpected error: ' . $e->getMessage());
            return [];
        }
    }
}
