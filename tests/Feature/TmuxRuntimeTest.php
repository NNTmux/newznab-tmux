<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\TmuxStart;
use App\Enums\TmuxMode;
use App\Enums\TmuxPaneRole;
use App\Services\Tmux\TmuxCommand;
use App\Services\Tmux\TmuxLayoutBuilder;
use App\Services\Tmux\TmuxPaneManager;
use App\Services\Tmux\TmuxSessionManager;
use App\Services\Tmux\TmuxTaskRunner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TmuxRuntimeTest extends TestCase
{
    private string $socket;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $version = new Process(['tmux', '-V']);
        $version->run();
        if (! $version->isSuccessful()) {
            $this->markTestSkipped('tmux is unavailable.');
        }
        $this->socket = 'nntmux-test-'.bin2hex(random_bytes(8));
        config(['tmux.socket_name' => $this->socket, 'tmux.config_file' => '/dev/null']);
    }

    protected function tearDown(): void
    {
        if (isset($this->socket)) {
            (new Process(['tmux', '-L', $this->socket, 'kill-server']))->run();
        }
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_layouts_and_roles_survive_an_existing_server_with_remain_on_exit_off(): void
    {
        $this->tmux(['new-session', '-d', '-s', 'unrelated', 'sleep', '60']);
        $this->tmux(['set-option', '-gw', 'remain-on-exit', 'off']);
        foreach (TmuxMode::cases() as $mode) {
            $session = 'mode-'.$mode->value;
            $layout = new class(new TmuxSessionManager($session)) extends TmuxLayoutBuilder
            {
                protected function createOptionalWindows(): void {}
            };
            $this->assertTrue($layout->buildLayout($mode->value), (string) $layout->lastError());
            $panes = new TmuxPaneManager($session);
            $roles = array_column($panes->paneSnapshot(), 'role');
            $expected = array_map(static fn (TmuxPaneRole $role): string => $role->value, array_values($mode->tasks()));
            $this->assertEqualsCanonicalizing(['monitor', ...$expected], $roles);
            foreach ($mode->tasks() as $role) {
                $id = $panes->paneForRole($role);
                $this->assertTrue($panes->respawnPane($id, [PHP_BINARY, '-r', 'exit(0);']));
            }
            $this->await(function () use ($panes): bool {
                $panes->refresh();

                return array_filter($panes->paneSnapshot(), static fn (array $state): bool => ! $state['dead']) === [];
            });
            $this->assertCount(count($expected) + 1, $panes->paneSnapshot());
        }
        $this->assertTrue((new TmuxSessionManager('unrelated'))->sessionExists());
    }

    public function test_default_socket_session_is_visible_to_a_plain_tmux_client(): void
    {
        $directory = sys_get_temp_dir().'/'.$this->socket;
        mkdir($directory, 0700);
        $previous = getenv('TMUX_TMPDIR');
        $previousEnvironment = $_ENV['TMUX_TMPDIR'] ?? null;
        putenv('TMUX_TMPDIR='.$directory);
        $_ENV['TMUX_TMPDIR'] = $directory;
        config(['tmux.socket_name' => '']);
        $session = new TmuxSessionManager('plain-client');

        try {
            $this->assertNotNull($session->createSession(), (string) $session->lastError());
            $plainClient = new Process(['tmux', 'has-session', '-t', '=plain-client']);
            $plainClient->run();
            $this->assertTrue($plainClient->isSuccessful(), $plainClient->getErrorOutput());
        } finally {
            $session->killSession();
            putenv($previous === false ? 'TMUX_TMPDIR' : 'TMUX_TMPDIR='.$previous);
            if ($previousEnvironment === null) {
                unset($_ENV['TMUX_TMPDIR']);
            } else {
                $_ENV['TMUX_TMPDIR'] = $previousEnvironment;
            }
            $socketDirectory = $directory.'/tmux-'.posix_getuid();
            @unlink($socketDirectory.'/default');
            @rmdir($socketDirectory);
            rmdir($directory);
        }
    }

    public function test_empty_available_queue_preserves_active_worker_and_safe_respawn_refuses_races(): void
    {
        $session = new TmuxSessionManager('custom-session');
        $id = $session->createSession();
        $this->assertNotNull($id);
        $panes = new TmuxPaneManager('custom-session');
        $this->assertTrue($panes->setPaneRole($id, TmuxPaneRole::PostAdditional), (string) $panes->lastError());
        $marker = $this->temporaryFile();
        $this->assertTrue($panes->respawnPane($id, [PHP_BINARY, '-r', 'usleep(700000); file_put_contents($argv[1], "finished");', $marker]), (string) $panes->lastError());
        $runner = new TmuxTaskRunner('custom-session');
        $runner->beginCycle();
        $this->assertTrue($runner->runPaneTask('ppadditional', [], ['settings' => ['post' => 3], 'counts' => ['now' => ['work_available' => 0, 'processnfo' => 0]]]));
        $this->assertFalse($panes->respawnPane($id, [PHP_BINARY, '-r', 'exit(0);']));
        $this->await(static fn (): bool => file_get_contents($marker) === 'finished');
        $panes->refresh();
        $this->assertSame(0, $panes->paneSnapshot()[$id]['exit_code']);
    }

    public function test_exact_session_lookup_never_matches_a_prefix_and_retains_its_id(): void
    {
        $production = new TmuxSessionManager('nntmux-prod');
        $this->assertNotNull($production->createSession());
        $missing = new TmuxSessionManager('nntmux');
        $this->assertFalse($missing->sessionExists());
        $this->assertTrue($missing->killSession());
        $this->assertTrue($production->sessionExists());
        $target = $production->target();
        // Keep the private server alive so session IDs are not reset.
        $keeper = new TmuxSessionManager('keeper');
        $this->assertNotNull($keeper->createSession());
        $this->assertTrue($production->killSession());
        $replacement = new TmuxSessionManager('nntmux-prod');
        $this->assertNotNull($replacement->createSession());
        $this->assertSame($target, $production->target());
        $this->assertFalse($production->sessionExists());
        $this->assertTrue($production->killSession());
        $this->assertTrue($replacement->sessionExists());
    }

    public function test_native_logging_preserves_worker_exit_status_through_cooldown_and_repeated_pipe_setup(): void
    {
        $session = new TmuxSessionManager('logging');
        $id = $session->createSession();
        $this->assertNotNull($id);
        $panes = new TmuxPaneManager('logging');
        $log = $this->temporaryFile();
        $command = (new TmuxTaskRunner('logging'))->buildCommand('printf "worker stdout\\n"; printf "worker stderr\\n" >&2; sleep 0.2; exit 7', ['sleep' => 0]);
        $this->assertTrue($panes->respawnLoggedPane($id, ['bash', '-c', $command], $log), (string) $panes->lastError());
        $panes->refresh();
        $this->assertTrue($panes->logPane($id, $log));
        $this->assertTrue($panes->logPane($id, $log));
        $this->await(function () use ($panes, $id, $log): bool {
            $panes->refresh();

            return $panes->paneSnapshot()[$id]['dead'] && str_contains(file_get_contents($log), 'worker stderr');
        });
        $this->assertSame(7, $panes->paneSnapshot()[$id]['exit_code']);
        $this->assertStringContainsString('worker stdout', file_get_contents($log));
        $this->assertStringContainsString(date('Y-m-d'), file_get_contents($log));
    }

    public function test_logging_remains_attached_across_repeated_batches(): void
    {
        $session = new TmuxSessionManager('repeated-logging');
        $id = $session->createSession();
        $this->assertNotNull($id);
        $panes = new TmuxPaneManager('repeated-logging');
        $log = $this->temporaryFile();
        for ($batch = 1; $batch <= 2; $batch++) {
            $panes->refresh();
            $this->assertTrue($panes->respawnLoggedPane($id, [PHP_BINARY, '-r', 'echo $argv[1];', 'batch-'.$batch], $log));
            $this->await(function () use ($panes, $id, $log, $batch): bool {
                $panes->refresh();

                return $panes->paneSnapshot()[$id]['dead'] && str_contains(file_get_contents($log), 'batch-'.$batch);
            });
        }
        $this->assertStringContainsString('batch-1', file_get_contents($log));
        $this->assertStringContainsString('batch-2', file_get_contents($log));
    }

    public function test_monitor_startup_acknowledges_readiness_in_a_custom_session(): void
    {
        $root = sys_get_temp_dir().'/nntmux monitor '.bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $originalBase = base_path();
        $script = '<?php $socket = '.var_export($this->socket, true).'; $session = ""; $channel = ""; foreach ($argv as $arg) { if (str_starts_with($arg, "--session=")) { $session = substr($arg, 10); } if (str_starts_with($arg, "--ready-channel=")) { $channel = substr($arg, 16); } } file_put_contents(__DIR__."/selected", $session); $process = proc_open(["tmux", "-L", $socket, "wait-for", "-S", $channel], [STDIN, STDOUT, STDERR], $pipes); exit(proc_close($process));';
        file_put_contents($root.'/artisan', $script);
        $this->app->setBasePath($root);
        try {
            $session = new TmuxSessionManager('selected-session');
            $id = $session->createSession();
            $this->assertNotNull($id);
            $panes = new TmuxPaneManager('selected-session');
            $this->assertTrue($panes->setPaneRole($id, TmuxPaneRole::Monitor));
            $command = new TmuxStart;
            (new \ReflectionMethod($command, 'startMonitor'))->invoke($command, 'selected-session');
            $this->assertSame('selected-session', file_get_contents($root.'/selected'));
        } finally {
            $this->app->setBasePath($originalBase);
            app('files')->deleteDirectory($root);
        }
    }

    public function test_direct_worker_arguments_directory_and_environment_are_literal(): void
    {
        $session = new TmuxSessionManager('literal-argv');
        $id = $session->createSession();
        $this->assertNotNull($id);
        $panes = new TmuxPaneManager('literal-argv');
        $marker = $this->temporaryFile();
        $value = 'literal spaces; $(token)';
        $this->assertTrue($panes->respawnPane($id, [PHP_BINARY, '-r', 'file_put_contents($argv[1], json_encode([getcwd(), getenv("NNTMUX_TEST_VALUE"), $argv[2]]));', $marker, $value], directory: '/tmp', environment: ['NNTMUX_TEST_VALUE' => $value]));
        $this->await(static fn (): bool => file_get_contents($marker) !== '');
        $this->assertSame(['/tmp', $value, $value], json_decode(file_get_contents($marker), true));
    }

    public function test_exit_hook_signals_and_heartbeat_are_scoped_to_the_selected_session(): void
    {
        $session = new TmuxSessionManager('hook-session');
        $id = $session->createSession();
        $this->assertNotNull($id);
        $panes = new TmuxPaneManager('hook-session');
        $this->assertTrue($panes->installExitHook());
        $this->assertTrue($panes->heartbeat());
        $this->assertEqualsWithDelta(time(), $panes->heartbeatTime(), 1);
        $this->assertTrue($panes->respawnPane($id, [PHP_BINARY, '-r', 'usleep(200000);']));
        $started = microtime(true);
        $panes->waitForExit(3);
        $this->assertLessThan(2, microtime(true) - $started);
    }

    public function test_stop_cancels_owned_workers_without_finishing_or_starting_queued_jobs(): void
    {
        if (! function_exists('pcntl_signal') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('POSIX signals are unavailable.');
        }
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });
        DB::table('settings')->insert(['name' => 'running', 'value' => '1']);
        $keeper = new TmuxSessionManager('unrelated');
        $this->assertNotNull($keeper->createSession());
        $session = new TmuxSessionManager('draining');
        $pane = $session->createSession();
        $this->assertNotNull($pane);
        $panes = new TmuxPaneManager('draining');
        $this->assertTrue($panes->setPaneRole($pane, TmuxPaneRole::PostAdditional));
        $marker = $this->temporaryFile();
        $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = new Illuminate\Foundation\Application($argv[1]);
