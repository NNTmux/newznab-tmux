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
        $message = "echo \"\\033[38;5;{$color}m\\n{$taskName} has been disabled: {$reason}\"";

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
        $niceness = Settings::settingValue('niceness') ?? 2;
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

        $niceness = Settings::settingValue('niceness') ?? 2;
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

        $niceness = Settings::settingValue('niceness') ?? 2;
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

        $niceness = Settings::settingValue('niceness') ?? 2;
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

        $niceness = Settings::settingValue('niceness') ?? 2;
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

        $niceness = Settings::settingValue('niceness') ?? 2;
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
        $fullCommand = "{$allCommands}; date +'%Y-%m-%d %T'; sleep {$sleep}";

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

        $niceness = Settings::settingValue('niceness') ?? 2;
        $artisan = base_path('artisan');
        $sleep = (int) ($runVar['settings']['crap_timer'] ?? 300);

        // Handle 'All' mode - run all types with 2 hour time limit
        if ($option === 'All') {
            $command = "nice -n{$niceness} php {$artisan} releases:remove-crap --time=2 --delete";
            $command = $this->buildCommand($command, ['log_pane' => 'removecrap', 'sleep' => $sleep]);
            // Wrap in while loop to keep pane alive
            $loopCommand = "while true; do {$command}; done";

            return $this->paneManager->respawnPane($pane, $loopCommand);
        }

        // Handle 'Custom' mode - cycle through selected types
        if ($option === 'Custom') {
            $selectedTypes = $runVar['settings']['fix_crap'] ?? '';

            if (empty($selectedTypes)) {
                return $this->disablePane($pane, 'Remove Crap', 'no crap types selected');
            }

            $types = is_array($selectedTypes) ? $selectedTypes : explode(',', $selectedTypes);
            $types = array_filter($types); // Remove empty values

            if (empty($types)) {
                return $this->disablePane($pane, 'Remove Crap', 'no crap types selected');
            }

            // Get the next type to process
            $stateFile = storage_path('tmux/removecrap_state.json');
            $state = $this->loadCrapState($stateFile);

            // Determine current type index
            $currentIndex = $state['current_index'] ?? 0;
            $isFirstRun = $state['first_run'] ?? true;

            // Validate index
            if ($currentIndex >= count($types)) {
                $currentIndex = 0;
                $isFirstRun = false;
            }

            $currentType = $types[$currentIndex];

            // Determine time limit: full on first run, 4 hours otherwise
            $time = $isFirstRun ? 'full' : '4';

            // Build and run command
            $command = "nice -n{$niceness} php {$artisan} releases:remove-crap --type={$currentType} --time={$time} --delete";
            $command = $this->buildCommand($command, ['log_pane' => 'removecrap', 'sleep' => $sleep]);

            // Update state for next run
            $nextIndex = $currentIndex + 1;
            if ($nextIndex >= count($types)) {
                $nextIndex = 0;
                $isFirstRun = false;
            }

            $this->saveCrapState($stateFile, [
                'current_index' => $nextIndex,
                'first_run' => $isFirstRun,
                'types' => $types,
            ]);

            // Wrap in while loop to keep pane alive
            $loopCommand = "while true; do {$command}; done";

            return $this->paneManager->respawnPane($pane, $loopCommand);
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
            return ['current_index' => 0, 'first_run' => true];
        }

        $content = file_get_contents($file);
        $state = json_decode($content, true);

        return $state ?: ['current_index' => 0, 'first_run' => true];
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
        $enabled = (int) ($runVar['settings']['post'] ?? 0);
        $pane = '2.0';

        if ($enabled !== 1) {
            return $this->disablePane($pane, 'Post-process Additional', 'disabled in settings');
        }

        $niceness = Settings::settingValue('niceness') ?? 2;
        $command = "nice -n{$niceness} ".PHP_BINARY.' artisan update:postprocess additional true';
        $sleep = (int) ($runVar['settings']['post_timer'] ?? 300);
        $command = $this->buildCommand($command, ['log_pane' => 'post_additional', 'sleep' => $sleep]);

        return $this->paneManager->respawnPane($pane, $command);
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

        $hasWork = (int) ($runVar['counts']['now']['processmovies'] ?? 0) > 0
            || (int) ($runVar['counts']['now']['processtv'] ?? 0) > 0
            || (int) ($runVar['counts']['now']['processanime'] ?? 0) > 0;

        if (! $hasWork) {
            return $this->disablePane($pane, 'Post-process Non-Amazon', 'no movies/tv/anime to process');
        }

        $niceness = Settings::settingValue('niceness') ?? 2;
        $log = $this->getLogFile('post_non');

        $artisan = PHP_BINARY.' artisan';
        $commands = [
            "{$artisan} update:postprocess tv true 2>&1 | tee -a {$log}",
            "{$artisan} update:postprocess movies true 2>&1 | tee -a {$log}",
            "{$artisan} update:postprocess anime true 2>&1 | tee -a {$log}",
        ];

        $sleep = (int) ($runVar['settings']['post_timer_non'] ?? 300);
        $allCommands = "nice -n{$niceness} ".implode('; nice -n{$niceness} ', $commands);
        $fullCommand = "{$allCommands}; date +'%Y-%m-%d %T'; sleep {$sleep}";

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
