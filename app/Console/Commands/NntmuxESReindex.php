<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class NntmuxESReindex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:reindex_es';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex elasticsearch releases and predb indexes';

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
     * Execute the console command.
     */
    public function handle(): int
    {
        passthru('php '.app()->/* @scrutinizer ignore-call */ path().'/../misc/elasticsearch/create_es_indexes.php');
        passthru('php '.app()->/* @scrutinizer ignore-call */ path().'/../misc/elasticsearch/populate_es_indexes.php releases');
        passthru('php '.app()->/* @scrutinizer ignore-call */ path().'/../misc/elasticsearch/populate_es_indexes.php predb');

        return self::SUCCESS;
    }
}
