<?php

namespace Blacklight\libraries\Runners;

use Blacklight\ColorCLI;
use Spatie\Async\Pool;
use Symfony\Component\Process\Process;

abstract class BaseRunner
{
    protected ColorCLI $colorCli;

    protected const ASYNC_BUFFER_SIZE = 2000000;

    public function __construct(?ColorCLI $colorCli = null)
    {
        $this->colorCli = $colorCli ?? new ColorCLI;
    }

    protected function createPool(int $concurrency): Pool
    {
        $concurrency = max(1, $concurrency);

        return Pool::create()
            ->concurrency($concurrency)
            ->timeout(config('nntmux.multiprocessing_max_child_time'));
    }

    protected function buildDnrCommand(string $args): string
    {
        $dnr_path = PHP_BINARY.' misc/update/multiprocessing/.do_not_run/switch.php "php  ';

        return $dnr_path.$args.'"';
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
