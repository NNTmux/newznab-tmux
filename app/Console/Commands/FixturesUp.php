<?php

namespace App\Console\Commands;

use DariusIII\L5Fixtures\FixturesFacade;
use Illuminate\Console\Command;

class FixturesUp extends Command
{
    /**
     * @var array
     */
    private static $allowedTables = [
        'binaryblacklist',
        'root_categories',
        'categories',
        'category_regexes',
        'collection_regexes',
        'content',
        'usenet_groups',
        'release_naming_regexes',
        'settings',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fixtures:up {--t|table=* : Populate all, multiple or single table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply database fixtures to all tables or just to select ones.
    Tables that are supported are :
    no argument <= Populates all the tables listed below
    binaryblacklist
    root_categories
    categories
    category_regexes
    collection_regexes
    content
    usenet_groups
    release_naming_regexes
    settings';

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
    public function handle()
    {
        if (empty($this->option('table'))) {
            $this->info('Populating all tables');
            FixturesFacade::up();
        } else {
            foreach ($this->option('table') as $option) {
                if (\in_array($option, self::$allowedTables, false)) {
                    $this->info('Populating '.$option.' table');
                    FixturesFacade::up($option);
                }
            }
        }
    }
}
