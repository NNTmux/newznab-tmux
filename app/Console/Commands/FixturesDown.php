<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Mayconbordin\L5Fixtures\FixturesFacade;

class FixturesDown extends Command
{
    /**
     * @var array
     */
    private static $allowedTables = [
        'binaryblacklist',
        'categories',
        'category_regexes',
        'collection_regexes',
        'content',
        'groups',
        'menu',
        'release_naming_regexes',
        'settings',
        'tmux',
    ];
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fixtures:down {type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Truncate(empty) all tables or just select ones.
    Tables that are supported are :
    all <= Populates all the tables listed below
    binaryblacklist
    categories
    category_regexes
    collection_regexes
    content
    groups
    menu
    release_naming_regexes
    settings
    tmux';

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
            $this->info('Truncating '.$this->argument('type').' table(s)');
            if ($this->argument('type') === 'all') {
                FixturesFacade::down();
            } elseif (\in_array($this->argument('type'), self::$allowedTables, false)) {
                FixturesFacade::down($this->argument('type'));
            }
        } else {
            $this->info('Command execution stopped');
        }
    }
}
