<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserActivityStat;
use App\Models\UserDownload;
use App\Models\UserRequest;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillUserActivityStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:backfill-user-activity-stats
                            {--type=daily : Type of stats to backfill (daily or hourly)}
                            {--days=30 : Number of days to backfill (for daily stats)}
                            {--hours=24 : Number of hours to backfill (for hourly stats)}
                            {--force : Force backfill even if data already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill user activity stats (daily or hourly) from existing user_downloads and user_requests data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');
        $force = $this->option('force');

        if (! in_array($type, ['daily', 'hourly'])) {
            $this->error("Invalid type '{$type}'. Must be 'daily' or 'hourly'.");

            return Command::FAILURE;
        }

        if ($type === 'daily') {
            return $this->backfillDaily($force);
        } else {
            return $this->backfillHourly($force);
        }
    }

    /**
     * Backfill daily stats
     */
    protected function backfillDaily(bool $force): int
    {
        $days = (int) $this->option('days');

        $this->info("Backfilling daily user activity stats for the last {$days} days...");

        $progressBar = $this->output->createProgressBar($days);

        $statsCollected = 0;
        $statsSkipped = 0;

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');

            // Check if stats already exist for this date
            if (! $force && UserActivityStat::where('stat_date', $date)->exists()) {
                $statsSkipped++;
                $progressBar->advance();

                continue;
            }

            // Count downloads for the date
            $downloadsCount = UserDownload::query()
                ->whereRaw('DATE(timestamp) = ?', [$date])
                ->count();

            // Count API hits for the date
            $apiHitsCount = UserRequest::query()
                ->whereRaw('DATE(timestamp) = ?', [$date])
                ->count();

            // Store or update the stats
            UserActivityStat::updateOrCreate(
                ['stat_date' => $date],
                [
                    'downloads_count' => $downloadsCount,
                    'api_hits_count' => $apiHitsCount,
                ]
            );

            $statsCollected++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Daily backfill complete!');
        $this->info("Stats collected: {$statsCollected}");
        if ($statsSkipped > 0) {
            $this->info("Stats skipped (already existed): {$statsSkipped}");
        }

        return Command::SUCCESS;
    }

    /**
     * Backfill hourly stats
     */
    protected function backfillHourly(bool $force): int
    {
        $hours = (int) $this->option('hours');

        $this->info("Backfilling hourly user activity stats for the last {$hours} hours...");

        $progressBar = $this->output->createProgressBar($hours);

        $statsCollected = 0;
        $statsSkipped = 0;

        for ($i = $hours - 1; $i >= 0; $i--) {
            $hour = Carbon::now()->subHours($i)->startOfHour()->format('Y-m-d H:00:00');

            // Check if stats already exist for this hour
            if (! $force && DB::table('user_activity_stats_hourly')->where('stat_hour', $hour)->exists()) {
                $statsSkipped++;
                $progressBar->advance();

                continue;
            }

            // Count downloads for the hour
            $downloadsCount = UserDownload::query()
                ->where('timestamp', '>=', $hour)
                ->where('timestamp', '<', Carbon::parse($hour)->addHour()->format('Y-m-d H:00:00'))
                ->count();

            // Count API hits for the hour
            $apiHitsCount = UserRequest::query()
                ->where('timestamp', '>=', $hour)
                ->where('timestamp', '<', Carbon::parse($hour)->addHour()->format('Y-m-d H:00:00'))
                ->count();

            // Store or update the stats
            DB::table('user_activity_stats_hourly')->updateOrInsert(
                ['stat_hour' => $hour],
                [
                    'downloads_count' => $downloadsCount,
                    'api_hits_count' => $apiHitsCount,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );

            $statsCollected++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Hourly backfill complete!');
        $this->info("Stats collected: {$statsCollected}");
        if ($statsSkipped > 0) {
            $this->info("Stats skipped (already existed): {$statsSkipped}");
        }

        return Command::SUCCESS;
    }
}
