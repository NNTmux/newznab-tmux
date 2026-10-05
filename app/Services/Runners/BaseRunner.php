<?php

declare(strict_types=1);

namespace App\Services\Runners;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

abstract class BaseRunner
{
    /** @var array<string|int, array{status: string, exit_code: ?int}> */
    protected array $workerOutcomes = [];

    private bool $cancellationRequested = false;

    /**
     * Resolve the configured worker timeout in seconds.
     */
    protected function concurrencyTimeout(): int
    {
        $configured = config('nntmux.concurrency_timeout');

        return (int) ($configured ?? config('nntmux.multiprocessing_max_child_time', 1800));
    }

    /** @return list<string> */
    protected function buildDnrCommand(string $args): array
    {
        $parts = explode('  ', trim($args));
        $command = array_shift($parts);
        $worker = match ($command) {
            'backfill' => ['backfill:group', $parts[0] ?? '', $parts[1] ?? '1'],
            'backfill_all_quantity' => ['backfill:group', $parts[0] ?? '', '1', $parts[1] ?? ''],
            'backfill_all_quick' => ['backfill:group', $parts[0] ?? '', '1', '10000'],
            'get_range' => ['articles:get-range', $parts[0] ?? '', $parts[1] ?? '', $parts[2] ?? '0', $parts[3] ?? '0'],
            'part_repair' => ['binaries:part-repair', $parts[0] ?? ''],
            'releases' => ['releases:process', ...$parts],
            'update_group_headers' => ['group:update-headers', $parts[0] ?? ''],
            'update_per_group' => ['group:update-all', $parts[0] ?? ''],
            'pp_additional' => ['postprocess:guid', 'additional', ...$parts],
            'pp_nfo' => ['postprocess:guid', 'nfo', ...$parts],
            'pp_movie' => ['postprocess:guid', 'movie', ...$parts],
            'pp_tv' => ['postprocess:guid', 'tv', ...$parts],
            default => throw new RuntimeException('Unrecognized multiprocessing command.'),
        };

        return [PHP_BINARY, base_path('artisan'), ...$worker];
    }

    /** @return list<string> */
    public function buildDnrCommandPublic(string $args): array
    {
        return $this->buildDnrCommand($args);
    }

    /** @param string|list<string> $command */
    protected function executeCommand(string|array $command): string
    {
        return $this->runWorkerPool([$command], 1, $this->concurrencyTimeout(), false)[0];
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
     * @param  array<string|int, string|list<string>>  $commands
     * @return array<string|int, string>
     */
    protected function runParallelCommands(array $commands, int $maxProcesses, ?int $timeout = null): array
    {
        return $this->runWorkerPool($commands, $maxProcesses, $timeout ?? $this->concurrencyTimeout(), false);
    }

    /** @param array<string|int, string|list<string>> $commands */
    protected function runStreamingCommands(array $commands, int $maxProcesses, string $desc): void
    {
        $this->headerStart('postprocess: '.$desc, count($commands), max(1, $maxProcesses));
        $this->runWorkerPool($commands, $maxProcesses, $this->concurrencyTimeout(), true);
    }

    /** @param string|list<string> $command */
    private function workerProcess(string|array $command): Process
    {
        return is_array($command) ? new Process($command, base_path()) : Process::fromShellCommandline($command, base_path());
    }

    /**
     * @param  array<string|int, string|list<string>>  $commands
     * @return array<string|int, string>
     */
    private function runWorkerPool(array $commands, int $maxProcesses, int $timeout, bool $stream): array
    {
        $queue = $commands;
        $running = [];
        $results = [];
        $this->workerOutcomes = [];
        $this->cancellationRequested = false;
        $handlers = [];
        $async = false;
        if (function_exists('pcntl_signal')) {
            $async = pcntl_async_signals(true);
            foreach ([SIGTERM, SIGINT, SIGHUP] as $signal) {
                $handlers[$signal] = pcntl_signal_get_handler($signal);
                pcntl_signal($signal, function (): void {
                    $this->cancellationRequested = true;
                });
            }
        }

        try {
            while ($queue !== [] || $running !== []) {
                if ($this->isCancellationRequested()) {
                    break;
                }
                while (! $this->isCancellationRequested() && count($running) < max(1, $maxProcesses) && $queue !== []) {
                    $key = array_key_first($queue);
                    $process = $this->workerProcess($queue[$key]);
                    unset($queue[$key]);
                    $process->setTimeout($timeout);
                    $running[$key] = $process;
                    try {
                        $process->start($stream ? static function (string $type, string $buffer): void {
                            echo $buffer;
                        } : null);
                    } catch (\Throwable) {
                        $this->recordWorkerOutcome($key, 'failure', null);
                        unset($running[$key]);
                    }
                }
                foreach ($running as $key => $process) {
                    $status = null;
                    try {
                        if ($process->isRunning()) {
                            $process->checkTimeout();

                            continue;
                        }
                        $status = $process->isSuccessful() ? 'success' : 'failure';
                    } catch (ProcessTimedOutException) {
                        $status = 'timeout';
                    }
                    $results[$key] = $process->getOutput();
                    if (! $stream) {
                        echo $process->getErrorOutput();
                    }
                    $this->recordWorkerOutcome($key, $status, $process->getExitCode());
                    unset($running[$key]);
                }
                usleep(50000);
            }
        } finally {
            foreach ($running as $process) {
                if ($process->isRunning()) {
                    try {
                        $process->signal(SIGTERM);
                    } catch (RuntimeException) {
                        // The owned worker may exit between the state query and signal.
                    }
                }
            }
            $deadline = microtime(true) + 3;
            do {
                $active = array_filter($running, static fn (Process $process): bool => $process->isRunning());
                if ($active === []) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);
            foreach ($running as $key => $process) {
                if ($process->isStarted()) {
                    $process->stop(0);
                }
                $this->recordWorkerOutcome($key, 'cancellation', $process->getExitCode());
            }
            foreach ($queue as $key => $command) {
                $this->recordWorkerOutcome($key, 'cancellation', null);
            }
            foreach ($handlers as $signal => $handler) {
                pcntl_signal($signal, $handler);
            }
            if ($handlers !== []) {
                pcntl_async_signals($async);
            }
        }

        $failed = array_filter($this->workerOutcomes, static fn (array $outcome): bool => $outcome['status'] !== 'success');
        if ($failed !== []) {
            throw new RuntimeException('Worker batch did not succeed: '.implode(', ', array_map(
                static fn (string|int $key, array $outcome): string => $key.' '.$outcome['status'].($outcome['status'] === 'timeout' ? ' exceeded the timeout' : '').' (exit '.($outcome['exit_code'] ?? 'none').')',
                array_keys($failed), array_values($failed),
            )));
        }

        return $results;
    }

    private function isCancellationRequested(): bool
    {
        return $this->cancellationRequested;
    }

    private function recordWorkerOutcome(string|int $key, string $status, ?int $exitCode): void
    {
        $this->workerOutcomes[$key] = ['status' => $status, 'exit_code' => $exitCode];
        if ($status !== 'success') {
            Log::error('Multiprocessing worker did not succeed', ['worker' => $key, 'outcome' => $status, 'exit_code' => $exitCode]);
        }
    }
}
