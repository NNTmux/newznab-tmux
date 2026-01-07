<?php

namespace App\Services;

use App\Models\Settings;
use App\Services\Runners\BackfillRunner;
use App\Services\Runners\BinariesRunner;
use App\Services\Runners\PostProcessRunner;
use App\Services\Runners\ReleasesRunner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Service for multiprocessing various tasks.
 *
 * This service orchestrates parallel processing of usenet indexing tasks
 * by delegating to specialized runner classes.
 */
class ForkingService
{
    protected BackfillRunner $backfillRunner;

    protected BinariesRunner $binariesRunner;

    protected ReleasesRunner $releasesRunner;

    protected PostProcessRunner $postProcessRunner;

    protected int $maxSize;

    protected int $minSize;

    protected int $maxRetries;

    public function __construct()
    {
        $this->maxSize = (int) Settings::settingValue('maxsizetoprocessnfo');
        $this->minSize = (int) Settings::settingValue('minsizetoprocessnfo');
        $this->maxRetries = (int) Settings::settingValue('maxnforetries') >= 0
            ? -((int) Settings::settingValue('maxnforetries') + 1)
            : NfoService::NFO_UNPROC;
        $this->maxRetries = max($this->maxRetries, -8);

        // Initialize runners
        $this->backfillRunner = new BackfillRunner;
        $this->binariesRunner = new BinariesRunner;
        $this->releasesRunner = new ReleasesRunner;
        $this->postProcessRunner = new PostProcessRunner;
    }

    /**
     * Process backfill for all groups with backfill enabled.
     */
    public function backfill(array $options = []): void
    {
        $this->runWithTiming('backfill', fn () => $this->backfillRunner->backfill($options));
    }

    /**
     * Process safe backfill (ordered by oldest first).
     */
    public function safeBackfill(): void
    {
        $this->runWithTiming('safe_backfill', fn () => $this->backfillRunner->safeBackfill());
    }

    /**
     * Download binaries (new headers) for all active groups.
     */
    public function binaries(int $maxPerGroup = 0): void
    {
        $this->runWithTiming('binaries', fn () => $this->binariesRunner->binaries($maxPerGroup));
    }

    /**
     * Process safe binaries (ordered by most recent activity).
     */
    public function safeBinaries(): void
    {
        $this->runWithTiming('safe_binaries', fn () => $this->binariesRunner->safeBinaries());
    }

    /**
     * Process releases for all groups.
     */
    public function releases(): void
    {
        $this->runWithTiming('releases', function () {
            $this->releasesRunner->releases();
            $this->processReleasesEndWork();
        });
    }

    /**
     * Update binaries and releases per group.
     */
    public function updatePerGroup(): void
    {
        $this->runWithTiming('update_per_group', function () {
            $this->releasesRunner->updatePerGroup();
            $this->processReleasesEndWork();
        });
    }

    /**
     * Fix release names using specified mode.
     *
     * @param  string  $mode  'standard' or 'predbft'
     */
    public function fixRelNames(string $mode): void
    {
        $this->runWithTiming("fixRelNames_{$mode}", fn () => $this->releasesRunner->fixRelNames(
            $mode,
            (int) Settings::settingValue('fixnamesperrun'),
            (int) Settings::settingValue('fixnamethreads')
        ));
    }

    /**
     * Process additional post-processing (preview images, samples, etc).
     */
    public function processAdditional(): void
    {
        $this->runWithTiming('postProcess_add', fn () => $this->postProcessRunner->processAdditional());
    }

    /**
     * Process NFO files.
     */
    public function processNfo(): void
    {
        $this->runWithTiming('postProcess_nfo', fn () => $this->postProcessRunner->processNfo());
    }

    /**
     * Process movie metadata.
     */
    public function processMovies(bool $renamedOnly = false): void
    {
        $this->runWithTiming('postProcess_mov', fn () => $this->postProcessRunner->processMovies($renamedOnly));
    }

