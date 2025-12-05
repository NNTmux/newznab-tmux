<?php

namespace App\Console\Commands;

use App\Models\UsenetGroup;
use Blacklight\ManticoreSearch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class NntmuxResetDb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:resetdb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will reset your database to blank state (will retain settings)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws \Exception
     */
    public function handle(): void
    {
        // Allow database reset only if app environment is local
        if (app()->environment() !== 'local') {
            $this->error('This command can only be run in local environment');

            return;
        }
        if ($this->confirm('This command removes all releases, nzb files, samples, previews , nfos, truncates all article tables and resets all groups. Are you sure you want reset the DB?')) {
            $timestart = now();

            DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

            UsenetGroup::query()->update([
                'first_record' => 0,
                'first_record_postdate' => null,
                'last_record' => 0,
                'last_record_postdate' => null,
                'last_updated' => null,
            ]);
            $this->info('Reseting all groups completed.');

            $arr = [
                'binaries',
                'collections',
                'parts',
                'missed_parts',
                'videos',
                'tv_episodes',
                'tv_info',
                'release_nfos',
                'release_comments',
                'users_releases',
                'user_movies',
                'user_series',
                'movieinfo',
                'musicinfo',
                'release_files',
                'audio_data',
                'release_subtitles',
                'video_data',
                'media_infos',
                'releases',
                'anidb_titles',
                'anidb_info',
                'releases_groups',
            ];
            foreach ($arr as &$value) {
                DB::statement("TRUNCATE TABLE $value");
                $this->info('Truncating '.$value.' completed.');
            }
            unset($value);

            if (config('nntmux.elasticsearch_enabled') === true) {
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
                                'fromname' => ['type' => 'text'],
                                'filename' => ['type' => 'text'],
                                'add_date' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
                                'post_date' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
                            ],
                        ],
                    ],
                ];

                \Elasticsearch::indices()->create($releases_index);

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

                \Elasticsearch::indices()->create($predb_index);

                $this->info('All done! ElasticSearch indexes are deleted and recreated.');
            } else {
                (new ManticoreSearch)->truncateRTIndex(['releases_rt', 'predb_rt']);
            }

            $this->info('Deleting nzbfiles subfolders.');
            $files = File::allFiles(config('nntmux_settings.path_to_nzbs'));
            File::delete($files);

            $this->info('Deleting all images, previews and samples that still remain.');

            $files = File::allFiles(storage_path('covers/'));
            foreach ($files as $file) {
                if (basename($file) !== '.gitignore' && basename($file) !== 'no-cover.jpg' && basename($file) !== 'no-backdrop.jpg') {
                    File::delete($file);
                }
            }

            $this->call('cache:clear');

            $this->info('Deleted all releases, images, previews and samples. This script finished '.now()->diffForHumans($timestart).' start');
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
        } else {
            $this->info('Script execution stopped');
        }
    }
}
