<?php

namespace App\Console\Commands;

use DariusIII\L5Fixtures\FixturesFacade;
use Illuminate\Console\Command;

class FixturesDown extends Command
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
        'menu',
        'release_naming_regexes',
        'settings',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fixtures:down {--t|table=* : Table to truncate, no argument truncates them all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Truncate(empty) all, multiple or just one table.
    Tables that are supported are :
    no option argument <= Truncates all the tables listed below
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
        if ($this->confirm('This command will truncate(empty) your tables. Should we continue?')) {
            if (empty($this->option('table'))) {
                $this->info('Truncating all tables');
                FixturesFacade::down();
            } else {
                foreach ($this->option('table') as $option) {
                    if (\in_array($option, self::$allowedTables, false)) {
                        $this->info('Truncating '.$option.' table');
                        FixturesFacade::down($option);
                    }
                }
            }
        } else {
            $this->info('Command execution stopped');
        }
    }
}
