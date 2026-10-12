<?php

declare(strict_types=1);

namespace App\Services\TvProcessing;

use App\Models\Release;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\TvProcessing\Pipes\AbstractTvProviderPipe;
use App\Services\TvProcessing\Pipes\LocalDbPipe;
use App\Services\TvProcessing\Pipes\ParseInfoPipe;
use App\Services\TvProcessing\Pipes\TmdbPipe;
use App\Services\TvProcessing\Pipes\TraktPipe;
use App\Services\TvProcessing\Pipes\TvdbPipe;
use App\Services\TvProcessing\Pipes\TvMazePipe;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Matches TV releases to shows and episodes by passing each one through the
 * provider pipes (parse → local DB → TVDB → TVMaze → TMDB → Trakt) and
 * persisting the outcome with TvLookupOutcomeWriter.
 */
class TvProcessingPipeline
{
    /**
     * @var Collection<int, AbstractTvProviderPipe>
     */
    protected Collection $pipes;

    protected bool $echoOutput;

    protected TvLookupOutcomeWriter $writer;

    /**
     * @var array{processed: int, matched: int, episode_missing: int, not_found: int, deferred: int, duration: float, providers: array<string, int>}
     */
    protected array $stats;

    /**
     * @param  iterable<AbstractTvProviderPipe>  $pipes
     */
    public function __construct(iterable $pipes = [], bool $echoOutput = true, ?TvLookupOutcomeWriter $writer = null)
    {
        $this->pipes = collect($pipes)
            ->sortBy(fn (AbstractTvProviderPipe $p) => $p->getPriority())
            ->values();

        $this->echoOutput = $echoOutput;
        $this->writer = $writer ?? new TvLookupOutcomeWriter;
        $this->resetStats();
    }

    /**
     * Add a provider pipe to the pipeline.
     */
    public function addPipe(AbstractTvProviderPipe $pipe): self
    {
        $this->pipes->push($pipe);
        $this->pipes = $this->pipes->sortBy(fn (AbstractTvProviderPipe $p) => $p->getPriority())->values();

        return $this;
    }

    public function setEchoOutput(bool $echoOutput): self
    {
        $this->echoOutput = $echoOutput;

        return $this;
    }

    /**
     * Shared memo for a batch of releases passed to processRelease().
     */
    public function newRunState(): TvProcessingRunState
    {
        return new TvProcessingRunState;
    }

