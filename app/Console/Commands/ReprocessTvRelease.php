<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Release;
use App\Services\TvProcessing\TvProcessingCandidateQuery;
use App\Services\TvProcessing\TvProcessingPipeline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReprocessTvRelease extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tv:reprocess
                            {guid : The release GUID to reprocess}
                            {--reset : Reset the release video/episode IDs before reprocessing}
                            {--debug : Show detailed debug information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reprocess a specific TV release by GUID to rematch it against TV databases';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $guid = $this->argument('guid');
        $reset = $this->option('reset');
        $debug = $this->option('debug');

        // Find the release by GUID
        $release = Release::query()->where('guid', $guid)->first();

        if ($release === null) {
            $this->error("Release with GUID '{$guid}' not found.");

            return self::FAILURE;
        }

        $this->info('Found release:');
        $this->table(
            ['ID', 'GUID', 'Search Name', 'Videos ID', 'Episode ID', 'Category'],
            [[
                $release->id,
                $release->guid,
                mb_substr($release->searchname, 0, 60).(strlen($release->searchname) > 60 ? '...' : ''),
                $release->videos_id,
                $release->tv_episodes_id,
                $release->categories_id,
            ]]
        );

        if ($reset) {
            $this->warn('Resetting videos_id, tv_episodes_id and the lookup retry state...');
            $release->update(TvProcessingCandidateQuery::resetColumns());
            $release->refresh();
        }

        $this->info('Processing release through TV pipeline...');
        $this->newLine();

        try {
            $result = app(TvProcessingPipeline::class)->processRelease($release, (bool) $debug);

            $this->newLine();

            if ($result['matched']) {
                cli()->primary('Successfully matched!');
                $this->info('Provider: '.($result['provider'] ?? 'Unknown'));
                $this->info('Video ID: '.($result['video_id'] ?? 'N/A'));
                $this->info('Episode ID: '.($result['episode_id'] ?? 'N/A'));
            } else {
                cli()->warning('No episode match.');
                $this->info('Status: '.($result['status'] ?? 'Unknown'));
                if (! empty($result['video_id'])) {
                    $this->info('Video ID: '.$result['video_id']);
                }
                if (! empty($result['provider'])) {
                    $this->info('Provider: '.$result['provider']);
                }
            }

            if ($debug && isset($result['debug'])) {
                $this->newLine();
                $this->info('Debug Information:');
                $this->line(json_encode($result['debug'], JSON_PRETTY_PRINT));
            }

            // Show final state of the release
            $release->refresh();
            $this->newLine();
            $this->info('Final release state:');
            $this->table(
                ['Videos ID', 'Episode ID', 'Lookup attempts', 'Retry at'],
                [[$release->videos_id, $release->tv_episodes_id, $release->tv_lookup_attempts, $release->tv_lookup_retry_at ?? '-']]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error($e->getTraceAsString());
            $this->error('Error processing release: '.$e->getMessage());
            if ($debug) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }
}
