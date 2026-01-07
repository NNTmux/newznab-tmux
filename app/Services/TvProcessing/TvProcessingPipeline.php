<?php

namespace App\Services\TvProcessing;

use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use App\Services\TvProcessing\Pipes\AbstractTvProviderPipe;
use App\Services\TvProcessing\Pipes\LocalDbPipe;
use App\Services\TvProcessing\Pipes\ParseInfoPipe;
use App\Services\TvProcessing\Pipes\TmdbPipe;
use App\Services\TvProcessing\Pipes\TraktPipe;
use App\Services\TvProcessing\Pipes\TvdbPipe;
use App\Services\TvProcessing\Pipes\TvMazePipe;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;

/**
 * Pipeline-based TV processing service using Laravel Pipeline.
 *
 * This service uses Laravel's Pipeline to orchestrate multiple TV data providers
 * to process TV releases and match them against video metadata from various sources.
 */
class TvProcessingPipeline
{
    /**
     * @var Collection<AbstractTvProviderPipe>
     */
    protected Collection $pipes;

    protected int $tvqty;

    protected bool $echoOutput;

    protected array $stats = [
        'processed' => 0,
        'matched' => 0,
        'failed' => 0,
        'skipped' => 0,
        'duration' => 0.0,
        'providers' => [],
    ];

    /**
     * @param  iterable<AbstractTvProviderPipe>  $pipes
     */
    public function __construct(iterable $pipes = [], bool $echoOutput = true)
    {
        $this->pipes = collect($pipes)
            ->sortBy(fn (AbstractTvProviderPipe $p) => $p->getPriority());

        $this->tvqty = Settings::settingValue('maxrageprocessed') !== ''
            ? (int) Settings::settingValue('maxrageprocessed')
            : 75;

        $this->echoOutput = $echoOutput;
    }

    /**
     * Add a provider pipe to the pipeline.
     */
    public function addPipe(AbstractTvProviderPipe $pipe): self
    {
        $this->pipes->push($pipe);
        $this->pipes = $this->pipes->sortBy(fn (AbstractTvProviderPipe $p) => $p->getPriority());

        return $this;
    }

    /**
     * Process a single release through the pipeline.
     *
     * @param  array|object  $release  Release data
     * @param  bool  $debug  Whether to include debug information
     * @return array Processing result
     */
    public function processRelease(array|object $release, bool $debug = false): array
    {
        $context = TvReleaseContext::fromRelease($release);
        $passable = new TvProcessingPassable($context, $debug);

        // Set echo output on all pipes
        foreach ($this->pipes as $pipe) {
            $pipe->setEchoOutput($this->echoOutput);
        }

        /** @var TvProcessingPassable $result */
        $result = app(Pipeline::class)
            ->send($passable)
            ->through($this->pipes->values()->all())
            ->thenReturn();

        return $result->toArray();
    }

    /**
     * Process all TV releases matching the criteria.
     *
     * @param  string  $groupID  Group ID to process
     * @param  string  $guidChar  GUID character to process
     * @param  int|string|null  $processTV  Processing setting (0/1/2 or '' to read from settings)
     */
    public function process(string $groupID = '', string $guidChar = '', int|string|null $processTV = ''): void
    {
        $processTV = (int) (is_numeric($processTV) ? $processTV : Settings::settingValue('lookuptv'));
        if ($processTV <= 0) {
            return;
        }

        $this->resetStats();
        $startTime = microtime(true);

        // Get releases that need processing
        $releases = $this->getTvReleases($groupID, $guidChar, $processTV);
        $totalCount = count($releases);

        if ($totalCount === 0) {
            if ($this->echoOutput) {
                cli()->header('No TV releases to process.');
            }

            return;
        }

        if ($this->echoOutput) {
            cli()->header('Processing '.$totalCount.' TV release(s).');
        }

        foreach ($releases as $release) {
            $result = $this->processRelease($release);
            $this->updateStats($result);
        }

        $this->stats['duration'] = microtime(true) - $startTime;

        $this->displaySummary();
    }

    /**
     * Get TV releases that need processing.
     */
    protected function getTvReleases(string $groupID, string $guidChar, int $processTV): Collection
    {
        $qry = Release::query()
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
            ->where(['videos_id' => 0, 'tv_episodes_id' => 0])
            ->where('size', '>', 1048576)
            ->whereBetween('categories_id', [Category::TV_ROOT, Category::TV_OTHER])
            ->where('categories_id', '<>', Category::TV_ANIME)
            ->orderByDesc('postdate')
            ->limit($this->tvqty);

        if ($groupID !== '') {
            $qry->where('groups_id', $groupID);
        }

        if ($guidChar !== '') {
            $qry->where('leftguid', $guidChar);
        }

        if ($processTV === 2) {
            $qry->where('isrenamed', '=', 1);
        }

        return $qry->get();
    }

    /**
     * Get all registered provider pipes.
     *
     * @return Collection<AbstractTvProviderPipe>
     */
    public function getPipes(): Collection
    {
        return $this->pipes;
    }

    /**
     * Get processing statistics.
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset statistics.
     */
    protected function resetStats(): void
    {
        $this->stats = [
            'processed' => 0,
            'matched' => 0,
            'failed' => 0,
            'skipped' => 0,
            'duration' => 0.0,
            'providers' => [],
        ];
    }

    /**
     * Update statistics from a processing result.
     */
    protected function updateStats(array $result): void
    {
        $this->stats['processed']++;

        switch ($result['status'] ?? '') {
            case TvProcessingResult::STATUS_MATCHED:
                $this->stats['matched']++;
                $provider = $result['provider'] ?? 'unknown';
                $this->stats['providers'][$provider] = ($this->stats['providers'][$provider] ?? 0) + 1;
                break;

            case TvProcessingResult::STATUS_PARSE_FAILED:
            case TvProcessingResult::STATUS_NOT_FOUND:
                $this->stats['failed']++;
                break;

            case TvProcessingResult::STATUS_SKIPPED:
                $this->stats['skipped']++;
                break;
        }
    }

    /**
     * Display processing header.
     */
    protected function displayHeader(string $guidChar = ''): void
    {
        // Header is now shown in process() after we know the release count
    }

    /**
     * Display processing summary.
     */
    protected function displaySummary(): void
    {
        if (! $this->echoOutput) {
            return;
        }

        cli()->header(sprintf(
            'TV processing complete: %d processed, %d matched, %d failed (%.2fs)',
            $this->stats['processed'],
            $this->stats['matched'],
            $this->stats['failed'],
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
