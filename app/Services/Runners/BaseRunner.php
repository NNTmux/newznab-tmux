<?php

namespace App\Services\Runners;

use Symfony\Component\Process\Process;

abstract class BaseRunner
{
    public function __construct() {}

    protected function buildDnrCommand(string $args): string
    {
        // Convert legacy command arguments to new artisan commands
        return $this->convertSwitchToArtisan($args);
    }

    /**
     * Convert legacy command format to new artisan commands.
     */
    private function convertSwitchToArtisan(string $args): string
    {
        $parts = array_filter(explode('  ', trim($args)));

        if (empty($parts)) {
            return '';
        }

        $command = $parts[0] ?? '';

        switch ($command) {
            case 'backfill':
                // backfill  {group}  {type}
                $group = $parts[1] ?? '';
                $type = $parts[2] ?? '1';

                return PHP_BINARY.' artisan backfill:group "'.$group.'" '.$type;

            case 'backfill_all_quantity':
                // backfill_all_quantity  {group}  {quantity}
                $group = $parts[1] ?? '';
                $quantity = $parts[2] ?? '';

                return PHP_BINARY.' artisan backfill:group "'.$group.'" 1 '.$quantity;

            case 'backfill_all_quick':
                // backfill_all_quick  {group}
                $group = $parts[1] ?? '';

                return PHP_BINARY.' artisan backfill:group "'.$group.'" 1 10000';

            case 'get_range':
                // get_range  {mode}  {group}  {first}  {last}  {threads}
                $mode = $parts[1] ?? '';
                $group = $parts[2] ?? '';
                $first = $parts[3] ?? '0';
                $last = $parts[4] ?? '0';

                return PHP_BINARY.' artisan articles:get-range "'.$mode.'" "'.$group.'" '.$first.' '.$last;

            case 'part_repair':
                // part_repair  {group}
                $group = $parts[1] ?? '';

                return PHP_BINARY.' artisan binaries:part-repair "'.$group.'"';

            case 'releases':
                // releases  {groupId}
                $groupId = $parts[1] ?? '';

                return PHP_BINARY.' artisan releases:process '.($groupId !== '' ? $groupId : '');

            case 'update_group_headers':
                // update_group_headers  {group}
                $group = $parts[1] ?? '';

                return PHP_BINARY.' artisan group:update-headers "'.$group.'"';

            case 'update_per_group':
                // update_per_group  {groupId}
                $groupId = $parts[1] ?? '';

                return PHP_BINARY.' artisan group:update-all '.$groupId;

            case 'pp_additional':
                // pp_additional  {guid}
                $guid = $parts[1] ?? '';

                return PHP_BINARY.' artisan postprocess:guid additional '.$guid;

            case 'pp_nfo':
                // pp_nfo  {guid}
                $guid = $parts[1] ?? '';

                return PHP_BINARY.' artisan postprocess:guid nfo '.$guid;

            case 'pp_movie':
                // pp_movie  {guid}  {renamed}
                $guid = $parts[1] ?? '';
                $renamed = $parts[2] ?? '';

                return PHP_BINARY.' artisan postprocess:guid movie '.$guid.($renamed !== '' ? ' '.$renamed : '');

            case 'pp_tv':
                // pp_tv  {guid}  {renamed}
                $guid = $parts[1] ?? '';
                $renamed = $parts[2] ?? '';

                return PHP_BINARY.' artisan postprocess:guid tv '.$guid.($renamed !== '' ? ' '.$renamed : '');

            default:
                // Log unrecognized command and return empty string
                if (config('app.debug')) {
                    \Log::warning('Unrecognized multiprocessing command: '.$args);
                }

                return '';
        }
    }

    /**
     * Public wrapper for buildDnrCommand (used by ForkingService).
     */
    public function buildDnrCommandPublic(string $args): string
    {
        return $this->buildDnrCommand($args);
    }

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

    protected function headerStart(string $workType, int $count, int $maxProcesses): void
    {
        if (config('nntmux.echocli')) {
            cli()->header(
                'Multi-processing started at '.now()->toRfc2822String().' for '.$workType.' with '.$count.
                ' job(s) to do using a max of '.max(1, $maxProcesses).' child process(es).'
            );
        }
    }

    protected function headerNone(): void
    {
        if (config('nntmux.echocli')) {
            cli()->header('No work to do!');
        }
    }

