<?php

namespace Blacklight;

use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Illuminate\Support\Str;
use sspat\ESQuerySanitizer\Sanitizer;

class ElasticSearchSiteSearch
{
    /**
     * @param $phrases
     * @param $limit
     * @return mixed
     */
    public function indexSearch($phrases, $limit)
    {
        $keywords = $this->sanitize($phrases);
        try {
            $search = [
                'scroll' => '30s',
                'index' => 'releases',
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => $keywords,
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
        $keywords = $this->sanitize($searchName);
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
        $keywords = $this->sanitize($name);
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
        $keywords = $this->sanitize($search);
        try {
            $search = [
                'scroll' => '30s',
                'index' => 'predb',
                'body' => [
                    'query' => [
                        'query_string' => [
                            'query' => $keywords,
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

    /**
     * @param array|string $phrases
     * @return string
     */
    private function sanitize($phrases): string
    {
        if (! is_array($phrases)) {
            $wordArray = explode(' ', str_replace('.', ' ', $phrases));
        } else {
            $wordArray = $phrases;
        }
        $keywords = [];
        foreach ($wordArray as $words) {
            $tempWords = [];
            $words = preg_split('/\s+/', $words);
            foreach ($words as $st) {
                if (Str::startsWith($st, ['!', '+', '-', '?', '*'])) {
                    $str = $st;
                } elseif (Str::endsWith($st, ['+', '-', '?', '*'])) {
                    $str = $st;
                } else {
                    $str = Sanitizer::escape($st);
                }
                $tempWords[] = $str;
            }

            $keywords = $tempWords;
        }

        return implode(' ', $keywords);
    }
}
