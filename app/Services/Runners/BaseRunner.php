<?php

namespace App\Services\Runners;

use Blacklight\ColorCLI;
use Symfony\Component\Process\Process;

abstract class BaseRunner
{
    protected ColorCLI $colorCli;

    public function __construct(?ColorCLI $colorCli = null)
    {
        $this->colorCli = $colorCli ?? new ColorCLI;
    }

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
            $this->colorCli->header(
                'Multi-processing started at '.now()->toRfc2822String().' for '.$workType.' with '.$count.
                ' job(s) to do using a max of '.max(1, $maxProcesses).' child process(es).'
            );
        }
    }

    protected function headerNone(): void
    {
        if (config('nntmux.echocli')) {
            $this->colorCli->header('No work to do!');
        }
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
                        $this->colorCli->primary('Finished task #'.($total - $finished + 1).' for '.$desc);
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

