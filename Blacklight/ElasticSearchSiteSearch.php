<?php

namespace Blacklight;

use App\Models\Release;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use sspat\ESQuerySanitizer\Sanitizer;

class ElasticSearchSiteSearch
{
    /**
     * @param  string|array  $phrases
     * @param  int  $limit
     * @return mixed
     */
    public function indexSearch($phrases, int $limit)
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
     * @param  string|array  $searchName
     * @param  int  $limit
     * @return array
     */
    public function indexSearchApi($searchName, int $limit)
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
     *
     * @param  string|array  $name
     * @param  int  $limit
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
     * @param  string|array  $search
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

    /**
     * @param  array  $parameters
     */
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
                'filename' => $parameters['filename'] ?? '',
                'add_date' => now()->format('Y-m-d H:i:s'),
                'post_date' => $parameters['postdate'],
            ],
            'index' => 'releases',
            'id' => $parameters['id'],
        ];

        \Elasticsearch::index($data);
    }

    /**
     * @param  int  $id
     */
    public function updateRelease(int $id)
    {
        $new = Release::query()
            ->where('releases.id', $id)
            ->leftJoin('release_files as rf', 'releases.id', '=', 'rf.releases_id')
            ->select(['releases.id', 'releases.name', 'releases.searchname', 'releases.fromname', DB::raw('IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename')])
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
                        'filename' => $new->filename,
                    ],
                    'doc_as_upsert' => true,
                ],

                'index' => 'releases',
                'id' => $new->id,
            ];

            \Elasticsearch::update($data);
        }
    }

    /**
     * @param $searchTerm
     * @return array
     */
    public function searchPreDb($searchTerm)
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
            $primaryResults = \Elasticsearch::search($search);

            $results = [];
            foreach ($primaryResults['hits']['hits'] as $primaryResult) {
                $results[] = $primaryResult['_source'];
            }
        } catch (BadRequest400Exception $badRequest400Exception) {
            return [];
        }

        return $results;
    }

    /**
     * @param $parameters
     */
    public function insertPreDb($parameters)
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

        \Elasticsearch::index($data);
    }

    /**
     * @param $parameters
     */
    public function updatePreDb($parameters)
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

        \Elasticsearch::update($data);
    }

    /**
     * @param  array|string  $phrases
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
        $tempWords = [];
        foreach ($wordArray as $words) {
            $words = preg_split('/\s+/', $words);
            foreach ($words as $st) {
                if (Str::startsWith($st, ['!', '+', '-', '?', '*']) && Str::length($st) > 1 && ! preg_match('/(!|\+|\?|-|\*){2,}/', $st)) {
                    $str = $st;
                } elseif (Str::endsWith($st, ['+', '-', '?', '*']) && Str::length($st) > 1 && ! preg_match('/(!|\+|\?|-|\*){2,}/', $st)) {
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