$app->instance('log', new Psr\Log\NullLogger);
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$runner = new class extends App\Services\Runners\BaseRunner {
    public function batch(string $marker): void {
        $commands = [];
        foreach ([1, 2, 3] as $number) {
            $commands[] = [PHP_BINARY, '-r', 'file_put_contents($argv[1], "child=".getmypid()."\n", FILE_APPEND); sleep(30); file_put_contents($argv[1], "completed=".$argv[2]."\n", FILE_APPEND);', $marker, (string) $number];
        }
        $this->runParallelCommands($commands, 1, 60);
    }
};
file_put_contents($argv[2], "started\n");
try {
    $runner->batch($argv[2]);
} catch (RuntimeException $exception) {
    file_put_contents($argv[2], "cancelled\n", FILE_APPEND);
}
PHP;
        $runner = new TmuxTaskRunner('draining');
        $worker = implode(' ', array_map('escapeshellarg', [PHP_BINARY, '-r', $script, base_path(), $marker]));
        $batch = (new \ReflectionMethod($runner, 'batchCommand'))->invoke($runner, [$worker, 'printf "late worker\\n" >> '.escapeshellarg($marker)]);
        $this->assertTrue($panes->respawnPane($pane, ['bash', '-c', $runner->buildCommand($batch, ['sleep' => 30])]));
        $this->await(static fn (): bool => str_contains(file_get_contents($marker), 'child='));
        preg_match('/child=(\d+)/', file_get_contents($marker), $match);
        $childPid = (int) $match[1];

        $this->artisan('tmux:stop --session=draining --force')->assertExitCode(0);

        $output = file_get_contents($marker);
        $this->assertStringContainsString("cancelled\n", $output);
        $this->assertStringNotContainsString('completed=', $output);
        $this->assertStringNotContainsString('late worker', $output);
        $this->assertSame(1, substr_count($output, 'child='));
        $this->assertFalse(posix_kill($childPid, 0), 'The owned child process must be gone when stop returns.');
        $this->assertSame(0, (int) DB::table('settings')->where('name', 'running')->value('value'));
        $this->assertSame(1, (int) DB::table('settings')->where('name', 'exit')->value('value'));
        $this->assertFalse($session->sessionExists());
        $this->assertTrue($keeper->sessionExists());
    }

    /** @param list<string> $arguments */
    private function tmux(array $arguments): void
    {
        $process = new Process(TmuxCommand::arguments($arguments));
        $process->mustRun();
    }

    private function temporaryFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'nntmux-test-');
        $this->assertNotFalse($file);
        $this->files[] = $file;

        return $file;
    }

    private function await(callable $condition): void
    {
        $deadline = microtime(true) + 5;
        do {
            if ($condition()) {
                return;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $this->fail('Timed out waiting for isolated tmux worker.');
    }
}
