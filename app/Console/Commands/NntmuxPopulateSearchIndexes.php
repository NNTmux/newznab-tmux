<?php

namespace App\Console\Commands;

use App\Models\Predb;
use App\Models\Release;
use Blacklight\ElasticSearchSiteSearch;
use Blacklight\ManticoreSearch;
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
    public function handle(): int
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
        $manticore = new ManticoreSearch;
        $manticore->truncateRTIndex(Arr::wrap('releases_rt'));
        $total = Release::count();
        if (! $total) {
            $this->warn('Releases table is empty. Nothing to do.');
            exit();
        }
        $max = $this->option('count');

        DB::statement('SET SESSION group_concat_max_len=16384;');

        $this->info("Starting to populate Manticore RT index 'releases_rt' with $total rows and $max inserts per loop.");

        $bar = $this->output->createProgressBar($total);
        $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
        $bar->start();

        Release::query()
            ->orderByDesc('id')
            ->leftJoin('release_files', 'releases.id', '=', 'release_files.releases_id')
            ->select(['releases.id', 'releases.name', 'releases.searchname', 'releases.fromname', 'releases.categories_id'])
            ->selectRaw('IFNULL(GROUP_CONCAT(release_files.name SEPARATOR " "),"") filename')
            ->groupBy('id')
            ->chunk($max, function ($releases) use ($manticore, $bar) {
                $data = [];
                foreach ($releases as $r) {
                    $data[] = [
                        'id' => $r->id,
                        'name' => (string) ($r->name ?? ''),
                        'searchname' => (string) ($r->searchname ?? ''),
                        'fromname' => (string) ($r->fromname ?? ''),
                        'categories_id' => (string) ($r->categories_id ?? '0'),
                        'filename' => (string) ($r->filename ?? ''),
                        'dummy' => 1, // Adding dummy integer field as required by schema
                    ];
                    $bar->advance();
                }
                if (! empty($data)) {
                    $manticore->manticoreSearch->table('releases_rt')->replaceDocuments($data);
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
        $manticore = new ManticoreSearch;
        $manticore->truncateRTIndex(['predb_rt']);

        $total = Predb::count();
        if (! $total) {
            $this->warn('PreDB table is empty. Nothing to do.');
            exit();
        }
        $max = $this->option('count');

        DB::statement('SET SESSION group_concat_max_len=16384;');

        $this->info("Starting to populate Manticore RT index 'predb_rt' with $total rows and $max inserts per loop.");

        $bar = $this->output->createProgressBar($total);
        $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
        $bar->start();
        Predb::query()
            ->select(['id', 'title', 'filename', 'source'])
            ->groupBy('id')
            ->orderBy('id')
            ->chunk($max, function ($pre) use ($manticore, $bar) {
                $data = [];
                foreach ($pre as $p) {
                    $data[] = [
                        'id' => $p->id,
                        'title' => (string) ($p->title ?? ''),
                        'filename' => (string) ($p->filename ?? ''),
                        'source' => (string) ($p->source ?? ''),
                        'dummy' => 1, // Adding dummy integer field as required by schema
                    ];
                    $bar->advance();
                }
                if (! empty($data)) {
                    $manticore->manticoreSearch->table('predb_rt')->replaceDocuments($data);
                }
            });

        $bar->finish();
        $this->newLine();
    }

    private function elasticReleases(): void
    {
        $data = ['body' => []];
        $elastic = new ElasticSearchSiteSearch;
        $total = Release::count();
        if (! $total) {
            $this->warn('Could not get database information for releases table.');
            exit();
        }
        $max = $this->option('count');
        $this->info("Starting to populate ElasticSearch index releases with $total releases.");

        $bar = $this->output->createProgressBar($total);
        $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
        $bar->start();

        DB::statement('SET SESSION group_concat_max_len=16384;');
        Release::query()
            ->orderByDesc('id')
            ->leftJoin('release_files', 'releases.id', '=', 'release_files.releases_id')
            ->select(['releases.id', 'releases.name', 'releases.searchname', 'releases.fromname', 'releases.categories_id', 'releases.postdate'])
            ->selectRaw('IFNULL(GROUP_CONCAT(release_files.name SEPARATOR " "),"") filename')
            ->groupBy('id')
            ->chunk($max, function ($releases) use ($bar, $data) {
                foreach ($releases as $r) {
                    $searchName = str_replace(['.', '-'], ' ', $r->searchname);
                    $data['body'][] = [
                        'index' => [
                            '_index' => 'releases',
                            '_id' => $r->id,
                        ],
                    ];
                    $data['body'][] = [
                        'id' => $r->id,
                        'name' => $r->name,
                        'searchname' => $r->searchname,
                        'plainsearchname' => $searchName,
                        'fromname' => $r->fromname,
                        'categories_id' => $r->categories_id,
                        'filename' => $r->filename,
                        'postdate' => $r->postdate,
                    ];
                    $bar->advance();
                }
                \Elasticsearch::bulk($data);
            });
        $bar->finish();
        $this->newLine();
        $this->info('Done');
    }

    private function elasticPreDB(): void
    {
        $data = ['body' => []];
        $elastic = new ElasticSearchSiteSearch;
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
        Predb::query()
            ->select(['id', 'title', 'filename', 'source'])
            ->groupBy('id')
            ->orderBy('id')
            ->chunk($max, function ($pre) use ($bar, $data) {
                foreach ($pre as $p) {
                    $data['body'][] = [
                        'index' => [
                            '_index' => 'predb',
                            '_id' => $p->id,
                        ],
                    ];
                    $data['body'][] = [
                        'id' => $p->id,
                        'title' => $p->title,
                        'filename' => $p->filename,
                        'source' => $p->source,
                    ];
                    $bar->advance();
                }
                \Elasticsearch::bulk($data);
            });

        $bar->finish();
        $this->newLine();
        $this->info('Done');
    }

    /**
     * Optimize Manticore indexes
     */
    private function optimize(): void
    {
        $this->info('Optimizing release_rt & predb_rt indexes');
        try {
            (new ManticoreSearch)->optimizeRTIndex();
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        $this->line('Done!');
    }
}
