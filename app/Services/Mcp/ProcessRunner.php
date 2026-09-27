<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessRunner
{
    public function __construct(
        private readonly SensitiveValueRedactor $redactor,
    ) {}

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     * @return array{command: list<string>, exit_code: int|null, duration_ms: int, status: string, output: string, truncated: bool}
     */
    public function run(array $command, int $timeoutSeconds, array $environment = []): array
    {
        $startedAt = hrtime(true);
        $process = new Process($command, base_path(), $environment, null, $timeoutSeconds);

        try {
            $process->run();
            $status = $process->isSuccessful() ? 'passed' : 'failed';
            $exitCode = $process->getExitCode();
        } catch (ProcessTimedOutException) {
            $status = 'timed_out';
            $exitCode = null;
        } catch (Throwable $throwable) {
            $status = 'failed';
            $exitCode = null;
            $processOutput = $throwable->getMessage();
        }

        $processOutput ??= trim($process->getOutput()."\n".$process->getErrorOutput());
        [$output, $truncated] = $this->limit($this->redactor->redact($processOutput));

        return [
            'command' => $command,
            'exit_code' => $exitCode,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'status' => $status,
            'output' => $output,
            'truncated' => $truncated,
        ];
    }

    /**
     * @return array{string, bool}
     */
    private function limit(string $output): array
    {
        $maximum = max(1, (int) config('nntmux-mcp.limits.max_process_output_bytes', 32_768));
        if (strlen($output) <= $maximum) {
            return [$output, false];
        }

        return [mb_strcut($output, 0, $maximum).'\n[output truncated]', true];
    }
}
