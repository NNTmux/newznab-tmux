<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Mayconbordin\L5Fixtures\Fixtures;
use Mayconbordin\L5Fixtures\FixturesFacade;

class FixturesUp extends Command
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
    protected $signature = 'fixtures:up {type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply database fixtures to all tables or just to select ones.
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
        $this->info('Populating '.$this->argument('type').' table(s)');

        if ($this->argument('type') === 'all') {
            FixturesFacade::up();
        } elseif (\in_array($this->argument('type'), self::$allowedTables, false)) {
            FixturesFacade::up($this->argument('type'));
        }
    }
}