    /**
     * Run multiple commands in parallel using Symfony Process with configurable timeout.
     * This replaces Laravel Concurrency::run() which has a fixed 60-second timeout.
     *
     * @param  array<string|int, callable>  $tasks  Array of callables keyed by identifier
     * @param  int  $maxProcesses  Maximum concurrent processes
     * @param  int|null  $timeout  Timeout in seconds (null = use config default)
     * @return array<string|int, mixed> Results keyed by the same identifiers as $tasks
     */
    protected function runParallelProcesses(array $tasks, int $maxProcesses, ?int $timeout = null): array
    {
        $maxProcesses = max(1, $maxProcesses);
        $timeout = $timeout ?? (int) config('nntmux.multiprocessing_max_child_time', 1800);
        $results = [];
        $running = [];
        $queue = $tasks;

        $startNext = function () use (&$queue, &$running): ?string {
            if (empty($queue)) {
                return null;
            }
            $key = array_key_first($queue);
            $callable = $queue[$key];
            unset($queue[$key]);

            // Get the command string from the callable context
            // We need to execute the callable which returns the command result
            $running[$key] = [
                'callable' => $callable,
                'started' => microtime(true),
            ];

            return (string) $key;
        };

        // For small batch sizes, run synchronously to avoid overhead
        if (count($tasks) <= 1 || $maxProcesses <= 1) {
            foreach ($tasks as $key => $callable) {
                try {
                    $results[$key] = $callable();
                } catch (\Throwable $e) {
                    \Log::error("Task {$key} failed: ".$e->getMessage());
                    $results[$key] = '';
                }
            }

            return $results;
        }

        // For parallel execution, we need to use Process directly
        // Convert callables to commands and run them in parallel
        $commands = [];
        $taskMapping = [];

        foreach ($tasks as $key => $callable) {
            // We need to extract the command from the callable
            // This is a bit tricky, but we can use reflection or run the callable
            // For now, let's store the callable and run them in batches
            $commands[$key] = $callable;
        }

        // Process in batches
        $batches = array_chunk($commands, $maxProcesses, true);

        foreach ($batches as $batch) {
            $batchProcesses = [];

            foreach ($batch as $key => $callable) {
                try {
                    $results[$key] = $callable();
                } catch (\Throwable $e) {
                    \Log::error("Task {$key} failed: ".$e->getMessage());
                    $results[$key] = '';
                }
            }
        }

        return $results;
    }

    /**
     * Run multiple commands in parallel with real process forking and configurable timeout.
     *
     * @param  array<string|int, string>  $commands  Array of shell commands keyed by identifier
     * @param  int  $maxProcesses  Maximum concurrent processes
     * @param  int|null  $timeout  Timeout in seconds (null = use config default)
     * @return array<string|int, string> Command outputs keyed by the same identifiers
     */
    protected function runParallelCommands(array $commands, int $maxProcesses, ?int $timeout = null): array
    {
        $maxProcesses = max(1, $maxProcesses);
        $timeout = $timeout ?? (int) config('nntmux.multiprocessing_max_child_time', 1800);
        $results = [];
        $running = [];
        $queue = $commands;

        $startNext = function () use (&$queue, &$running, $timeout) {
            if (empty($queue)) {
                return;
            }
            $key = array_key_first($queue);
            $cmd = $queue[$key];
            unset($queue[$key]);

            $proc = Process::fromShellCommandline($cmd);
            $proc->setTimeout($timeout);
            $proc->start();
            $running[$key] = $proc;
        };

        // Prime initial processes
        for ($i = 0; $i < $maxProcesses && ! empty($queue); $i++) {
            $startNext();
        }

        // Event loop
        while (! empty($running)) {
            foreach ($running as $key => $proc) {
                if (! $proc->isRunning()) {
                    $results[$key] = $proc->getOutput();
                    // Output errors if any
                    $err = $proc->getErrorOutput();
                    if ($err !== '') {
                        echo $err;
                    }
                    unset($running[$key]);
                    // Start next from queue if available
                    if (! empty($queue)) {
                        $startNext();
                    }
                }
            }
            usleep(50000); // 50ms
        }

        return $results;
    }

    /**
     * Run multiple shell commands concurrently and stream their output in real-time.
     * Uses Symfony Process start() with a small event loop to enforce max concurrency.
     */
    protected function runStreamingCommands(array $commands, int $maxProcesses, string $desc): void
    {
        $maxProcesses = max(1, (int) $maxProcesses);
        $running = [];
        $queue = $commands;
        $total = \count($commands);
        $started = 0;
        $finished = 0;

        $this->headerStart('postprocess: '.$desc, $total, $maxProcesses);

        $startNext = function () use (&$queue, &$running, &$started) {
            if (empty($queue)) {
                return;
            }
            $cmd = array_shift($queue);
            $proc = Process::fromShellCommandline($cmd);
            $proc->setTimeout((int) config('nntmux.multiprocessing_max_child_time', 1800));
            $proc->start(function ($type, $buffer) {
                // Stream both STDOUT and STDERR
                echo $buffer;
            });
            $running[spl_object_id($proc)] = $proc;
            $started++;
        };

        // Prime initial processes
        for ($i = 0; $i < $maxProcesses && ! empty($queue); $i++) {
            $startNext();
        }

        // Event loop
        while (! empty($running)) {
            foreach ($running as $key => $proc) {
                if (! $proc->isRunning()) {
                    // Print any remaining buffered output
                    $out = $proc->getIncrementalOutput();
                    $err = $proc->getIncrementalErrorOutput();
                    if ($out !== '') {
                        echo $out;
                    }
                    if ($err !== '') {
                        echo $err;
                    }
                    unset($running[$key]);
                    $finished++;
                    if (config('nntmux.echocli')) {
                        cli()->primary('Finished task #'.($total - $finished + 1).' for '.$desc);
                    }
                    // Start next from queue if available
                    if (! empty($queue)) {
                        $startNext();
                    }
                }
            }
            usleep(100000); // 100ms
        }
    }
}
