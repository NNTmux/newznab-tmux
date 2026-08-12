<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use PDO;
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
        $this->databasePath = $this->makeTempPath('nntmux-tmux-health-check-test', '.sqlite');

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

        Process::assertRanTimes(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'has-session'), 4);
        Process::assertRan('which tmux 2>/dev/null');
        Process::assertRan(fn (PendingProcess $process): bool => $this->isTmuxCommand($process, 'new-session'));
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
