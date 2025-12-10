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
use Blacklight\ColorCLI;
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
    protected ColorCLI $colorCli;

    protected array $stats = [
        'processed' => 0,
        'matched' => 0,
        'failed' => 0,
        'skipped' => 0,
        'duration' => 0.0,
        'providers' => [],
    ];

    /**
     * @param iterable<AbstractTvProviderPipe> $pipes
     */
    public function __construct(iterable $pipes = [], bool $echoOutput = true)
    {
        $this->pipes = collect($pipes)
            ->sortBy(fn (AbstractTvProviderPipe $p) => $p->getPriority());

        $this->tvqty = Settings::settingValue('maxrageprocessed') !== ''
            ? (int) Settings::settingValue('maxrageprocessed')
            : 75;

        $this->echoOutput = $echoOutput;
        $this->colorCli = new ColorCLI();
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
     * @param array|object $release Release data
     * @param bool $debug Whether to include debug information
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
     * @param string $groupID Group ID to process
     * @param string $guidChar GUID character to process
     * @param int|string|null $processTV Processing setting (0/1/2 or '' to read from settings)
     */
    public function process(string $groupID = '', string $guidChar = '', int|string|null $processTV = ''): void
    {
        $processTV = (int) (is_numeric($processTV) ? $processTV : Settings::settingValue('lookuptv'));
        if ($processTV <= 0) {
            return;
        }

        $this->resetStats();
        $startTime = microtime(true);

        $this->displayHeader($guidChar);

        // Get releases that need processing
        $releases = $this->getTvReleases($groupID, $guidChar, $processTV);
        $totalCount = count($releases);

        if ($totalCount === 0) {
            if ($this->echoOutput) {
                $this->colorCli->primary('  No TV releases to process');
            }
            return;
        }

        if ($this->echoOutput) {
            echo "\n";
            $this->colorCli->primaryOver('  Processing ');
            $this->colorCli->warning($totalCount);
            $this->colorCli->primary(' releases through pipeline');
            echo "\n";
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
     *
     * @return Collection
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
        if (! $this->echoOutput) {
            return;
        }

        echo "\n";
        $this->colorCli->headerOver('▶ TV Processing');
        $this->colorCli->primaryOver(' → ');
        $this->colorCli->headerOver('PIPELINE Mode');
        if ($guidChar !== '') {
            $this->colorCli->primaryOver(' → ');
            $this->colorCli->warningOver('Bucket: ');
            $this->colorCli->header(strtoupper($guidChar));
        }
        echo "\n";
    }

    /**
     * Display processing summary.
     */
    protected function displaySummary(): void
    {
        if (! $this->echoOutput) {
            return;
        }

        echo "\n";
        $this->colorCli->primaryOver('✓ Pipeline Complete');
        $this->colorCli->primaryOver(' → ');
        $this->colorCli->warningOver(sprintf(
            '%d processed, %d matched, %d failed',
            $this->stats['processed'],
            $this->stats['matched'],
            $this->stats['failed']
        ));
        $this->colorCli->primaryOver(' in ');
        $this->colorCli->warning(sprintf('%.2fs', $this->stats['duration']));
        echo "\n";

        if (! empty($this->stats['providers'])) {
            $providerSummary = [];
            foreach ($this->stats['providers'] as $provider => $count) {
                $providerSummary[] = "$provider: $count";
            }
            $this->colorCli->primary('  Matches by provider: ' . implode(', ', $providerSummary));
            echo "\n";
        }
    }

    /**
     * Create a default pipeline with all standard providers.
     */
    public static function createDefault(bool $echoOutput = true): self
    {
        return new self([
            new ParseInfoPipe(),
            new LocalDbPipe(),
            new TvdbPipe(),
            new TvMazePipe(),
            new TmdbPipe(),
            new TraktPipe(),
        ], $echoOutput);
    }
}

