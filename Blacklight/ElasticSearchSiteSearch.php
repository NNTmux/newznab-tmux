<?php

namespace Blacklight;

use App\Models\Release;
use Elasticsearch;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ElasticSearchSiteSearch
{
    public function indexSearch(array|string $phrases, int $limit): array|Collection
    {
        $keywords = sanitize($phrases);

        try {
            $search = [
                'scroll' => '30s',
                'index' => 'releases',
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => $keywords,
                            'fields' => ['searchname', 'plainsearchname', 'fromname', 'filename', 'name', 'categories_id'],
                            'analyze_wildcard' => true,
                            'default_operator' => 'and',
                        ],
                    ],
                    'size' => $limit,
                    'sort' => [
                        'add_date' => [
                            'order' => 'desc',
                        ],
                        'post_date' => [
                            'order' => 'desc',
                        ],
                    ],
                ],
            ];

            return $this->search($search);
        } catch (ElasticsearchException $request400Exception) {
            return [];
        }
    }

    public function indexSearchApi(array|string $searchName, int $limit): array|Collection
    {
        $keywords = sanitize($searchName);
        try {
            $search = [
                'scroll' => '30s',
                'index' => 'releases',
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => $keywords,
                            'fields' => ['searchname', 'plainsearchname', 'fromname', 'filename', 'name', 'categories_id'],
                            'analyze_wildcard' => true,
                            'default_operator' => 'and',
                        ],
                    ],
                    'size' => $limit,
                    'sort' => [
                        'add_date' => [
                            'order' => 'desc',
                        ],
                        'post_date' => [
                            'order' => 'desc',
                        ],
                    ],
                ],
            ];

            return $this->search($search);
        } catch (ElasticsearchException $request400Exception) {
            return [];
        }
    }

    public function indexSearchTMA(array|string $name, int $limit): array|Collection
    {
        $keywords = sanitize($name);
        try {
            $search = [
                'scroll' => '30s',
                'index' => 'releases',
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => $keywords,
                            'fields' => ['searchname', 'plainsearchname'],
                            'analyze_wildcard' => true,
                            'default_operator' => 'and',
                        ],
                    ],
                    'size' => $limit,
                    'sort' => [
                        'add_date' => [
                            'order' => 'desc',
                        ],
                        'post_date' => [
                            'order' => 'desc',
                        ],
                    ],
                ],
            ];

            return $this->search($search);
        } catch (ElasticsearchException $request400Exception) {
            return [];
        }
    }

    public function predbIndexSearch(array|string $search): array|Collection
    {
        try {
            $search = [
                'scroll' => '30s',
                'index' => 'predb',
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => $search,
                            'fields' => ['title'],
                            'analyze_wildcard' => true,
                            'default_operator' => 'and',
                        ],
                    ],
                    'size' => 1000,
                ],
            ];

            return $this->search($search);
        } catch (ElasticsearchException $request400Exception) {
            return [];
        }
    }

    public function insertRelease(array $parameters): void
    {
        $searchNameDotless = str_replace(['.', '-'], ' ', $parameters['searchname']);
        $data = [
            'body' => [
                'id' => $parameters['id'],
                'name' => $parameters['name'],
                'searchname' => $parameters['searchname'],
                'plainsearchname' => $searchNameDotless,
                'fromname' => $parameters['fromname'],
                'categories_id' => $parameters['categories_id'],
                'filename' => $parameters['filename'] ?? '',
                'add_date' => now()->format('Y-m-d H:i:s'),
                'post_date' => $parameters['postdate'],
            ],
            'index' => 'releases',
            'id' => $parameters['id'],
        ];

        Elasticsearch::index($data);
    }

    public function updateRelease(int $id): void
    {
        $new = Release::query()
            ->where('releases.id', $id)
            ->leftJoin('release_files as rf', 'releases.id', '=', 'rf.releases_id')
            ->select(['releases.id', 'releases.name', 'releases.searchname', 'releases.fromname', 'releases.categories_id', DB::raw('IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename')])
            ->groupBy('releases.id')
            ->first();
        if ($new !== null) {
            $searchNameDotless = str_replace(['.', '-'], ' ', $new->searchname);
            $data = [
                'body' => [
                    'doc' => [
                        'id' => $new->id,
                        'name' => $new->name,
                        'searchname' => $new->searchname,
                        'plainsearchname' => $searchNameDotless,
                        'fromname' => $new->fromname,
                        'categories_id' => $new->categories_id,
                        'filename' => $new->filename,
                    ],
                    'doc_as_upsert' => true,
                ],

                'index' => 'releases',
                'id' => $new->id,
            ];

            Elasticsearch::update($data);
        }
    }

    public function searchPreDb($searchTerm): array
    {
        $search = [
            'index' => 'predb',
            'body' => [
                'query' => [
                    'query_string' => [
                        'query' => $searchTerm,
                        'fields' => ['title', 'filename'],
                        'analyze_wildcard' => true,
                        'default_operator' => 'and',
                    ],
                ],
            ],
        ];

        try {
            $primaryResults = Elasticsearch::search($search);

            $results = [];
            foreach ($primaryResults['hits']['hits'] as $primaryResult) {
                $results[] = $primaryResult['_source'];
            }
        } catch (ElasticsearchException $badRequest400Exception) {
            return [];
        }

        return $results;
    }

    public function insertPreDb(array $parameters): void
    {
        $data = [
            'body' => [
                'id' => $parameters['id'],
                'title' => $parameters['title'],
                'source' => $parameters['source'],
                'filename' => $parameters['filename'],
            ],
            'index' => 'predb',
            'id' => $parameters['id'],
        ];

        Elasticsearch::index($data);
    }

    public function updatePreDb(array $parameters): void
    {
        $data = [
            'body' => [
                'doc' => [
                    'id' => $parameters['id'],
                    'title' => $parameters['title'],
                    'filename' => $parameters['filename'],
                    'source' => $parameters['source'],
                ],
                'doc_as_upsert' => true,
            ],

            'index' => 'predb',
            'id' => $parameters['id'],
        ];

        Elasticsearch::update($data);
    }

    protected function search(array $search, bool $fullResults = false): array
    {
        $results = Elasticsearch::search($search);

        $searchResult = [];
        while (isset($results['hits']['hits']) && count($results['hits']['hits']) > 0) {
            foreach ($results['hits']['hits'] as $result) {
                if ($fullResults === true) {
                    $searchResult[] = $result['_source'];
                } else {
                    $searchResult[] = $result['_source']['id'];
                }
            }
            // When done, get the new scroll_id
            // You must always refresh your _scroll_id!  It can change sometimes
            $scroll_id = $results['_scroll_id'];

            // Execute a Scroll request and repeat
            $results = Elasticsearch::scroll([
                'scroll_id' => $scroll_id,  //...using our previously obtained _scroll_id
                'scroll' => '30s',        // and the same timeout window
            ]
            );
        }
        if (empty($searchResult)) {
            return [];
        }

        return $searchResult;
    }
}
