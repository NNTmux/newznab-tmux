<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CollectionsReleaseWindowIndexTest extends TestCase
{
    public function test_mariadb_online_index_supports_group_keyset_seek_and_restores_session_lock_timeout(): void
    {
        $host = getenv('TEST_MARIADB_HOST');
        if ($host === false) {
            $this->markTestSkipped('Requires the isolated MariaDB validation service.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.host' => $host,
            'database.connections.mysql.database' => 'cleanup_validation',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => 'isolated-validation']);
        DB::purge();
        DB::reconnect();
        Schema::dropIfExists('collections');
        Schema::create('collections', static function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('groups_id');
            $table->unsignedTinyInteger('filecheck');
            $table->unsignedBigInteger('filesize');
            $table->index(['filecheck', 'filesize', 'groups_id'], 'ix_collections_filecheck_filesize_groups');
            $table->index(['filecheck'], 'ix_collection_filecheck');
        });
        $rows = [];
        for ($id = 1; $id <= 10000; $id++) {
            $rows[] = ['id' => $id, 'groups_id' => $id % 100 === 0 ? 109 : 110, 'filecheck' => 3, 'filesize' => 100];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('collections')->insert($chunk);
        }
        DB::statement('SET SESSION lock_wait_timeout = 19');
        $migration = require database_path('migrations/2026_10_11_181548_add_collections_group_release_window_index.php');
        try {
            $migration->up();
            $migration->up();
            $plan = DB::selectOne('EXPLAIN SELECT id FROM collections WHERE groups_id = 109 AND filecheck = 3 AND id > 5000 ORDER BY id LIMIT 500');
            $this->assertSame('ix_collections_group_filecheck_id', $plan->key);
            $this->assertStringNotContainsString('filesort', $plan->Extra);
            $this->assertLessThan(1000, (int) $plan->rows);
            $globalPlan = DB::selectOne('EXPLAIN SELECT id FROM collections WHERE filecheck = 3 AND id > 5000 ORDER BY id LIMIT 500');
            $this->assertSame('ix_collection_filecheck', $globalPlan->key);
            $this->assertStringNotContainsString('filesort', $globalPlan->Extra);
            $this->assertSame(19, (int) DB::selectOne('SELECT @@SESSION.lock_wait_timeout AS seconds')->seconds);
            $migration->down();
            $migration->down();
            $this->assertFalse(Schema::hasIndex('collections', 'ix_collections_group_filecheck_id'));
            $this->assertSame(10000, DB::table('collections')->count());
        } finally {
            Schema::dropIfExists('collections');
        }
    }

    public function test_group_window_seeks_use_a_covering_index_without_sorting_and_migration_is_reversible(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();
        Schema::create('collections', static function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('groups_id');
            $table->unsignedTinyInteger('filecheck');
        });
        DB::table('collections')->insert([
            ['id' => 1, 'groups_id' => 109, 'filecheck' => 3],
            ['id' => 2, 'groups_id' => 110, 'filecheck' => 3],
            ['id' => 3, 'groups_id' => 109, 'filecheck' => 2],
            ['id' => 4, 'groups_id' => 109, 'filecheck' => 3],
        ]);
        $migration = require database_path('migrations/2026_10_11_181548_add_collections_group_release_window_index.php');
        $migration->up();
        $migration->up();
        $plan = DB::select('EXPLAIN QUERY PLAN SELECT id FROM collections WHERE groups_id = 109 AND filecheck = 3 AND id > 1 ORDER BY id LIMIT 500');
        $details = implode(' ', array_map(static fn (object $row): string => $row->detail, $plan));
        $this->assertStringContainsString('COVERING INDEX ix_collections_group_filecheck_id', $details);
        $this->assertStringNotContainsString('TEMP B-TREE', $details);
        $this->assertSame([4], DB::table('collections')->where('groups_id', 109)->where('filecheck', 3)->where('id', '>', 1)->orderBy('id')->limit(500)->pluck('id')->all());

        $migration->down();
        $migration->down();
        $this->assertFalse(Schema::hasIndex('collections', 'ix_collections_group_filecheck_id'));
        $this->assertSame(4, DB::table('collections')->count());
    }
}
