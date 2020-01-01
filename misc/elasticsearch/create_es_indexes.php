<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

if (Elasticsearch::indices()->exists(['index' => 'releases'])) {
    Elasticsearch::indices()->delete(['index' => 'releases']);
}
$releases_index = [
    'index' => 'releases',
    'body'  => [
        'settings' => [
            'number_of_shards' => 2,
            'number_of_replicas' => 0
        ]
    ]
];

$response = Elasticsearch::indices()->create($releases_index);

print_r($response);
if (Elasticsearch::indices()->exists(['index' => 'predb'])) {
    Elasticsearch::indices()->delete(['index' => 'predb']);
}
$predb_index = [
    'index' => 'predb',
    'body'  => [
        'settings' => [
            'number_of_shards' => 2,
            'number_of_replicas' => 0
        ]
    ]
];

$response = Elasticsearch::indices()->create($predb_index);

print_r($response);


echo 'All done! ElasticSearch indexes are created now.'.PHP_EOL;
