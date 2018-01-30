<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Mayconbordin\L5Fixtures\FixturesFacade;

class FixturesDown extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fixtures:down';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
            FixturesFacade::down();
        } else {
            $this->info('Command execution stopped');
        }
    }
}
