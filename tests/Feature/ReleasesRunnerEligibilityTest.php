<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConfigurationDomain;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\Runners\ReleasesRunner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ConfigurationTestBuilder;
use Tests\TestCase;

class ReleasesRunnerEligibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'nntmux.echocli' => false,
        ]);
        DB::purge();
        DB::reconnect();

        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name');
            $table->integer('active')->default(0);
            $table->integer('backfill')->default(0);
        });
        Schema::create('collections', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('groups_id')->index();
        });
        ConfigurationTestBuilder::update(ConfigurationDomain::Ingestion, ['release_threads' => 2]);
        app(ConfigurationProvider::class)->ingestion();
    }

    /** @return array<string, array{bool}> */
    public static function outputModes(): array
    {
        return ['buffered' => [false], 'streaming' => [true]];
    }

    /** @return array<string, array{string}> */
    public static function groupProcessingModes(): array
    {
        return ['releases' => ['releases'], 'update per group' => ['updatePerGroup']];
    }

    #[DataProvider('groupProcessingModes')]
    public function test_buffered_group_processing_refills_a_free_slot_before_a_slow_group_finishes(string $method): void
    {
        config(['nntmux.stream_fork_output' => false]);
        foreach ([1, 2, 109] as $id) {
            DB::table('usenet_groups')->insert(['id' => $id, 'name' => 'alt.test.'.$id, 'active' => 1, 'backfill' => 0]);
            DB::table('collections')->insert(['groups_id' => $id]);
        }

        $directory = sys_get_temp_dir().'/nntmux-release-refill-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $runner = new RefillingEligibilityReleasesRunner($directory);
        ob_start();
        try {
            $runner->{$method}();
            $events = file($directory.'/events', FILE_IGNORE_NEW_LINES);
            $this->assertIsArray($events);
            $this->assertLessThan(array_search('finish:1', $events, true), array_search('start:109', $events, true));
            foreach ([1, 2, 109] as $id) {
                $this->assertSame(1, count(array_keys($events, 'start:'.$id, true)));
                $this->assertSame(1, count(array_keys($events, 'finish:'.$id, true)));
            }
        } finally {
            ob_end_clean();
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    #[DataProvider('groupProcessingModes')]
    public function test_buffered_group_processing_reports_a_worker_failure_after_running_later_groups(string $method): void
    {
        config(['nntmux.stream_fork_output' => false]);
        foreach ([1, 2, 109] as $id) {
            DB::table('usenet_groups')->insert(['id' => $id, 'name' => 'alt.test.'.$id, 'active' => 1, 'backfill' => 0]);
            DB::table('collections')->insert(['groups_id' => $id]);
        }

        $directory = sys_get_temp_dir().'/nntmux-release-failure-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $runner = new RefillingEligibilityReleasesRunner($directory, true);
        ob_start();
        try {
            try {
                $runner->{$method}();
                $this->fail('A failed group must fail the runner.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Worker batch failed.', $exception->getMessage());
                $this->assertStringContainsString('1 failure (exit 7)', $exception->getPrevious()?->getMessage() ?? '');
                $events = file($directory.'/events', FILE_IGNORE_NEW_LINES);
                $this->assertIsArray($events);
                $this->assertContains('finish:109', $events);
                $this->assertStringContainsString(
                    'worker-output:109',
                    (string) ob_get_contents(),
                );
            }
        } finally {
            ob_end_clean();
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    #[DataProvider('groupProcessingModes')]
    public function test_every_group_gets_a_ready_pass_before_full_preparation_starts(string $method): void
    {
        config(['nntmux.stream_fork_output' => false]);
        foreach ([1, 2, 109] as $id) {
            DB::table('usenet_groups')->insert(['id' => $id, 'name' => 'alt.test.'.$id, 'active' => 1, 'backfill' => 0]);
            DB::table('collections')->insert(['groups_id' => $id]);
        }
        $directory = sys_get_temp_dir().'/nntmux-ready-phase-'.bin2hex(random_bytes(8));
        mkdir($directory);
        ob_start();
        try {
            (new RefillingEligibilityReleasesRunner($directory))->{$method}();
            $events = file($directory.'/phases', FILE_IGNORE_NEW_LINES);
            $this->assertIsArray($events);
            $this->assertContains('ready:finish:109', $events);
            $this->assertLessThan(array_search('full:start:1', $events, true), array_search('ready:finish:109', $events, true));
        } finally {
            ob_end_clean();
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    #[DataProvider('groupProcessingModes')]
    public function test_ready_failure_recovered_by_full_processing_returns_for_maintenance(string $method): void
    {
        foreach ([1, 2, 109] as $id) {
            DB::table('usenet_groups')->insert(['id' => $id, 'name' => 'alt.test.'.$id, 'active' => 1, 'backfill' => 0]);
            DB::table('collections')->insert(['groups_id' => $id]);
        }
        $runner = new class extends CapturingEligibilityReleasesRunner
        {
            protected function runParallelCommandsWithCompletion(array $commands, int $maxProcesses, callable $onComplete): void
            {
                if (in_array('--ready-only', reset($commands), true)) {
                    throw new RuntimeException('ready pass temporarily failed');
                }
                parent::runParallelCommandsWithCompletion($commands, $maxProcesses, $onComplete);
            }
        };
        $runner->{$method}();
        $this->assertCount(3, $runner->commands);
    }

    #[DataProvider('groupProcessingModes')]
    public function test_cancelling_the_ready_pass_does_not_launch_full_preparation(string $method): void
    {
        if (! function_exists('pcntl_signal') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('POSIX signals are unavailable.');
        }
        config(['nntmux.stream_fork_output' => false]);
        foreach ([1, 2, 109] as $id) {
            DB::table('usenet_groups')->insert(['id' => $id, 'name' => 'alt.test.'.$id, 'active' => 1, 'backfill' => 0]);
            DB::table('collections')->insert(['groups_id' => $id]);
        }
        $directory = sys_get_temp_dir().'/nntmux-ready-cancel-'.bin2hex(random_bytes(8));
        mkdir($directory);
        ob_start();
        try {
            try {
                (new RefillingEligibilityReleasesRunner($directory, cancelReady: true))->{$method}();
                $this->fail('Expected cancellation.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('cancellation', $exception->getMessage());
                $events = file($directory.'/phases', FILE_IGNORE_NEW_LINES);
                $this->assertIsArray($events);
                $this->assertFalse(collect($events)->contains(static fn (string $event): bool => str_starts_with($event, 'full:start:')));
            }
        } finally {
            ob_end_clean();
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function test_cancellation_during_final_ready_completion_does_not_start_full_processing(): void
    {
        if (! function_exists('pcntl_signal') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('POSIX signals are unavailable.');
        }
        config(['nntmux.stream_fork_output' => false]);
        DB::table('usenet_groups')->insert(['id' => 109, 'name' => 'alt.test.109', 'active' => 1, 'backfill' => 0]);
        DB::table('collections')->insert(['groups_id' => 109]);
        $directory = sys_get_temp_dir().'/nntmux-ready-final-cancel-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $runner = new class($directory) extends RefillingEligibilityReleasesRunner
        {
            protected function runParallelCommandsWithCompletion(array $commands, int $maxProcesses, callable $onComplete): void
            {
                $ready = in_array('--ready-only', reset($commands), true);
                parent::runParallelCommandsWithCompletion($commands, $maxProcesses, static function (int|string $key, string $output) use ($onComplete, $ready): void {
                    $onComplete($key, $output);
                    if ($ready) {
                        posix_kill(getmypid(), SIGTERM);
                    }
                });
            }
        };
        ob_start();
        try {
            try {
                $runner->releases();
                $this->fail('Expected cancellation at the phase boundary.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('cancellation', $exception->getMessage());
                $this->assertFalse(is_file($directory.'/events'));
            }
        } finally {
            ob_end_clean();
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    #[DataProvider('outputModes')]
    public function test_dispatches_each_eligible_group_once_in_existing_order_with_two_selection_queries(bool $streaming): void
    {
        config(['nntmux.stream_fork_output' => $streaming]);
        DB::table('usenet_groups')->insert([
            ['id' => 80, 'name' => 'alt.test.last', 'active' => 1, 'backfill' => 0],
            ['id' => 10, 'name' => 'alt.test.active', 'active' => 1, 'backfill' => 0],
            ['id' => 20, 'name' => 'alt.test.backfill', 'active' => 0, 'backfill' => 1],
            ['id' => 30, 'name' => 'alt.test.both', 'active' => 1, 'backfill' => 1],
            ['id' => 40, 'name' => 'alt.test.empty', 'active' => 1, 'backfill' => 0],
            ['id' => 50, 'name' => 'alt.test.disabled', 'active' => 0, 'backfill' => 0],
            ['id' => 70, 'name' => 'alt.test.other-flag', 'active' => 2, 'backfill' => 2],
        ]);
        DB::table('collections')->insert([
            ['groups_id' => 10], ['groups_id' => 10], ['groups_id' => 20],
            ['groups_id' => 30], ['groups_id' => 50], ['groups_id' => 70], ['groups_id' => 80],
        ]);

        $runner = new CapturingEligibilityReleasesRunner;
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $runner->releases();
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame([
            [PHP_BINARY, base_path('artisan'), 'releases:process', '10'],
            [PHP_BINARY, base_path('artisan'), 'releases:process', '20'],
            [PHP_BINARY, base_path('artisan'), 'releases:process', '30'],
            [PHP_BINARY, base_path('artisan'), 'releases:process', '80'],
        ], $runner->commands);
        $this->assertSame([2], $runner->limits);
        $this->assertSame($streaming ? ['releases'] : [], $runner->streamingDescriptions);
        $selectionQueries = array_filter($queries, static fn (array $query): bool => preg_match('/\bfrom\s+["`]?\b(?:usenet_groups|collections)\b/i', $query['query']) === 1);
        $this->assertCount(2, $selectionQueries, 'Group dispatch must not issue one collection lookup per enabled group.');
    }

    public function test_failed_batch_lookup_falls_back_and_skips_only_failed_group_lookups(): void
    {
        config(['nntmux.stream_fork_output' => true]);
        DB::table('usenet_groups')->insert([
            ['id' => 1, 'name' => 'alt.test.first', 'active' => 1, 'backfill' => 0],
            ['id' => 2, 'name' => 'alt.test.failed', 'active' => 0, 'backfill' => 1],
            ['id' => 3, 'name' => 'alt.test.last', 'active' => 1, 'backfill' => 0],
        ]);
        DB::table('collections')->insert([
            ['groups_id' => 1], ['groups_id' => 2], ['groups_id' => 3],
        ]);

        $connection = DB::connection();
        DB::shouldReceive('connection')->andReturn($connection);
        DB::shouldReceive('select')->andReturnUsing(static function (string $sql, array $bindings = []) use ($connection): array {
            if (str_contains($sql, 'EXISTS')) {
                throw new \PDOException('Batch collection lookup failed.');
            }
            if (str_contains($sql, 'groups_id = 2 LIMIT 1')) {
                throw new \PDOException('One group collection lookup failed.');
            }

            return $connection->select($sql, $bindings);
        });

        $runner = new CapturingEligibilityReleasesRunner;
        $runner->releases();

        $this->assertSame([
            [PHP_BINARY, base_path('artisan'), 'releases:process', '1'],
            [PHP_BINARY, base_path('artisan'), 'releases:process', '3'],
        ], $runner->commands);
        $this->assertSame([2], $runner->limits);
    }

    public function test_does_not_dispatch_enabled_groups_without_collections(): void
    {
        DB::table('usenet_groups')->insert([
            ['id' => 1, 'name' => 'alt.test.empty', 'active' => 1, 'backfill' => 0],
            ['id' => 2, 'name' => 'alt.test.disabled', 'active' => 0, 'backfill' => 0],
        ]);
        DB::table('collections')->insert(['groups_id' => 2]);

        $runner = new CapturingEligibilityReleasesRunner;
        $runner->releases();

        $this->assertSame([], $runner->commands);
        $this->assertSame([], $runner->limits);
    }
}

class CapturingEligibilityReleasesRunner extends ReleasesRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    /** @var list<list<string>> */
    public array $readyCommands = [];

    /** @var list<int> */
    public array $limits = [];

    /** @var list<string> */
    public array $streamingDescriptions = [];

    protected function runParallelCommands(array $commands, int $maxProcesses, ?int $timeout = null): array
    {
        array_push($this->commands, ...array_values($commands));
        $this->limits[] = $maxProcesses;

        return [];
    }

    protected function runParallelCommandsWithCompletion(array $commands, int $maxProcesses, callable $onComplete): void
    {
        if (in_array('--ready-only', reset($commands), true)) {
            array_push($this->readyCommands, ...array_values($commands));

            return;
        }
        array_push($this->commands, ...array_values($commands));
        $this->limits[] = $maxProcesses;
    }

    protected function runStreamingCommands(array $commands, int $maxProcesses, string $desc): void
    {
        if ($desc === 'ready_releases') {
            array_push($this->readyCommands, ...array_values($commands));

            return;
        }
        array_push($this->commands, ...array_values($commands));
        $this->limits[] = $maxProcesses;
        $this->streamingDescriptions[] = $desc;
    }
}

class RefillingEligibilityReleasesRunner extends ReleasesRunner
{
    public function __construct(private readonly string $directory, private readonly bool $failFirst = false, private readonly bool $cancelReady = false) {}

    protected function buildDnrCommand(string $args): array
    {
        $id = explode('  ', trim($args))[1];
        $worker = <<<'REFILL_WORKER'
            [$script, $directory, $id, $failFirst, $cancelReady] = $argv;
            $ready = in_array('--ready-only', $argv, true);
            $mode = $ready ? 'ready' : 'full';
            $marker = $directory.'/later-started-'.$mode;
            $event = static function (string $phase) use ($directory, $id, $mode, $ready): void {
                file_put_contents($directory.($ready ? '/ready-events' : '/events'), $phase.':'.$id.PHP_EOL, FILE_APPEND | LOCK_EX);
                file_put_contents($directory.'/phases', $mode.':'.$phase.':'.$id.PHP_EOL, FILE_APPEND | LOCK_EX);
            };
            $event('start');
            if ($ready && $id === '1' && $cancelReady === '1') {
                usleep(100000);
                posix_kill(posix_getppid(), SIGTERM);
                sleep(5);
            }
            if ($id === '1') {
                $deadline = microtime(true) + 10;
                while (!is_file($marker)) {
                    if (microtime(true) >= $deadline) { exit(7); }
                    usleep(10000);
                }
            }
            if ($id === '109') { touch($marker); }
            $event('finish');
            echo 'worker-output:'.$id.PHP_EOL;
            if ($id === '1' && $failFirst === '1') { exit(7); }
            REFILL_WORKER;

        return [PHP_BINARY, '-r', $worker, $this->directory, $id, $this->failFirst ? '1' : '0', $this->cancelReady ? '1' : '0'];
    }
}
