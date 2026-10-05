<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TmuxHealthCheckCommandTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-tmux-health-check-test.sqlite';

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec('CREATE TABLE collections (id INTEGER PRIMARY KEY AUTOINCREMENT, dateadded TEXT NULL)');
        $pdo->exec("INSERT INTO settings (name, value) VALUES
            ('categorizeforeign', '0'),
            ('catwebdl', '0'),
            ('running', '0'),
            ('sequential', '0'),
            ('delaytime', '2'),
            ('monitor_delay', '0'),
            ('tmux_session', 'test-session')");

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', $this->databasePath);

        $app = require __DIR__.'/../../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

        config(['tmux.socket_name' => '']);
        Process::preventStrayProcesses();
    }

    protected function tearDown(): void
    {
        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_missing_session_succeeds_when_engine_is_stopped(): void
    {
        $this->fakeMissingSession();

        $this->artisan('tmux:health-check --auto-restart --session=test-session')
            ->expectsOutputToContain("Tmux session 'test-session' does not exist.")
            ->assertExitCode(0);

        Process::assertRanTimes(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'has-session'), 1);
        Process::assertNotRan('which tmux 2>/dev/null');
    }

    public function test_missing_session_fails_without_auto_restart_when_engine_should_be_running(): void
    {
        $this->setSetting('running', '1');
        $this->fakeMissingSession();

        $this->artisan('tmux:health-check --session=test-session')
            ->expectsOutputToContain("Tmux session 'test-session' does not exist.")
            ->assertExitCode(1);

        Process::assertRanTimes(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'has-session'), 1);
        Process::assertNotRan('which tmux 2>/dev/null');
    }

    public function test_missing_session_auto_restarts_when_engine_should_be_running(): void
    {
        $this->setSetting('running', '1');
        $this->fakeMissingSessionWithSuccessfulStart();

        $this->artisan('tmux:health-check --auto-restart --session=test-session')
            ->expectsOutputToContain("Tmux session 'test-session' does not exist.")
            ->expectsOutputToContain("Tmux session 'test-session' restarted successfully.")
            ->assertExitCode(0);

        Process::assertRanTimes(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'has-session'), 3);
        Process::assertRan('which tmux 2>/dev/null');
        Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command) && in_array('--session=test-session', $process->command, true) && in_array('tmux:monitor', $process->command, true));
        Process::assertRan(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'new-session'));
    }

    public function test_existing_dead_monitor_is_preserved_when_stopped_intentionally(): void
    {
        $this->fakeExistingMonitor(dead: true);
        $this->artisan('tmux:health-check --auto-restart --session=custom-session')->assertExitCode(0);
        Process::assertNotRan(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'respawn-pane') || $this->isTmuxCommand($process, 'kill-session'));
    }

    public function test_dead_monitor_restarts_only_its_pane_in_the_custom_session(): void
    {
        $this->setSetting('running', '1');
        $this->fakeExistingMonitor(dead: true);
        $this->artisan('tmux:health-check --auto-restart --session=custom-session')->assertExitCode(0);
        Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
            && in_array('respawn-pane', $process->command, true)
            && in_array('%9', $process->command, true)
            && in_array('--session=custom-session', $process->command, true)
            && in_array(PHP_BINARY, $process->command, true)
            && ! in_array('-k', $process->command, true));
        Process::assertNotRan(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'kill-session') || $this->isTmuxCommand($process, 'new-session'));
    }

    public function test_stale_heartbeat_reports_failure_without_killing_an_active_monitor(): void
    {
        $this->setSetting('running', '1');
        $this->fakeExistingMonitor(dead: false, heartbeat: time() - 3600);
        $this->artisan('tmux:health-check --auto-restart --session=custom-session')->assertExitCode(1);
        Process::assertNotRan(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'respawn-pane') || $this->isTmuxCommand($process, 'kill-session'));
    }

    public function test_session_probe_reports_missing_even_when_stopped(): void
    {
        $this->fakeMissingSession();
        $this->artisan('tmux:health-check --require-session --quiet --session=custom-session')->assertExitCode(1);
    }

    #[DataProvider('stopConfirmationModes')]
    public function test_stop_interrupts_workers_and_closes_session_when_cleanup_timeout_expires(bool $force): void
    {
        $this->setSetting('running', '1');
        $this->fakeExistingMonitor(dead: false);
        $command = $this->artisan('tmux:stop --session=custom-session --timeout=0'.($force ? ' --force' : ''));
        if (! $force) {
            $command->expectsConfirmation("Stop tmux session 'custom-session'?", 'yes');
        }
        $command->assertExitCode(0);
        $command->run();
        $this->assertSame('0', (string) $this->app['db']->table('settings')->where('name', 'running')->value('value'));
        $this->assertSame('1', (string) $this->app['db']->table('settings')->where('name', 'exit')->value('value'));
        Process::assertRanTimes(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'kill-session'), 1);
        Process::assertRanTimes(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'send-keys'), 2);
    }

    /** @return iterable<string, array{bool}> */
    public static function stopConfirmationModes(): iterable
    {
        yield 'confirmed stop' => [false];
        yield 'force only skips confirmation' => [true];
    }

    public function test_stop_interrupts_processing_and_waits_only_for_cleanup_before_closing_session(): void
    {
        $snapshots = 0;
        Process::fake(function (PendingProcess $process) use (&$snapshots) {
            if ($this->isTmuxCommand($process, 'list-panes')) {
                $snapshots++;
                $this->assertSame('0', (string) $this->app['db']->table('settings')->where('name', 'running')->value('value'));
                $this->assertSame('1', (string) $this->app['db']->table('settings')->where('name', 'exit')->value('value'));

                return Process::result("%9\tmonitor\t".($snapshots >= 3 ? '1' : '0')."\t0\t123\t0\t@1\n"
                    ."%10\tpost_additional\t".($snapshots >= 2 ? '1' : '0')."\t0\t456\t0\t@2\n"
                    ."%11\tpost_movies\t".($snapshots >= 4 ? '1' : '0')."\t0\t789\t0\t@2\n"
                    ."%12\tconsole\t0\t\t900\t0\t@3\n");
            }
            if ($this->isTmuxCommand($process, 'kill-session')) {
                $this->assertSame(4, $snapshots);
            }
            if (is_array($process->command) && in_array('#{session_id}', $process->command, true)) {
                return Process::result('$3');
            }

            return Process::result();
        });

        $this->artisan('tmux:stop --session=custom-session --force')->assertExitCode(0);

        Process::assertRanTimes(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'kill-session'), 1);
        foreach (['%9', '%10', '%11'] as $pane) {
            Process::assertRanTimes(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'send-keys')
                && in_array($pane, $process->command, true) && in_array('C-c', $process->command, true), 1);
        }
        Process::assertNotRan(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'send-keys')
            && in_array('%12', $process->command, true));
    }

    public function test_restart_aborts_if_existing_session_cannot_be_drained(): void
    {
        Process::fake(function (PendingProcess $process) {
            if ($process->command === 'which tmux 2>/dev/null') {
                return Process::result('/usr/bin/tmux');
            }
            if ($this->isTmuxCommand($process, 'list-panes')) {
                return Process::result('', 'Unable to inspect workers', 1);
            }
            if (is_array($process->command) && in_array('#{session_id}', $process->command, true)) {
                return Process::result('$3');
            }

            return Process::result();
        });

        $this->artisan('tmux:start --session=custom-session --force')->assertExitCode(1);

        Process::assertNotRan(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'kill-session'));
        Process::assertNotRan(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'new-session'));
    }

    private function fakeExistingMonitor(bool $dead, ?int $heartbeat = null): void
    {
        Process::fake(function (PendingProcess $process) use ($dead, $heartbeat) {
            $command = $process->command;
            if (in_array('#{session_id}', $command, true)) {
                return Process::result('$3');
            }
            if (in_array('list-panes', $command, true)) {
                return Process::result("%9\tmonitor\t".($dead ? '1' : '0')."\t0\t123\t0\t@1\n%10\tpost_additional\t0\t\t456\t0\t@2\n");
            }
            if (in_array('show-options', $command, true)) {
                return Process::result((string) ($heartbeat ?? time()));
            }

            return Process::result();
        });
    }

    private function fakeMissingSession(): void
    {
        Process::fake(fn () => Process::result('', '', 1));
    }

    private function fakeMissingSessionWithSuccessfulStart(): void
    {
        $nextPaneId = 1;

        Process::fake(function (PendingProcess $process) use (&$nextPaneId) {
            if ($process->command === 'which tmux 2>/dev/null') {
                return Process::result('/usr/bin/tmux'.PHP_EOL);
            }

            if (! is_array($process->command)) {
                return Process::result();
            }

            if (in_array('#{window_id}', $process->command, true)) {
                return Process::result('@1');
            }
            if (in_array('#{pane_dead}', $process->command, true)) {
                return Process::result('1');
            }
            if (in_array('#{session_id}', $process->command, true)) {
                return Process::result('$1');
            }
            if (in_array('has-session', $process->command, true)) {
                return Process::result('', '', 1);
            }

            if (in_array('new-session', $process->command, true)
                || in_array('new-window', $process->command, true)
                || in_array('split-window', $process->command, true)) {
                return Process::result('%'.$nextPaneId++."\n");
            }

            if (in_array('list-panes', $process->command, true)) {
                return Process::result("%1\tmonitor\n");
            }

            return Process::result();
        });
    }

    private function isTmuxCommand(PendingProcess $process, string $command): bool
    {
        return is_array($process->command) && in_array($command, $process->command, true);
    }

    private function setSetting(string $name, string $value): void
    {
        $this->app['db']->table('settings')->where('name', $name)->update(['value' => $value]);
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
