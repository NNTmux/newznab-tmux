<?php

namespace App\Console\Commands;

use App\Models\UserActivityStat;
use App\Models\UserDownload;
use App\Models\UserRequest;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillUserActivityStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:backfill-user-activity-stats
                            {--days=30 : Number of days to backfill}
                            {--force : Force backfill even if data already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill user activity stats from existing user_downloads and user_requests data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $force = $this->option('force');

        $this->info("Backfilling user activity stats for the last {$days} days...");

        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
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

        $this->info('Backfill complete!');
        $this->info("Stats collected: {$statsCollected}");
        if ($statsSkipped > 0) {
            $this->info("Stats skipped (already existed): {$statsSkipped}");
        }

        return Command::SUCCESS;
    }
}
