<?php

namespace App\Console\Commands;

use Blacklight\PopulateAniList;
use Illuminate\Console\Command;

class NntmuxPopulateAniDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:populate-anidb
    {--info : Populate info only}
    {--anilistid : Populate tables for specific AniList ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate AniList table (replaces AniDB)';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $palist = new PopulateAniList;
        
        if ($this->option('info') && is_numeric($this->option('anilistid'))) {
            $palist->populateTable('info', $this->option('anilistid'));
        } elseif ($this->option('info')) {
            $palist->populateTable('info');
        } else {
            $this->error('Please specify --info option');
            return;
        }
        
        $this->info('AniList tables populated with requested data');
    }
}
