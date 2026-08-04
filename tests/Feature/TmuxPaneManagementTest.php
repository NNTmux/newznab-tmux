<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TmuxPaneRole;
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

        Process::preventStrayProcesses();
    }

    public function test_roles_resolve_to_stable_pane_ids(): void
    {
        Process::fake([
            '*' => Process::result("%12\tmonitor\n%27\tpost_movies\n"),
        ]);

        $manager = new TmuxPaneManager('test session');

        $this->assertSame('%12', $manager->paneForRole(TmuxPaneRole::Monitor));
        $this->assertSame('%27', $manager->paneForRole(TmuxPaneRole::PostMovies));

        Process::assertRanTimes(
            fn (PendingProcess $process): bool => is_array($process->command)
                && in_array('list-panes', $process->command, true),
            1,
        );
    }

    public function test_duplicate_roles_are_rejected(): void
    {
        Process::fake([
            '*' => Process::result("%12\tmonitor\n%27\tmonitor\n"),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("role 'monitor' is assigned more than once");

        (new TmuxPaneManager('test-session'))->paneForRole(TmuxPaneRole::Monitor);
    }

    public function test_missing_roles_are_rejected(): void
    {
        Process::fake([
            '*' => Process::result("%12\tmonitor\n"),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("role 'post_movies' was not found");

        (new TmuxPaneManager('test-session'))->paneForRole(TmuxPaneRole::PostMovies);
    }

    public function test_legacy_coordinate_is_tagged_during_role_resolution(): void
    {
        Process::fake(function (PendingProcess $process) {
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
                $command,
            ];
        });
    }

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
            if (is_array($process->command) && in_array('list-panes', $process->command, true)) {
                return Process::result("%9\tpost_movies\n");
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
                'php artisan example',
            ];
        });
    }
}
