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
        $this->colorCli = $colorCli ?? new ColorCLI();
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
}
