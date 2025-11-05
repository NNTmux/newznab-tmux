<?php

namespace App\Services;

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

    private array $stats = [
        'total' => 0,
        'processed' => 0,
        'matched' => 0,
        'failed' => 0,
        'skipped' => 0,
        'byProvider' => [
            'Local DB' => ['processed' => 0, 'matched' => 0, 'failed' => 0],
            'TVDB' => ['processed' => 0, 'matched' => 0, 'failed' => 0],
            'TVMaze' => ['processed' => 0, 'matched' => 0, 'failed' => 0],
            'TMDB' => ['processed' => 0, 'matched' => 0, 'failed' => 0],
            'Trakt' => ['processed' => 0, 'matched' => 0, 'failed' => 0],
        ],
    ];

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
        $this->colorCli = new ColorCLI;
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
        $processTV = (is_numeric($processTV) ? $processTV : Settings::settingValue('lookuptv'));
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
     * Process releases through providers in parallel (all providers process all releases).
     * This is faster but uses more API calls. Compatible with Forking class.
     */
    private function processParallel(string $groupID, string $guidChar, int|string $processTV): void
    {
        // $this->displayHeaderParallel($guidChar);

        $providers = $this->getProviderPipeline();
        $totalTime = 0;

        foreach ($providers as $index => $provider) {
            // Check if there's any work remaining for this provider
            if (! $this->hasWorkForProvider($provider['name'], $groupID, $guidChar, $processTV)) {
                if ($this->echooutput) {
                    $this->colorCli->primaryOver('  [');
                    $this->colorCli->warningOver($index + 1);
                    $this->colorCli->primaryOver('/');
                    $this->colorCli->warningOver(count($providers));
                    $this->colorCli->primaryOver('] ');
                    $this->colorCli->alternateOver('→ ');
                    $this->colorCli->alternate($provider['name'].' → No work remaining, skipping');
                    echo "\n";
                }

                continue;
            }

            $this->displayProviderHeader($provider['name'], $index + 1, count($providers));

            $startTime = microtime(true);
            $provider['instance']->processSite($groupID, $guidChar, $processTV);
            $elapsedTime = microtime(true) - $startTime;
            $totalTime += $elapsedTime;

            $this->displayProviderComplete($provider['name'], $elapsedTime);
        }

        // $this->displaySummaryParallel($totalTime);
    }

    /**
     * Process releases through providers in pipeline (sequential, each processes failures from previous).
     * This is more efficient and reduces API calls significantly.
     */
    private function processPipeline(string $groupID, string $guidChar, int|string $processTV): void
    {
        // $this->displayHeader($guidChar);

        // Pipeline: Process releases through each provider in sequence
        // Each provider only processes releases that failed in previous steps
        $providers = $this->getProviderPipeline();

        foreach ($providers as $index => $provider) {
            // Check if there's any work remaining for this stage of the pipeline
            if (! $this->hasWorkForProvider($provider['name'], $groupID, $guidChar, $processTV)) {
                if ($this->echooutput) {
                    $this->colorCli->primaryOver('  [');
                    $this->colorCli->warningOver($index + 1);
                    $this->colorCli->primaryOver('/');
                    $this->colorCli->warningOver(count($providers));
                    $this->colorCli->primaryOver('] ');
                    $this->colorCli->alternateOver('→ ');
                    $this->colorCli->alternate($provider['name'].' → No work remaining, skipping');
                    echo "\n";
                }

                continue;
            }

            $this->displayProviderHeader($provider['name'], $index + 1, count($providers));

            $startTime = microtime(true);
            $provider['instance']->processSite($groupID, $guidChar, $processTV);
            $elapsedTime = microtime(true) - $startTime;

            $this->displayProviderComplete($provider['name'], $elapsedTime);
        }

        // $this->displaySummary();
    }

    /**
     * Get the provider pipeline in order of preference.
     */
    private function getProviderPipeline(): array
    {
        return [
            ['name' => 'Local DB', 'instance' => new LocalDB, 'status' => 0],
            ['name' => 'TVDB', 'instance' => new TVDB, 'status' => 0],
            ['name' => 'TVMaze', 'instance' => new TVMaze, 'status' => -1],
            ['name' => 'TMDB', 'instance' => new TMDB, 'status' => -2],
            ['name' => 'Trakt', 'instance' => new TraktTv, 'status' => -3],
        ];
    }

    /**
     * Check if there's work remaining for a specific provider in the pipeline.
     */
    private function hasWorkForProvider(string $providerName, string $groupID, string $guidChar, int|string $processTV): bool
    {
        $statusMap = [
            'Local DB' => 0,   // Process unprocessed releases
            'TVDB' => 0,       // Process unprocessed releases (runs in parallel with LocalDB conceptually)
            'TVMaze' => -1,    // Process releases not found by TVDB
            'TMDB' => -2,      // Process releases not found by TVMaze
            'Trakt' => -3,     // Process releases not found by TMDB
        ];

        $status = $statusMap[$providerName] ?? 0;

        // Build the same query logic as getTvReleases but just check for existence
        $query = \App\Models\Release::query()
            ->where(['videos_id' => 0, 'tv_episodes_id' => $status])
            ->where('size', '>', 1048576)
            ->whereBetween('categories_id', [5000, 5999])
            ->where('categories_id', '<>', 5070)
            ->limit(1);

        if ($groupID !== '') {
            $query->where('groups_id', $groupID);
        }

        if ($guidChar !== '') {
            $query->where('leftguid', $guidChar);
        }

        if ($processTV == 2) {
            $query->where('isrenamed', '=', 1);
        }

        return $query->exists();
    }

    /**
     * Display the processing header.
     */
    private function displayHeader(string $guidChar = ''): void
    {
        if (! $this->echooutput) {
            return;
        }

        echo "\n";
        $this->colorCli->headerOver('▶ TV Processing');
        if ($guidChar !== '') {
            $this->colorCli->primaryOver(' → ');
            $this->colorCli->headerOver('PIPELINE Mode');
            $this->colorCli->primaryOver(' → ');
            $this->colorCli->warningOver('Bucket: ');
            $this->colorCli->header(strtoupper($guidChar));
        } else {
            $this->colorCli->primaryOver(' → ');
            $this->colorCli->header('PIPELINE Mode');
        }
        echo "\n";
    }

    /**
     * Display the processing header for parallel mode.
     */
    private function displayHeaderParallel(string $guidChar = ''): void
    {
        if (! $this->echooutput) {
            return;
        }

        echo "\n";
        $this->colorCli->headerOver('▶ TV Processing');
        if ($guidChar !== '') {
            $this->colorCli->primaryOver(' → ');
            $this->colorCli->headerOver('PARALLEL Mode');
            $this->colorCli->primaryOver(' → ');
            $this->colorCli->warningOver('Bucket: ');
            $this->colorCli->header(strtoupper($guidChar));
        } else {
            $this->colorCli->primaryOver(' → ');
            $this->colorCli->header('PARALLEL Mode');
        }
        echo "\n";
    }

    /**
     * Display provider processing header.
     */
    private function displayProviderHeader(string $providerName, int $step, int $total): void
    {
        if (! $this->echooutput) {
            return;
        }

        echo "\n";
        $this->colorCli->primaryOver('  [');
        $this->colorCli->warningOver($step);
        $this->colorCli->primaryOver('/');
        $this->colorCli->warningOver($total);
        $this->colorCli->primaryOver('] ');
        $this->colorCli->headerOver('→ ');
        $this->colorCli->header($providerName);
    }

    /**
     * Display provider completion message.
     */
    private function displayProviderComplete(string $providerName, float $elapsedTime): void
    {
        if (! $this->echooutput) {
            return;
        }

        echo "\n";
        $this->colorCli->primaryOver('  ✓ ');
        $this->colorCli->primaryOver($providerName);
        $this->colorCli->primaryOver(' → ');
        $this->colorCli->alternateOver('Completed in ');
        $this->colorCli->warning(sprintf('%.2fs', $elapsedTime));
        echo "\n";
    }

    /**
     * Display final processing summary.
     */
    private function displaySummary(): void
    {
        if (! $this->echooutput) {
            return;
        }

        echo "\n";
        $this->colorCli->primaryOver('✓ Pipeline Complete');
        $this->colorCli->primaryOver(' → ');
        $this->colorCli->primary('Local DB → TVDB → TVMaze → TMDB → Trakt');
        echo "\n";
    }

    /**
     * Display final processing summary for parallel mode.
     */
    private function displaySummaryParallel(float $totalTime): void
    {
        if (! $this->echooutput) {
            return;
        }

        echo "\n";
        $this->colorCli->primaryOver('✓ Parallel Processing Complete');
        $this->colorCli->primaryOver(' → ');
        $this->colorCli->warningOver('Total: ');
        $this->colorCli->warning(sprintf('%.2fs', $totalTime));
        echo "\n";
    }
}
