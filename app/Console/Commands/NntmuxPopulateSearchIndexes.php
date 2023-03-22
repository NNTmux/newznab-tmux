<?php

namespace App\Console\Commands;

use App\Models\Predb;
use App\Models\Release;
use Blacklight\ManticoreSearch;
use Elasticsearch;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class NntmuxPopulateSearchIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '
    nntmux:populate
    {--manticore : Use ManticoreSearch}
    {--elastic : Use ElasticSearch}
    {--releases : Populates the releases_rt index}
    {--predb  : Populates the predb_rt index}
    {--count=20000 : Sets the chunk size}
    {--optimize : Optimize ManticoreSearch indexes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate Manticore/Elasticsearch indexes with either releases or predb';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('releases') && $this->option('manticore')) {
            $this->manticoreReleases();
        } elseif ($this->option('predb') && $this->option('manticore')) {
            $this->manticorePreDB();
        } elseif ($this->option('optimize')) {
            $this->optimize();
        } elseif ($this->option('elastic') && $this->option('releases')) {
            $this->elasticReleases();
        } elseif ($this->option('elastic') && $this->option('predb')) {
            $this->elasticPreDB();
        } else {
            $this->error('You need to specify which index to populate! Check the options with the -h parameter.');
        }

        return 0;
    }

    /**
     * Run releases.
     */
    private function manticoreReleases(): void
    {
        $manticore = new ManticoreSearch();
        $manticore->truncateRTIndex(Arr::wrap('releases_rt'));
        $total = Release::count();
        if (! $total) {
            $this->warn('Could not get database information for releases table.');
            exit();
        }
        $max = $this->option('count');

        DB::statement('SET SESSION group_concat_max_len=16384;');

        $this->info("Starting to populate Manticore RT index 'releases_rt' with $total rows and $max inserts per loop.");

        $bar = $this->output->createProgressBar($total);
        $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
        $bar->start();

        DB::table('releases')
            ->orderBy('id')
            ->join('release_files', 'releases.id', '=', 'release_files.releases_id')
            ->select('releases.id', 'releases.name', 'releases.searchname', 'releases.fromname', 'releases.categories_id')
            ->selectRaw('IFNULL(GROUP_CONCAT(release_files.name SEPARATOR " "),"") filename')
            ->groupBy('id')
            ->chunk($max, function ($releases) use ($manticore, $bar) {
                foreach ($releases as $r) {
                    $manticore->insertRelease([
                        'id' => $r->id,
                        'name' => $r->name,
                        'searchname' => $r->searchname,
                        'fromname' => $r->fromname,
                        'categories_id' => (string) $r->categories_id,
                        'filename' => $r->filename,
                    ]);
                    $bar->advance();
                }
            });
        $bar->finish();
        $this->newLine();
    }

    /**
     * Run predb.
     */
    private function manticorePreDB(): void
    {
        $manticore = new ManticoreSearch();
        $manticore->truncateRTIndex(['predb_rt']);

        $total = Predb::count();
        if (! $total) {
            $this->warn('Could not get database information for predb table.');
            exit();
        }
        $max = $this->option('count');

        DB::statement('SET SESSION group_concat_max_len=16384;');

        $this->info("Starting to populate Manticore RT index 'predb_rt' with $total rows and $max inserts per loop.");

        $bar = $this->output->createProgressBar($total);
        $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
        $bar->start();

        DB::table('predb')
            ->select('id', 'title', 'filename', 'source')
            ->groupBy('id')
            ->orderBy('id')
            ->chunk($max, function ($pre) use ($manticore, $bar) {
                foreach ($pre as $p) {
                    $manticore->insertPredb([
                        'id' => $p->id,
                        'title' => $p->title,
                        'filename' => $p->filename,
                        'source' => $p->source,
                    ]);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
    }

    /**
     * @return void
     */
    private function elasticReleases()
    {
        $max = $this->option('count');
        DB::statement('SET SESSION group_concat_max_len=16384;');
        $query = 'SELECT r.id, r.name, r.searchname, r.fromname, r.categories_id, r.adddate, r.postdate, IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename
            FROM releases r
            LEFT JOIN release_files rf ON r.id = rf.releases_id
            WHERE r.id > %d
            GROUP BY r.id
            ORDER BY r.id ASC
            LIMIT %d';

        $totals = Release::fromQuery('SELECT COUNT(id) AS c, MIN(id) AS min FROM releases')->first();
        if (! $totals) {
            $this->error('Could not get database information for releases table.');
        }
        $total = $totals->c;
        $minId = $totals->min;

        $lastId = $minId - 1;
        $this->info("Starting to populate ElasticSearch index releases with $total releases.");
        $bar = $this->output->createProgressBar($total);
        $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
        $bar->start();
        $data = ['body' => []];
        for ($i = $minId; $i <= ($total + $minId); $i += $max) {
            $rows = DB::select(sprintf($query, $lastId, $max));
            if ($rows === 0) {
                continue;
            }

            foreach ($rows as $row) {
                if ($row->id > $lastId) {
                    $lastId = $row->id;
                }
                $searchName = str_replace(['.', '-'], ' ', $row->searchname);
                $data['body'][] = [
                    'index' => [
                        '_index' => 'releases',
                        '_id' => $row->id,
                    ],
                ];

                $data['body'][] = [
                    'id' => $row->id,
                    'name' => $row->name,
                    'searchname' => $row->searchname,
                    'plainsearchname' => $searchName,
                    'fromname' => $row->fromname,
                    'categories_id' => $row->categories_id,
                    'filename' => $row->filename,
                    'add_date' => $row->adddate,
                    'post_date' => $row->postdate,
                ];
            }
            if (! empty($data['body'])) {
                // Stop and send the bulk request
                $responses = Elasticsearch::bulk($data);

                // erase the old bulk request
                $data = ['body' => []];

                // unset the bulk response when you are done to save memory
                unset($responses);

                $bar->advance();
            }
        }

        // Send the last batch if it exists
        if (! empty($data['body'])) {
            $responses = Elasticsearch::bulk($data);
        }
        $bar->finish();
        $this->newLine();
        $this->info('Done');
    }

    /**
     * @return void
     */
    private function elasticPreDB()
    {
        $max = $this->option('count');
        DB::statement('SET SESSION group_concat_max_len=16384;');
        $query = 'SELECT id, title, filename, source
				FROM predb
				WHERE id > %d
				GROUP BY id
				ORDER BY id ASC
				LIMIT %d';

        $totals = Predb::fromQuery('SELECT COUNT(id) AS c, MIN(id) AS min FROM predb')->first();
        if (! $totals) {
            $this->error('Could not get database information for releases table.');
        }
        $total = $totals->c;
        $minId = $totals->min;

        $lastId = $minId - 1;
        $this->info("Starting to populate ElasticSearch index predb with $total releases.");
        $bar = $this->output->createProgressBar($total);
        $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
        $bar->start();
        $data = ['body' => []];
        for ($i = $minId; $i <= ($total + $minId); $i += $max) {
            $rows = DB::select(sprintf($query, $lastId, $max));
            if ($rows === 0) {
                continue;
            }

            foreach ($rows as $row) {
                if ($row->id > $lastId) {
                    $lastId = $row->id;
                }
                $data['body'][] = [
                    'index' => [
                        '_index' => 'predb',
                        '_id' => $row->id,
                    ],
                ];
                $data['body'][] = [
                    'id' => $row->id,
                    'title' => $row->title,
                    'filename' => $row->filename,
                    'source' => $row->source,
                ];
            }
            if (! empty($data['body'])) {
                // Stop and send the bulk request
                $responses = Elasticsearch::bulk($data);

                // erase the old bulk request
                $data = ['body' => []];

                // unset the bulk response when you are done to save memory
                unset($responses);

                $bar->advance();
            }
        }

        // Send the last batch if it exists
        if (! empty($data['body'])) {
            $responses = Elasticsearch::bulk($data);
        }
        $bar->finish();
        $this->newLine();
        $this->info('Done');
    }

    /**
     * Run predb.
     */
    private function optimize(): void
    {
        $this->info('Optimizing release_rt & predb_rt indexes');
        try {
            (new ManticoreSearch())->optimizeRTIndex();
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        $this->line('Done!');
    }
}
