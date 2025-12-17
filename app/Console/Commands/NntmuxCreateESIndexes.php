<?php

namespace App\Console\Commands;

use Elasticsearch;
use Illuminate\Console\Command;

class NntmuxCreateESIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:create-es-indexes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create ElasticSearch Indexes';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (Elasticsearch::indices()->exists(['index' => 'releases'])) {
            Elasticsearch::indices()->delete(['index' => 'releases']);
        }
        $releases_index = [
            'index' => 'releases',
            'body' => [
                'settings' => [
                    'number_of_shards' => 2,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            'release_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'asciifolding'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => [
                            'type' => 'long',
                            'index' => false,
                        ],
                        'name' => [
                            'type' => 'text',
                            'analyzer' => 'release_analyzer',
                        ],
                        'searchname' => [
                            'type' => 'text',
                            'analyzer' => 'release_analyzer',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                                'sort' => [
                                    'type' => 'keyword',
                                ],
                            ],
                        ],
                        'plainsearchname' => [
                            'type' => 'text',
                            'analyzer' => 'release_analyzer',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                                'sort' => [
                                    'type' => 'keyword',
                                ],
                            ],
                        ],
                        'categories_id' => [
                            'type' => 'integer',
                        ],
                        'fromname' => [
                            'type' => 'text',
                            'analyzer' => 'release_analyzer',
                        ],
                        'filename' => [
                            'type' => 'text',
                            'analyzer' => 'release_analyzer',
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

        $response = Elasticsearch::indices()->create($releases_index);

        $this->info('Index releases created successfully');
        if (Elasticsearch::indices()->exists(['index' => 'predb'])) {
            Elasticsearch::indices()->delete(['index' => 'predb']);
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

        $response = Elasticsearch::indices()->create($predb_index);

        $this->info('Index predb created successfully');

        $this->info('All done! ElasticSearch indexes are created now.');
    }
}
