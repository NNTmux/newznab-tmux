<?php

use App\Jobs\PurgeDeletedAccounts;
use App\Jobs\RemoveInactiveAccounts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('disposable:update')->weekly();
Schedule::command('clean:directories')->hourly()->withoutOverlapping();
Schedule::command('nntmux:delete-unverified-users')->twiceDaily(1, 13);
Schedule::command('nntmux:update-expired-roles')->daily();
Schedule::command('nntmux:remove-bad')->hourly()->withoutOverlapping();
Schedule::command('telescope:prune')->daily();
Schedule::command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('cloudflare:reload')->daily();
Schedule::command('cache:prune-stale-tags')->hourly();
Schedule::command('nntmux:collect-stats')->hourly();
Schedule::command('nntmux:populate-steam-apps')->monthly();
// Collect system metrics every 5 minutes
Schedule::command('metrics:collect')->everyFiveMinutes()->withoutOverlapping();
// Cleanup old system metrics daily (keep last 60 days)
Schedule::command('metrics:collect --cleanup')->dailyAt('03:00');
// Cleanup old user activity stats weekly (keep last 90 days)
Schedule::call(function () {
    \App\Models\UserActivityStat::cleanupOldStats(90);
})->weeklyOn(1, '04:00');
if (config('nntmux.purge_inactive_users') === true) {
    Schedule::job(new RemoveInactiveAccounts)->daily();
    Schedule::job(new PurgeDeletedAccounts)->daily();
}