    /**
     * Process a single release through the pipeline and persist the outcome.
     *
     * @param  array<string, mixed>|object  $release  Release data
     * @param  bool  $debug  Whether to include debug information
     * @return array<string, mixed> Processing result
     */
    public function processRelease(array|object $release, bool $debug = false, ?TvProcessingRunState $runState = null): array
    {
        $context = TvReleaseContext::fromRelease($release);
        $passable = new TvProcessingPassable($context, $runState ?? $this->newRunState(), $debug);

        foreach ($this->pipes as $pipe) {
            $pipe->setEchoOutput($this->echoOutput);
        }

        try {
            app(Pipeline::class)
                ->send($passable)
                ->through($this->pipes->all())
                ->thenReturn();
        } catch (\Throwable $e) {
            // One broken release must not abort the batch; the attempt cap stops it looping forever.
            Log::error('TV processing failed for release', [
                'release_id' => $context->releaseId,
                'searchname' => $context->searchName,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            $passable->markTransient('pipeline');
        }

        $this->writer->record($context, $passable->finalResult());

        return $passable->toArray();
    }

    /**
     * Process all TV releases due for a lookup.
     *
     * @param  string  $groupID  Group ID to process
     * @param  string  $guidChar  GUID character to process
     * @param  int|string|null  $processTV  0 = skip, 1 = all, 2 = renamed only, '' = use the configured lookup mode
     */
    public function process(string $groupID = '', string $guidChar = '', int|string|null $processTV = ''): void
    {
        $configuredMode = (int) app(ConfigurationProvider::class)->metadata()->tvLookup->value;
        $mode = is_numeric($processTV) ? (int) $processTV : $configuredMode;
        if ($mode <= 0) {
            return;
        }

        // The configured "renamed only" mode applies even when a caller passes 1.
        $renamedOnly = $mode === 2 || $configuredMode === 2;

        $releases = $this->getTvReleases($groupID, $guidChar, $renamedOnly);
        if ($releases->isEmpty()) {
            if ($this->echoOutput) {
                cli()->header('No TV releases to process.');
            }

            return;
        }

        if ($this->echoOutput) {
            cli()->header('Processing '.$releases->count().' TV release(s).');
        }

        $this->processBatch($releases);
    }

    /**
     * Process a set of releases with one shared run state, then show the summary.
     *
     * @param  iterable<array<string, mixed>|object>  $releases
     */
    public function processBatch(iterable $releases): void
    {
        $this->resetStats();
        $startTime = microtime(true);
        $runState = $this->newRunState();

        foreach ($releases as $release) {
            $this->updateStats($this->processRelease($release, false, $runState));
        }

        $this->stats['duration'] = microtime(true) - $startTime;

        $this->displaySummary();
    }

    /**
     * @return Collection<int, Release>
     */
    protected function getTvReleases(string $groupID, string $guidChar, bool $renamedOnly): Collection
    {
        $qry = Release::query()
            ->select(['id', 'guid', 'leftguid', 'groups_id', 'searchname', 'size', 'categories_id', 'videos_id', 'tv_episodes_id', 'postdate'])
            ->whereRaw(TvProcessingCandidateQuery::workCondition($renamedOnly ? 2 : 1))
            ->orderByDesc('postdate')
            ->limit($this->batchSize());

        if ($groupID !== '') {
            $qry->where('groups_id', $groupID);
        }

        if ($guidChar !== '') {
            $qry->where('leftguid', $guidChar);
        }

        return $qry->get();
    }

    /** Releases per worker run: the configured max_tv_processed, default 75. */
    protected function batchSize(): int
    {
        $maxTvProcessed = (int) app(ConfigurationProvider::class)->metadata()->maxTvProcessed;

        return $maxTvProcessed > 0 ? $maxTvProcessed : 75;
    }

    /**
     * @return Collection<int, AbstractTvProviderPipe>
     */
    public function getPipes(): Collection
    {
        return $this->pipes;
    }

    /**
     * @return array{processed: int, matched: int, episode_missing: int, not_found: int, deferred: int, duration: float, providers: array<string, int>}
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    protected function resetStats(): void
    {
        $this->stats = [
            'processed' => 0,
            'matched' => 0,
            'episode_missing' => 0,
            'not_found' => 0,
            'deferred' => 0,
            'duration' => 0.0,
            'providers' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function updateStats(array $result): void
    {
        $this->stats['processed']++;

        switch ($result['status'] ?? '') {
            case TvProcessingResult::STATUS_MATCHED:
            case TvProcessingResult::STATUS_SEASON_PACK:
                $this->stats['matched']++;
                $provider = (string) ($result['provider'] ?? 'unknown');
                $this->stats['providers'][$provider] = ($this->stats['providers'][$provider] ?? 0) + 1;
                break;

            case TvProcessingResult::STATUS_EPISODE_MISSING:
                $this->stats['episode_missing']++;
                break;

            case TvProcessingResult::STATUS_TRANSIENT:
                $this->stats['deferred']++;
                break;

            default:
                $this->stats['not_found']++;
                break;
        }
    }

    protected function displaySummary(): void
    {
        if (! $this->echoOutput) {
            return;
        }

        cli()->header(sprintf(
            'TV processing complete: %d processed, %d matched, %d episode missing, %d not found, %d deferred (%.2fs)',
            $this->stats['processed'],
            $this->stats['matched'],
            $this->stats['episode_missing'],
            $this->stats['not_found'],
            $this->stats['deferred'],
            $this->stats['duration']
        ));

        if (! empty($this->stats['providers'])) {
            $providerSummary = [];
            foreach ($this->stats['providers'] as $provider => $count) {
                $providerSummary[] = "$provider: $count";
            }
            cli()->primary('Matches by provider: '.implode(', ', $providerSummary));
        }
    }

    /**
     * Create a default pipeline with all standard providers.
     */
    public static function createDefault(bool $echoOutput = true): self
    {
        return new self([
            new ParseInfoPipe,
            new LocalDbPipe,
            new TvdbPipe,
            new TvMazePipe,
            new TmdbPipe,
            new TraktPipe,
        ], $echoOutput);
    }
}
