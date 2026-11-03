<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;

/** Executes RARLAB commands with literal arguments and bounded diagnostic output. */
class UnrarRunner
{
    private ?float $deadline = null;

    public function setDeadline(?float $deadline): void
    {
        $this->deadline = $deadline;
    }

    /** @var array<string, string> */
    private array $capabilities = [];

    public function capability(string $binary): string
    {
        if (isset($this->capabilities[$binary])) {
            return $this->capabilities[$binary];
        }
        $path = str_contains($binary, '/') ? $binary : (new ExecutableFinder)->find($binary);
        if ($path === null || ! is_file($path) || ! is_executable($path)) {
            return $this->capabilities[$binary] = 'missing_binary';
        }
        try {
            $banner = Process::timeout(max(0.01, min(3, $this->remainingSeconds())))->env(['LC_ALL' => 'C', 'RAR' => false])->run([$binary]);
            $output = substr($banner->output().$banner->errorOutput(), 0, 4096);
            $compatible = preg_match('/\bUNRAR\s+\d+\.\d+/i', $output) === 1 && stripos($output, 'unrar-free') === false;

            return $this->capabilities[$binary] = $compatible ? 'ready' : 'incompatible_tool';
        } catch (\Throwable) {
            return $this->capabilities[$binary] = 'missing_binary';
        }
    }

    /** @param list<string> $arguments */
    public function run(string $binary, array $arguments, float $seconds = 60): ProcessResult
    {
        $seconds = min($seconds, $this->remainingSeconds());
        if ($seconds <= 0) {
            return Process::result('', 'budget_exhausted', 124);
        }
        $capability = $this->capability($binary);
        if ($capability !== 'ready') {
            Log::warning('Archive tool unavailable; configure UNRAR_PATH with a compatible RARLAB UnRAR executable.', ['reason' => $capability]);

            return Process::result('', $capability, 127);
        }
        $separator = array_search('--', $arguments, true);
        $archive = $separator !== false ? ($arguments[$separator + 1] ?? '') : '';
        if ($archive === '' || ! is_file($archive)) {
            return Process::result('', 'missing_input', 10);
        }
        if (! is_readable($archive)) {
            return Process::result('', 'filesystem_failure', 6);
        }
        $signature = file_get_contents($archive, false, null, 0, 8);
        if (! is_string($signature) || ! str_starts_with($signature, "Rar!\x1a\x07")) {
            return Process::result('', 'invalid_archive', 2);
        }
        $seconds = min($seconds, $this->remainingSeconds());
        if ($seconds <= 0) {
            return Process::result('', 'budget_exhausted', 124);
        }
        $outputBytes = 0;
        try {
            $result = Process::timeout(min(120, $seconds))->input('')->env(['LC_ALL' => 'C', 'RAR' => false])
                ->run([$binary, ...$arguments], function (string $type, string $output) use (&$outputBytes): void {
                    $outputBytes += strlen($output);
                    if ($outputBytes > 131072) {
                        throw new \RuntimeException('output_limit');
                    }
                });
            $reason = $this->reason($result);
            if ($reason !== 'success') {
                Log::debug('Archive tool completed', ['operation' => $arguments[0] ?? '', 'exit_code' => $result->exitCode(), 'reason' => $reason]);
            }

            return $result;
        } catch (ProcessTimedOutException) {
            return Process::result('', 'budget_exhausted', 124);
        } catch (\Throwable) {
            return Process::result('', 'resource_limit', 2);
        }
    }

    public function remainingSeconds(): float
    {
        return $this->deadline === null ? 120 : max(0, $this->deadline - hrtime(true) / 1e9);
    }

    public function reason(ProcessResult $result): string
    {
        $output = strtolower(substr($result->output()."\n".$result->errorOutput(), 0, 131072));
        foreach (['missing_binary', 'incompatible_tool', 'budget_exhausted', 'resource_limit', 'filesystem_failure', 'missing_input', 'invalid_archive'] as $reason) {
            if (str_contains($output, $reason)) {
                return $reason;
            }
        }

        return match (true) {
            $result->exitCode() === 11, str_contains($output, 'incorrect password'), str_contains($output, 'wrong password') => 'wrong_password',
            str_contains($output, 'unknown option'), $result->exitCode() === 7 => 'invocation_error',
            str_contains($output, 'cannot find volume'), str_contains($output, 'previous volume') => 'missing_volume',
            str_contains($output, 'unexpected end') => 'truncated_data',
            str_contains($output, 'unknown method'), str_contains($output, 'unsupported'), str_contains($output, 'dictionary size') => 'unsupported_format',
            str_contains($output, 'permission denied'), str_contains($output, 'access denied'), str_contains($output, 'cannot create') => 'filesystem_failure',
            str_contains($output, 'cannot open'), $result->exitCode() === 10 => 'missing_input',
            $result->exitCode() === 5, $result->exitCode() === 6, $result->exitCode() === 9 => 'filesystem_failure',
            $result->exitCode() === 8 => 'resource_limit',
            $result->exitCode() === 3 => 'corrupt_data',
            $result->exitCode() === 0 => 'success',
            default => 'archive_failure',
        };
    }
}
