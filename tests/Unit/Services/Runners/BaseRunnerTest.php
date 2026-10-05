<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Runners;

use App\Services\Runners\BaseRunner;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class BaseRunnerTest extends TestCase
{
    #[Test]
    public function execute_command_returns_output_on_success(): void
    {
        $runner = new BaseRunnerTestDouble;

        $this->assertSame('hello', trim($runner->runCommand('echo hello')));
    }

    #[Test]
    public function execute_command_throws_runtime_exception_with_clear_message_on_timeout(): void
    {
        config(['nntmux.concurrency_timeout' => 1]);

        $runner = new BaseRunnerTestDouble;

        try {
            $runner->runCommand('sleep 5');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            // Laravel's Concurrency ProcessDriver cannot reconstruct
            // ProcessTimedOutException, so executeCommand() must surface a
            // RuntimeException carrying the original timeout message instead.
            $this->assertStringContainsString('exceeded the timeout', $e->getMessage());
        }
    }

    #[Test]
    public function asynchronous_pool_enforces_timeout_and_continues_other_jobs(): void
    {
        $runner = new BaseRunnerTestDouble;
        try {
            $runner->pool([
                'hung' => [PHP_BINARY, '-r', 'sleep(3);'],
                'ok' => [PHP_BINARY, '-r', 'echo "done";'],
            ], 2, 1);
            $this->fail('Expected a failed batch.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('hung timeout', $exception->getMessage());
            $this->assertSame('timeout', $runner->outcomes()['hung']['status']);
            $this->assertSame(['status' => 'success', 'exit_code' => 0], $runner->outcomes()['ok']);
        }
    }

    #[Test]
    public function asynchronous_pool_reports_nonzero_exit_codes(): void
    {
        $runner = new BaseRunnerTestDouble;
        try {
            $runner->pool(['failed' => [PHP_BINARY, '-r', 'exit(7);']], 1, 3);
            $this->fail('Expected a failed batch.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('failed failure (exit 7)', $exception->getMessage());
            $this->assertSame(['status' => 'failure', 'exit_code' => 7], $runner->outcomes()['failed']);
        }
    }

    #[Test]
    public function streaming_output_is_emitted_exactly_once(): void
    {
        $runner = new BaseRunnerTestDouble;
        ob_start();
        try {
            $runner->stream([[PHP_BINARY, '-r', 'echo "unique stdout"; fwrite(STDERR, "unique stderr");']], 1);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertSame(1, substr_count($output, 'unique stdout'));
        $this->assertSame(1, substr_count($output, 'unique stderr'));
    }

    #[Test]
    public function cancellation_stops_owned_workers_and_does_not_launch_queued_jobs(): void
    {
        if (! function_exists('pcntl_signal') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('POSIX signals are unavailable.');
        }
        $runner = new BaseRunnerTestDouble;
        $marker = tempnam(sys_get_temp_dir(), 'nntmux-cancel-');
        $this->assertNotFalse($marker);
        $started = microtime(true);
        try {
            $runner->pool([
                'signal' => [PHP_BINARY, '-r', 'usleep(100000); posix_kill(posix_getppid(), SIGTERM); sleep(5);'],
                'owned' => [PHP_BINARY, '-r', 'sleep(5); file_put_contents($argv[1], "orphan");', $marker],
                'queued' => [PHP_BINARY, '-r', 'file_put_contents($argv[1], "queued");', $marker],
            ], 2, 10);
            $this->fail('Expected cancellation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('cancellation', $exception->getMessage());
            $this->assertSame('', file_get_contents($marker));
            $this->assertSame('cancellation', $runner->outcomes()['queued']['status']);
            $this->assertLessThan(4, microtime(true) - $started);
        } finally {
            unlink($marker);
        }
    }

    #[Test]
    public function legacy_worker_commands_preserve_arguments_without_shell_interpolation(): void
    {
        $group = 'alt.test; literal "quote" $(literal)';
        $this->assertSame([PHP_BINARY, base_path('artisan'), 'backfill:group', $group, '1'],
            (new BaseRunnerTestDouble)->buildDnrCommandPublic('backfill  '.$group.'  1'));
    }

    #[Test]
    public function concurrency_timeout_prefers_concurrency_timeout_config(): void
    {
        config(['nntmux.concurrency_timeout' => 60]);
        config(['nntmux.multiprocessing_max_child_time' => 42]);

        $this->assertSame(60, (new BaseRunnerTestDouble)->timeout());
    }

    #[Test]
    public function concurrency_timeout_falls_back_to_multiprocessing_max_child_time(): void
    {
        config(['nntmux.concurrency_timeout' => null]);
        config(['nntmux.multiprocessing_max_child_time' => 42]);

        $this->assertSame(42, (new BaseRunnerTestDouble)->timeout());
    }
}

class BaseRunnerTestDouble extends BaseRunner
{
    public function runCommand(string $command): string
    {
        return $this->executeCommand($command);
    }

    /** @param array<string|int, string|list<string>> $commands
     * @return array<string|int, string>
     */
    public function pool(array $commands, int $limit, int $timeout): array
    {
        return $this->runParallelCommands($commands, $limit, $timeout);
    }

    /** @return array<string|int, array{status: string, exit_code: ?int}> */
    public function outcomes(): array
    {
        return $this->workerOutcomes;
    }

    /** @param array<string|int, string|list<string>> $commands */
    public function stream(array $commands, int $limit): void
    {
        $this->runStreamingCommands($commands, $limit, 'test');
    }

    public function timeout(): int
    {
        return $this->concurrencyTimeout();
    }
}
