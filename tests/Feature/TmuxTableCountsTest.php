<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Tmux\Tmux;
use App\Services\Tmux\TmuxMonitorService;
use Illuminate\Support\Facades\DB;
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
}
