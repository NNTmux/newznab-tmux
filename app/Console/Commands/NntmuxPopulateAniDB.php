<?php

namespace App\Console\Commands;

use Blacklight\PopulateAniDB;
use Illuminate\Console\Command;

class NntmuxPopulateAniDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:populate-anidb
    {--full : Populate full data}
    {--info : Populate info only}
    {--anidbid : Populate tables for specific anidbid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate AniDB table';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ($this->option('full')) {
            (new PopulateAniDB(['Echo' => true]))->populateTable('full');
        } elseif ($this->option('info') && is_numeric($this->option('anidbid'))) {
            (new PopulateAniDB(['Echo' => true]))->populateTable('info', $this->option('anidbid'));
        } elseif ($this->option('info')) {
            (new PopulateAniDB(['Echo' => true]))->populateTable('info');
        }
        $this->info('AniDB tables populated with requested data');
    }
}
