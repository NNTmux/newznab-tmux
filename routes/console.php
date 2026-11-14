<?php

use App\Jobs\PurgeDeletedAccounts;
use App\Jobs\RemoveInactiveAccounts;
use App\Models\UserActivityStat;
use App\Models\UserDownload;
use App\Models\UserRequest;
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
// Automatically disable promotions that have passed their end_date
Schedule::command('nntmux:disable-expired-promotions')->daily();
// Automatically mark completed registration periods as done shortly after they end
Schedule::command('nntmux:disable-expired-registration-periods')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('nntmux:remove-bad')->hourly()->withoutOverlapping();
Schedule::command('cloudflare:reload')->daily()->withoutOverlapping();
Schedule::command('cache:prune-stale-tags')->hourly();
Schedule::command('gdpr:purge-expired-exports')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('nntmux:collect-stats')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('binaries:flush-aggregates')->everyMinute()->withoutOverlapping()->when(static fn (): bool => (bool) config('nntmux.defer_header_aggregates'));
Schedule::command('nntmux:populate-steam-apps')->monthly();
// Collect system metrics every 5 minutes
Schedule::command('metrics:collect')->everyFiveMinutes()->withoutOverlapping();
// Cleanup old system metrics daily (keep last 60 days)
Schedule::command('metrics:collect --cleanup')->dailyAt('03:00');
// Push NNTmux application metrics to the Prometheus Pushgateway (see config/monitoring.php)
Schedule::command('monitoring:export-metrics')->everyMinute()->withoutOverlapping()->when(static fn (): bool => (bool) config('monitoring.enabled'));
// Cleanup old user activity stats weekly (keep last 90 days)
Schedule::call(function () {
    UserActivityStat::cleanupOldStats(90);
})->weeklyOn(1, '04:00');
// Cleanup old hourly stats daily (keep last 30 days)
Schedule::call(function () {
    UserActivityStat::cleanupOldHourlyStats(30);
})->dailyAt('04:30');
if (config('nntmux.purge_inactive_users') === true) {
    Schedule::job(new RemoveInactiveAccounts)->daily();
    Schedule::job(new PurgeDeletedAccounts)->daily();
}

if (config('nntmux.nzb_import_folder') !== null) {
    Schedule::command('nntmux:import-nzbs --folder='.config('nntmux.nzb_import_folder').' --delete --filename')->everyFiveMinutes()->withoutOverlapping();
    // Import NFO files from the same folder (offset 2 min after NZB import)
    Schedule::command('nntmux:import-nfos --folder='.config('nntmux.nzb_import_folder').' --delete --delete-failed')->cron('2-59/5 * * * *')->withoutOverlapping();
}
// Cleanup old API requests and download logs (older than 1 day) - deferred from inline API calls
Schedule::call(function () {
    UserRequest::clearApiRequests(false);
    UserDownload::where('timestamp', '<', now()->subDay())->delete();
})->name('cleanup-api-request-logs')->hourly()->withoutOverlapping();
// Check tmux health and auto-restart if monitor pane is dead
Schedule::command('tmux:health-check --auto-restart')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('nntmux:check-service-health')->everyMinute()->withoutOverlapping();
Schedule::command('nntmux:search-repair --limit=100')->everyMinute()->withoutOverlapping();
// TV: retry episode lookups for shows whose episode wasn't available yet, and normalize
// any release still carrying a legacy TV processor status.
Schedule::command('tv:reprocess-missing-episodes --limit=2000')->everyThirtyMinutes()->withoutOverlapping()->runInBackground();
Schedule::command('tv:repair-links --legacy --stuck --orphans --limit=50000')->dailyAt('05:15')->withoutOverlapping();
// TV: link releases to episodes stored since they were processed, from local tables only (resumes where it stopped).
Schedule::command('tv:relink-local --limit=50000')->hourlyAt(40)->withoutOverlapping()->runInBackground();
Schedule::command('tv:relink-local --unmatched --limit=50000')->dailyAt('04:45')->withoutOverlapping()->runInBackground();
// Tail storage/logs into the Manticore log index used by the admin log viewer search
Schedule::command('nntmux:index-logs')->everyMinute()->withoutOverlapping()->runInBackground()->when(static fn (): bool => (bool) config('nntmux.log_viewer.index.enabled'));
// Keep the admin dashboard snapshot (Cache::flexible) hot so admins never pay
// the cold-cache cost when opening /admin/index.
Schedule::command('admin:warm-dashboard')->everyFifteenMinutes()->withoutOverlapping();
