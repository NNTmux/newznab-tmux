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
// Cleanup old API requests and download logs (older than 1 day) - deferred from inline API calls
Schedule::call(function () {
    UserRequest::clearApiRequests(false);
    UserDownload::where('timestamp', '<', now()->subDay())->delete();
})->name('cleanup-api-request-logs')->hourly()->withoutOverlapping();
// Check tmux health and auto-restart if monitor pane is dead
Schedule::command('tmux:health-check --auto-restart')->everyThirtyMinutes()->withoutOverlapping()->when(static fn (): bool => (bool) config('tmux.scheduled_health_check'));
Schedule::command('nntmux:check-service-health')->everyMinute()->withoutOverlapping();
Schedule::command('nntmux:search-repair --limit=100')->everyMinute()->withoutOverlapping();
// Tail storage/logs into the Manticore log index used by the admin log viewer search
Schedule::command('nntmux:index-logs')->everyMinute()->withoutOverlapping()->runInBackground()->when(static fn (): bool => (bool) config('nntmux.log_viewer.index.enabled'));
// Keep the admin dashboard snapshot (Cache::flexible) hot so admins never pay
// the cold-cache cost when opening /admin/index.
Schedule::command('admin:warm-dashboard')->everyFifteenMinutes()->withoutOverlapping();
// Poll public PreDB JSON/RSS feeds (config/predb_feeds.php); the IRC scraper remains the realtime source
Schedule::command('predb:import-feed --quiet')->everyFiveMinutes()->withoutOverlapping()->runInBackground()->when(static fn (): bool => (bool) config('predb_feeds.enabled'));
// Link releases to PreDB titles that arrived after the release was created (feeds, IRC, history imports).
// Only recent releases are scanned; the old ones are covered by running `predb:check` by hand.
Schedule::command('predb:check 3 --quiet')->everyTenMinutes()->withoutOverlapping()->runInBackground();
// Match each new PreDB title against the raw subjects of existing releases (full-text). A run takes up to
// `fix_names_per_run` titles per worker (a few seconds). The tmux fix-names chain also ends with this pass,
// but its slow NNTP passes can run for an hour or more, so on its own it reached only about 100 titles a day;
// the tmux cycle skips it while this is on (TMUX_FIX_NAMES_PREDBFT_SCHEDULED), so there is one launcher.
// TMUX_FIX_NAMES_PREDBFT=false still turns the pass off entirely.
Schedule::command('multiprocessing:fixrelnames predbft')->everyFiveMinutes()->withoutOverlapping()->runInBackground()
    ->when(static fn (): bool => (bool) config('tmux.fix_names.predbft') && (bool) config('tmux.fix_names.predbft_scheduled'));
