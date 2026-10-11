<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Tmux\TmuxPaneManager;
use App\Services\Tmux\TmuxTaskRunner;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

class TmuxFixNamesScheduleTest extends TestCase
{
    private const string SLOT = 'tmux:fix-names:full-backlog';

    /** @var list<string> */
    private array $launched = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'tmux.fix_names.full_backlog' => true,
            'tmux.fix_names.full_backlog_interval_minutes' => 60,
            'tmux.fix_names.predbft' => true,
            'tmux.fix_names.predbft_scheduled' => false,
            'cache.stores.fixnames_claims' => ['driver' => 'array'],
            'tmux.fix_names.cache_store' => 'fixnames_claims',
        ]);
        Cache::flush();
    }

    #[Test]
    public function a_busy_pane_does_not_consume_the_full_backlog_interval(): void
    {
        $this->assertTrue($this->runTask(alive: true, renames: 5));

        $this->assertFalse(Cache::store('fixnames_claims')->has(self::SLOT));
        $this->assertSame([], $this->launched);
    }

    #[Test]
    public function full_backlog_passes_run_once_per_interval_after_a_successful_launch(): void
    {
        $this->assertTrue($this->runTask(alive: false, renames: 5));
        $this->assertTrue($this->runTask(alive: false, renames: 5));

        $this->assertTrue(Cache::store('fixnames_claims')->has(self::SLOT));
        $this->assertFalse(Cache::store('array')->has(self::SLOT), 'The claim must use the configured store, not the default.');
        $this->assertCount(2, $this->launched);
        foreach ([3, 9, 19] as $level) {
            $this->assertStringContainsString("releases:fix-names {$level} ", $this->launched[0]);
            $this->assertStringContainsString("releases:fix-names {$level} ", $this->launched[1]);
        }
        foreach ([4, 10, 20] as $level) {
            $this->assertStringContainsString("releases:fix-names {$level} ", $this->launched[0]);
            $this->assertStringNotContainsString("releases:fix-names {$level} ", $this->launched[1]);
        }
        $this->assertStringContainsString('predbft', $this->launched[1]);
    }

    #[Test]
    public function a_failed_launch_releases_the_full_backlog_interval(): void
    {
        $this->assertFalse($this->runTask(alive: false, renames: 5, respawnSucceeds: false));

        $this->assertFalse(Cache::store('fixnames_claims')->has(self::SLOT));
    }

    #[Test]
    public function full_backlog_and_predb_passes_run_without_recent_rename_work(): void
    {
        $this->assertTrue($this->runTask(alive: false, renames: 0));

        $this->assertCount(1, $this->launched);
        $this->assertStringContainsString('releases:fix-names 4 ', $this->launched[0]);
        $this->assertStringNotContainsString('releases:fix-names 3 ', $this->launched[0]);
        $this->assertStringContainsString('predbft', $this->launched[0]);
        $this->assertTrue(Cache::store('fixnames_claims')->has(self::SLOT));
    }

    #[Test]
    public function the_pane_leaves_the_predb_pass_to_the_scheduler_while_it_runs_it(): void
    {
        // A legacy TMUX_FIX_NAMES_PREDBFT=true must not make the pass start twice.
        config(['tmux.fix_names.predbft' => true, 'tmux.fix_names.predbft_scheduled' => true]);

        $this->assertTrue($this->runTask(alive: false, renames: 5));

        $this->assertStringNotContainsString('predbft', $this->launched[0]);
        $this->assertStringContainsString('releases:fix-names 3 ', $this->launched[0]);
    }

    #[Test]
    public function the_pane_idles_when_nothing_is_due(): void
    {
        config(['tmux.fix_names.predbft' => false]);
        Cache::store('fixnames_claims')->put(self::SLOT, true, 3600);

        $this->assertTrue($this->runTask(alive: false, renames: 0));

        $this->assertCount(1, $this->launched);
        $this->assertStringContainsString('no releases to process', $this->launched[0]);
    }

    #[Test]
    public function the_extra_passes_can_be_switched_off(): void
    {
        config(['tmux.fix_names.full_backlog' => false, 'tmux.fix_names.predbft' => false]);

        $this->assertTrue($this->runTask(alive: false, renames: 5));

        $this->assertStringNotContainsString('releases:fix-names 4 ', $this->launched[0]);
        $this->assertStringNotContainsString('predbft', $this->launched[0]);
        $this->assertStringContainsString('releases:fix-names 3 ', $this->launched[0]);
    }

    #[Test]
    public function a_cache_outage_skips_the_full_backlog_pass_but_keeps_scheduling(): void
    {
        Cache::shouldReceive('store')->with('fixnames_claims')->andThrow(new RuntimeException('cache down'));

        $this->assertTrue($this->runTask(alive: false, renames: 5));

        $this->assertStringContainsString('releases:fix-names 3 ', $this->launched[0]);
        $this->assertStringNotContainsString('releases:fix-names 4 ', $this->launched[0]);
    }

    private function runTask(bool $alive, int $renames, bool $respawnSucceeds = true): bool
    {
        $runner = new TmuxTaskRunner('fixnames-test');
        /** @var TmuxPaneManager&MockObject $panes */
        $panes = $this->createMock(TmuxPaneManager::class);
        $panes->method('paneForRole')->willReturn('%1');
        $panes->method('isAlive')->willReturn($alive);
        $capture = function (string $pane, string|array $command) use ($respawnSucceeds): bool {
            $this->launched[] = is_array($command) ? implode(' ', $command) : $command;

            return $respawnSucceeds;
        };
        $panes->method('respawnPane')->willReturnCallback($capture);
        $panes->method('respawnLoggedPane')->willReturnCallback(static fn (string $pane, array $command, string $file): bool => $capture($pane, $command));
        (new ReflectionProperty(TmuxTaskRunner::class, 'paneManager'))->setValue($runner, $panes);

        return (new ReflectionMethod(TmuxTaskRunner::class, 'runFixNamesTask'))->invoke($runner, [
            'settings' => ['fix_names' => 1, 'fix_timer' => 0],
            'counts' => ['now' => ['processrenames' => $renames]],
        ]);
    }
}
