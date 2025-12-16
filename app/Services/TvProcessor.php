<?php

namespace App\Services;

use App\Models\Release;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\processing\tv\LocalDB;
use Blacklight\processing\tv\TMDB;
use Blacklight\processing\tv\TraktTv;
use Blacklight\processing\tv\TVDB;
use Blacklight\processing\tv\TVMaze;

class TvProcessor
{
    // Processing modes
    public const MODE_PIPELINE = 'pipeline';  // Sequential processing (efficient, reduces API calls)

    public const MODE_PARALLEL = 'parallel';  // Parallel processing (faster, more API calls)

    private bool $echooutput;

    private ColorCLI $colorCli;

    /**
     * @var array<int, array{name: string, factory: callable, status: int}>
     */
    private array $providers;

    private array $stats = [
        'mode' => self::MODE_PIPELINE,
        'totalDuration' => 0.0,
        'providers' => [],
    ];

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
        $this->colorCli = new ColorCLI;
        $this->providers = $this->buildProviderPipeline();
    }

    /**
     * Process all TV related releases across supported providers.
     *
     * @param  string  $groupID  Group ID to process
     * @param  string  $guidChar  GUID character to process
     * @param  int|string|null  $processTV  0/1/2 or '' to read from settings
     * @param  string  $mode  Processing mode: 'pipeline' (sequential) or 'parallel' (simultaneous)
     */
    public function process(string $groupID = '', string $guidChar = '', int|string|null $processTV = '', string $mode = self::MODE_PIPELINE): void
    {
        $processTV = (int) (is_numeric($processTV) ? $processTV : Settings::settingValue('lookuptv'));
        if ($processTV <= 0) {
            return;
        }

        if ($mode === self::MODE_PARALLEL) {
            $this->processParallel($groupID, $guidChar, $processTV);
        } else {
            $this->processPipeline($groupID, $guidChar, $processTV);
        }
    }

    /**
     * Retrieve statistics from the most recent run.
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Process releases through providers in parallel (all providers process all releases).
     * This is faster but uses more API calls. Compatible with Forking class.
     */
    private function processParallel(string $groupID, string $guidChar, int $processTV): void
    {
        $this->resetStats(self::MODE_PARALLEL);
        $this->displayModeHeader(self::MODE_PARALLEL, $guidChar);
        [$totalTime, $processedAny] = $this->runProviders(self::MODE_PARALLEL, $groupID, $guidChar, $processTV);

        if ($processedAny) {
            $this->displaySummaryParallel($totalTime);
        }
    }

    /**
     * Process releases through providers in pipeline (sequential, each processes failures from previous).
     * This is more efficient and reduces API calls significantly.
     */
    private function processPipeline(string $groupID, string $guidChar, int $processTV): void
    {
        $this->resetStats(self::MODE_PIPELINE);
        $this->displayModeHeader(self::MODE_PIPELINE, $guidChar);
        [, $processedAny] = $this->runProviders(self::MODE_PIPELINE, $groupID, $guidChar, $processTV);

        if ($processedAny) {
            $this->displaySummary();
        }
    }

    private function resetStats(string $mode): void
    {
        $this->stats = [
            'mode' => $mode,
            'totalDuration' => 0.0,
            'providers' => [],
        ];

        foreach ($this->providers as $provider) {
            $this->stats['providers'][$provider['name']] = [
                'status' => 'pending',
                'duration' => 0.0,
            ];
        }
    }

    /**
     * Execute providers in the configured order and track timing.
     *
     * @return array{0: float, 1: bool} [total elapsed time, processed flag]
     */
    private function runProviders(string $mode, string $groupID, string $guidChar, int $processTV): array
    {
        $totalTime = 0.0;
        $providerCount = count($this->providers);
        $processedAny = false;

        foreach ($this->providers as $index => $provider) {
            $pendingWork = $this->getPendingWorkForProvider($provider, $groupID, $guidChar, $processTV);
            if ($pendingWork === null) {
                $this->displayProviderSkip($provider['name'], $index + 1, $providerCount);
                $this->stats['providers'][$provider['name']] = [
                    'status' => 'skipped',
                    'duration' => 0.0,
                ];

                continue;
            }

            $this->displayProviderHeader($provider['name'], $index + 1, $providerCount);
            $this->displayProviderPreview(
                $provider['name'],
                $pendingWork['release'],
                $pendingWork['total'],
                $provider['status'] ?? 0
            );

            /** @var object $processor */
            $processor = ($provider['factory'])();
            if (property_exists($processor, 'echooutput')) {
                $processor->echooutput = $this->echooutput;
            }

            $startTime = microtime(true);
            $processor->processSite($groupID, $guidChar, $processTV);
            $elapsedTime = microtime(true) - $startTime;
            $processedAny = true;
            $this->stats['providers'][$provider['name']] = [
                'status' => 'processed',
                'duration' => $elapsedTime,
            ];
            $this->stats['totalDuration'] += $elapsedTime;

            if ($mode === self::MODE_PARALLEL) {
                $totalTime += $elapsedTime;
            }

            $this->displayProviderComplete($provider['name'], $elapsedTime);
        }

        return [$totalTime, $processedAny];
    }

    /**
     * Get the provider pipeline in order of preference.
     */
    private function buildProviderPipeline(): array
    {
        return [
            ['name' => 'Local DB', 'factory' => static fn () => new LocalDB, 'status' => 0],
            ['name' => 'TVDB', 'factory' => static fn () => new TVDB, 'status' => 0],
            ['name' => 'TVMaze', 'factory' => static fn () => new TVMaze, 'status' => -1],
            ['name' => 'TMDB', 'factory' => static fn () => new TMDB, 'status' => -2],
            ['name' => 'Trakt', 'factory' => static fn () => new TraktTv, 'status' => -3],
        ];
    }

    /**
     * Determine whether a provider has pending work and return a preview release.
     *
     * @return array{release: Release, total: int}|null
     */
    private function getPendingWorkForProvider(array $provider, string $groupID, string $guidChar, int $processTV): ?array
    {
        $status = $provider['status'] ?? 0;

        $baseQuery = Release::query()
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
            ->where(['videos_id' => 0, 'tv_episodes_id' => $status])
            ->where('size', '>', 1048576)
            ->whereBetween('categories_id', [5000, 5999])
            ->where('categories_id', '<>', 5070);

        if ($groupID !== '') {
            $baseQuery->where('groups_id', $groupID);
        }

        if ($guidChar !== '') {
            $baseQuery->where('leftguid', $guidChar);
        }

        if ($processTV === 2) {
            $baseQuery->where('isrenamed', '=', 1);
        }

        $total = (clone $baseQuery)->count();
        if ($total === 0) {
            return null;
        }

        $release = (clone $baseQuery)
            ->orderByDesc('postdate')
            ->first();

        if ($release === null) {
            return null;
        }

        return [
            'release' => $release,
            'total' => $total,
        ];
    }

    private function displayModeHeader(string $mode, string $guidChar = ''): void
    {
        if ($mode === self::MODE_PARALLEL) {
            $this->displayHeaderParallel($guidChar);
        } else {
            $this->displayHeader($guidChar);
        }
    }

    /**
     * Display the processing header.
     */
    private function displayHeader(string $guidChar = ''): void
    {
        // Header shown when processing starts
    }

    /**
     * Display the processing header for parallel mode.
     */
    private function displayHeaderParallel(string $guidChar = ''): void
    {
        // Header shown when processing starts
    }

    /**
     * Display provider processing header.
     */
    private function displayProviderHeader(string $providerName, int $step, int $total): void
    {
        // Provider header shown in displayProviderPreview
    }

    /**
     * Display details about the next release the provider will work on.
     */
    private function displayProviderPreview(string $providerName, Release $release, int $total, int $status): void
    {
        if (! $this->echooutput) {
            return;
        }

        $this->colorCli->header('Processing '.$total.' TV release(s) via '.$providerName.'.');
    }

    /**
     * Display provider skip message.
     */
    private function displayProviderSkip(string $providerName, int $step, int $total): void
    {
        // No output needed for skipped providers
    }

    /**
     * Display provider completion message.
     */
    private function displayProviderComplete(string $providerName, float $elapsedTime): void
    {
        // No output needed for individual provider completion
    }

    /**
     * Display final processing summary.
     */
    private function displaySummary(): void
    {
        // Summary handled by individual providers
    }

    /**
     * Display final processing summary for parallel mode.
     */
    private function displaySummaryParallel(float $totalTime): void
    {
        // Summary handled by individual providers
    }
}
