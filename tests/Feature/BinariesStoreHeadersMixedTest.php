<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\TestBinariesHarness;
use Tests\TestCase;

class BinariesStoreHeadersMixedTest extends TestCase
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
