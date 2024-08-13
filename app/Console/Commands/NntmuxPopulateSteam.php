<?php

namespace App\Console\Commands;

use Blacklight\Steam;
use Illuminate\Console\Command;

class NntmuxPopulateSteam extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:populate-steam';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate Steam Apps table';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $steam = new Steam;

        $steam->populateSteamAppsTable();
    }
}
