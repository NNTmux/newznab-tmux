<?php

namespace Tests\Feature;

use App\Services\Binaries\CbpOrphanPruner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CbpOrphanPrunerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();

        DB::statement('CREATE TABLE collections (id INTEGER PRIMARY KEY)');
        DB::statement('CREATE TABLE collection_groups (collections_id INTEGER, group_name VARCHAR(255), PRIMARY KEY (collections_id, group_name))');
        DB::statement('CREATE TABLE binaries (id INTEGER PRIMARY KEY, collections_id INTEGER)');
        DB::statement('CREATE TABLE parts (binaries_id INTEGER, partnumber INTEGER, PRIMARY KEY (binaries_id, partnumber))');

        DB::table('collections')->insert([['id' => 1], ['id' => 2]]);
        DB::table('collection_groups')->insert([
            ['collections_id' => 1, 'group_name' => 'alt.one'],
            ['collections_id' => 1, 'group_name' => 'alt.two'],
            ['collections_id' => 3, 'group_name' => 'alt.one'],
            // Stale row far above the live id range: the next collections to
            // reach this id would inherit its group.
            ['collections_id' => 4294891425, 'group_name' => 'alt.stale'],
        ]);
        DB::table('binaries')->insert([
            ['id' => 10, 'collections_id' => 1],
            ['id' => 11, 'collections_id' => 2],
            ['id' => 12, 'collections_id' => 7],
        ]);
        DB::table('parts')->insert([
            ['binaries_id' => 10, 'partnumber' => 1],
            ['binaries_id' => 11, 'partnumber' => 1],
            ['binaries_id' => 12, 'partnumber' => 1],
            ['binaries_id' => 12, 'partnumber' => 2],
            ['binaries_id' => 13, 'partnumber' => 1],
        ]);
    }

    public function test_sweep_removes_only_rows_without_a_parent(): void
    {
        $pruner = new CbpOrphanPruner;

        $this->assertSame(['deleted' => 2, 'cursor' => null], $pruner->sweep('collection_groups', batchSize: 1));
        $this->assertSame(['deleted' => 1, 'cursor' => null], $pruner->sweep('binaries', batchSize: 1));
        $this->assertSame(['deleted' => 3, 'cursor' => null], $pruner->sweep('parts', batchSize: 1));

        $this->assertSame([1, 1], $this->ids('collection_groups', 'collections_id'));
        $this->assertSame([10, 11], $this->ids('binaries', 'id'));
        $this->assertSame([10, 11], $this->ids('parts', 'binaries_id'));
    }

    public function test_incremental_sweep_resumes_from_cached_cursor_until_table_is_done(): void
    {
        Cache::flush();
        $pruner = new CbpOrphanPruner;

        $first = $pruner->sweepIncrementally(batchSize: 1, maxBatchesPerTable: 1);
        // One batch of one key only covers collections_id 1, which is live.
        $this->assertSame(0, $first['collection_groups']);
        $this->assertCount(4, $this->ids('collection_groups', 'collections_id'));

        for ($run = 0; $run < 5; $run++) {
            $pruner->sweepIncrementally(batchSize: 1, maxBatchesPerTable: 1);
        }

        $this->assertSame([1, 1], $this->ids('collection_groups', 'collections_id'));
        $this->assertSame([10, 11], $this->ids('binaries', 'id'));
        $this->assertSame([10, 11], $this->ids('parts', 'binaries_id'));
    }

    public function test_command_prunes_selected_tables(): void
    {
        $this->artisan('cbp:prune-orphans', ['--table' => ['binaries', 'parts'], '--batch' => 2])
            ->expectsOutputToContain('binaries: deleted 1 orphan rows.')
            ->expectsOutputToContain('parts: deleted 3 orphan rows.')
            ->assertSuccessful();

        $this->assertCount(4, $this->ids('collection_groups', 'collections_id'));
    }

    public function test_command_refuses_collection_groups_rebuild_on_sqlite(): void
    {
        $this->artisan('cbp:prune-orphans', ['--rebuild-collection-groups' => true])
            ->expectsOutputToContain('requires MySQL or MariaDB')
            ->assertFailed();
    }

    /**
     * @return list<int>
     */
    private function ids(string $table, string $column): array
    {
        return DB::table($table)->orderBy($column)->pluck($column)->map(fn ($id): int => (int) $id)->all();
    }
}
