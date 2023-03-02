<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

if (\Elasticsearch::indices()->exists(['index' => 'releases'])) {
    \Elasticsearch::indices()->delete(['index' => 'releases']);
}
$releases_index = [
    'index' => 'releases',
    'body' => [
        'settings' => [
            'number_of_shards' => 2,
            'number_of_replicas' => 0,
        ],
        'mappings' => [
            'properties' => [
                'id' => [
                    'type' => 'long',
                    'index' => false,
                ],
                'name' => ['type' => 'text'],
                'searchname' => [
                    'type' => 'text',
                    'fields' => [
                        'sort' => [
                            'type' => 'keyword',
                        ],
                    ],
                ],
                'plainsearchname' => [
                    'type' => 'text',
                    'fields' => [
                        'sort' => [
                            'type' => 'keyword',
                        ],
                    ],
                ],
                'categories_id' => [
                    'type' => 'text',
                ],
                'fromname' => [
                    'type' => 'text',
                ],
                'filename' => [
                    'type' => 'text',
                ],
                'add_date' => [
                    'type' => 'date',
                    'format' => 'yyyy-MM-dd HH:mm:ss',
                ],
                'post_date' => [
                    'type' => 'date',
                    'format' => 'yyyy-MM-dd HH:mm:ss',
                ],
            ],
        ],
    ],
];

$response = \Elasticsearch::indices()->create($releases_index);

print_r($response);
if (\Elasticsearch::indices()->exists(['index' => 'predb'])) {
    \Elasticsearch::indices()->delete(['index' => 'predb']);
}
$predb_index = [
    'index' => 'predb',
    'body' => [
        'settings' => [
            'number_of_shards' => 2,
            'number_of_replicas' => 0,
        ],
        'mappings' => [
            'properties' => [
                'id' => [
                    'type' => 'long',
                    'index' => false,
                ],
                'title' => [
                    'type' => 'text',
                    'fields' => [
                        'sort' => [
                            'type' => 'keyword',
                        ],
                    ],
                ],
                'filename' => ['type' => 'text'],
                'source' => ['type' => 'text'],
            ],
        ],
    ],

];

$response = \Elasticsearch::indices()->create($predb_index);

print_r($response);

echo 'All done! ElasticSearch indexes are created now.'.PHP_EOL;
