<?php

namespace App\Console;

use App\Console\Commands\TmuxUIStart;
use App\Console\Commands\TmuxUIStop;
use App\Console\Commands\UpdateNNTmux;
use App\Console\Commands\UpdateNNTmuxComposer;
use App\Console\Commands\UpdateNNTmuxDB;
use App\Console\Commands\UpdateNNTmuxGit;
use App\Console\Commands\VerifyNNTmuxSettings;
use App\Console\Commands\VerifyNNTmuxVersion;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        UpdateNNTmux::class,
		UpdateNNTmuxDB::class,
		UpdateNNTmuxGit::class,
		UpdateNNTmuxComposer::class,
		VerifyNNTmuxSettings::class,
		VerifyNNTmuxVersion::class,
		TmuxUIStart::class,
		TmuxUIStop::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();
    }

    /**
	 * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
		$this->load(__DIR__.'/Commands');

    	require base_path('routes/console.php');
    }
}
