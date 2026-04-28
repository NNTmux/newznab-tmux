<?php

namespace Tests\Feature;

use App\Services\Binaries\MissedPartHandler;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MissedPartHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();

        DB::statement('CREATE TABLE missed_parts (
            id INTEGER PRIMARY KEY,
            numberid INT,
            groups_id INT,
            attempts INT DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            UNIQUE(numberid, groups_id)
        )');
    }

    public function test_add_missing_parts_inserts_and_increments_duplicates(): void
    {
        $handler = new MissedPartHandler(partRepairLimit: 10, partRepairMaxTries: 3);

        $handler->addMissingParts([100, 101, 102], 7);
        $handler->addMissingParts([101, 103], 7);
        $handler->addMissingParts([101], 8);

        $this->assertSame(4, DB::table('missed_parts')->where('groups_id', 7)->count());
        $this->assertSame(1, (int) DB::table('missed_parts')->where(['groups_id' => 7, 'numberid' => 100])->value('attempts'));
        $this->assertSame(2, (int) DB::table('missed_parts')->where(['groups_id' => 7, 'numberid' => 101])->value('attempts'));
        $this->assertSame(1, (int) DB::table('missed_parts')->where(['groups_id' => 8, 'numberid' => 101])->value('attempts'));
    }

    public function test_get_missing_parts_applies_attempt_limit_order_and_repair_limit(): void
    {
        $handler = new MissedPartHandler(partRepairLimit: 2, partRepairMaxTries: 3);
        DB::table('missed_parts')->insert([
            ['numberid' => 300, 'groups_id' => 9, 'attempts' => 1],
            ['numberid' => 100, 'groups_id' => 9, 'attempts' => 1],
            ['numberid' => 200, 'groups_id' => 9, 'attempts' => 3],
            ['numberid' => 50, 'groups_id' => 8, 'attempts' => 1],
        ]);

        $parts = $handler->getMissingParts(9);

        $this->assertCount(2, $parts);
        $this->assertSame([100, 300], array_map(static fn (object $part): int => (int) $part->numberid, $parts));
    }

    public function test_remove_increment_count_and_cleanup_paths(): void
    {
        $handler = new MissedPartHandler(partRepairLimit: 10, partRepairMaxTries: 3);
        DB::table('missed_parts')->insert([
            ['numberid' => 10, 'groups_id' => 1, 'attempts' => 1],
            ['numberid' => 20, 'groups_id' => 1, 'attempts' => 1],
            ['numberid' => 30, 'groups_id' => 1, 'attempts' => 2],
            ['numberid' => 20, 'groups_id' => 2, 'attempts' => 1],
        ]);

        $handler->removeRepairedParts([20, 999], 1);
        $this->assertFalse(DB::table('missed_parts')->where(['groups_id' => 1, 'numberid' => 20])->exists());
        $this->assertTrue(DB::table('missed_parts')->where(['groups_id' => 2, 'numberid' => 20])->exists());

        $handler->incrementAttempts(1, 30);
        $this->assertSame(2, (int) DB::table('missed_parts')->where(['groups_id' => 1, 'numberid' => 10])->value('attempts'));
        $this->assertSame(3, (int) DB::table('missed_parts')->where(['groups_id' => 1, 'numberid' => 30])->value('attempts'));
        $this->assertSame(2, $handler->getCount(1, 30));

        $handler->cleanupExhaustedParts(1);
        $this->assertSame([10], DB::table('missed_parts')->where('groups_id', 1)->pluck('numberid')->all());
    }

    public function test_increment_range_attempts_handles_single_and_multi_article_ranges(): void
    {
        $handler = new MissedPartHandler(partRepairLimit: 10, partRepairMaxTries: 3);
        DB::table('missed_parts')->insert([
            ['numberid' => 10, 'groups_id' => 1, 'attempts' => 0],
            ['numberid' => 11, 'groups_id' => 1, 'attempts' => 0],
            ['numberid' => 12, 'groups_id' => 1, 'attempts' => 0],
            ['numberid' => 12, 'groups_id' => 2, 'attempts' => 0],
        ]);

        $handler->incrementRangeAttempts(1, 10, 10);
        $handler->incrementRangeAttempts(1, 11, 12);

        $this->assertSame(1, (int) DB::table('missed_parts')->where(['groups_id' => 1, 'numberid' => 10])->value('attempts'));
        $this->assertSame(1, (int) DB::table('missed_parts')->where(['groups_id' => 1, 'numberid' => 11])->value('attempts'));
        $this->assertSame(1, (int) DB::table('missed_parts')->where(['groups_id' => 1, 'numberid' => 12])->value('attempts'));
        $this->assertSame(0, (int) DB::table('missed_parts')->where(['groups_id' => 2, 'numberid' => 12])->value('attempts'));
    }
}

