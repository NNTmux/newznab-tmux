<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Search;
use App\Models\Release;
use App\Models\TvEpisode;
use App\Models\Video;
use App\Services\TvProcessing\Providers\LocalDbProvider;
use App\Services\TvProcessing\TvProcessingPipeline;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

#[Signature('tv:fix-loki-2021
                {--dry-run : Preview matching releases without API calls or database writes}
                {--debug : Show provider diagnostics while reprocessing releases}')]
#[Description('Reprocess Loki (2021) releases and retain only matches to TVDB series 362472')]
class FixLoki2021TvMatches extends Command
{
    private const string TARGET_CLEAN_NAME = 'Loki (2021)';

    private const int TARGET_TVDB_ID = 362472;

    public function handle(LocalDbProvider $parser, TvProcessingPipeline $pipeline): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $debug = (bool) $this->option('debug');
        $candidates = $this->findCandidates($parser);

        if ($candidates->isEmpty()) {
            $this->info('No releases parsed as '.self::TARGET_CLEAN_NAME.' were found.');

            return self::SUCCESS;
        }

        $alreadyCorrect = $candidates->filter(fn (Release $release): bool => $this->hasCorrectAssociation($release));
        $releasesToRepair = $candidates->reject(fn (Release $release): bool => $this->hasCorrectAssociation($release))->values();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Candidates', $candidates->count()],
                ['Already correct', $alreadyCorrect->count()],
                ['Need repair', $releasesToRepair->count()],
            ]
        );

        if ($dryRun) {
            $this->displayPreview($candidates);
            $this->warn('Dry run complete. No provider calls or database writes were performed.');

            return self::SUCCESS;
        }

        if ($releasesToRepair->isEmpty()) {
            $this->info('All Loki (2021) releases already point to TVDB '.self::TARGET_TVDB_ID.'.');

            return self::SUCCESS;
        }

        $pipeline->setEchoOutput($debug);

        $repaired = 0;
        $failed = 0;
        $episodeLinksCleared = 0;
        $searchSyncFailures = 0;
        $progressBar = $this->output->createProgressBar($releasesToRepair->count());
        $progressBar->start();

        foreach ($releasesToRepair as $release) {
            $this->clearTvAssociation($release);

            try {
                $pipeline->processRelease($release, $debug);
            } catch (\Throwable $e) {
                $failed++;
                $this->clearTvAssociation($release);
                $this->reportProcessingFailure($release, $e, $debug);
                $this->syncRelease($release, $searchSyncFailures);
                $progressBar->advance();

                continue;
            }

            $release->refresh()->load(['video:id,tvdb', 'episode:id,videos_id']);

            if ($this->currentTvdbId($release) !== self::TARGET_TVDB_ID) {
                $failed++;
                $this->clearTvAssociation($release);
                $this->reportRejectedMatch($release, $debug);
            } else {
                if ($this->hasMismatchedEpisode($release)) {
                    Release::query()->whereKey($release->id)->update(['tv_episodes_id' => 0]);
                    $release->tv_episodes_id = 0;
                    $episodeLinksCleared++;
                }

                $repaired++;
            }

            $this->syncRelease($release, $searchSyncFailures);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Candidates', $candidates->count()],
                ['Already correct', $alreadyCorrect->count()],
                ['Repaired', $repaired],
                ['Failures', $failed],
                ['Invalid episode links cleared', $episodeLinksCleared],
                ['Search sync failures', $searchSyncFailures],
            ]
        );

        if ($failed > 0 || $searchSyncFailures > 0) {
            $this->error('Repair finished with failures. Unverified TV associations were left cleared.');

            return self::FAILURE;
        }

        $this->info('Repair complete. Every candidate now points to TVDB '.self::TARGET_TVDB_ID.'.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Release>
     */
    private function findCandidates(LocalDbProvider $parser): Collection
    {
        return Release::query()
            ->select([
                'id',
                'guid',
                'leftguid',
                'groups_id',
                'searchname',
                'size',
                'categories_id',
                'videos_id',
                'tv_episodes_id',
                'postdate',
            ])
            ->with(['video:id,tvdb', 'episode:id,videos_id'])
            ->whereRaw('LOWER(searchname) LIKE ?', ['%loki%'])
            ->where('searchname', 'like', '%2021%')
            ->orderBy('id')
            ->get()
            ->filter(function (Release $release) use ($parser): bool {
                $parsedInfo = $parser->parseInfo((string) $release->searchname);

                return is_array($parsedInfo)
                    && strcasecmp((string) ($parsedInfo['cleanname'] ?? ''), self::TARGET_CLEAN_NAME) === 0;
            })
            ->values();
    }

    private function hasCorrectAssociation(Release $release): bool
    {
        if ($this->currentTvdbId($release) !== self::TARGET_TVDB_ID) {
            return false;
        }

        return ! $this->hasMismatchedEpisode($release);
    }

    private function hasMismatchedEpisode(Release $release): bool
    {
        $episode = $release->getRelation('episode');

        return (int) $release->tv_episodes_id > 0
            && (! $episode instanceof TvEpisode || (int) $episode->videos_id !== (int) $release->videos_id);
    }

    private function currentTvdbId(Release $release): int
    {
        $video = $release->getRelation('video');

        return $video instanceof Video ? (int) $video->tvdb : 0;
    }

    /**
     * @param  Collection<int, Release>  $candidates
     */
    private function displayPreview(Collection $candidates): void
    {
        $rows = $candidates
            ->take(20)
            ->map(fn (Release $release): array => [
                $release->id,
                mb_substr((string) $release->searchname, 0, 70),
                $this->currentTvdbId($release),
                $release->tv_episodes_id,
                $this->hasCorrectAssociation($release) ? 'already correct' : 'repair',
            ])
            ->all();

        $this->newLine();
        $this->table(['ID', 'Search name', 'Current TVDB', 'Episode ID', 'Action'], $rows);

        if ($candidates->count() > 20) {
            $this->line('... and '.($candidates->count() - 20).' more candidate(s).');
        }
    }

    private function clearTvAssociation(Release $release): void
    {
        Release::query()->whereKey($release->id)->update([
            'videos_id' => 0,
            'tv_episodes_id' => 0,
        ]);

        $release->videos_id = 0;
        $release->tv_episodes_id = 0;
        $release->unsetRelation('video');
        $release->unsetRelation('episode');
    }

    private function reportProcessingFailure(Release $release, \Throwable $exception, bool $debug): void
    {
        Log::error('Failed to reprocess Loki (2021) release', [
            'release_id' => $release->id,
            'guid' => $release->guid,
            'error' => $exception->getMessage(),
        ]);

        if ($debug) {
            $this->newLine();
            $this->error(sprintf('Release %d failed: %s', $release->id, $exception->getMessage()));
        }
    }

    private function reportRejectedMatch(Release $release, bool $debug): void
    {
        Log::warning('Loki (2021) release did not resolve to the required TVDB series', [
            'release_id' => $release->id,
            'guid' => $release->guid,
            'required_tvdb' => self::TARGET_TVDB_ID,
        ]);

        if ($debug || ! $this->output->isQuiet()) {
            $this->newLine();
            $this->warn(sprintf(
                'Release %d did not resolve to TVDB %d; its TV association was cleared.',
                $release->id,
                self::TARGET_TVDB_ID
            ));
        }
    }

    private function syncRelease(Release $release, int &$searchSyncFailures): void
    {
        try {
            Search::updateRelease((int) $release->id);
        } catch (\Throwable $e) {
            $searchSyncFailures++;

            Log::warning('Failed to sync Loki (2021) release after TV repair', [
                'release_id' => $release->id,
                'guid' => $release->guid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
