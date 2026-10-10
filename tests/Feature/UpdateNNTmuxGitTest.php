<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\NntmuxUpdateLock;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class UpdateNNTmuxGitTest extends TestCase
{
    private string $directory;

    private string $originalBasePath;

    private string $originalStoragePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/nntmux-git-update-'.bin2hex(random_bytes(8));
        $this->originalBasePath = $this->app->basePath();
        $this->originalStoragePath = $this->app->storagePath();
        $this->app->setBasePath($this->directory);
        $this->app->useStoragePath($this->directory.'/storage');
        File::ensureDirectoryExists($this->directory.'/.git');
        Process::preventStrayProcesses();
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->originalBasePath);
        $this->app->useStoragePath($this->originalStoragePath);
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    #[DataProvider('forceOptions')]
    public function test_persistent_git_lock_fails_without_deleting_it_or_running_git_writes(bool $force): void
    {
        File::put($this->directory.'/.git/index.lock', 'owned by another Git process');
        $this->fakeGit();

        $this->artisan('nntmux:git', ['--git-lock-timeout' => 0, '--force' => $force])
            ->expectsOutputToContain('Git index is locked')
            ->assertFailed();

        $this->assertSame('owned by another Git process', File::get($this->directory.'/.git/index.lock'));
        Process::assertRanTimes(fn (PendingProcess $process): bool => $process->command[1] === 'rev-parse', 1);
        Process::assertNotRan(fn (PendingProcess $process): bool => $process->command[1] !== 'rev-parse');
    }

    /** @return iterable<string, array{bool}> */
    public static function forceOptions(): iterable
    {
        yield 'normal update' => [false];
        yield 'forced update' => [true];
    }

    public function test_transient_git_lock_can_clear_during_the_bounded_wait(): void
    {
        $path = $this->directory.'/.git/index.lock';
        File::put($path, 'temporary');
        $this->fakeGit();
        $releaseLock = new SymfonyProcess([PHP_BINARY, '-r', 'usleep(150000); unlink($argv[1]);', $path]);
        $releaseLock->start();

        try {
            $this->artisan('nntmux:git', ['--git-lock-timeout' => 2])->assertSuccessful();
        } finally {
            $releaseLock->wait();
        }

        $this->assertFileDoesNotExist($path);
    }

    public function test_git_lock_path_is_resolved_for_worktrees(): void
    {
        File::deleteDirectory($this->directory.'/.git');
        File::put($this->directory.'/.git', 'gitdir: linked-worktree');
        $path = $this->directory.'/linked-worktree/index.lock';
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'worktree lock');
        $this->fakeGit(indexLockPath: $path);

        $this->artisan('nntmux:git', ['--git-lock-timeout' => 0])
            ->expectsOutputToContain($path)
            ->assertFailed();

        $this->assertFileExists($path);
    }

    public function test_status_failure_aborts_before_stash_fetch_or_pull(): void
    {
        $this->fakeGit(statusExitCode: 128);

        $this->artisan('nntmux:git')->expectsOutputToContain('Failed to check local changes')->assertFailed();

        Process::assertNotRan(fn (PendingProcess $process): bool => in_array($process->command[1], ['stash', 'fetch', 'pull', 'reset'], true));
    }

    public function test_tracked_changes_are_stashed_and_all_git_commands_use_the_installation_path(): void
    {
        $this->fakeGit(status: " M composer.json\n", behind: 1);

        $this->artisan('nntmux:git')->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['git', '--no-optional-locks', 'status', '--porcelain', '--untracked-files=no']);
        Process::assertRan(fn (PendingProcess $process): bool => $process->command[1] === 'stash' && $process->timeout === 300);
        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['git', 'pull', 'origin', 'main']);
        Process::assertNotRan(fn (PendingProcess $process): bool => $process->path !== $this->directory);
    }

    public function test_clean_tracked_files_do_not_trigger_a_stash(): void
    {
        $this->fakeGit();

        $this->artisan('nntmux:git')->expectsOutputToContain('Already up-to-date')->assertSuccessful();

        Process::assertNotRan(fn (PendingProcess $process): bool => in_array($process->command[1], ['stash', 'pull'], true));
    }

    public function test_no_stash_option_preserves_local_changes(): void
    {
        $this->fakeGit(status: " M composer.json\n");

        $this->artisan('nntmux:git', ['--no-stash' => true])->assertSuccessful();

        Process::assertNotRan(fn (PendingProcess $process): bool => $process->command[1] === 'stash');
    }

    public function test_stash_failure_releases_update_lock_for_retry(): void
    {
        $this->fakeGit(status: " M composer.json\n", stashExitCode: 1);
        $this->artisan('nntmux:git')->expectsOutputToContain('Failed to stash changes')->assertFailed();
        Process::assertNotRan(fn (PendingProcess $process): bool => $process->command[1] === 'fetch');

        $this->fakeGit();
        $this->artisan('nntmux:git')->assertSuccessful();
    }

    public function test_nested_updates_keep_the_outer_lock_until_the_whole_update_finishes(): void
    {
        $lock = app(NntmuxUpdateLock::class);
        $this->assertSame($lock, app(NntmuxUpdateLock::class));
        $this->fakeGit();

        $lock->run(function (): int {
            $this->artisan('nntmux:git')->assertSuccessful();
            $contender = new NntmuxUpdateLock;
            try {
                $contender->run(fn (): int => 0, checkGit: false);
                $this->fail('The outer update lock was released by the nested Git command.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Another NNTmux update is already running', $exception->getMessage());
            }

            return 0;
        }, checkGit: false);

        $this->assertSame(0, (new NntmuxUpdateLock)->run(fn (): int => 0, checkGit: false));
    }

    private function fakeGit(string $status = '', int $statusExitCode = 0, int $behind = 0, int $stashExitCode = 0, ?string $indexLockPath = null): void
    {
        Process::fake(fn (PendingProcess $process) => match ($process->command[1]) {
            'rev-parse' => Process::result($indexLockPath ?? $this->directory.'/.git/index.lock'),
            '--no-optional-locks' => Process::result($status, 'status error', $statusExitCode),
            'branch' => Process::result('main'),
            'rev-list' => Process::result((string) $behind),
            'stash' => Process::result('', 'stash error', $stashExitCode),
            'fetch', 'pull', 'reset' => Process::result(),
            default => throw new RuntimeException('Unexpected Git command in test.'),
        });
    }
}
