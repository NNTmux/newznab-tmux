<?php

namespace App\Services\Tmux;

use App\Models\Settings;
use Blacklight\ColorCLI;

/**
 * Service for running tasks in tmux panes
 */
class TmuxTaskRunner
{
    protected TmuxPaneManager $paneManager;

    protected ColorCLI $colorCli;

    protected string $sessionName;

    public function __construct(string $sessionName)
    {
        $this->sessionName = $sessionName;
        $this->paneManager = new TmuxPaneManager($sessionName);
        $this->colorCli = new ColorCLI;
    }

    /**
     * Get niceness value from settings or config with sensible default
     */
    protected function getNiceness(): int
    {
        // Try to get from settings first
        $niceness = Settings::settingValue('niceness');

        // If empty string or null, try config
        if (empty($niceness) && $niceness !== 0 && $niceness !== '0') {
            $niceness = config('nntmux.niceness');
        }

        // If still empty, use system default
        if (empty($niceness) && $niceness !== 0 && $niceness !== '0') {
            $niceness = 10; // Standard nice default
        }

        return (int) $niceness;
    }

    /**
     * Run a task in a specific pane
     */
    public function runTask(string $taskName, array $config): bool
    {
        $pane = $config['pane'] ?? null;
        $command = $config['command'] ?? null;
        $enabled = $config['enabled'] ?? true;
        $workAvailable = $config['work_available'] ?? true;

        if (! $pane || ! $command) {
            return false;
        }

        // Check if task is enabled and has work
        if (! $enabled) {
            return $this->disablePane($pane, $taskName, 'disabled in settings');
        }

        if (! $workAvailable) {
            return $this->disablePane($pane, $taskName, 'no work available');
        }

        // Respawn the pane with the command
        return $this->paneManager->respawnPane($pane, $command);
    }

    /**
     * Disable a pane with a message
     */
    protected function disablePane(string $pane, string $taskName, string $reason): bool
    {
        $color = $this->getRandomColor();
        $message = "echo -e \"\033[38;5;{$color}m\n{$taskName} has been disabled: {$reason}\"";

        return $this->paneManager->respawnPane($pane, $message, kill: true);
    }

    /**
     * Build a command with logging
     */
    public function buildCommand(string $baseCommand, array $options = []): string
    {
        $parts = [$baseCommand];

        // Add sleep timer at the end if specified
        if (isset($options['sleep'])) {
            $sleepCommand = $this->buildSleepCommand($options['sleep']);
            $parts[] = 'date +"%Y-%m-%d %T"';
            $parts[] = $sleepCommand;
        }

        // Add logging if enabled
        if (isset($options['log_pane'])) {
            $logFile = $this->getLogFile($options['log_pane']);
            $command = implode('; ', $parts);

            return "{$command} 2>&1 | tee -a {$logFile}";
        }

        return implode('; ', $parts);
    }

    /**
     * Build sleep command
     */
    protected function buildSleepCommand(int $seconds): string
    {
        $niceness = $this->getNiceness();
        $sleepScript = base_path('app/Services/Tmux/Scripts/showsleep.php');

        if (file_exists($sleepScript)) {
            return "nice -n{$niceness} php {$sleepScript} {$seconds}";
        }

        return "sleep {$seconds}";
    }

