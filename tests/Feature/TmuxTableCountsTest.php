<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Tmux\Tmux;
use App\Services\Tmux\TmuxMonitorService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

final class TmuxTableCountsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        foreach (['collections', 'releases', 'binaries', 'parts', 'missed_parts', 'multigroup_parts_1'] as $table) {
            DB::statement("CREATE TABLE {$table} (id INTEGER PRIMARY KEY)");
        }
    }

    public function test_table_counts_ignore_legacy_tables_and_report_empty_active_tables_as_zero(): void
    {
        DB::table('multigroup_parts_1')->insert(array_map(static fn (int $id): array => ['id' => $id], range(1, 128)));
        $tmux = (new ReflectionClass(Tmux::class))->newInstanceWithoutConstructor();

        $this->assertEquals([
            (object) ['name' => 'binaries', 'row_count' => 0],
            (object) ['name' => 'parts', 'row_count' => 0],
            (object) ['name' => 'missed_parts', 'row_count' => 0],
        ], $tmux->cbpmTableQuery());
    }

    public function test_monitor_table_refresh_reflects_inserted_and_deleted_parts(): void
    {
        $tmux = $this->getMockBuilder(Tmux::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['proc_query'])
            ->getMock();
        $tmux->method('proc_query')->willReturn('SELECT 0 AS active_groups');
        $monitor = (new ReflectionClass(TmuxMonitorService::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(TmuxMonitorService::class, 'tmux'))->setValue($monitor, $tmux);
        $refresh = new ReflectionMethod(TmuxMonitorService::class, 'getTableCounts');
        $state = new ReflectionProperty(TmuxMonitorService::class, 'runVar');

        DB::table('collections')->insert(['id' => 1]);
        DB::table('releases')->insert([['id' => 1], ['id' => 2]]);
        DB::table('binaries')->insert(['id' => 1]);
        DB::table('missed_parts')->insert(['id' => 1]);
        DB::table('parts')->insert([['id' => 1], ['id' => 2]]);
        $refresh->invoke($monitor);
        $counts = $state->getValue($monitor)['counts']['now'];

        $this->assertSame(1, $counts['collections_table']);
        $this->assertSame(2, $counts['releases']);
        $this->assertSame(1, $counts['binaries_table']);
        $this->assertSame(1, $counts['missed_parts_table']);
        $this->assertSame(2, $counts['parts_table']);

        DB::table('parts')->insert(['id' => 3]);
        $refresh->invoke($monitor);
        $this->assertSame(3, $state->getValue($monitor)['counts']['now']['parts_table']);

        DB::table('parts')->delete();
        $refresh->invoke($monitor);
        $this->assertSame(0, $state->getValue($monitor)['counts']['now']['parts_table']);
    }

    public function test_large_tables_use_the_row_estimate_and_small_ones_an_exact_count(): void
    {
        DB::table('missed_parts')->insert([['id' => 1], ['id' => 2]]);
        $tmux = $this->getMockBuilder(Tmux::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['estimatedRowCount'])
            ->getMock();
        $tmux->method('estimatedRowCount')->willReturnCallback(static fn (string $table): int => match ($table) {
            'parts' => 98_000_000,
            'binaries' => 14_900_000,
            default => 12,
        });

        $this->assertEquals([
            (object) ['name' => 'binaries', 'row_count' => 14_900_000],
            (object) ['name' => 'parts', 'row_count' => 98_000_000],
            (object) ['name' => 'missed_parts', 'row_count' => 2],
        ], $tmux->cbpmTableQuery());
    }

    public function test_monitor_uses_the_row_estimate_for_a_large_collections_table(): void
    {
        DB::table('collections')->insert(['id' => 1]);
        DB::table('releases')->insert([['id' => 1], ['id' => 2]]);
        $tmux = $this->getMockBuilder(Tmux::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['proc_query', 'estimatedRowCount'])
            ->getMock();
        $tmux->method('proc_query')->willReturn('SELECT 0 AS active_groups');
        $tmux->expects($this->atLeastOnce())->method('estimatedRowCount')->willReturnCallback(static fn (string $table): ?int => $table === 'collections' ? 14_100_000 : null);
        $monitor = (new ReflectionClass(TmuxMonitorService::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(TmuxMonitorService::class, 'tmux'))->setValue($monitor, $tmux);

        (new ReflectionMethod(TmuxMonitorService::class, 'getTableCounts'))->invoke($monitor);
        $counts = (new ReflectionProperty(TmuxMonitorService::class, 'runVar'))->getValue($monitor)['counts']['now'];

        $this->assertSame(14_100_000, $counts['collections_table']);
        $this->assertSame(2, $counts['releases'], 'Releases stay exact: the monitor shows how many were added.');
    }

    /**
     * @return iterable<string, array{array<string, int>}>
     */
    public static function backfillControls(): iterable
    {
        yield 'collections kill switch' => [['backfill' => 1, 'collections_kill' => 2_000_000]];
        yield 'progressive backfill' => [['backfill' => 4, 'progressive' => 1]];
    }

    /**
     * @param  array<string, int>  $settings
     */
    #[DataProvider('backfillControls')]
    public function test_collections_count_stays_exact_when_it_controls_backfill(array $settings): void
    {
        DB::table('collections')->insert([['id' => 1], ['id' => 2], ['id' => 3]]);
        $tmux = $this->getMockBuilder(Tmux::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['proc_query', 'estimatedRowCount'])
            ->getMock();
        $tmux->method('proc_query')->willReturn('SELECT 0 AS active_groups');
        $estimated = [];
        $tmux->expects($this->atLeastOnce())->method('estimatedRowCount')->willReturnCallback(static function (string $table) use (&$estimated): ?int {
            $estimated[] = $table;

            return 14_100_000;
        });
        $monitor = (new ReflectionClass(TmuxMonitorService::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(TmuxMonitorService::class, 'tmux'))->setValue($monitor, $tmux);
        $state = new ReflectionProperty(TmuxMonitorService::class, 'runVar');
        $state->setValue($monitor, ['settings' => $settings]);

        (new ReflectionMethod(TmuxMonitorService::class, 'getTableCounts'))->invoke($monitor);

        $this->assertSame(3, $state->getValue($monitor)['counts']['now']['collections_table']);
        $this->assertNotContains('collections', $estimated);
    }

    public function test_an_estimated_collections_count_is_made_exact_when_a_backfill_control_is_turned_on(): void
    {
        DB::table('collections')->insert([['id' => 1], ['id' => 2]]);
        $monitor = (new ReflectionClass(TmuxMonitorService::class))->newInstanceWithoutConstructor();
        $state = new ReflectionProperty(TmuxMonitorService::class, 'runVar');
        (new ReflectionProperty(TmuxMonitorService::class, 'collectionsCountIsEstimate'))->setValue($monitor, true);
        $refresh = new ReflectionMethod(TmuxMonitorService::class, 'refreshCollectionCountForBackfillControls');

        $state->setValue($monitor, ['settings' => [], 'counts' => ['now' => ['collections_table' => 14_100_000]]]);
        $this->assertFalse($refresh->invoke($monitor), 'Display-only: the estimate stays.');
        $this->assertSame(14_100_000, $state->getValue($monitor)['counts']['now']['collections_table']);

        $state->setValue($monitor, ['settings' => ['backfill' => 1, 'progressive' => 1], 'counts' => ['now' => ['collections_table' => 14_100_000]]]);
        $this->assertTrue($refresh->invoke($monitor));
        $this->assertSame(2, $state->getValue($monitor)['counts']['now']['collections_table']);
        $this->assertFalse($refresh->invoke($monitor), 'Already exact.');
    }

    public function test_a_failed_exact_collections_count_is_retried_without_stopping_the_monitor(): void
    {
        DB::statement('DROP TABLE collections');
        $monitor = (new ReflectionClass(TmuxMonitorService::class))->newInstanceWithoutConstructor();
        $state = new ReflectionProperty(TmuxMonitorService::class, 'runVar');
        $isEstimate = new ReflectionProperty(TmuxMonitorService::class, 'collectionsCountIsEstimate');
        $isEstimate->setValue($monitor, true);
        $state->setValue($monitor, ['settings' => ['backfill' => 1, 'collections_kill' => 20_000_000], 'counts' => ['now' => ['collections_table' => 14_100_000]]]);

        $this->assertFalse((new ReflectionMethod(TmuxMonitorService::class, 'refreshCollectionCountForBackfillControls'))->invoke($monitor));
        $this->assertTrue($isEstimate->getValue($monitor), 'Still marked as an estimate, so the next loop retries.');

        (new ReflectionMethod(TmuxMonitorService::class, 'setKillswitches'))->invoke($monitor);
        $this->assertTrue($state->getValue($monitor)['killswitch']['coll'], 'Backfill is held until an exact count succeeds.');
    }

    public function test_backfill_controls_need_no_exact_count_while_backfill_does_not_run(): void
    {
        DB::table('collections')->insert(['id' => 1]);
        $tmux = $this->getMockBuilder(Tmux::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['proc_query', 'estimatedRowCount'])
            ->getMock();
        $tmux->method('proc_query')->willReturn('SELECT 0 AS active_groups');
        $tmux->expects($this->atLeastOnce())->method('estimatedRowCount')->willReturn(14_100_000);
        $monitor = (new ReflectionClass(TmuxMonitorService::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(TmuxMonitorService::class, 'tmux'))->setValue($monitor, $tmux);
        $state = new ReflectionProperty(TmuxMonitorService::class, 'runVar');
        $refresh = new ReflectionMethod(TmuxMonitorService::class, 'getTableCounts');

        foreach ([[0, 0], [1, 1], [1, 2]] as [$backfill, $sequential]) {
            $state->setValue($monitor, [
                'constants' => ['sequential' => $sequential],
                'settings' => ['backfill' => $backfill, 'collections_kill' => 2_000_000, 'progressive' => 1],
            ]);
            $refresh->invoke($monitor);

            $this->assertSame(14_100_000, $state->getValue($monitor)['counts']['now']['collections_table'], "backfill {$backfill}, sequential {$sequential}");
        }
    }
}
