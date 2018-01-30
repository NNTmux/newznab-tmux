<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Mayconbordin\L5Fixtures\Fixtures;
use Mayconbordin\L5Fixtures\FixturesFacade;

class FixturesUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fixtures:up';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply database fixtures';

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
        $this->info('Populating tables...');
        FixturesFacade::up();
    }
}