    /**
     * Get log file path for a pane
     */
    protected function getLogFile(string $paneName): string
    {
        $logsEnabled = (int) Settings::settingValue('write_logs') === 1;

        if (! $logsEnabled) {
            return '/dev/null';
        }

        $logDir = config('tmux.paths.logs', storage_path('logs/tmux'));

        if (! is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $date = now()->format('Y_m_d');

        return "{$logDir}/{$paneName}-{$date}.log";
    }

    /**
     * Get a random color for terminal output
     */
    protected function getRandomColor(): int
    {
        $start = (int) Settings::settingValue('colors_start') ?? 0;
        $end = (int) Settings::settingValue('colors_end') ?? 255;
        $exclude = Settings::settingValue('colors_exc') ?? '';

        if (empty($exclude)) {
            return random_int($start, $end);
        }

        $exceptions = array_map('intval', explode(',', $exclude));
        sort($exceptions);

        $number = random_int($start, $end - count($exceptions));

        foreach ($exceptions as $exception) {
            if ($number >= $exception) {
                $number++;
            } else {
                break;
            }
        }

        return $number;
    }

    /**
     * Run the IRC scraper
     */
    public function runIRCScraper(array $config): bool
    {
        $runScraper = (int) ($config['constants']['run_ircscraper'] ?? 0);
        $pane = '3.0';

        if ($runScraper !== 1) {
            return $this->disablePane($pane, 'IRC Scraper', 'disabled in settings');
        }

        $niceness = $this->getNiceness();
        $artisan = base_path('artisan');
        $command = "nice -n{$niceness} php {$artisan} irc:scrape";
        $command = $this->buildCommand($command, ['log_pane' => 'scraper']);

        return $this->paneManager->respawnPane($pane, $command);
    }

    /**
     * Run binaries update
     */
    public function runBinariesUpdate(array $config): bool
    {
        $enabled = (int) ($config['settings']['binaries_run'] ?? 0);
        $killswitch = $config['killswitch']['pp'] ?? false;
        $pane = '0.1';

        if (! $enabled) {
            return $this->disablePane($pane, 'Update Binaries', 'disabled in settings');
        }

        if ($killswitch) {
            return $this->disablePane($pane, 'Update Binaries', 'postprocess kill limit exceeded');
        }

        $artisanCommand = match ((int) $enabled) {
            1 => 'multiprocessing:safe binaries',
            default => null,
        };

        if (! $artisanCommand) {
            return false;
        }

        $niceness = $this->getNiceness();
        $command = "nice -n{$niceness} ".PHP_BINARY." artisan {$artisanCommand}";
        $sleep = (int) ($config['settings']['bins_timer'] ?? 60);
        $command = $this->buildCommand($command, ['log_pane' => 'binaries', 'sleep' => $sleep]);

        return $this->paneManager->respawnPane($pane, $command);
    }

    /**
     * Run backfill
     */
    public function runBackfill(array $config): bool
    {
        $enabled = (int) ($config['settings']['backfill'] ?? 0);
        $collKillswitch = $config['killswitch']['coll'] ?? false;
        $ppKillswitch = $config['killswitch']['pp'] ?? false;
        $pane = '0.2';

        if (! $enabled) {
            return $this->disablePane($pane, 'Backfill', 'disabled in settings');
        }

        if ($collKillswitch || $ppKillswitch) {
            return $this->disablePane($pane, 'Backfill', 'kill limit exceeded');
        }

        $artisanCommand = match ((int) $enabled) {
            1 => 'multiprocessing:backfill',
            4 => 'multiprocessing:safe backfill',
            default => null,
        };

        if (! $artisanCommand) {
            return false;
        }

        // Calculate sleep time (progressive if enabled)
        $baseSleep = (int) ($config['settings']['back_timer'] ?? 600);
        $collections = (int) ($config['counts']['now']['collections_table'] ?? 0);
        $progressive = (int) ($config['settings']['progressive'] ?? 0);

        $sleep = ($progressive === 1 && floor($collections / 500) > $baseSleep)
            ? floor($collections / 500)
            : $baseSleep;

        $niceness = $this->getNiceness();
        $command = "nice -n{$niceness} ".PHP_BINARY." artisan {$artisanCommand}";
        $command = $this->buildCommand($command, ['log_pane' => 'backfill', 'sleep' => $sleep]);

        return $this->paneManager->respawnPane($pane, $command);
    }

    /**
     * Run releases update
     */
    public function runReleasesUpdate(array $config): bool
    {
        $enabled = (int) ($config['settings']['releases_run'] ?? 0);
        $pane = $config['pane'] ?? '0.3';

        if (! $enabled) {
            return $this->disablePane($pane, 'Update Releases', 'disabled in settings');
        }

        $niceness = $this->getNiceness();
        $command = "nice -n{$niceness} ".PHP_BINARY.' artisan multiprocessing:releases';
        $sleep = (int) ($config['settings']['rel_timer'] ?? 60);
        $command = $this->buildCommand($command, ['log_pane' => 'releases', 'sleep' => $sleep]);

        return $this->paneManager->respawnPane($pane, $command);
    }

    /**
     * Run a specific pane task based on task name
     *
     * @param  string  $taskName  The name of the task to run
     * @param  array  $config  Configuration for the task (target pane, etc.)
     * @param  array  $runVar  Runtime variables and settings
     * @return bool Success status
     */
    public function runPaneTask(string $taskName, array $config, array $runVar): bool
    {
        $sequential = (int) ($runVar['constants']['sequential'] ?? 0);

        return match ($taskName) {
            'main' => $this->runMainTask($sequential, $runVar),
            'fixnames' => $this->runFixNamesTask($runVar),
            'removecrap' => $this->runRemoveCrapTask($runVar),
            'ppadditional' => $this->runPostProcessAdditional($runVar),
            'nonamazon' => $this->runNonAmazonTask($runVar),
            'amazon' => $this->runAmazonTask($runVar),
            'scraper' => $this->runIRCScraper($runVar),
            default => false,
        };
    }

    /**
     * Run main task (varies by sequential mode)
     */
    protected function runMainTask(int $sequential, array $runVar): bool
    {
        return match ($sequential) {
            0 => $this->runMainNonSequential($runVar),
            1 => $this->runMainBasic($runVar),
            2 => $this->runMainSequential($runVar),
            default => false,
        };
    }

    /**
     * Run main non-sequential task (binaries, backfill, releases)
     */
    protected function runMainNonSequential(array $runVar): bool
    {
        // This runs in pane 0.1, 0.2, 0.3
        // For now, delegate to existing methods
        $this->runBinariesUpdate($runVar);
        $this->runBackfill($runVar);
        $this->runReleasesUpdate(array_merge($runVar, ['pane' => '0.3']));

        return true;
    }

    /**
     * Run main basic sequential task (just releases)
     */
    protected function runMainBasic(array $runVar): bool
    {
        return $this->runReleasesUpdate(array_merge($runVar, ['pane' => '0.1']));
    }

    /**
     * Run main full sequential task
     */
    protected function runMainSequential(array $runVar): bool
    {
        // Full sequential mode - runs group:update-all for each group
        $pane = '0.1';

        $niceness = $this->getNiceness();
        $artisan = base_path('artisan');
        $command = "nice -n{$niceness} php {$artisan} group:update-all";
        $command = $this->buildCommand($command, ['log_pane' => 'sequential']);

        return $this->paneManager->respawnPane($pane, $command);
    }

    /**
     * Run fix release names task
     */
    protected function runFixNamesTask(array $runVar): bool
    {
        $enabled = (int) ($runVar['settings']['fix_names'] ?? 0);
        $work = (int) ($runVar['counts']['now']['processrenames'] ?? 0);
        $pane = '1.0';

        if ($enabled !== 1) {
            return $this->disablePane($pane, 'Fix Release Names', 'disabled in settings');
        }

        if ($work === 0) {
            return $this->disablePane($pane, 'Fix Release Names', 'no releases to process');
        }

        $artisan = base_path('artisan');
        $log = $this->getLogFile('fixnames');

        // Run multiple fix-names passes
        $commands = [];
        foreach ([3, 5, 7, 9, 11, 13, 15, 17, 19] as $level) {
            $commands[] = "php {$artisan} releases:fix-names {$level} --update --category=other --set-status --show 2>&1 | tee -a {$log}";
        }

        $sleep = (int) ($runVar['settings']['fix_timer'] ?? 300);
        $allCommands = implode('; ', $commands);
        $sleepCommand = $this->buildSleepCommand($sleep);
        $fullCommand = "{$allCommands}; date +'%Y-%m-%d %T'; {$sleepCommand}";

        return $this->paneManager->respawnPane($pane, $fullCommand);
    }

    /**
     * Run remove crap releases task
     */
    protected function runRemoveCrapTask(array $runVar): bool
    {
        $option = $runVar['settings']['fix_crap_opt'] ?? 'Disabled';
        $pane = '1.1';

        // Handle disabled state
        if ($option === 'Disabled' || $option === 0 || $option === '0') {
            return $this->disablePane($pane, 'Remove Crap', 'disabled in settings');
        }

        $niceness = $this->getNiceness();
        $artisan = base_path('artisan');
        $sleep = (int) ($runVar['settings']['crap_timer'] ?? 300);

        // Handle 'All' mode - run all types with 2 hour time limit
        if ($option === 'All') {
            $command = "nice -n{$niceness} php {$artisan} releases:remove-crap --time=2 --delete";
            $command = $this->buildCommand($command, ['log_pane' => 'removecrap', 'sleep' => $sleep]);

            return $this->paneManager->respawnPane($pane, $command);
        }

        // Handle 'Custom' mode - run all selected types sequentially
        if ($option === 'Custom') {
            $selectedTypes = $runVar['settings']['fix_crap'] ?? '';

            // Convert numeric 0 or empty values to empty string
            if (empty($selectedTypes) || $selectedTypes === 0 || $selectedTypes === '0') {
                return $this->disablePane($pane, 'Remove Crap', 'no crap types selected');
            }

            $types = is_array($selectedTypes) ? $selectedTypes : explode(',', $selectedTypes);

            // Trim whitespace and filter out empty values and '0'
            $types = array_map('trim', $types);
            $types = array_filter($types, fn ($type) => ! empty($type) && $type !== '0');

            // Re-index array to ensure sequential keys
            $types = array_values($types);

            if (empty($types)) {
                return $this->disablePane($pane, 'Remove Crap', 'no crap types selected');
            }

            // Get state to determine if this is first run
            $stateFile = storage_path('tmux/removecrap_state.json');
            $state = $this->loadCrapState($stateFile);
            $isFirstRun = $state['first_run'] ?? true;

            // Determine time limit: full on first run, 4 hours otherwise
            $time = $isFirstRun ? 'full' : '4';

            // Build commands for all enabled types to run sequentially
            $log = $this->getLogFile('removecrap');
            $commands = [];
            foreach ($types as $type) {
                $commands[] = "echo \"\nRunning removeCrapReleases for {$type}\"; nice -n{$niceness} php {$artisan} releases:remove-crap --type={$type} --time={$time} --delete 2>&1 | tee -a {$log}";
            }

            // Join all commands with semicolons and add final timestamp and sleep
            $allCommands = implode('; ', $commands);
            $sleepCommand = $this->buildSleepCommand($sleep);
            $fullCommand = "{$allCommands}; date +'%Y-%m-%d %T'; {$sleepCommand}";

            // Mark that we're not on the first run anymore for next cycle
            $this->saveCrapState($stateFile, [
                'first_run' => false,
                'types' => $types,
            ]);

            return $this->paneManager->respawnPane($pane, $fullCommand);
        }

        // Default fallback - disabled
        return $this->disablePane($pane, 'Remove Crap', 'invalid configuration');
    }

    /**
     * Load crap removal state
     */
    protected function loadCrapState(string $file): array
    {
        if (! file_exists($file)) {
            return ['first_run' => true];
        }

        $content = file_get_contents($file);
        $state = json_decode($content, true);

        return $state ?: ['first_run' => true];
    }

    /**
     * Save crap removal state
     */
    protected function saveCrapState(string $file, array $state): void
    {
        $dir = dirname($file);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT));
    }

    /**
     * Run post-process additional task
     */
    protected function runPostProcessAdditional(array $runVar): bool
    {
        $postSetting = (int) ($runVar['settings']['post'] ?? 0);
        $pane = '2.0';

        // Check if post processing is enabled (1 = additional, 2 = nfo, 3 = both)
        if ($postSetting === 0) {
            return $this->disablePane($pane, 'Post-process Additional', 'disabled in settings');
        }

        $hasWork = (int) ($runVar['counts']['now']['work'] ?? 0) > 0;
        $hasNfo = (int) ($runVar['counts']['now']['processnfo'] ?? 0) > 0;

        $niceness = Settings::settingValue('niceness') ?? 2;
        $log = $this->getLogFile('post_additional');
        $sleep = (int) ($runVar['settings']['post_timer'] ?? 300);

        $commands = [];

        // Build commands based on post setting value
        if ($postSetting === 1) {
            // Post = 1: Additional processing only
            if ($hasWork) {
                $commands[] = "nice -n{$niceness} ".PHP_BINARY." artisan update:postprocess additional true 2>&1 | tee -a {$log}";
            }
        } elseif ($postSetting === 2) {
            // Post = 2: NFO processing only
            if ($hasNfo) {
                $commands[] = "nice -n{$niceness} ".PHP_BINARY." artisan update:postprocess nfo true 2>&1 | tee -a {$log}";
            }
        } elseif ($postSetting === 3) {
            // Post = 3: Both additional and NFO
            if ($hasWork) {
                $commands[] = "nice -n{$niceness} ".PHP_BINARY." artisan update:postprocess additional true 2>&1 | tee -a {$log}";
            }
            if ($hasNfo) {
                $commands[] = "nice -n{$niceness} ".PHP_BINARY." artisan update:postprocess nfo true 2>&1 | tee -a {$log}";
            }
        }

        // If no work available, disable the pane
        if (empty($commands)) {
            $reason = match ($postSetting) {
                1 => 'no additional work to process',
                2 => 'no NFOs to process',
                3 => 'no additional work or NFOs to process',
                default => 'invalid post setting value',
            };

            return $this->disablePane($pane, 'Post-process Additional', $reason);
        }

        // Build the full command with all parts
        $allCommands = implode('; ', $commands);
        $sleepCommand = $this->buildSleepCommand($sleep);
        $fullCommand = "{$allCommands}; date +'%Y-%m-%d %T'; {$sleepCommand}";

        return $this->paneManager->respawnPane($pane, $fullCommand);
    }

    /**
     * Run non-Amazon post-processing (TV, Movies, Anime)
     */
    protected function runNonAmazonTask(array $runVar): bool
    {
        $enabled = (int) ($runVar['settings']['post_non'] ?? 0);
        $pane = '2.1';

        if ($enabled !== 1) {
            return $this->disablePane($pane, 'Post-process Non-Amazon', 'disabled in settings');
        }

        $niceness = $this->getNiceness();
        $log = $this->getLogFile('post_non');
        $artisan = PHP_BINARY.' artisan';
        $commands = [];

        // Only add TV processing if enabled and has work
        $processTv = (int) ($runVar['settings']['processtvrage'] ?? 0);
        $hasTvWork = (int) ($runVar['counts']['now']['processtv'] ?? 0) > 0;
        if ($processTv > 0 && $hasTvWork) {
            $commands[] = "nice -n{$niceness} {$artisan} update:postprocess tv true 2>&1 | tee -a {$log}";
        }

        // Only add Movies processing if enabled and has work
        $processMovies = (int) ($runVar['settings']['processmovies'] ?? 0);
        $hasMoviesWork = (int) ($runVar['counts']['now']['processmovies'] ?? 0) > 0;
        if ($processMovies > 0 && $hasMoviesWork) {
            $commands[] = "nice -n{$niceness} {$artisan} update:postprocess movies true 2>&1 | tee -a {$log}";
        }

        // Only add Anime processing if enabled and has work
        $processAnime = (int) ($runVar['settings']['processanime'] ?? 0);
        $hasAnimeWork = (int) ($runVar['counts']['now']['processanime'] ?? 0) > 0;
        if ($processAnime > 0 && $hasAnimeWork) {
            $commands[] = "nice -n{$niceness} {$artisan} update:postprocess anime true 2>&1 | tee -a {$log}";
        }

        // If no work available for any enabled type, disable the pane
        if (empty($commands)) {
            $enabledTypes = [];
            if ($processTv > 0) {
                $enabledTypes[] = 'TV';
            }
            if ($processMovies > 0) {
                $enabledTypes[] = 'Movies';
            }
            if ($processAnime > 0) {
                $enabledTypes[] = 'Anime';
            }

            if (empty($enabledTypes)) {
                return $this->disablePane($pane, 'Post-process Non-Amazon', 'no types enabled (TV/Movies/Anime)');
            }

            $typesList = implode(', ', $enabledTypes);

            return $this->disablePane($pane, 'Post-process Non-Amazon', "no work for enabled types ({$typesList})");
        }

        $sleep = (int) ($runVar['settings']['post_timer_non'] ?? 300);
        $allCommands = implode('; ', $commands);
        $sleepCommand = $this->buildSleepCommand($sleep);
        $fullCommand = "{$allCommands}; date +'%Y-%m-%d %T'; {$sleepCommand}";

        return $this->paneManager->respawnPane($pane, $fullCommand);
    }

    /**
     * Run Amazon post-processing (Books, Music, Games, Console, XXX)
     */
    protected function runAmazonTask(array $runVar): bool
    {
        $enabled = (int) ($runVar['settings']['post_amazon'] ?? 0);
        $pane = '2.2';

        if ($enabled !== 1) {
            return $this->disablePane($pane, 'Post-process Amazon', 'disabled in settings');
        }

        $hasWork = (int) ($runVar['counts']['now']['processmusic'] ?? 0) > 0
            || (int) ($runVar['counts']['now']['processbooks'] ?? 0) > 0
            || (int) ($runVar['counts']['now']['processconsole'] ?? 0) > 0
            || (int) ($runVar['counts']['now']['processgames'] ?? 0) > 0
            || (int) ($runVar['counts']['now']['processxxx'] ?? 0) > 0;

        if (! $hasWork) {
            return $this->disablePane($pane, 'Post-process Amazon', 'no music/books/games to process');
        }

        $niceness = Settings::settingValue('niceness') ?? 2;
        $command = "nice -n{$niceness} ".PHP_BINARY.' artisan update:postprocess amazon true';
        $sleep = (int) ($runVar['settings']['post_timer_amazon'] ?? 300);
        $command = $this->buildCommand($command, ['log_pane' => 'post_amazon', 'sleep' => $sleep]);

        return $this->paneManager->respawnPane($pane, $command);
    }
}
