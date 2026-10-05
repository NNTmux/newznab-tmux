<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\TmuxMonitor;
use App\Enums\TmuxPaneRole;
use App\Services\Tmux\Tmux;
use App\Services\Tmux\TmuxCommand;
use App\Services\Tmux\TmuxLayoutBuilder;
use App\Services\Tmux\TmuxPaneManager;
use App\Services\Tmux\TmuxSessionManager;
use App\Services\Tmux\TmuxTaskRunner;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TmuxPaneManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['tmux.socket_name' => '']);
        Process::preventStrayProcesses();
    }

    public function test_roles_resolve_to_stable_pane_ids(): void
    {
        Process::fake(fn (PendingProcess $process) => in_array('display-message', $process->command, true) ? Process::result('$1') : Process::result("%12\tmonitor\t1\n%27\tpost_movies\t1\n"));

        $manager = new TmuxPaneManager('test session');

        $this->assertSame('%12', $manager->paneForRole(TmuxPaneRole::Monitor));
        $this->assertSame('%27', $manager->paneForRole(TmuxPaneRole::PostMovies));

        Process::assertRanTimes(
            fn (PendingProcess $process): bool => is_array($process->command)
                && in_array('list-panes', $process->command, true),
            1,
        );
    }

    public function test_unconfigured_socket_uses_the_same_server_as_plain_tmux_attach(): void
    {
        config(['tmux' => []]);

        $this->assertSame(['tmux', 'attach-session', '-t', '=nntmux'], TmuxCommand::arguments(['attach-session', '-t', '=nntmux']));
    }

    /** @param array<string, int> $settings */
    #[DataProvider('postprocessingSettings')]
    public function test_postprocessing_dispatch_respects_settings_and_identifies_the_disabled_switch(string $task, array $settings, string $expected): void
    {
        Process::fake(function (PendingProcess $process) {
            if (in_array('#{session_id}', $process->command, true)) {
                return Process::result('$1');
            }
            if (in_array('list-panes', $process->command, true)) {
                return Process::result("%41\tpost_movies\t1\n%42\tpost_metadata\t1\n%43\tpost_tv\t1\n");
            }

            return Process::result();
        });

        $runner = new TmuxTaskRunner('test-session');
        $this->assertTrue($runner->runPaneTask($task, [], [
            'settings' => $settings,
            'counts' => ['now' => ['processmovies' => 1, 'processbooks' => 1, 'processtv' => 1]],
        ]));

        Process::assertRan(fn (PendingProcess $process): bool => in_array('respawn-pane', $process->command, true)
            && str_contains(implode(' ', $process->command), $expected));
    }

    /** @return iterable<string, array{string, array<string, int>, string}> */
    public static function postprocessingSettings(): iterable
    {
        yield 'all movies enabled' => ['movies', ['post_non' => 1, 'processmovies' => 1], 'multiprocessing:postprocess mov'];
        yield 'renamed movies enabled' => ['movies', ['post_non' => 1, 'processmovies' => 2], 'multiprocessing:postprocess mov'];
        yield 'metadata enabled' => ['amazon', ['post_amazon' => 1, 'processbooks' => 1], 'multiprocessing:postprocess ama'];
        yield 'tv enabled' => ['tv', ['post_non' => 1, 'processtvrage' => 1], 'multiprocessing:postprocess tv'];
        yield 'movie pane disabled' => ['movies', ['post_non' => 0, 'processmovies' => 1], 'Postprocess Video Metadata (post_non)'];
        yield 'metadata pane disabled' => ['amazon', ['post_amazon' => 0, 'processbooks' => 1], 'Postprocess Metadata (post_amazon)'];
        yield 'missing movie pane switch' => ['movies', ['processmovies' => 1], 'Postprocess Video Metadata (post_non)'];
        yield 'movie lookup disabled' => ['movies', ['post_non' => 1, 'processmovies' => 0], 'Lookup Movies (lookupimdb)'];
        yield 'tv pane disabled' => ['tv', ['post_non' => 0, 'processtvrage' => 1], 'Postprocess Video Metadata (post_non)'];
    }

    public function test_duplicate_roles_are_rejected(): void
    {
        Process::fake(fn (PendingProcess $process) => in_array('display-message', $process->command, true) ? Process::result('$1') : Process::result("%12\tmonitor\t1\n%27\tmonitor\t1\n"));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("role 'monitor' is assigned more than once");

        (new TmuxPaneManager('test-session'))->paneForRole(TmuxPaneRole::Monitor);
    }

    public function test_missing_roles_are_rejected(): void
    {
        Process::fake(fn (PendingProcess $process) => in_array('display-message', $process->command, true) ? Process::result('$1') : Process::result("%12\tmonitor\t1\n"));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("role 'post_movies' was not found");

        (new TmuxPaneManager('test-session'))->paneForRole(TmuxPaneRole::PostMovies);
    }

    public function test_legacy_coordinate_is_tagged_during_role_resolution(): void
    {
        Process::fake(function (PendingProcess $process) {
            if (is_array($process->command) && in_array('#{session_id}', $process->command, true)) {
                return Process::result('$1');
            }
            if (is_array($process->command) && in_array('list-panes', $process->command, true)) {
                return Process::result("%8\t\n");
            }

            if (is_array($process->command) && in_array('display-message', $process->command, true)) {
                return Process::result("%8\n");
            }

            return Process::result();
        });

        $manager = new TmuxPaneManager('legacy-session');

        $this->assertSame('%8', $manager->paneForRole(TmuxPaneRole::Monitor, '0.0'));

        Process::assertRan(function (PendingProcess $process): bool {
            return $process->command === [
                'tmux',
                'set-option',
                '-p',
                '-t',
                '%8',
                '@nntmux_role',
                'monitor',
            ];
        });
    }

    public function test_respawn_passes_the_command_as_one_argument(): void
    {
        Process::fake();

        $command = <<<'SH'
echo "$HOME" && printf '%s\n' 'quoted value'
SH;

        $this->assertTrue((new TmuxPaneManager('test session'))->respawnPane('%42', $command, kill: true));

        Process::assertRan(function (PendingProcess $process) use ($command): bool {
            return $process->command === [
                'tmux',
                'respawn-pane',
                '-k',
                '-t',
                '%42',
                '-c',
                base_path(),
                $command,
            ];
        });
    }

    /** @param list<string> $expectedRoles */
    #[DataProvider('layoutRolesProvider')]
    public function test_layouts_tag_every_logical_pane(int $mode, array $expectedRoles): void
    {
        $nextPaneId = 1;
        $assignedRoles = [];
        $splitTargets = [];

        Process::fake(function (PendingProcess $process) use (&$nextPaneId, &$assignedRoles, &$splitTargets) {
            $command = $process->command;
            if (! is_array($command)) {
                return Process::result();
            }

            if (in_array('#{window_id}', $command, true)) {
                return Process::result('@1');
            }
            if (in_array('#{pane_dead}', $command, true)) {
                return Process::result('1');
            }
            if (in_array('#{session_id}', $command, true)) {
                return Process::result('$1');
            }
            if (in_array('has-session', $command, true)) {
                return Process::result('', '', 1);
            }

            if (in_array('new-session', $command, true)
                || in_array('new-window', $command, true)
                || in_array('split-window', $command, true)) {
                if (in_array('split-window', $command, true)) {
                    $splitTargets[] = $command[array_search('-t', $command, true) + 1];
                }

                return Process::result('%'.$nextPaneId++."\n");
            }

            $roleOptionIndex = array_search('@nntmux_role', $command, true);
            if ($roleOptionIndex !== false) {
                $assignedRoles[] = $command[$roleOptionIndex + 1];
            }

            return Process::result();
        });

        $sessionManager = new TmuxSessionManager('test-session');
        $layoutBuilder = new class($sessionManager) extends TmuxLayoutBuilder
        {
            protected function createOptionalWindows(): void {}
        };

        $this->assertTrue($layoutBuilder->buildLayout($mode), (string) $layoutBuilder->lastError());
        $this->assertEqualsCanonicalizing($expectedRoles, $assignedRoles);

        foreach ($splitTargets as $target) {
            $this->assertStringStartsWith('%', $target);
        }
    }

    public function test_monitor_dispatches_every_stripped_mode_task(): void
    {
        $runner = new class('test-session') extends TmuxTaskRunner
        {
            /** @var list<string> */
            public array $tasks = [];

            public function beginCycle(): void {}

            public function runPaneTask(string $taskName, array $config, array $runVar): bool
            {
                $this->tasks[] = $taskName;

                return true;
            }
        };
        $command = new TmuxMonitor;
        (new \ReflectionProperty($command, 'taskRunner'))->setValue($command, $runner);
        (new \ReflectionMethod($command, 'runPaneTasks'))->invoke($command, ['constants' => ['sequential' => 2]]);
        $this->assertSame(['main', 'fixnames', 'amazon', 'scraper'], $runner->tasks);
    }

    public function test_legacy_migration_never_overwrites_another_role(): void
    {
        Process::fake(function (PendingProcess $process) {
            if (in_array('#{session_id}', $process->command, true)) {
                return Process::result('$1');
            }
            if (in_array('list-panes', $process->command, true)) {
                return Process::result("%9\tpost_movies\t1\n");
            }

            return Process::result("%9\tpost_movies");
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("already has role 'post_movies'");
        (new TmuxPaneManager('test-session'))->paneForRole(TmuxPaneRole::PostTv, '2.1');
    }

    public function test_each_monitor_cycle_refreshes_role_and_state_mapping(): void
    {
        $listCalls = 0;
        Process::fake(function (PendingProcess $process) use (&$listCalls) {
            if (in_array('#{session_id}', $process->command, true)) {
                return Process::result('$1');
            }
            $listCalls++;

            return Process::result('%'.($listCalls === 1 ? '9' : '10')."\tpost_movies\t1\n");
        });
        $manager = new TmuxPaneManager('test-session');
        $this->assertSame('%9', $manager->paneForRole(TmuxPaneRole::PostMovies));
        $manager->refresh();
        $this->assertSame('%10', $manager->paneForRole(TmuxPaneRole::PostMovies));
        $this->assertSame(2, $listCalls);
    }

    public function test_idle_pane_is_not_repeatedly_respawned_by_exit_hooks(): void
    {
        Process::fake(function (PendingProcess $process) {
            if (in_array('#{session_id}', $process->command, true)) {
                return Process::result('$1');
            }
            if (in_array('list-panes', $process->command, true)) {
                return Process::result("%9\tpost_additional\t1\n");
            }

            return Process::result();
        });
        $runner = new TmuxTaskRunner('test-session');
        $settings = ['settings' => ['post' => 3], 'counts' => ['now' => ['work_available' => 0, 'processnfo' => 0]]];
        $runner->beginCycle();
        $this->assertTrue($runner->runPaneTask('ppadditional', [], $settings));
        $runner->beginCycle();
        $this->assertTrue($runner->runPaneTask('ppadditional', [], $settings));
        Process::assertRanTimes(fn (PendingProcess $process): bool => in_array('respawn-pane', $process->command, true), 1);
    }

    public function test_multiple_commands_preserve_an_earlier_failure_after_later_success_and_cooldown(): void
    {
        $runner = new TmuxTaskRunner('test-session');
        $batch = (new \ReflectionMethod($runner, 'batchCommand'))->invoke($runner, ['exit 7', 'printf "later success"']);
        $process = new \Symfony\Component\Process\Process(['bash', '-c', $runner->buildCommand($batch, ['sleep' => 0])]);
        $process->run();
        $this->assertSame(7, $process->getExitCode());
        $this->assertStringContainsString('later success', $process->getOutput());
        $this->assertStringContainsString(date('Y-m-d'), $process->getOutput());
    }

    public function test_legacy_pane_listing_uses_the_selected_socket_and_preserves_titles_with_spaces(): void
    {
        config(['tmux.socket_name' => 'application-test']);
        Process::fake(function (PendingProcess $process) {
            if (in_array('#{session_id}', $process->command, true)) {
                return Process::result('$7');
            }
            if (in_array('list-panes', $process->command, true)) {
                return Process::result("0:0\tMonitor title\n1:0\tFix Names\n");
            }

            return Process::result();
        });
        $panes = (new Tmux)->getListOfPanes(['tmux_session' => 'custom-session', 'sequential' => 2]);
        $this->assertSame(['zero' => ['Monitor title'], 'one' => ['Fix Names'], 'two' => []], $panes);
        Process::assertRan(fn (PendingProcess $process): bool => in_array('list-panes', $process->command, true)
            && in_array('application-test', $process->command, true) && in_array('$7', $process->command, true));
    }

    /** @return array<string, array{int, list<string>}> */
    public static function layoutRolesProvider(): array
    {
        return [
            'full' => [
                0,
                [
                    'monitor',
                    'binaries',
                    'backfill',
                    'releases',
                    'fix_names',
                    'remove_crap',
                    'post_additional',
                    'post_movies',
                    'post_tv',
                    'post_metadata',
                    'irc_scraper',
                ],
            ],
            'basic' => [
                1,
                [
                    'monitor',
                    'releases',
                    'fix_names',
                    'remove_crap',
                    'post_additional',
                    'post_movies',
                    'post_tv',
                    'post_metadata',
                    'irc_scraper',
                ],
            ],
            'stripped' => [
                2,
                [
                    'monitor',
                    'sequential',
                    'fix_names',
                    'post_metadata',
                    'irc_scraper',
                ],
            ],
        ];
    }

    public function test_partial_layout_is_removed_after_a_split_failure(): void
    {
        $sessionExists = false;
        $splitAttempts = 0;

        Process::fake(function (PendingProcess $process) use (&$sessionExists, &$splitAttempts) {
            $command = $process->command;
            if (! is_array($command)) {
                return Process::result();
            }

            if (in_array('#{window_id}', $command, true)) {
                return Process::result('@1');
            }
            if (in_array('#{pane_dead}', $command, true)) {
                return Process::result('1');
            }
            if (in_array('#{session_id}', $command, true)) {
                return Process::result('$1');
            }
            if (in_array('has-session', $command, true)) {
                return Process::result('', '', $sessionExists ? 0 : 1);
            }

            if (in_array('new-session', $command, true)) {
                $sessionExists = true;

                return Process::result("%1\n");
            }

            if (in_array('split-window', $command, true)) {
                $splitAttempts++;
                if ($splitAttempts === 2) {
                    return Process::result('', 'split failed', 1);
                }

                return Process::result("%2\n");
            }

            if (in_array('kill-session', $command, true)) {
                $sessionExists = false;
            }

            return Process::result();
        });

        $sessionManager = new TmuxSessionManager('test-session');
        $layoutBuilder = new class($sessionManager) extends TmuxLayoutBuilder
        {
            protected function createOptionalWindows(): void {}
        };

        $this->assertFalse($layoutBuilder->buildLayout(0));
        $this->assertSame('split failed', $layoutBuilder->lastError());
        $this->assertFalse($sessionExists);

        Process::assertRan(
            fn (PendingProcess $process): bool => is_array($process->command)
                && in_array('kill-session', $process->command, true),
        );
    }

    public function test_task_runner_resolves_a_role_before_respawning(): void
    {
        Process::fake(function (PendingProcess $process) {
            if (is_array($process->command) && in_array('#{session_id}', $process->command, true)) {
                return Process::result('$1');
            }
            if (is_array($process->command) && in_array('list-panes', $process->command, true)) {
                return Process::result("%9\tpost_movies\t1\n");
            }

            return Process::result();
        });

        $runner = new TmuxTaskRunner('test-session');

        $this->assertTrue($runner->runTask('Movies', [
            'role' => TmuxPaneRole::PostMovies,
            'command' => 'php artisan example',
        ]));

        Process::assertRan(function (PendingProcess $process): bool {
            return $process->command === [
                'tmux',
                'respawn-pane',
                '-t',
                '%9',
                '-c',
                base_path(),
                'php artisan example',
            ];
        });
    }
}
