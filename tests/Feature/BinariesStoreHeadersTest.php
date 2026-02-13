<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\TestBinariesHarness;
use Tests\TestCase;

class BinariesStoreHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        \Illuminate\Support\Facades\DB::purge();
        \Illuminate\Support\Facades\DB::reconnect();

        // Minimal tables.
        DB::statement('CREATE TABLE settings (
            section TEXT NULL,
            subsection TEXT NULL,
            name TEXT PRIMARY KEY,
            value TEXT NULL,
            hint TEXT NULL,
            setting TEXT NULL
        )');
        // Seed the settings queried in Binaries constructor.
        $defaults = [
            'maxmssgs' => '20000',
            'partrepair' => '1',
            'newgroupscanmethod' => '0',
            'newgroupmsgstoscan' => '50000',
            'newgroupdaystoscan' => '3',
            'maxpartrepair' => '15000',
            'partrepairmaxtries' => '3',
        ];
        foreach ($defaults as $k => $v) {
            DB::table('settings')->insert(['name' => $k, 'value' => $v]);
        }

        DB::statement('CREATE TABLE collections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject VARCHAR(255),
            fromname VARCHAR(255),
            date DATETIME NULL,
            xref TEXT DEFAULT "",
            groups_id INT,
            totalfiles INT,
            collectionhash VARCHAR(40) UNIQUE,
            collection_regexes_id INT,
            dateadded DATETIME NULL,
            noise VARCHAR(64) DEFAULT ""
        )');

        DB::statement('CREATE TABLE binaries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            binaryhash BLOB,
            name VARCHAR(255),
            collections_id INT,
            totalparts INT,
            currentparts INT,
            filenumber INT,
            partsize INT,
            UNIQUE(binaryhash, collections_id)
        )');

        DB::statement('CREATE TABLE parts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            binaries_id INT,
            number INT,
            messageid VARCHAR(255),
            partnumber INT,
            size INT,
            UNIQUE(number)
        )');

        DB::statement('CREATE TABLE missed_parts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            numberid INT,
            groups_id INT,
            attempts INT DEFAULT 0,
            UNIQUE(numberid, groups_id)
        )');

        DB::statement('CREATE TABLE collection_regexes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_regex VARCHAR(255),
            regex VARCHAR(255),
            status INT DEFAULT 1,
            ordinal INT DEFAULT 0
        )');
    }

    private function makeHeader(int $articleNumber, int $partNumber, int $totalParts, int $bytes = 100): array
    {
        $subjectBase = 'Example.File.Name';

        return [
            'Number' => $articleNumber,
            'Subject' => $subjectBase.' ('.$partNumber.'/'.$totalParts.')',
            'From' => 'poster@example.com',
            'Date' => time(),
            'Bytes' => $bytes,
            'Message-ID' => '<msg'.$articleNumber.'@example.com>',
            'Xref' => 'news.example.com group:'.$articleNumber,
            'matches' => [
                0 => $subjectBase.' ('.$partNumber.'/'.$totalParts.')',
                1 => $subjectBase,
                2 => $partNumber,
                3 => $totalParts,
            ],
        ];
    }

    public function test_duplicate_collection_and_binary_reuse(): void
    {
        $harness = new TestBinariesHarness;

        $headers = [
            $this->makeHeader(1001, 1, 2, 150),
            $this->makeHeader(1002, 2, 2, 175),
        ];

        $harness->publicStoreHeaders($headers);

        $collections = DB::table('collections')->count();
        $binaries = DB::table('binaries')->count();
        $parts = DB::table('parts')->count();
        $binary = DB::table('binaries')->first();

        $this->assertEquals(1, $collections, 'Should have reused collection');
        $this->assertEquals(1, $binaries, 'Should have reused binary');
        $this->assertEquals(2, $parts, 'Two part rows expected');
        $this->assertEquals(325, $binary->partsize, 'Binary partsize should be sum of part sizes');
        $this->assertEquals(2, $binary->currentparts, 'Binary currentparts should reflect both parts');
    }

    public function test_raw_message_id_stored_unmodified(): void
    {
        $harness = new TestBinariesHarness;
        $headers = [$this->makeHeader(3001, 1, 1, 123)];
        $harness->publicStoreHeaders($headers);
        $stored = DB::table('parts')->where('number', 3001)->value('messageid');
        $this->assertSame('<msg3001@example.com>', $stored, 'Message-ID should be stored with angle brackets intact');
    }

    public function test_rollback_on_parts_insert_failure_and_part_repair_queue(): void
    {
        $harness = new TestBinariesHarness;
        $group = ['id' => 2, 'name' => 'alt.rollback'];

        config(['nntmux.parts_chunk_size' => 2]); // force flush earlier

        $headers = [
            $this->makeHeader(2001, 1, 3, 111),
            $this->makeHeader(2002, 2, 3, 222),
            $this->makeHeader(2003, 3, 3, 333),
        ];

        $harness->failPartsInsert = true; // Force flushPartsChunk failure
        $harness->simulateScan($headers, $group, true);

        $this->assertEquals(0, DB::table('collections')->count(), 'Collections should rollback');
        $this->assertEquals(0, DB::table('binaries')->count(), 'Binaries should rollback');
        $this->assertEquals(0, DB::table('parts')->count(), 'Parts should rollback');

        $missed = DB::table('missed_parts')->pluck('numberid')->toArray();
        sort($missed);
        $this->assertEquals([2001, 2002, 2003], $missed, 'All headers should be marked missed after rollback');
    }

    public function test_mixed_success_then_failure_rolls_back_everything(): void
    {
        $harness = new TestBinariesHarness;
        $group = ['id' => 3, 'name' => 'alt.mixed'];

        // Chunk of 2: first flush succeeds, second flush fails
        config(['nntmux.parts_chunk_size' => 2]);

        $headers = [
            $this->makeHeader(4001, 1, 5, 101),
            $this->makeHeader(4002, 2, 5, 102), // triggers first successful flush
            $this->makeHeader(4003, 3, 5, 103),
            $this->makeHeader(4004, 4, 5, 104), // triggers failing flush
            $this->makeHeader(4005, 5, 5, 105), // buffered but not flushed
        ];

        $harness->failPartsInsert = true;
        $harness->failAfterFlushCount = 1; // fail on second flush
        $harness->simulateScan($headers, $group, true);

        $this->assertEquals(0, DB::table('collections')->count(), 'Collections should rollback after mixed flush');
        $this->assertEquals(0, DB::table('binaries')->count(), 'Binaries should rollback after mixed flush');
        $this->assertEquals(0, DB::table('parts')->count(), 'Parts should rollback after mixed flush');

        $missed = DB::table('missed_parts')->pluck('numberid')->toArray();
        sort($missed);
        $this->assertEquals([4001, 4002, 4003, 4004, 4005], $missed, 'All headers should be marked missed after mixed rollback');
    }
}
