<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Elasticsearch;
use App\Services\Search\Support\ElasticsearchResponseHelper;
use Elastic\Elasticsearch\Client;
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
    protected $description = 'Create Elasticsearch indexes (releases, predb, movies, tvshows, and metadata indexes)';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        /** @var Client $client */
        $client = app('elasticsearch');

        $names = config('search.drivers.elasticsearch.indexes', []);
        $releasesIndex = (string) ($names['releases'] ?? 'releases');
        $predbIndex = (string) ($names['predb'] ?? 'predb');

        $defaultSettings = [
            'number_of_shards' => 2,
            'number_of_replicas' => 0,
            'analysis' => [
                'analyzer' => [
                    'release_analyzer' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'asciifolding'],
                    ],
                    'metadata_analyzer' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'asciifolding'],
                    ],
                ],
            ],
        ];

        $this->recreateIndex($client, $releasesIndex, [
            'settings' => $defaultSettings,
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
                    'imdbid' => [
                        'type' => 'keyword',
                        'ignore_above' => 32,
                    ],
                    'add_date' => [
                        'type' => 'date',
                        'format' => 'yyyy-MM-dd HH:mm:ss',
                    ],
                    'post_date' => [
                        'type' => 'date',
                        'format' => 'yyyy-MM-dd HH:mm:ss',
                    ],
                    'postdate_ts' => ['type' => 'long'],
                    'adddate_ts' => ['type' => 'long'],
                    'size' => ['type' => 'long'],
                    'totalpart' => ['type' => 'integer'],
                    'grabs' => ['type' => 'integer'],
                    'passwordstatus' => ['type' => 'integer'],
                    'groups_id' => ['type' => 'integer'],
                    'nzbstatus' => ['type' => 'integer'],
                    'haspreview' => ['type' => 'integer'],
                    'tmdbid' => ['type' => 'integer'],
                    'traktid' => ['type' => 'integer'],
                    'tvdb' => ['type' => 'integer'],
                    'tvmaze' => ['type' => 'integer'],
                    'tvrage' => ['type' => 'integer'],
                    'videos_id' => ['type' => 'integer'],
                    'movieinfo_id' => ['type' => 'integer'],
                ],
            ],
        ]);

        $this->recreateIndex($client, $predbIndex, [
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
        ]);

        $metaSettings = $defaultSettings;

        $moviesIndex = (string) ($names['movies'] ?? 'movies');
        $this->recreateIndex($client, $moviesIndex, [
            'settings' => $metaSettings,
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'long', 'index' => false],
                    'imdbid' => ['type' => 'keyword', 'ignore_above' => 32],
                    'tmdbid' => ['type' => 'long'],
                    'traktid' => ['type' => 'long'],
                    'title' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                    'year' => ['type' => 'keyword', 'ignore_above' => 16],
                    'genre' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                    'actors' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                    'director' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                    'rating' => ['type' => 'keyword', 'ignore_above' => 32],
                    'plot' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                ],
            ],
        ]);

        $tvshowsIndex = (string) ($names['tvshows'] ?? 'tvshows');
        $this->recreateIndex($client, $tvshowsIndex, [
            'settings' => $metaSettings,
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'long', 'index' => false],
                    'title' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                    'tvdb' => ['type' => 'integer'],
                    'trakt' => ['type' => 'integer'],
                    'tvmaze' => ['type' => 'integer'],
                    'tvrage' => ['type' => 'integer'],
                    'imdb' => ['type' => 'keyword', 'ignore_above' => 32],
                    'tmdb' => ['type' => 'integer'],
                    'started' => ['type' => 'keyword', 'ignore_above' => 32],
                    'type' => ['type' => 'integer'],
                ],
            ],
        ]);

        $musicIndex = (string) ($names['music'] ?? 'music');
        $this->recreateIndex($client, $musicIndex, [
            'settings' => $metaSettings,
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                    'artist' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                    'year' => ['type' => 'keyword', 'ignore_above' => 16],
                    'genres_id' => ['type' => 'integer'],
                    'cover' => ['type' => 'integer'],
                ],
            ],
        ]);

        $booksIndex = (string) ($names['books'] ?? 'books');
        $this->recreateIndex($client, $booksIndex, [
            'settings' => $metaSettings,
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                    'author' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                    'publishdate' => ['type' => 'keyword', 'ignore_above' => 32],
                    'cover' => ['type' => 'integer'],
                ],
            ],
        ]);

        $gamesIndex = (string) ($names['games'] ?? 'games');
        $this->recreateIndex($client, $gamesIndex, [
            'settings' => $metaSettings,
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                    'genres_id' => ['type' => 'integer'],
                    'releasedate_ts' => ['type' => 'long'],
                    'cover' => ['type' => 'integer'],
                ],
            ],
        ]);

        $consoleIndex = (string) ($names['console'] ?? 'console');
        $this->recreateIndex($client, $consoleIndex, [
            'settings' => $metaSettings,
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                    'platform' => ['type' => 'keyword', 'ignore_above' => 128],
                    'genres_id' => ['type' => 'integer'],
                    'releasedate_ts' => ['type' => 'long'],
                    'cover' => ['type' => 'integer'],
                ],
            ],
        ]);

        $steamIndex = (string) ($names['steam'] ?? 'steam');
        $this->recreateIndex($client, $steamIndex, [
            'settings' => $metaSettings,
            'mappings' => [
                'properties' => [
                    'name' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                    'appid' => ['type' => 'integer'],
                ],
            ],
        ]);

        $animeIndex = (string) ($names['anime'] ?? 'anime');
        $this->recreateIndex($client, $animeIndex, [
            'settings' => $metaSettings,
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text', 'analyzer' => 'metadata_analyzer'],
                    'anidbid' => ['type' => 'integer'],
                    'anilist_id' => ['type' => 'integer'],
                    'mal_id' => ['type' => 'integer'],
                    'lang' => ['type' => 'keyword', 'ignore_above' => 16],
                    'title_type' => ['type' => 'keyword', 'ignore_above' => 32],
                    'media_type' => ['type' => 'keyword', 'ignore_above' => 32],
                    'status' => ['type' => 'keyword', 'ignore_above' => 32],
                ],
            ],
        ]);

        $this->info('All Elasticsearch indexes are created (or recreated).');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function recreateIndex(Client $client, string $indexName, array $body): void
    {
        if (ElasticsearchResponseHelper::boolResponse($client, fn (Client $elasticClient) => $elasticClient->indices()->exists(['index' => $indexName]))) {
            Elasticsearch::indices()->delete(['index' => $indexName]);
        }

        Elasticsearch::indices()->create([
            'index' => $indexName,
            'body' => $body,
        ]);

        $this->info("Index {$indexName} created successfully.");
    }
}
