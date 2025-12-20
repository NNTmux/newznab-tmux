<?php

namespace App\Console\Commands;

use App\Services\SteamService;
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
            $this->info('Populating steam apps table...');

            $service = new SteamService;
            $stats = $service->populateSteamAppsTable(function ($processed, $total) {
                if ($processed % 1000 === 0) {
                    $this->info("Processed {$processed} of {$total} apps");
                }
            });

            $this->info(sprintf(
                'Added %d new steam app(s), %d skipped, %d errors',
                $stats['inserted'],
                $stats['skipped'],
                $stats['errors']
            ));
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->error('There was an error populating the steam_apps table');
        }
    }
}