    /**
     * Process TV metadata.
     */
    public function processTv(bool $renamedOnly = false): void
    {
        $this->runWithTiming('postProcess_tv', function () use ($renamedOnly) {
            if ($this->postProcessRunner->hasTvWork($renamedOnly)) {
                $this->postProcessRunner->processTv($renamedOnly);
            } else {
                $this->log('No TV work to do.');
            }
        });
    }

    /**
     * Process anime metadata.
     */
    public function processAnime(): void
    {
        $this->runWithTiming('postProcess_ani', fn () => $this->postProcessRunner->processAnime());
    }

    /**
     * Process book metadata.
     */
    public function processBooks(): void
    {
        $this->runWithTiming('postProcess_ama', fn () => $this->postProcessRunner->processBooks());
    }

    /**
     * Generic work type processor for backwards compatibility.
     *
     * @deprecated Use specific methods instead (e.g., backfill(), binaries(), etc.)
     */
    public function processWorkType(string $type, array $options = []): void
    {
        match ($type) {
            'backfill' => $this->backfill($options),
            'binaries' => $this->binaries((int) ($options[0] ?? 0)),
            'fixRelNames_standard' => $this->fixRelNames('standard'),
            'fixRelNames_predbft' => $this->fixRelNames('predbft'),
            'releases' => $this->releases(),
            'postProcess_add' => $this->processAdditional(),
            'postProcess_ani' => $this->processAnime(),
            'postProcess_ama' => $this->processBooks(),
            'postProcess_mov' => $this->processMovies($options[0] ?? false),
            'postProcess_nfo' => $this->processNfo(),
            'postProcess_tv' => $this->processTv($options[0] ?? false),
            'safe_backfill' => $this->safeBackfill(),
            'safe_binaries' => $this->safeBinaries(),
            'update_per_group' => $this->updatePerGroup(),
            default => Log::warning("Unknown work type: {$type}"),
        };
    }

    /**
     * Run a task with timing output.
     */
    protected function runWithTiming(string $taskName, callable $task): void
    {
        $startTime = now()->timestamp;

        try {
            $task();
        } catch (\Throwable $e) {
            Log::error("Task {$taskName} failed: {$e->getMessage()}", [
                'exception' => $e,
            ]);
            throw $e;
        }

        if (config('nntmux.echocli')) {
            cli()->header(
                "Multi-processing for {$taskName} finished in ".(now()->timestamp - $startTime).
                ' seconds at '.now()->toRfc2822String().'.'.PHP_EOL
            );
        }
    }

    /**
     * Process end work for releases (DNR signalling).
     */
    protected function processReleasesEndWork(): void
    {
        $count = $this->getReleaseWorkCount();
        $command = $this->backfillRunner->buildDnrCommandPublic("releases  {$count}_");
        $this->executeCommand($command);
    }

    /**
     * Count groups with pending collections.
     */
    protected function getReleaseWorkCount(): int
    {
        $groups = DB::select('SELECT id FROM usenet_groups WHERE (active = 1 OR backfill = 1)');
        $count = 0;

        foreach ($groups as $group) {
            try {
                $query = DB::select(
                    sprintf('SELECT id FROM collections WHERE groups_id = %d LIMIT 1', $group->id)
                );
                if (! empty($query)) {
                    $count++;
                }
            } catch (\PDOException $e) {
                if (config('app.debug')) {
                    Log::debug($e->getMessage());
                }
            }
        }

        return $count;
    }

    /**
     * Execute a shell command.
     */
    protected function executeCommand(string $command): string
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(1800);
        $process->run(function ($type, $buffer) {
            if ($type === Process::ERR) {
                echo $buffer;
            }
        });

        return $process->getOutput();
    }

    /**
     * Log a message to console.
     */
    protected function log(string $message): void
    {
        if (config('nntmux.echocli')) {
            echo $message.PHP_EOL;
        }
    }
}
