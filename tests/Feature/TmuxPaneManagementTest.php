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

    /** @return array<string, array{string, int, int, string}> */
    public static function enabledPostProcessingModes(): array
    {
        return [
            'movies enabled' => ['movies', 1, 1, 'mov'],
            'movies NFO mode' => ['movies', 2, 1, 'mov'],
            'movies all mode' => ['movies', 3, 1, 'mov'],
            'renamed movies all mode' => ['movies', 3, 2, 'mov'],
            'TV NFO mode' => ['tv', 2, 1, 'tv'],
            'TV all mode' => ['tv', 3, 1, 'tv'],
            'metadata NFO mode' => ['amazon', 2, 1, 'ama'],
            'metadata all mode' => ['amazon', 3, 1, 'ama'],
        ];
    }

    #[DataProvider('enabledPostProcessingModes')]
    public function test_nonzero_modes_run_enabled_post_processing_panes(string $task, int $paneMode, int $lookupMode, string $type): void
    {
        Process::fake(function (PendingProcess $process) {
            if (is_array($process->command) && in_array('list-panes', $process->command, true)) {
                return Process::result("%9\tpost_movies\n%10\tpost_tv\n%11\tpost_metadata\n");
            }

            return Process::result();
        });

        $this->assertTrue((new TmuxTaskRunner('test-session'))->runPaneTask($task, [], [
            'settings' => [
                'post_non' => $paneMode,
                'post_amazon' => $paneMode,
                'processmovies' => $lookupMode,
                'processtvrage' => $lookupMode,
            ],
            'counts' => ['now' => ['processmovies' => 5, 'processtv' => 5, 'processmusic' => 5]],
        ]));

        Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
            && in_array('respawn-pane', $process->command, true)
            && str_contains((string) end($process->command), 'multiprocessing:postprocess '.$type));
    }

    /** @return array<string, array{int, int, int, string}> */
    public static function inactiveMovieProcessing(): array
    {
        return [
            'pane switch disabled' => [0, 1, 5, 'TV, Anime and Movie Panes is disabled'],
            'lookup disabled' => [3, 0, 5, 'Process Movies is disabled'],
            'no eligible work' => [3, 1, 0, 'no work available'],
        ];
    }

    #[DataProvider('inactiveMovieProcessing')]
    public function test_inactive_movie_panes_explain_which_setting_or_work_is_missing(int $paneMode, int $lookupMode, int $count, string $reason): void
    {
        Process::fake(function (PendingProcess $process) {
            if (is_array($process->command) && in_array('list-panes', $process->command, true)) {
                return Process::result("%9\tpost_movies\n");
            }

            return Process::result();
        });

        $this->assertTrue((new TmuxTaskRunner('test-session'))->runPaneTask('movies', [], [
            'settings' => ['post_non' => $paneMode, 'processmovies' => $lookupMode],
            'counts' => ['now' => ['processmovies' => $count]],
        ]));

        Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
            && in_array('respawn-pane', $process->command, true)
            && str_contains((string) end($process->command), $reason));
        Process::assertNotRan(fn (PendingProcess $process): bool => is_array($process->command)
            && str_contains((string) end($process->command), 'multiprocessing:postprocess mov'));
    }
}
