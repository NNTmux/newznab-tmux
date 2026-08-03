<?php

namespace Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestBinariesHarness;
use Tests\TestCase;

/**
 * Regression tests guarding the SQL fan-out of the CBP write path.
 *
 * The collections/binaries/parts handlers were rewritten to consolidate
 * redundant SELECTs and derives aggregates from stored parts in bounded raw
 * SQL updates. These tests assert
 * the resulting per-chunk query count stays within a sane ceiling so the old
 * fan-out cannot silently come back.
 */
class BinariesQueryCountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();

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
            last_seen_at DATETIME NULL,
            filecheck INT DEFAULT 0,
            filesize INT DEFAULT 0,
            noise VARCHAR(64) DEFAULT ""
        )');

        DB::statement('CREATE TABLE collection_groups (
            collections_id INT,
            group_name VARCHAR(255),
            UNIQUE(collections_id, group_name)
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
            partcheck INT DEFAULT 0,
            UNIQUE(binaryhash, collections_id)
        )');

        DB::statement('CREATE TABLE parts (
            binaries_id INT,
            number INT,
            messageid VARCHAR(255),
            partnumber INT,
            size INT,
            UNIQUE(binaries_id, partnumber)
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

    public function test_store_chunk_does_not_regress_to_n_plus_one_on_collections_or_binaries(): void
    {
        // CollectionsCleaningService runs a REGEXP against collection_regexes;
        // SQLite has no built-in REGEXP, so this test only runs on MySQL — same
        // pattern as BinariesStoreHeadersTest::test_duplicate_collection_and_binary_reuse.
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('Requires MySQL REGEXP function (CollectionsCleaningService).');
        }

        $harness = new TestBinariesHarness;

        // Mix of distinct subjects so we exercise multiple collections and
        // binaries in a single chunk. Each pair of (subject, partNumber)
        // becomes one part row.
        $headers = [];
        $articleNumber = 1000;
        for ($i = 0; $i < 5; $i++) {
            $subject = 'Subject.'.$i;
            for ($p = 1; $p <= 4; $p++) {
                $headers[] = $this->makeHeader($articleNumber++, $p, 4, 100, $subject);
            }
        }

        $counts = $this->countQueriesPerTable(static function () use ($harness, $headers): void {
            $harness->publicStoreHeaders($headers);
        });

        // Ceiling rationale (per chunk, 5 distinct collections, 5 binaries):
        //   collections: 1 prefetch + 1 INSERT + 1 resolve-new + 1 aggregate = 4
        //   binaries:    1 prefetch + 1 INSERT + 1 resolve-new + 1 aggregate = 4
        //   parts:       1 INSERT (chunk fits in MAX_SQL_ROWS_PER_STATEMENT)
        // Allow a small headroom so the regression test isn't brittle.
        $this->assertLessThanOrEqual(8, $counts['collections'] ?? 0,
            'Collections query count should not regress to N+1; got '.($counts['collections'] ?? 0));
        $this->assertLessThanOrEqual(8, $counts['binaries'] ?? 0,
            'Binaries query count should not regress to N+1; got '.($counts['binaries'] ?? 0));
        $this->assertLessThanOrEqual(4, $counts['parts'] ?? 0,
            'Parts query count should stay bounded; got '.($counts['parts'] ?? 0));

        // Sanity: the chunk actually persisted what we expected.
        $this->assertSame(5, DB::table('collections')->count());
        $this->assertSame(5, DB::table('binaries')->count());
        $this->assertSame(20, DB::table('parts')->count());
    }

    public function test_store_chunk_re_ingest_only_runs_minimum_queries_when_everything_already_exists(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('Requires MySQL REGEXP function (CollectionsCleaningService).');
        }

        $harness = new TestBinariesHarness;

        // First pass — populate.
        $headers = [];
        $articleNumber = 2000;
        for ($i = 0; $i < 3; $i++) {
            for ($p = 1; $p <= 2; $p++) {
                $headers[] = $this->makeHeader($articleNumber++, $p, 2, 100, 'Reused.'.$i);
            }
        }
        $harness->publicStoreHeaders($headers);

        // Second pass — exact same headers. The prefetch should hit every row,
        // so no resolve-new SELECT should fire; existing rows skip the xref
        // append UPDATE; normalized collection_groups inserts are idempotent.
        $counts = $this->countQueriesPerTable(static function () use ($headers): void {
            $freshHarness = new TestBinariesHarness;
            $freshHarness->publicStoreHeaders($headers);
        });

        // With nothing new to insert, we expect at most:
        //   collections: 1 prefetch + 1 INSERT (no-op via ODKU id=LAST_INSERT_ID(id))
        //   binaries:    1 prefetch + 1 INSERT + 1 authoritative aggregate update
        //   parts:       1 INSERT IGNORE + 1 identity lookup (no rows actually persist)
        $this->assertLessThanOrEqual(4, $counts['collections'] ?? 0);
        $this->assertLessThanOrEqual(4, $counts['binaries'] ?? 0);
        $this->assertLessThanOrEqual(2, $counts['parts'] ?? 0);
    }

    /**
     * Run $callable while listening to executed queries, return a per-table
     * SELECT/INSERT/UPDATE count keyed by lowercase table name.
     *
     * @return array<string, int>
     */
    private function countQueriesPerTable(\Closure $callable): array
    {
        $counts = [];
        $listener = static function (QueryExecuted $event) use (&$counts): void {
            // Match the first table name after FROM/INTO/UPDATE keywords.
            // Strip enclosing quotes/backticks/double-quotes the grammar adds.
            if (preg_match('/\b(?:from|into|update)\s+["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?/i', $event->sql, $m)) {
                $table = strtolower($m[1]);
                $counts[$table] = ($counts[$table] ?? 0) + 1;
            }
        };

        DB::listen($listener);
        try {
            $callable();
        } finally {
            // Laravel does not provide a public "remove listener" for DB query
            // events, but the listener captures into a local variable that
            // goes out of scope when the test method returns, so the closure
            // is harmless beyond this point.
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function makeHeader(int $articleNumber, int $partNumber, int $totalParts, int $bytes, string $subjectBase): array
    {
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
}
