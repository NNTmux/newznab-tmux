<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Release;
use App\Services\TvProcessing\TvProcessingPipeline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reprocess TV releases that have a matched show (videos_id > 0) but no matched episode (tv_episodes_id = 0).
 * This can happen when the show was found but episodes weren't in the local database at the time.
 */
class ReprocessMissingEpisodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tv:reprocess-missing-episodes
                            {--limit=0 : Limit the number of releases to process (0 = no limit)}
                            {--video-id= : Process only releases for a specific video ID}
                            {--dry-run : Show how many releases would be processed without making changes}
                            {--debug : Show detailed debug information for each release}
                            {--sleep=0 : Sleep time in milliseconds between processing each release}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reprocess TV releases with matched shows but missing episode matches (videos_id > 0, tv_episodes_id = 0)';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $videoId = $this->option('video-id');
        $dryRun = (bool) $this->option('dry-run');
        $debug = (bool) $this->option('debug');
        $sleep = (int) $this->option('sleep');

        // Build query for TV releases with matched show but no episode match
        $query = Release::query()
            ->select(['id', 'guid', 'searchname', 'videos_id', 'tv_episodes_id', 'categories_id', 'adddate', 'postdate'])
            ->where('videos_id', '>', 0)
            ->where('tv_episodes_id', 0)
            ->whereBetween('categories_id', [Category::TV_ROOT, Category::TV_OTHER])
            ->orderBy('adddate', 'desc')
            ->orderBy('postdate', 'desc');

        if ($videoId !== null) {
            $query->where('videos_id', (int) $videoId);
            $this->info("Filtering by video ID: {$videoId}");
        }

        $totalCount = (clone $query)->count();

        if ($totalCount === 0) {
            $this->info('No TV releases with missing episode matches found.');
            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} TV release(s) with missing episode matches.");

        if ($limit > 0) {
            $query->limit($limit);
            $processCount = min($limit, $totalCount);
            $this->info("Processing limited to {$processCount} release(s).");
        } else {
            $processCount = $totalCount;
        }

        if ($dryRun) {
            $this->line("[Dry Run] Would process {$processCount} TV release(s) with missing episode matches.");

            // Show sample of releases that would be processed
            $sample = (clone $query)->limit(15)->get();
            if ($sample->isNotEmpty()) {
                $this->newLine();
                $this->info('Sample of releases to be processed:');
                $rows = $sample->map(fn ($release) => [
                    $release->id,
                    $release->videos_id,
                    mb_substr($release->searchname, 0, 55) . (strlen($release->searchname) > 55 ? '...' : ''),
                ])->toArray();
                $this->table(['ID', 'Video ID', 'Search Name'], $rows);
            }

            return self::SUCCESS;
        }

        $this->info("Starting to process {$processCount} TV release(s) with missing episode matches...");
        $this->newLine();

        $bar = $this->output->createProgressBar($processCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Matched: %matched% | Failed: %failed% | Elapsed: %elapsed:6s%');
        $bar->setMessage('0', 'matched');
        $bar->setMessage('0', 'failed');
        $bar->start();

        $matched = 0;
        $failed = 0;
        $processed = 0;

        try {
            $pipeline = TvProcessingPipeline::createDefault(echoOutput: false);

            // Use cursor to iterate without chunking issues when ordering by non-id columns
            $cursor = $query->cursor();

            foreach ($cursor as $release) {
                if ($limit > 0 && $processed >= $limit) {
                    break;
                }

                try {
                    $result = $pipeline->processRelease($release, $debug);

                    // Check if episode was matched (episode_id > 0)
                    $episodeMatched = isset($result['episode_id']) && $result['episode_id'] > 0;

                    if ($episodeMatched) {
                        $matched++;
                        if ($debug) {
                            $this->newLine();
                            cli()->primary("Episode matched: {$release->searchname}");
                            $this->info('  Provider: ' . ($result['provider'] ?? 'Unknown'));
                            $this->info('  Video ID: ' . ($result['video_id'] ?? 'N/A'));
                            $this->info('  Episode ID: ' . ($result['episode_id'] ?? 'N/A'));
                        }
                    } else {
                        $failed++;
                        if ($debug) {
                            $this->newLine();
                            cli()->warning("Episode not found: {$release->searchname}");
                        }
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error("Error processing release {$release->guid}: " . $e->getMessage());
                    if ($debug) {
                        $this->newLine();
                        $this->error("Error processing {$release->searchname}: " . $e->getMessage());
                    }
                }

                $processed++;
                $bar->setMessage((string) $matched, 'matched');
                $bar->setMessage((string) $failed, 'failed');
                $bar->advance();

                if ($sleep > 0) {
                    usleep($sleep * 1000);
                }
            }

        } catch (\Throwable $e) {
            $bar->finish();
            $this->newLine();
            $this->error('Fatal error during processing: ' . $e->getMessage());
            Log::error('Fatal error in tv:reprocess-missing-episodes: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Processing complete!');
        $this->table(
            ['Total Processed', 'Episodes Matched', 'Not Matched', 'Match Rate'],
            [[
                number_format($processed),
                number_format($matched),
                number_format($failed),
                $processed > 0 ? round(($matched / $processed) * 100, 2) . '%' : '0%',
            ]]
        );

        return self::SUCCESS;
    }
}

