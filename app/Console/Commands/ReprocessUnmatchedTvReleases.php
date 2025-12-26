<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Release;
use App\Services\TvProcessing\TvProcessingPipeline;
use Blacklight\ColorCLI;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReprocessUnmatchedTvReleases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tv:reprocess-unmatched
                            {--limit=0 : Limit the number of releases to process (0 = no limit)}
                            {--dry-run : Show how many releases would be processed without making changes}
                            {--debug : Show detailed debug information for each release}
                            {--sleep=0 : Sleep time in milliseconds between processing each release}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reprocess all TV releases that are not matched to any show in the database (videos_id = 0)';

    protected ColorCLI $colorCli;

    public function __construct()
    {
        parent::__construct();
        $this->colorCli = new ColorCLI();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $debug = (bool) $this->option('debug');
        $sleep = (int) $this->option('sleep');

        // Build query for unmatched TV releases
        $query = Release::query()
            ->select(['id', 'guid', 'searchname', 'videos_id', 'tv_episodes_id', 'categories_id'])
            ->where('videos_id', 0)
            ->whereBetween('categories_id', [Category::TV_ROOT, Category::TV_OTHER])
            ->orderBy('id', 'desc');

        $totalCount = (clone $query)->count();

        if ($totalCount === 0) {
            $this->info('No unmatched TV releases found.');
            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} unmatched TV release(s).");

        if ($limit > 0) {
            $query->limit($limit);
            $processCount = min($limit, $totalCount);
            $this->info("Processing limited to {$processCount} release(s).");
        } else {
            $processCount = $totalCount;
        }

        if ($dryRun) {
            $this->line("[Dry Run] Would process {$processCount} unmatched TV release(s).");

            // Show sample of releases that would be processed
            $sample = (clone $query)->limit(10)->get();
            if ($sample->isNotEmpty()) {
                $this->newLine();
                $this->info('Sample of releases to be processed:');
                $rows = $sample->map(fn ($release) => [
                    $release->id,
                    $release->guid,
                    mb_substr($release->searchname, 0, 60) . (strlen($release->searchname) > 60 ? '...' : ''),
                    $release->categories_id,
                ])->toArray();
                $this->table(['ID', 'GUID', 'Search Name', 'Category'], $rows);
            }

            return self::SUCCESS;
        }

        $this->info("Starting to process {$processCount} unmatched TV release(s)...");
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

            $query->chunkById(100, function ($releases) use ($pipeline, $debug, $sleep, $bar, &$matched, &$failed, &$processed, $limit) {
                foreach ($releases as $release) {
                    if ($limit > 0 && $processed >= $limit) {
                        return false; // Stop chunking
                    }

                    try {
                        $result = $pipeline->processRelease($release, $debug);

                        if ($result['matched']) {
                            $matched++;
                            if ($debug) {
                                $this->newLine();
                                $this->colorCli->primary("Matched: {$release->searchname}");
                                $this->info('  Provider: ' . ($result['provider'] ?? 'Unknown'));
                                $this->info('  Video ID: ' . ($result['video_id'] ?? 'N/A'));
                                $this->info('  Episode ID: ' . ($result['episode_id'] ?? 'N/A'));
                            }
                        } else {
                            $failed++;
                            if ($debug) {
                                $this->newLine();
                                $this->colorCli->warning("No match: {$release->searchname}");
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

                return true;
            });

        } catch (\Throwable $e) {
            $bar->finish();
            $this->newLine();
            $this->error('Fatal error during processing: ' . $e->getMessage());
            Log::error('Fatal error in tv:reprocess-unmatched: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Processing complete!');
        $this->table(
            ['Total Processed', 'Matched', 'Not Matched', 'Match Rate'],
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

