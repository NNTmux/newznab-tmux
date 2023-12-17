<?php

namespace App\Console;

use App\Jobs\RemoveInactiveAccounts;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Spatie\WebhookClient\Models\WebhookCall;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('disposable:update')->weekly();
        $schedule->command('clean:directories')->hourly()->withoutOverlapping();
        $schedule->command('nntmux:delete-unverified-users')->twiceDaily(1, 13);
        $schedule->command('nntmux:update-expired-roles')->daily();
        $schedule->command('nntmux:remove-bad')->hourly()->withoutOverlapping();
        $schedule->command('telescope:prune')->daily();
        $schedule->command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('cloudflare:reload')->daily();
        $schedule->command('cache:prune-stale-tags')->hourly();
        if (config('nntmux.purge_inactive_users') === true) {
            $schedule->job(new RemoveInactiveAccounts())->daily();
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
