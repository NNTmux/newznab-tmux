<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CollectionFileCheckStatus;
use App\Services\Binaries\BinariesConfig;
use App\Services\Binaries\BinaryHandler;
use App\Services\Binaries\CollectionHandler;
use App\Services\Binaries\HeaderStorageService;
use App\Services\Binaries\HeaderStorageTransaction;
use App\Services\CollectionsCleaningService;
use App\Services\ReleaseProcessingService;
use App\Services\Tmux\CollectionReconciliationBarrier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CbpReleaseEligibilityTest extends TestCase
{
    protected bool $ownsTables = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureDatabase();
        $this->travelTo(now()->startOfSecond());
        config(['nntmux.echocli' => false]);
        $this->createTables();
    }

    protected function configureDatabase(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();
    }

    protected function tearDown(): void
    {
        if ($this->ownsTables) {
            foreach (['parts', 'binaries', 'collection_groups', 'collections', 'usenet_groups'] as $table) {
                Schema::dropIfExists($table);
            }
        }
        parent::tearDown();
    }

    public function test_duplicate_ingestion_does_not_postpone_unknown_file_count_collection(): void
    {
        $storage = $this->storage();
        $headers = [$this->header(1, 1), $this->header(2, 2)];
        $group = ['id' => 1, 'name' => 'alt.test'];
        $this->assertSame([], $storage->store($headers, $group));
        $createdAt = now()->subHours(3)->format('Y-m-d H:i:s');
        DB::table('collections')->update(['dateadded' => $createdAt, 'added' => $createdAt, 'last_seen_at' => $createdAt]);

        $this->assertSame([], $storage->store($headers, $group));
        $this->assertSame(0, (int) DB::table('collections')->value('totalfiles'));
        $this->assertGreaterThan($createdAt, DB::table('collections')->value('last_seen_at'));
        $this->process(1);

        $collection = DB::table('collections')->first();
        $this->assertSame(CollectionFileCheckStatus::Sized->value, (int) $collection->filecheck);
        $this->assertSame(1, (int) $collection->totalfiles);
        $this->assertSame(200, (int) $collection->filesize);
        $this->assertSame($createdAt, $collection->dateadded);
        $this->assertSame(2, DB::table('parts')->count());
    }

    public function test_delay_boundary_original_timestamp_precedence_and_group_isolation(): void
    {
        $cutoff = now()->subHours(2);
        $eligible = $this->collection(1, $cutoff->copy()->subSecond()->format('Y-m-d H:i:s'));
        $boundary = $this->collection(1, $cutoff->format('Y-m-d H:i:s'));
        $recent = $this->collection(1, $cutoff->copy()->addSecond()->format('Y-m-d H:i:s'));
        $otherGroup = $this->collection(2, $cutoff->copy()->subHour()->format('Y-m-d H:i:s'));
        DB::table('collections')->where('id', $recent)->update(['added' => now()->subHours(4), 'last_seen_at' => now()->subHours(4)]);

        $this->process(1);

        $this->assertStatus($eligible, CollectionFileCheckStatus::Sized);
        foreach ([$boundary, $recent, $otherGroup] as $id) {
            $this->assertStatus($id, CollectionFileCheckStatus::Default);
        }
        $this->travel(1)->seconds();
        $this->process(1);
        $this->assertStatus($boundary, CollectionFileCheckStatus::Sized);
        $this->assertStatus($recent, CollectionFileCheckStatus::Default);
        $this->assertStatus($otherGroup, CollectionFileCheckStatus::Default);
    }

    public function test_missing_dateadded_falls_back_to_added_and_missing_dates_stay_pending(): void
    {
        $fallback = $this->collection(1, null);
        $unknown = $this->collection(1, null);
        DB::table('collections')->where('id', $fallback)->update(['added' => now()->subHours(3)]);
        DB::table('collections')->where('id', $unknown)->update(['added' => null]);

        $this->process(1);

        $this->assertStatus($fallback, CollectionFileCheckStatus::Sized);
        $this->assertStatus($unknown, CollectionFileCheckStatus::Default);
    }

    public function test_complete_collections_are_ready_immediately(): void
    {
        $storage = $this->storage();
        $headers = [$this->header(1, 1, 'Complete.Release [1/1] '), $this->header(2, 2, 'Complete.Release [1/1] ')];

        $this->assertSame([], $storage->store($headers, ['id' => 1, 'name' => 'alt.test']));

        $this->assertSame(CollectionFileCheckStatus::Sized->value, (int) DB::table('collections')->value('filecheck'));
        $this->assertSame(200, (int) DB::table('collections')->value('filesize'));
    }

    public function test_bounded_pages_preserve_completed_statuses_and_reconcile_all_eligible_collections(): void
    {
        $pending = [];
        foreach (range(1, 5) as $index) {
            $pending[] = $this->collection(1, now()->subHours(3)->format('Y-m-d H:i:s'));
        }
        $inserted = $this->collection(1, now()->subHours(3)->format('Y-m-d H:i:s'));
        DB::table('collections')->where('id', $inserted)->update(['filecheck' => CollectionFileCheckStatus::Inserted->value]);
        $completeParts = $this->collection(1, now()->format('Y-m-d H:i:s'));
        DB::table('collections')->where('id', $completeParts)->update(['filecheck' => CollectionFileCheckStatus::CompleteParts->value]);

        $this->process(null);

        foreach ([...$pending, $completeParts] as $id) {
            $this->assertStatus($id, CollectionFileCheckStatus::Sized);
            $this->assertSame(100, (int) DB::table('collections')->where('id', $id)->value('filesize'));
        }
        $this->assertStatus($inserted, CollectionFileCheckStatus::Inserted);
    }

    public function test_reconcile_repairs_drifted_binary_aggregates_across_chunks(): void
    {
        $collectionId = $this->collection(1, now()->subHours(3)->format('Y-m-d H:i:s'));
        $partial = (int) DB::table('binaries')->where('collections_id', $collectionId)->value('id');
        $complete = DB::table('binaries')->insertGetId(['collections_id' => $collectionId, 'totalparts' => 2]);
        foreach ([1, 2] as $part) {
            DB::table('parts')->insert(['binaries_id' => $complete, 'partnumber' => $part, 'size' => 150, 'number' => 1000 + $part, 'messageid' => '<'.$complete.'-'.$part.'@example.test>']);
        }
        $empty = DB::table('binaries')->insertGetId(['collections_id' => $collectionId, 'totalparts' => 1]);
        DB::table('binaries')->where('collections_id', $collectionId)->update(['currentparts' => 9, 'partsize' => 9, 'partcheck' => 1]);

        $this->process(1);

        $binaries = DB::table('binaries')->where('collections_id', $collectionId)->get()->keyBy('id');
        $this->assertSame([1, 100, 0], [(int) $binaries[$partial]->currentparts, (int) $binaries[$partial]->partsize, (int) $binaries[$partial]->partcheck]);
        $this->assertSame([2, 300, 1], [(int) $binaries[$complete]->currentparts, (int) $binaries[$complete]->partsize, (int) $binaries[$complete]->partcheck]);
        $this->assertSame([0, 0, 0], [(int) $binaries[$empty]->currentparts, (int) $binaries[$empty]->partsize, (int) $binaries[$empty]->partcheck]);
        $this->assertSame(400, (int) DB::table('collections')->where('id', $collectionId)->value('filesize'));
    }

    public function test_reconcile_writes_nothing_when_no_aggregate_drifted(): void
    {
        $first = $this->collection(1, now()->subHours(3)->format('Y-m-d H:i:s'));
        $second = $this->collection(1, now()->subHours(3)->format('Y-m-d H:i:s'));
        $reconciler = new ReleaseProcessingService(binariesConfig: new BinariesConfig(sqlChunkSize: 2, reconcileBatchSize: 2));
        $reconciler->processIncompleteCollections(1);
        $this->assertStatus($first, CollectionFileCheckStatus::CompleteParts);
        $this->assertStatus($second, CollectionFileCheckStatus::CompleteParts);

        $writes = [];
        DB::listen(static function ($query) use (&$writes): void {
            if (preg_match('/^\s*update\b/i', $query->sql) === 1) {
                $writes[] = $query->sql;
            }
        });
        $reads = 0;
        DB::listen(static function ($query) use (&$reads): void {
            $reads += preg_match('/^\s*select\b.*\bfrom\s+[`"]?parts\b/is', $query->sql);
        });
        $reconciler->processIncompleteCollections(1);

        $this->assertGreaterThan(0, $reads, 'The collections were reconciled again.');

        $this->assertSame([], array_values(array_filter($writes, static fn (string $sql): bool => preg_match('/\b(binaries|collections)\b/', $sql) === 1)));
    }

    public function test_header_storage_aggregate_refresh_writes_parts_totals_across_chunks(): void
    {
        $collectionId = $this->collection(1, now()->format('Y-m-d H:i:s'));
        $partial = (int) DB::table('binaries')->where('collections_id', $collectionId)->value('id');
        $complete = DB::table('binaries')->insertGetId(['collections_id' => $collectionId, 'totalparts' => 2]);
        foreach ([1, 2] as $part) {
            DB::table('parts')->insert(['binaries_id' => $complete, 'partnumber' => $part, 'size' => 150, 'number' => 2000 + $part, 'messageid' => '<h'.$complete.'-'.$part.'@example.test>']);
        }
        $third = DB::table('binaries')->insertGetId(['collections_id' => $collectionId, 'totalparts' => 1]);
        DB::table('parts')->insert(['binaries_id' => $third, 'partnumber' => 1, 'size' => 50, 'number' => 3001, 'messageid' => '<h'.$third.'@example.test>']);
        DB::table('collections')->where('id', $collectionId)->update(['totalfiles' => 3, 'filecheck' => CollectionFileCheckStatus::Default->value]);

        $this->assertTrue((new BinaryHandler)->refreshAggregates([$third, $partial, $complete], 2));
        $this->assertTrue((new CollectionHandler(new CollectionsCleaningService))->refreshAggregates([$collectionId], 2));

        $binaries = DB::table('binaries')->where('collections_id', $collectionId)->get()->keyBy('id');
        $this->assertSame([1, 100, 0], [(int) $binaries[$partial]->currentparts, (int) $binaries[$partial]->partsize, (int) $binaries[$partial]->partcheck]);
        $this->assertSame([2, 300, 1], [(int) $binaries[$complete]->currentparts, (int) $binaries[$complete]->partsize, (int) $binaries[$complete]->partcheck]);
        $this->assertSame([1, 50, 1], [(int) $binaries[$third]->currentparts, (int) $binaries[$third]->partsize, (int) $binaries[$third]->partcheck]);
        $this->assertSame(450, (int) DB::table('collections')->where('id', $collectionId)->value('filesize'));
        $this->assertStatus($collectionId, CollectionFileCheckStatus::Default);

        DB::table('parts')->insert(['binaries_id' => $partial, 'partnumber' => 2, 'size' => 100, 'number' => 1999, 'messageid' => '<h'.$partial.'-2@example.test>']);
        (new BinaryHandler)->refreshAggregates([$partial], 2);
        (new CollectionHandler(new CollectionsCleaningService))->refreshAggregates([$collectionId], 2);
        $this->assertStatus($collectionId, CollectionFileCheckStatus::Sized);
        $this->assertSame(550, (int) DB::table('collections')->where('id', $collectionId)->value('filesize'));
    }

    public function test_header_storage_transaction_reads_committed_rows(): void
    {
        $connection = DB::getDefaultConnection();
        if (! \in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Isolation levels apply to MariaDB.');
        }
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        config(["database.connections.{$connection}_other" => config("database.connections.{$connection}")]);

        $transaction = new HeaderStorageTransaction(new CollectionHandler(new CollectionsCleaningService), new BinaryHandler);
        $transaction->begin();
        $before = DB::table('collections')->count();
        DB::connection("{$connection}_other")->table('collections')->insert(['groups_id' => 1]);
        $after = DB::table('collections')->count();
        $transaction->finish();
        DB::purge("{$connection}_other");

        $this->assertSame($before + 1, $after, 'Rows other workers commit are visible inside the transaction.');
    }

    public function test_aggregate_refreshes_lock_their_rows_before_reading_totals(): void
    {
        if (! \in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Row locks apply to MariaDB.');
        }
        $collectionId = $this->collection(1, now()->format('Y-m-d H:i:s'));
        $binaryId = (int) DB::table('binaries')->where('collections_id', $collectionId)->value('id');
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        (new BinaryHandler)->refreshAggregates([$binaryId]);
        (new CollectionHandler(new CollectionsCleaningService))->refreshAggregates([$collectionId]);

        // A concurrent writer to the same row must commit before the totals are read,
        // or its parts are missing from the totals written back.
        $position = static function (string $pattern) use ($queries): int {
            foreach ($queries as $index => $sql) {
                if (preg_match($pattern, $sql) === 1) {
                    return $index;
                }
            }

            return PHP_INT_MAX;
        };
        $this->assertLessThan($position('/from `parts`/'), $position('/from `binaries`.*for update/'));
        $this->assertLessThan($position('/count\(\*\) as currentfiles/'), $position('/from `collections`.*for update/'));
    }

    public function test_unwanted_collection_cleanup_deletes_every_batch_without_sorting(): void
    {
        Schema::create('usenet_groups', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->bigInteger('minsizetoformrelease')->default(0);
            $table->integer('minfilestoformrelease')->default(0);
        });
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.binaries.test', 'minsizetoformrelease' => 1000]);
        if (DB::getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('REGEXP', static fn (string $pattern, ?string $value): int => (int) preg_match('~'.$pattern.'~i', (string) $value));
        }
        $rows = [];
        foreach (range(1, 501) as $i) {
            $rows[] = ['groups_id' => 1, 'filecheck' => CollectionFileCheckStatus::Sized->value, 'filesize' => $i, 'totalfiles' => 2];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('collections')->insert($chunk);
        }
        $kept = DB::table('collections')->insertGetId(['groups_id' => 1, 'filecheck' => CollectionFileCheckStatus::Sized->value, 'filesize' => 104857600, 'totalfiles' => 2]);
        $selects = [];
        DB::listen(static function ($query) use (&$selects): void {
            if (preg_match('/^\s*select\b.*\bfrom\s+[`"]?collections\b.*\blimit\b/is', $query->sql) === 1) {
                $selects[] = strtolower($query->sql);
            }
        });

        (new ReleaseProcessingService)->deleteUnwantedCollections(1);

        $this->assertSame([$kept], DB::table('collections')->pluck('id')->map(static fn ($id): int => (int) $id)->all(), 'All 501 small collections are deleted across batches.');
        $this->assertNotEmpty($selects);
        foreach ($selects as $sql) {
            $this->assertStringNotContainsString('order by', $sql);
        }
    }

    public function test_stuck_collection_cleanup_matches_the_coalesce_rule_without_sorting(): void
    {
        $cutoff = now()->subHours(48);
        $old = $cutoff->copy()->subHour()->format('Y-m-d H:i:s');
        $new = $cutoff->copy()->addHour()->format('Y-m-d H:i:s');
        $row = static fn (int $group, int $filecheck, ?string $seen, ?string $dateAdded, ?string $added): int => DB::table('collections')->insertGetId([
            'groups_id' => $group, 'filecheck' => $filecheck, 'last_seen_at' => $seen, 'dateadded' => $dateAdded, 'added' => $added,
        ]);
        $deleted = [
            'last seen long ago' => $row(1, 0, $old, $new, $new),
            'never seen, added long ago' => $row(1, 1, null, $old, $new),
            'never seen, only the original timestamp' => $row(1, 15, null, null, $old),
        ];
        $kept = [
            'seen recently' => $row(1, 0, $new, $old, $old),
            'never seen, dateadded recent' => $row(1, 0, null, $new, $old),
            'already sized' => $row(1, CollectionFileCheckStatus::Sized->value, $old, $old, $old),
            'another group' => $row(2, 0, $old, $old, $old),
        ];
        $selects = [];
        DB::listen(static function ($query) use (&$selects): void {
            if (preg_match('/^\s*select\b.*\bfrom\s+[`"]?collections\b.*\blimit\b/is', $query->sql) === 1) {
                $selects[] = strtolower($query->sql);
            }
        });

        $service = new ReleaseProcessingService;
        (new \ReflectionMethod($service, 'deleteStuckCollectionBatch'))->invoke($service, 1, $cutoff);

        $remaining = DB::table('collections')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        foreach ($deleted as $case => $id) {
            $this->assertNotContains($id, $remaining, $case);
        }
        foreach ($kept as $case => $id) {
            $this->assertContains($id, $remaining, $case);
        }
        $this->assertNotEmpty($selects);
        foreach ($selects as $sql) {
            $this->assertStringNotContainsString('order by', $sql);
            $this->assertStringNotContainsString('coalesce(last_seen_at', $sql);
        }
    }

    public function test_reconciliation_waits_for_downtime_compensation_before_stale_selection(): void
    {
        $waiting = $this->collection(1, now()->subHours(3)->format('Y-m-d H:i:s'));
        $this->app->instance(CollectionReconciliationBarrier::class, new class($waiting) implements CollectionReconciliationBarrier
        {
            public function __construct(private readonly int $collectionId) {}

            public function withReconciliationBarrier(int $delayHours, callable $work): mixed
            {
                DB::table('collections')->where('id', $this->collectionId)->update(['dateadded' => now()]);

                return $work();
            }
        });

        $this->process(1);

        $this->assertStatus($waiting, CollectionFileCheckStatus::Default);
        $this->assertSame(2, (int) DB::table('collections')->where('id', $waiting)->value('totalfiles'));
        $this->assertSame(1, DB::table('parts')->count());
    }

    public function test_stuck_cleanup_waits_for_downtime_compensation_before_deletion(): void
    {
        $waiting = $this->collection(1, now()->subDays(3)->format('Y-m-d H:i:s'));
        DB::table('collections')->where('id', $waiting)->update(['last_seen_at' => null]);
        $this->app->instance(CollectionReconciliationBarrier::class, new class($waiting) implements CollectionReconciliationBarrier
        {
            public function __construct(private readonly int $collectionId) {}

            public function withReconciliationBarrier(int $delayHours, callable $work): mixed
            {
                DB::table('collections')->where('id', $this->collectionId)->update(['dateadded' => now()]);

                return $work();
            }
        });

        $service = new ReleaseProcessingService;
        (new \ReflectionMethod($service, 'processStuckCollections'))->invoke($service, 1);

        $this->assertSame(1, DB::table('collections')->where('id', $waiting)->count());
        $this->assertStatus($waiting, CollectionFileCheckStatus::Default);
        $this->assertSame(1, DB::table('binaries')->count());
        $this->assertSame(1, DB::table('parts')->count());
    }

    private function process(?int $groupId): void
    {
        $processor = new ReleaseProcessingService(binariesConfig: new BinariesConfig(sqlChunkSize: 2, reconcileBatchSize: 2));
        $processor->processIncompleteCollections($groupId);
        $processor->processCollectionSizes($groupId);
    }

    private function assertStatus(int $collectionId, CollectionFileCheckStatus $status): void
    {
        $this->assertSame($status->value, (int) DB::table('collections')->where('id', $collectionId)->value('filecheck'));
    }

    private function collection(int $groupId, ?string $createdAt): int
    {
        $id = DB::table('collections')->insertGetId([
            'groups_id' => $groupId,
            'dateadded' => $createdAt,
            'added' => $createdAt,
            'last_seen_at' => now(),
            'totalfiles' => 2,
        ]);
        $binaryId = DB::table('binaries')->insertGetId(['collections_id' => $id, 'totalparts' => 2]);
        DB::table('parts')->insert([
            'binaries_id' => $binaryId, 'partnumber' => 1, 'size' => 100,
            'number' => $binaryId, 'messageid' => '<'.$binaryId.'@example.test>',
        ]);

        return $id;
    }

    private function storage(): HeaderStorageService
    {
        return new HeaderStorageService(new CollectionHandler(new class extends CollectionsCleaningService
        {
            public function collectionsCleaner(string $subject, string $groupName = ''): array
            {
                return ['id' => 0, 'name' => $subject];
            }
        }), config: new BinariesConfig(headerChunkSize: 2, sqlChunkSize: 2));
    }

    /** @return array<string, mixed> */
    private function header(int $number, int $part, string $subject = 'Unknown.Count.Release'): array
    {
        return [
            'Number' => $number, 'From' => 'poster@example.test', 'Date' => now()->timestamp,
            'Message-ID' => '<'.$number.'@example.test>', 'Bytes' => 100, 'Xref' => '',
            'matches' => [$subject.' ('.$part.'/2)', $subject, $part, 2],
        ];
    }

    private function createTables(): void
    {
        $this->ownsTables = true;
        Schema::create('collections', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('subject')->default('');
            $table->string('fromname')->default('');
            $table->dateTime('date')->nullable();
            $table->string('xref')->default('');
            $table->unsignedInteger('groups_id');
            $table->unsignedInteger('totalfiles')->default(0);
            $table->binary('collectionhash', 20, true)->nullable()->unique();
            $table->integer('collection_regexes_id')->default(0);
            $table->dateTime('dateadded')->nullable();
            $table->dateTime('added')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->integer('filecheck')->default(0);
            $table->unsignedBigInteger('filesize')->default(0);
            $table->string('noise')->default('');
        });
        Schema::create('collection_groups', function (Blueprint $table): void {
            $table->unsignedInteger('collections_id');
            $table->string('group_name');
            $table->unique(['collections_id', 'group_name']);
        });
        Schema::create('binaries', function (Blueprint $table): void {
            $table->increments('id');
            $table->binary('binaryhash', 16, true)->nullable();
            $table->string('name')->default('');
            $table->unsignedInteger('collections_id');
            $table->integer('totalparts')->default(0);
            $table->integer('currentparts')->default(0);
            $table->integer('filenumber')->default(0);
            $table->unsignedBigInteger('partsize')->default(0);
            $table->integer('partcheck')->default(0);
            $table->unique(['collections_id', 'binaryhash']);
        });
        Schema::create('parts', function (Blueprint $table): void {
            $table->unsignedInteger('binaries_id');
            $table->unsignedBigInteger('number');
            $table->string('messageid');
            $table->unsignedInteger('partnumber');
            $table->unsignedInteger('size');
            $table->primary(['binaries_id', 'partnumber']);
        });
    }
}
