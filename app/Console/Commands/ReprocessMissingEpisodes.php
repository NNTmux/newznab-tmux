<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Release;
use App\Services\TvProcessing\TvProcessingCandidateQuery;
use App\Services\TvProcessing\TvProcessingPipeline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Retry the episode lookup for TV releases whose show is known but whose episode
 * wasn't available yet (tv_episodes_id = -11). Scheduled every 30 minutes; each
 * release backs off 1h, 6h, 24h, 72h, then 7 days before giving up.
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
                            {--all : Ignore the retry schedule and attempt cap}
                            {--dry-run : Show how many releases would be processed without making changes}
                            {--debug : Show detailed debug information for each release}
                            {--sleep=0 : Sleep time in milliseconds between processing each release}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry the episode lookup for TV releases whose show matched but whose episode was missing';

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

        // Newest first, so episodes that just aired aren't queued behind an old backlog.
        // The run state still downloads each show's episode list only once.
        $query = Release::query()
            ->select(['id', 'guid', 'searchname', 'videos_id', 'tv_episodes_id', 'categories_id', 'groups_id', 'postdate'])
            ->whereRaw(TvProcessingCandidateQuery::episodeRetryCondition((bool) $this->option('all')))
            ->orderByDesc('postdate')
            ->orderByDesc('id');

        if ($videoId !== null) {
            $query->where('videos_id', (int) $videoId);
            $this->info("Filtering by video ID: {$videoId}");
        }

        $totalCount = (clone $query)->count();

        if ($totalCount === 0) {
            $this->info('No TV releases with missing episodes are due for a retry.');

            return self::SUCCESS;
        }

        $processCount = $limit > 0 ? min($limit, $totalCount) : $totalCount;
        if ($limit > 0) {
            $query->limit($limit);
        }
        $this->info("Found {$totalCount} TV release(s) with missing episodes due for a retry; processing {$processCount}.");

        if ($dryRun) {
            $sample = (clone $query)->limit(15)->get();
            $this->table(['ID', 'Video ID', 'Search Name'], $sample->map(fn ($release) => [
                $release->id,
                $release->videos_id,
                mb_substr($release->searchname, 0, 55).(mb_strlen($release->searchname) > 55 ? '...' : ''),
            ])->toArray());

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($processCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Matched: %matched% | Missing: %failed% | Elapsed: %elapsed:6s%');
        $bar->setMessage('0', 'matched');
        $bar->setMessage('0', 'failed');
        $bar->start();

        $matched = 0;
        $failed = 0;
        $processed = 0;

        $pipeline = app(TvProcessingPipeline::class)->setEchoOutput(false);
        $runState = $pipeline->newRunState();

        foreach ($query->cursor() as $release) {
            try {
                $result = $pipeline->processRelease($release, $debug, $runState);

                if ($result['matched']) {
                    $matched++;
                    if ($debug) {
                        $this->newLine();
                        cli()->primary("Episode matched: {$release->searchname} (".($result['provider'] ?? 'unknown').')');
                    }
                } else {
                    $failed++;
                    if ($debug) {
                        $this->newLine();
                        cli()->warning("Episode not found: {$release->searchname} ({$result['status']})");
                    }
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error("Error processing release {$release->guid}: ".$e->getMessage());
            }

            $processed++;
            $bar->setMessage((string) $matched, 'matched');
            $bar->setMessage((string) $failed, 'failed');
            $bar->advance();

            if ($sleep > 0) {
                usleep($sleep * 1000);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Total Processed', 'Episodes Matched', 'Still Missing', 'Match Rate'],
            [[
                number_format($processed),
                number_format($matched),
                number_format($failed),
                $processed > 0 ? round(($matched / $processed) * 100, 2).'%' : '0%',
            ]]
        );

        return self::SUCCESS;
    }
}
