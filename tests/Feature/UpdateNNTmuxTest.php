<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\UpdateNNTmux;
use App\Services\NntmuxUpdateLock;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\ConfigurationTestBuilder;
use Tests\TestCase;

class UpdateNNTmuxTest extends TestCase
{
    private string $directory;

    private string $originalBasePath;

    private string $originalStoragePath;

    private bool $maintenanceActive = false;

    private int $tmuxStartExitCode = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.redis.client' => 'phpredis']);
        ConfigurationTestBuilder::updateRuntime(['tmux_running' => true]);
        $this->directory = sys_get_temp_dir().'/nntmux-all-update-'.bin2hex(random_bytes(8));
        $this->originalBasePath = $this->app->basePath();
        $this->originalStoragePath = $this->app->storagePath();
        $this->app->setBasePath($this->directory);
        $this->app->useStoragePath($this->directory.'/storage');
        File::ensureDirectoryExists($this->directory.'/.git');
        $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturnUsing(fn (): bool => $this->maintenanceActive);
        Process::preventStrayProcesses();
        Process::fake(fn (PendingProcess $process) => $this->isFreshTmuxStart($process)
            ? Process::result(exitCode: $this->tmuxStartExitCode)
            : Process::result($this->directory.'/.git/index.lock'));
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->originalBasePath);
        $this->app->useStoragePath($this->originalStoragePath);
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_git_lock_aborts_before_maintenance_or_tmux_shutdown_and_leaves_lock_intact(): void
    {
        File::put($this->directory.'/.git/index.lock', 'another Git process');
        $command = $this->command();
        $command->shouldReceive('call')->never();

        [$status, $output] = $this->runCommand($command, ['--git-lock-timeout' => 0]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Git index is locked', $output);
        $this->assertStringNotContainsString('Preparing environment', $output);
        $this->assertFalse($this->maintenanceActive);
        $this->assertSame('another Git process', File::get($this->directory.'/.git/index.lock'));
    }

    public function test_concurrent_updates_are_rejected_even_when_git_is_skipped(): void
    {
        $lock = new NntmuxUpdateLock;
        $lock->run(function (): int {
            $command = $this->command();
            $command->shouldReceive('call')->never();

            [$status, $output] = $this->runCommand($command, ['--skip-git' => true]);

            $this->assertSame(1, $status);
            $this->assertStringContainsString('Another NNTmux update is already running', $output);
            $this->assertFalse($this->maintenanceActive);

            return 0;
        }, checkGit: false);
    }

    public function test_repository_preflight_failure_leaves_environment_untouched(): void
    {
        Process::fake(fn () => Process::result('', 'not a git repository', 128));
        $command = $this->command();
        $command->shouldReceive('call')->never();

        [$status, $output] = $this->runCommand($command);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('not a git repository', $output);
        $this->assertFalse($this->maintenanceActive);
    }

    public function test_failed_update_restores_web_access_but_does_not_restart_tmux_or_report_completion(): void
    {
        $command = $this->command();
        $this->expectPreparation($command);
        $command->shouldReceive('call')->once()->with('nntmux:git', ['--git-lock-timeout' => 0])->andReturn(1);
        $this->expectWebRestore($command);
        $command->shouldReceive('call')->with('tmux:start')->never();

        [$status, $output] = $this->runCommand($command, ['--git-lock-timeout' => 0]);

        $this->assertSame(1, $status);
        $this->assertFalse($this->maintenanceActive);
        $this->assertStringContainsString('Skipping tmux restart', $output);
        $this->assertStringNotContainsString('100%', $output);
        $this->assertStringNotContainsString('update completed successfully', $output);
        $this->assertSame(0, (new NntmuxUpdateLock)->run(fn (): int => 0, checkGit: false));
    }

    public function test_successful_update_restores_tmux_and_uses_a_random_maintenance_secret(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        $command = $this->command();
        $this->expectPreparation($command);
        $command->shouldReceive('call')->once()->with('nntmux:git', ['--git-lock-timeout' => 0])->andReturn(0);
        $this->expectMaintenanceTasks($command);
        $this->expectHealthyRedis();
        $this->expectWebRestore($command);
        $command->shouldReceive('call')->with('tmux:start')->never();

        [$status, $output] = $this->runCommand($command, ['--git-lock-timeout' => 0]);

        $this->assertSame(0, $status);
        Process::assertRanTimes(fn (PendingProcess $process): bool => $this->isFreshTmuxStart($process), 1);
        $this->assertFalse($this->maintenanceActive);
        $this->assertStringContainsString('update completed successfully', $output);
        $this->assertStringNotContainsString(config('app.key'), $output);
    }

    public function test_pre_existing_maintenance_mode_is_preserved_after_failure(): void
    {
        $this->maintenanceActive = true;
        $command = $this->command();
        $command->shouldReceive('call')->with('down', Mockery::any())->never();
        $command->shouldReceive('call')->once()->with('tmux:stop', ['--force' => true])->andReturn(0);
        $command->shouldReceive('call')->once()->with('nntmux:git', ['--git-lock-timeout' => 0])->andReturn(1);
        $command->shouldReceive('call')->with('up')->never();
        $command->shouldReceive('call')->with('tmux:start')->never();

        [$status] = $this->runCommand($command, ['--git-lock-timeout' => 0]);

        $this->assertSame(1, $status);
        $this->assertTrue($this->maintenanceActive);
    }

    public function test_redis_preflight_failure_restores_web_access_without_restarting_tmux(): void
    {
        config(['database.redis.client' => 'predis']);
        $command = $this->command();
        $this->expectPreparation($command);
        $this->expectMaintenanceTasks($command);
        $this->expectWebRestore($command);
        $command->shouldReceive('call')->with('tmux:start')->never();
        Redis::shouldReceive('connection')->never();

        [$status, $output] = $this->runCommand($command, ['--skip-git' => true]);

        $this->assertSame(1, $status);
        $this->assertFalse($this->maintenanceActive);
        $this->assertStringContainsString('Redis client mismatch', $output);
        $this->assertStringContainsString('Skipping tmux restart', $output);
        $this->assertStringNotContainsString('update completed successfully', $output);
        $this->assertSame(0, (new NntmuxUpdateLock)->run(fn (): int => 0, checkGit: false));
    }

    public function test_tmux_restart_failure_fails_the_update_and_releases_the_lock(): void
    {
        $command = $this->command();
        $this->expectPreparation($command);
        $this->expectMaintenanceTasks($command);
        $this->expectHealthyRedis();
        $this->expectWebRestore($command);
        $this->tmuxStartExitCode = 1;

        [$status, $output] = $this->runCommand($command, ['--skip-git' => true]);

        $this->assertSame(1, $status);
        $this->assertFalse($this->maintenanceActive);
        $this->assertStringContainsString('tmux:start failed while restoring environment', $output);
        $this->assertStringNotContainsString('update completed successfully', $output);
        $this->assertSame(0, (new NntmuxUpdateLock)->run(fn (): int => 0, checkGit: false));
    }

    public function test_skip_git_does_not_require_a_repository_or_check_the_git_index_lock(): void
    {
        File::deleteDirectory($this->directory.'/.git');
        $command = $this->command();
        $this->expectPreparation($command);
        $command->shouldReceive('call')->with('nntmux:git', Mockery::any())->never();
        $this->expectMaintenanceTasks($command);
        $this->expectHealthyRedis();
        $this->expectWebRestore($command);

        [$status] = $this->runCommand($command, ['--skip-git' => true]);

        $this->assertSame(0, $status);
        Process::assertNotRan(fn (PendingProcess $process): bool => ! $this->isFreshTmuxStart($process));
    }

    public function test_npm_ci_failure_reports_diagnostics_and_completes_using_install_fallback(): void
    {
        $this->fakeBuildFailure('npm ci --silent', 'dependency conflict', 'lockfile mismatch', 1);
        $command = $this->command();
        $this->expectPreparation($command);
        $this->expectMaintenanceTasks($command);
        $this->expectHealthyRedis();
        $this->expectWebRestore($command);

        [$status, $output] = $this->runCommand($command, ['--skip-git' => true, '--skip-npm' => false]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('npm ci failed, falling back to npm install', $output);
        $this->assertStringContainsString('stderr=lockfile mismatch', $output);
        $this->assertStringContainsString('stdout=dependency conflict', $output);
        foreach (['npm install --silent', 'php artisan typescript:transform --force', 'npm exec vite build'] as $buildCommand) {
            Process::assertRanTimes(fn (PendingProcess $process): bool => $process->command === $buildCommand && $process->path === $this->directory && $process->timeout === 600, 1);
        }
        Process::assertNotRan('npm run build');
    }

    #[DataProvider('buildFailures')]
    /** @param list<string> $skippedCommands */
    public function test_build_failure_restores_web_access_and_skips_later_steps(string $failedCommand, string $stdout, string $stderr, string $expected, array $skippedCommands): void
    {
        $this->fakeBuildFailure($failedCommand, $stdout, $stderr, 2);
        $command = $this->command();
        $this->expectPreparation($command);
        $this->expectWebRestore($command);
        $command->shouldReceive('call')->with('config:clear')->never();
        Cache::shouldReceive('flush')->never();
        Redis::shouldReceive('connection')->never();

        [$status, $output] = $this->runCommand($command, ['--skip-git' => true, '--skip-npm' => false]);

        $this->assertSame(1, $status);
        $this->assertFalse($this->maintenanceActive);
        $this->assertStringContainsString($expected, $output);
        $this->assertStringContainsString('exit_code=2', $output);
        $this->assertStringNotContainsString('update completed successfully', $output);
        foreach ($skippedCommands as $skippedCommand) {
            Process::assertNotRan(fn (PendingProcess $process): bool => $process->command === $skippedCommand);
        }
        Process::assertNotRan(fn (PendingProcess $process): bool => $this->isFreshTmuxStart($process));
        $this->assertSame(0, (new NntmuxUpdateLock)->run(fn (): int => 0, checkGit: false));
    }

    /** @return iterable<string, array{string, string, string, string, list<string>}> */
    public static function buildFailures(): iterable
    {
        yield 'install fallback fails' => ['npm install --silent', '', 'dependency resolution failed', 'npm install failed', ['php artisan typescript:transform --force', 'npm exec vite build']];
        yield 'type transform fails' => ['php artisan typescript:transform --force', 'transform error', '', 'stdout=transform error', ['npm exec vite build']];
        yield 'vite fails silently' => ['npm exec vite build', '', '', 'no stdout/stderr captured', []];
    }

    private function fakeBuildFailure(string $failedCommand, string $stdout, string $stderr, int $exitCode): void
    {
        Process::fake(function (PendingProcess $process) use ($failedCommand, $stdout, $stderr, $exitCode) {
            if ($process->command === $failedCommand || ($failedCommand === 'npm install --silent' && $process->command === 'npm ci --silent')) {
                return Process::result($stdout, $stderr, $exitCode);
            }

            return Process::result();
        });
    }

    public function test_redis_connection_failure_restores_web_access_without_restarting_tmux(): void
    {
        $command = $this->command();
        $this->expectPreparation($command);
        $this->expectMaintenanceTasks($command);
        $this->expectWebRestore($command);
        Redis::shouldReceive('connection->ping')->once()->andThrow(new RuntimeException('connection refused'));

        [$status, $output] = $this->runCommand($command, ['--skip-git' => true]);

        $this->assertSame(1, $status);
        $this->assertFalse($this->maintenanceActive);
        $this->assertStringContainsString('Redis preflight failed: connection refused', $output);
        Process::assertNotRan(fn (PendingProcess $process): bool => $this->isFreshTmuxStart($process));
        $this->assertSame(0, (new NntmuxUpdateLock)->run(fn (): int => 0, checkGit: false));
    }

    private function isFreshTmuxStart(PendingProcess $process): bool
    {
        return $process->command === [PHP_BINARY, 'artisan', 'tmux:start'];
    }

    /** @return UpdateNNTmux&MockInterface */
    private function command(): UpdateNNTmux
    {
        $command = Mockery::mock(UpdateNNTmux::class)->makePartial();
        $command->__construct();
        $command->setLaravel($this->app);

        return $command;
    }

    /** @param UpdateNNTmux&MockInterface $command */
    private function expectPreparation(UpdateNNTmux $command): void
    {
        $command->shouldReceive('call')->once()->with('down', Mockery::on(function (array $options): bool {
            $this->assertNotSame(config('app.key'), $options['--secret']);
            $this->assertSame(40, strlen($options['--secret']));

            return true;
        }))->andReturnUsing(function (): int {
            $this->maintenanceActive = true;

            return 0;
        });
        $command->shouldReceive('call')->once()->with('tmux:stop', ['--force' => true])->andReturn(0);
    }

    /** @param UpdateNNTmux&MockInterface $command */
    private function expectWebRestore(UpdateNNTmux $command): void
    {
        $command->shouldReceive('call')->once()->with('up')->andReturnUsing(function (): int {
            $this->maintenanceActive = false;

            return 0;
        });
    }

    /** @param UpdateNNTmux&MockInterface $command */
    private function expectMaintenanceTasks(UpdateNNTmux $command): void
    {
        foreach (['config:clear', 'config:cache', 'view:clear', 'route:clear', 'route:cache'] as $name) {
            $command->shouldReceive('call')->once()->with($name)->andReturn(0);
        }
        Cache::shouldReceive('flush')->once()->andReturn(true);
    }

    private function expectHealthyRedis(): void
    {
        Redis::shouldReceive('connection->ping')->once()->andReturn('PONG');
    }

    /**
     * @param  UpdateNNTmux&MockInterface  $command
     * @param  array<string, mixed>  $options
     * @return array{int, string}
     */
    private function runCommand(UpdateNNTmux $command, array $options = []): array
    {
        $output = new BufferedOutput;
        $status = $command->run(new ArrayInput([
            '--skip-composer' => true,
            '--skip-npm' => true,
            '--skip-db' => true,
            ...$options,
        ]), $output);

        return [$status, $output->fetch()];
    }
}
