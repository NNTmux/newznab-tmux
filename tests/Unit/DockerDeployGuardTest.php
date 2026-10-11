<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * docker/bin/deploy-guard: one deploy at a time, a deploy log, and no rebuild when nothing changed.
 */
final class DockerDeployGuardTest extends TestCase
{
    private string $dir;

    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/deploy-guard-'.bin2hex(random_bytes(4));
        $this->repo = $this->dir.'/repo';

        if ((new Process(['git', '--version']))->run() !== 0) {
            $this->markTestSkipped('git is not available.');
        }

        mkdir($this->repo, 0777, true);
        $this->git('init', '-q');
        $this->git('config', 'user.email', 'test@example.com');
        $this->git('config', 'user.name', 'Test');
        file_put_contents($this->repo.'/app.txt', "one\n");
        $this->git('add', '.');
        $this->git('commit', '-q', '-m', 'first');
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->dir]))->run();

        parent::tearDown();
    }

    #[Test]
    public function it_runs_the_command_and_logs_who_deployed(): void
    {
        $process = $this->guard(['run', 'up', '--', 'sh', '-c', 'echo ran > '.$this->dir.'/marker'], ['DEPLOY_BY' => 'session-a']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertFileExists($this->dir.'/marker');
        $log = (string) file_get_contents($this->dir.'/deploy.log');
        $this->assertStringContainsString('up by=session-a', $log);
        $this->assertStringContainsString('result=started', $log);
        $this->assertStringContainsString('result=finished rc=0', $log);
        $this->assertFalse(is_link($this->dir.'/lock'), 'the lock is released afterwards');
    }

    #[Test]
    public function a_second_deploy_is_refused_while_the_first_holds_the_lock(): void
    {
        $first = $this->guardProcess(['run', 'build', '--', 'sleep', '5'], ['DEPLOY_BY' => 'session-a']);
        $first->start();
        $this->waitFor(fn (): bool => is_link($this->dir.'/lock'));

        $second = $this->guard(['run', 'up', '--', 'sh', '-c', 'touch '.$this->dir.'/second-ran'], ['DEPLOY_BY' => 'session-b']);

        $this->assertSame(75, $second->getExitCode());
        $this->assertStringContainsString('by=session-a', $second->getErrorOutput());
        $this->assertFileDoesNotExist($this->dir.'/second-ran');

        $first->stop(0);
    }

    #[Test]
    public function a_stale_lock_is_replaced(): void
    {
        symlink('pid=999999 by=gone target=up since=then', $this->dir.'/lock');

        $process = $this->guard(['run', 'up', '--', 'true']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('stale lock', $process->getErrorOutput());
    }

    #[Test]
    public function sessions_racing_for_one_stale_lock_never_deploy_at_the_same_time(): void
    {
        symlink('pid=999999 by=gone target=up since=then', $this->dir.'/lock');

        // Each deploy enters a critical section; mkdir fails if another one is inside it.
        $critical = $this->dir.'/critical';
        $command = ['run', 'up', '--', 'sh', '-c', "mkdir $critical || touch {$this->dir}/overlap; sleep 1; rmdir $critical 2>/dev/null; true"];

        $processes = [];
        foreach (range(1, 6) as $i) {
            $processes[$i] = $this->guardProcess($command, ['DEPLOY_BY' => "session-$i"]);
            $processes[$i]->start();
        }
        $codes = [];
        foreach ($processes as $process) {
            $process->wait();
            $codes[] = $process->getExitCode();
        }

        $this->assertFileDoesNotExist($this->dir.'/overlap', 'two deploys ran at once');
        $this->assertContains(0, $codes, 'one session should have deployed');
        $this->assertEmpty(array_diff($codes, [0, 75]), 'every other session is refused, never crashes');
    }

    #[Test]
    public function a_guarded_command_can_run_another_under_the_same_lock(): void
    {
        $inner = 'sh '.dirname(__DIR__, 2).'/docker/bin/deploy-guard run inner -- sh -c "echo inner > '.$this->dir.'/inner; test -L '.$this->dir.'/lock"';

        $process = $this->guard(['run', 'deploy', '--', 'sh', '-c', $inner]);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertFileExists($this->dir.'/inner');
        $log = (string) file_get_contents($this->dir.'/deploy.log');
        $this->assertStringContainsString('inner by=', $log);
        $this->assertFalse(is_link($this->dir.'/lock'), 'the outer guard releases the lock once');
    }

    #[Test]
    public function moving_bytes_between_untracked_files_counts_as_a_change(): void
    {
        $build = ['run', 'build', '--', 'sh', '-c', 'echo x >> '.$this->dir.'/builds'];
        $flags = ['SKIP_IF_UNCHANGED' => '1', 'STAMP_ON_SUCCESS' => '1'];
        file_put_contents($this->repo.'/a.txt', 'a');
        file_put_contents($this->repo.'/b.txt', 'bc');

        $this->guard($build, $flags);

        file_put_contents($this->repo.'/a.txt', 'ab');
        file_put_contents($this->repo.'/b.txt', 'c');
        $this->guard($build, $flags);

        $this->assertCount(2, file($this->dir.'/builds'), 'the same bytes split differently is a different tree');
    }

    #[Test]
    public function an_untracked_file_whose_name_starts_with_a_dash_is_fingerprinted(): void
    {
        $build = ['run', 'build', '--', 'sh', '-c', 'echo x >> '.$this->dir.'/builds'];
        $flags = ['SKIP_IF_UNCHANGED' => '1', 'STAMP_ON_SUCCESS' => '1'];
        file_put_contents($this->repo.'/-notes', "a\n");

        $this->guard($build, $flags);
        file_put_contents($this->repo.'/-notes', "b\n");
        $this->guard($build, $flags);

        $this->assertCount(2, file($this->dir.'/builds'));
    }

    #[Test]
    public function retargeting_an_untracked_symlink_counts_as_a_change(): void
    {
        $build = ['run', 'build', '--', 'sh', '-c', 'echo x >> '.$this->dir.'/builds'];
        $flags = ['SKIP_IF_UNCHANGED' => '1', 'STAMP_ON_SUCCESS' => '1'];
        file_put_contents($this->repo.'/one.txt', "same\n");
        file_put_contents($this->repo.'/two.txt', "same\n");
        symlink('one.txt', $this->repo.'/current');

        $this->guard($build, $flags);
        unlink($this->repo.'/current');
        symlink('two.txt', $this->repo.'/current');
        $this->guard($build, $flags);

        $this->assertCount(2, file($this->dir.'/builds'), 'same bytes at the end, different link, so rebuilt');
    }

    #[Test]
    public function verify_refuses_when_the_tree_changed_since_the_last_build(): void
    {
        $flags = ['SKIP_IF_UNCHANGED' => '1', 'STAMP_ON_SUCCESS' => '1'];

        $this->assertSame(0, $this->guard(['verify'])->getExitCode(), 'nothing built yet, nothing to contradict');

        $this->guard(['run', 'build', '--', 'true'], $flags);
        $this->assertSame(0, $this->guard(['verify'])->getExitCode());

        file_put_contents($this->repo.'/app.txt', "changed after the build\n");
        $process = $this->guard(['verify']);

        $this->assertSame(76, $process->getExitCode());
        $this->assertStringContainsString('out of date', $process->getErrorOutput());
    }

    #[Test]
    public function a_lock_whose_pid_was_reused_by_another_process_is_stale(): void
    {
        // PID 1 exists, but it did not start when the lock owner did.
        symlink('pid=1 by=old target=up since=then start=Mon_Jan__1_00:00:00_2001', $this->dir.'/lock');

        $process = $this->guard(['run', 'up', '--', 'true']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('stale lock', $process->getErrorOutput());
    }

    #[Test]
    public function a_lock_holder_is_recognized_by_its_recorded_start_time(): void
    {
        $first = $this->guardProcess(['run', 'build', '--', 'sleep', '3']);
        $first->start();
        $this->waitFor(fn (): bool => is_link($this->dir.'/lock'));

        $this->assertMatchesRegularExpression('/ start=\S+/', (string) readlink($this->dir.'/lock'));
        $this->assertSame(75, $this->guard(['run', 'up', '--', 'true'])->getExitCode());

        $first->stop(0);
    }

    #[Test]
    public function changing_only_the_mode_of_an_untracked_file_counts_as_a_change(): void
    {
        $build = ['run', 'build', '--', 'sh', '-c', 'echo x >> '.$this->dir.'/builds'];
        $flags = ['SKIP_IF_UNCHANGED' => '1', 'STAMP_ON_SUCCESS' => '1'];
        file_put_contents($this->repo.'/tool.sh', "echo hi\n");
        chmod($this->repo.'/tool.sh', 0644);

        $this->guard($build, $flags);
        chmod($this->repo.'/tool.sh', 0755);
        $this->guard($build, $flags);

        $this->assertCount(2, file($this->dir.'/builds'), 'chmod +x changes the image, so it rebuilds');
    }

    #[Test]
    public function a_lock_owned_by_a_process_we_cannot_signal_counts_as_held(): void
    {
        // PID 1 is alive and, for a non-root user, owned by someone else.
        symlink('pid=1 by=init target=up since=boot', $this->dir.'/lock');

        $process = $this->guard(['run', 'up', '--', 'true']);

        $this->assertSame(75, $process->getExitCode());
        $this->assertStringContainsString('pid=1', $process->getErrorOutput());
    }

    #[Test]
    public function editing_an_untracked_file_without_renaming_it_counts_as_a_change(): void
    {
        $build = ['run', 'build', '--', 'sh', '-c', 'echo x >> '.$this->dir.'/builds'];
        $flags = ['SKIP_IF_UNCHANGED' => '1', 'STAMP_ON_SUCCESS' => '1'];
        file_put_contents($this->repo.'/notes.txt', "a\n");

        $this->guard($build, $flags);
        $this->guard($build, $flags);
        $this->assertCount(1, file($this->dir.'/builds'), 'unchanged, so skipped');

        file_put_contents($this->repo.'/notes.txt', "b\n");
        $this->guard($build, $flags);
        $this->assertCount(2, file($this->dir.'/builds'), 'same name, new contents, so rebuilt');
    }

    #[Test]
    public function the_command_status_is_passed_on_and_a_failed_run_is_not_stamped(): void
    {
        $process = $this->guard(['run', 'build', '--', 'sh', '-c', 'exit 3'], ['STAMP_ON_SUCCESS' => '1']);

        $this->assertSame(3, $process->getExitCode());
        $this->assertFileDoesNotExist($this->dir.'/stamp');
        $this->assertStringContainsString('rc=3', (string) file_get_contents($this->dir.'/deploy.log'));
    }

    #[Test]
    public function a_rebuild_is_skipped_until_the_tree_changes_or_it_is_forced(): void
    {
        $build = ['run', 'build', '--', 'sh', '-c', 'echo x >> '.$this->dir.'/builds'];
        $flags = ['SKIP_IF_UNCHANGED' => '1', 'STAMP_ON_SUCCESS' => '1'];
        $builds = fn (): int => is_file($this->dir.'/builds') ? count(file($this->dir.'/builds')) : 0;

        $this->guard($build, $flags);
        $this->assertSame(1, $builds());

        $skipped = $this->guard($build, $flags);
        $this->assertSame(0, $skipped->getExitCode());
        $this->assertSame(1, $builds(), 'nothing changed, so no rebuild');
        $this->assertStringContainsString('result=skipped', (string) file_get_contents($this->dir.'/deploy.log'));

        file_put_contents($this->repo.'/app.txt', "two\n");
        $this->guard($build, $flags);
        $this->assertSame(2, $builds(), 'a changed file rebuilds');

        $this->guard($build, $flags);
        $this->assertSame(2, $builds());

        $this->guard($build, $flags + ['FORCE' => '1']);
        $this->assertSame(3, $builds(), 'FORCE=1 always rebuilds');
    }

    #[Test]
    public function a_tree_that_changes_during_the_run_is_not_stamped(): void
    {
        $build = ['run', 'build', '--', 'sh', '-c', 'echo changed > '.$this->repo.'/app.txt'];
        $flags = ['SKIP_IF_UNCHANGED' => '1', 'STAMP_ON_SUCCESS' => '1'];

        $first = $this->guard($build, $flags);

        $this->assertSame(76, $first->getExitCode(), 'a stale result must not let the next step run');
        $this->assertFileDoesNotExist($this->dir.'/stamp');
        $this->assertStringContainsString('tree-changed-during-run', (string) file_get_contents($this->dir.'/deploy.log'));

        // With the tree now settled, the next build runs and is stamped.
        $second = $this->guard(['run', 'build', '--', 'true'], $flags);
        $this->assertSame(0, $second->getExitCode());
        $this->assertFileExists($this->dir.'/stamp');
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $env
     */
    private function guard(array $arguments, array $env = []): Process
    {
        $process = $this->guardProcess($arguments, $env);
        $process->run();

        return $process;
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $env
     */
    private function guardProcess(array $arguments, array $env = []): Process
    {
        return new Process(
            ['sh', dirname(__DIR__, 2).'/docker/bin/deploy-guard', ...$arguments],
            null,
            $env + [
                'DEPLOY_LOCK_DIR' => $this->dir.'/lock',
                'DEPLOY_LOG' => $this->dir.'/deploy.log',
                'DEPLOY_STAMP' => $this->dir.'/stamp',
                'DEPLOY_REPO' => $this->repo,
            ],
        );
    }

    private function git(string ...$arguments): void
    {
        $process = new Process(['git', ...$arguments], $this->repo);
        $process->mustRun();
    }

    private function waitFor(callable $condition): void
    {
        $deadline = microtime(true) + 5;
        while (! $condition() && microtime(true) < $deadline) {
            usleep(50_000);
        }
        $this->assertTrue($condition(), 'timed out waiting');
    }
}
