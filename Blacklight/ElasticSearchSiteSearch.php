<?php

namespace Blacklight;

use Elasticsearch\Common\Exceptions\BadRequest400Exception;

class ElasticSearchSiteSearch
{
    /**
     * @param $phrases
     * @param $limit
     * @return mixed
     */
    public function indexSearch($phrases, $limit)
    {
        try {
            $search = [
                'scroll' => '30s',
                'index' => 'releases',
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => implode(' ', $phrases),
                            'fields' => ['searchname', 'plainsearchname', 'fromname', 'filename', 'name'],
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

            $results = \Elasticsearch::search($search);

            $searchResult = [];
            while (isset($results['hits']['hits']) && count($results['hits']['hits']) > 0) {
                foreach ($results['hits']['hits'] as $result) {
                    $searchResult[] = $result['_source']['id'];
                }

                // When done, get the new scroll_id
                // You must always refresh your _scroll_id!  It can change sometimes
                $scroll_id = $results['_scroll_id'];

                // Execute a Scroll request and repeat
                $results = \Elasticsearch::scroll([
                    'scroll_id' => $scroll_id,  //...using our previously obtained _scroll_id
                    'scroll' => '30s',        // and the same timeout window
                ]
                );
            }

            return $searchResult;
        } catch (BadRequest400Exception $request400Exception) {
            return [];
        }
    }

    /**
     * @param $searchName
     * @param $limit
     * @return array
     */
    public function indexSearchApi($searchName, $limit)
    {
        try {
            $search = [
                'scroll' => '30s',
                'index' => 'releases',
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => $searchName,
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

            $results = \Elasticsearch::search($search);

            $searchResult = [];
            while (isset($results['hits']['hits']) && count($results['hits']['hits']) > 0) {
                foreach ($results['hits']['hits'] as $result) {
                    $searchResult[] = $result['_source']['id'];
                }

                // When done, get the new scroll_id
                // You must always refresh your _scroll_id!  It can change sometimes
                $scroll_id = $results['_scroll_id'];

                // Execute a Scroll request and repeat
                $results = \Elasticsearch::scroll([
                    'scroll_id' => $scroll_id,  //...using our previously obtained _scroll_id
                    'scroll' => '30s',        // and the same timeout window
                ]
                );
            }

            return $searchResult;
        } catch (BadRequest400Exception $request400Exception) {
            return [];
        }
    }

    /**
     * Search function used in TV, TV API, Movies and Anime searches.
     * @param $name
     * @param $limit
     * @return array
     */
    public function indexSearchTMA($name, $limit)
    {
        try {
            $search = [
                'scroll' => '30s',
                'index' => 'releases',
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => $name,
                            'fields' => ['searchname', 'plainsearchname'],
                            'analyze_wildcard' => true,
                            'default_operator' => 'and',
                        ],
                    ],
                    'size' => $limit,
                    'sort' => [
                        'add_date' => [
                            'order' =>'desc',
                        ],
                        'post_date' => [
                            'order' => 'desc',
                        ],
                    ],
                ],
            ];

            $results = \Elasticsearch::search($search);

            $searchResult = [];
            while (isset($results['hits']['hits']) && count($results['hits']['hits']) > 0) {
                foreach ($results['hits']['hits'] as $result) {
                    $searchResult[] = $result['_source']['id'];
                }

                // When done, get the new scroll_id
                // You must always refresh your _scroll_id!  It can change sometimes
                $scroll_id = $results['_scroll_id'];

                // Execute a Scroll request and repeat
                $results = \Elasticsearch::scroll([
                    'scroll_id' => $scroll_id,  //...using our previously obtained _scroll_id
                    'scroll'    => '30s',        // and the same timeout window
                ]
                );
            }

            return $searchResult;
        } catch (BadRequest400Exception $request400Exception) {
            return [];
        }
    }

    /**
     * @param $search
     * @return array|\Illuminate\Support\Collection
     */
    public function predbIndexSearch($search)
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

            $results = \Elasticsearch::search($search);

            $ids = [];
            while (isset($results['hits']['hits']) && count($results['hits']['hits']) > 0) {
                foreach ($results['hits']['hits'] as $result) {
                    $ids[] = $result['_source']['id'];
                }
                if (empty($ids)) {
                    return collect();
                }
                // When done, get the new scroll_id
                // You must always refresh your _scroll_id!  It can change sometimes
                $scroll_id = $results['_scroll_id'];

                // Execute a Scroll request and repeat
                $results = \Elasticsearch::scroll([
                    'scroll_id' => $scroll_id,  //...using our previously obtained _scroll_id
                    'scroll' => '30s',        // and the same timeout window
                ]
                );
            }

            return $ids;
        } catch (BadRequest400Exception $request400Exception) {
            return [];
        }
    }
}
