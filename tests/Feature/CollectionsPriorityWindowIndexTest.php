<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CollectionsPriorityWindowIndexTest extends TestCase
{
    public function test_sqlite_priority_seek_skips_undersized_prefix_and_preserves_older_larger_tail(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();
        $this->assertPrioritySeek(false);
    }

    public function test_mariadb_online_index_supports_priority_seek_and_restores_metadata_lock_timeout(): void
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
        $this->assertPrioritySeek(true);
    }

    private function assertPrioritySeek(bool $mariadb): void
    {
        Schema::dropIfExists('collections');
        Schema::create('collections', static function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('groups_id');
            $table->unsignedTinyInteger('filecheck');
            $table->unsignedBigInteger('filesize');
            $table->unsignedInteger('totalfiles');
            $table->index(['groups_id', 'filecheck', 'id'], 'ix_collections_group_filecheck_id');
        });
        $rows = [];
        for ($id = 1; $id <= 10000; $id++) {
            $rows[] = ['id' => $id, 'groups_id' => 109, 'filecheck' => 3, 'filesize' => 100, 'totalfiles' => 1];
        }
        $rows[] = ['id' => 10001, 'groups_id' => 109, 'filecheck' => 3, 'filesize' => 4000000, 'totalfiles' => 1];
        $rows[] = ['id' => 10002, 'groups_id' => 109, 'filecheck' => 3, 'filesize' => 3000000, 'totalfiles' => 1];
        $rows[] = ['id' => 10003, 'groups_id' => 109, 'filecheck' => 3, 'filesize' => 3000000, 'totalfiles' => 1];
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('collections')->insert($chunk);
        }
        if ($mariadb) {
            DB::statement('SET SESSION lock_wait_timeout = 19');
        }
        $migration = require database_path('migrations/2026_10_11_195234_add_collections_priority_window_index.php');
        try {
            $migration->up();
            $migration->up();
            $sql = 'SELECT id, filesize FROM collections WHERE groups_id = 109 AND filecheck = 3 AND filesize >= 2097152 AND totalfiles >= 1 AND id <= 10002 ORDER BY filesize, id LIMIT 501';
            if ($mariadb) {
                $plan = DB::selectOne('EXPLAIN '.$sql);
                $this->assertSame('ix_collections_group_filecheck_size_id', $plan->key);
                $this->assertStringNotContainsString('filesort', $plan->Extra);
                $this->assertLessThan(10, (int) $plan->rows);
                $this->assertSame(19, (int) DB::selectOne('SELECT @@SESSION.lock_wait_timeout AS seconds')->seconds);
            } else {
                $plan = DB::select('EXPLAIN QUERY PLAN '.$sql);
                $details = implode(' ', array_map(static fn (object $row): string => $row->detail, $plan));
                $this->assertStringContainsString('INDEX ix_collections_group_filecheck_size_id', $details);
                $this->assertStringNotContainsString('TEMP B-TREE', $details);
            }
            $this->assertSame([10002, 10001], array_map(static fn (object $row): int => (int) $row->id, DB::select($sql)));
            $tail = DB::select('SELECT id FROM collections WHERE groups_id = 109 AND filecheck = 3 AND filesize >= 2097152 AND id <= 10002 AND (filesize > 3000000 OR (filesize = 3000000 AND id > 10002)) ORDER BY filesize, id LIMIT 501');
            $this->assertSame([10001], array_map(static fn (object $row): int => (int) $row->id, $tail));
            $migration->down();
            $migration->down();
            $this->assertFalse(Schema::hasIndex('collections', 'ix_collections_group_filecheck_size_id'));
            $this->assertTrue(Schema::hasIndex('collections', 'ix_collections_group_filecheck_id'));
            $this->assertSame(10003, DB::table('collections')->count());
            if ($mariadb) {
                $this->assertSame(19, (int) DB::selectOne('SELECT @@SESSION.lock_wait_timeout AS seconds')->seconds);
            }
        } finally {
            Schema::dropIfExists('collections');
        }
    }
}
