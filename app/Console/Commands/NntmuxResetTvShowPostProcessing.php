<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Release;
use App\Models\Video;
use Illuminate\Console\Command;

class NntmuxResetTvShowPostProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * videos_id: ID from videos table (type = 0 TV) whose releases should be reset.
     * --dry-run: Show how many releases would be reset without making changes.
     */
    protected $signature = 'nntmux:reset-tv-show {videos_id : ID of the TV show (videos.id)} {--dry-run : Show counts only, do not modify releases}';

    /**
     * The console command description.
     */
    protected $description = 'Reset postprocessing for all TV releases belonging to a specific videos_id so they can be reprocessed again.';

    public function handle(): int
    {
        $videosId = (int) $this->argument('videos_id');
        $dryRun = (bool) $this->option('dry-run');

        // Validate target show exists and is TV type (0)
        $video = Video::query()->where('id', $videosId)->where('type', 0)->first(['id', 'title']);
        if ($video === null) {
            $this->error("videos_id {$videosId} not found or is not a TV show (type=0)");

            return self::FAILURE;
        }

        // Gather releases to reset
        $baseQuery = Release::query()
            ->select(['id'])
            ->where('videos_id', $videosId)
            ->whereBetween('categories_id', [Category::TV_ROOT, Category::TV_OTHER]);

        $total = (clone $baseQuery)->count();
        if ($total === 0) {
            $this->info('No releases found for this videos_id in TV categories');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->line("[Dry Run] Would reset {$total} release(s) for TV show: {$video->title} (videos_id={$videosId})");

            return self::SUCCESS;
        }

        $this->info("Resetting postprocessing for TV show: {$video->title} (videos_id={$videosId})");

        // Progress bar
        $bar = $this->output->createProgressBar($total);
        $bar->setOverwrite(true);
        $bar->start();

        // Chunk through releases to avoid loading all models simultaneously
        $baseQuery->chunkById(1000, function ($chunk) use ($bar) {
            foreach ($chunk as $release) {
                Release::query()->where('id', $release->id)->update([
                    // Reset association so TV pipeline picks them up again
                    'videos_id' => 0,
                    'tv_episodes_id' => 0,
                ]);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info(number_format($total).' release(s) reset. They will be considered for TV postprocessing again.');
        $this->line('You can now run: php artisan postprocess:tv-pipeline <guid-bucket> (or your existing TV processing routines)');

        return self::SUCCESS;
    }
}
