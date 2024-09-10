<?php

namespace App\Console\Commands;

use App\Models\DownloadStat;
use App\Models\GrabStat;
use App\Models\ReleaseStat;
use App\Models\RoleStat;
use App\Models\SignupStat;
use Illuminate\Console\Command;

class CollectStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:collect-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collects and stores various statistics about the site.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Collecting site stats...');
        GrabStat::insertTopGrabbers();
        $this->info('Top grabbers collected.');
        DownloadStat::insertTopDownloads();
        $this->info('Top downloads collected.');
        ReleaseStat::insertRecentlyAdded();
        $this->info('Recently added releases collected.');
        SignupStat::insertUsersByMonth();
        $this->info('New users by month collected.');
        RoleStat::insertUsersByRole();
        $this->info('Users by role collected.');
        $this->info('Site stats collected.');
    }
}
