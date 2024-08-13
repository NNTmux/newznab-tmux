<?php

namespace App\Console\Commands;

use Blacklight\Steam;
use Illuminate\Console\Command;

class NntmuxPopulateSteamApps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:populate-steam-apps';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate Steam apps table';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        try {
            (new Steam)->populateSteamAppsTable();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->error('There was an error populating the steam_apps table');
        }
    }
}
