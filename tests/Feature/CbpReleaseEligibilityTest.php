<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CollectionFileCheckStatus;
use App\Services\Binaries\BinariesConfig;
use App\Services\Binaries\CollectionHandler;
use App\Services\Binaries\HeaderStorageService;
use App\Services\CollectionsCleaningService;
use App\Services\ReleaseProcessingService;
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
            foreach (['parts', 'binaries', 'collection_groups', 'collections'] as $table) {
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

    public function test_incomplete_nzb_split_set_is_held_past_the_delay(): void
    {
        $id = $this->nzbSplitCollection(declaredFiles: 5, presentFiles: 3, createdAt: now()->subHours(3)->format('Y-m-d H:i:s'));

        $this->process(1);

        $this->assertStatus($id, CollectionFileCheckStatus::Default);
        $this->assertSame(5, (int) DB::table('collections')->where('id', $id)->value('totalfiles'));
    }

    public function test_complete_nzb_split_set_becomes_ready(): void
    {
        $id = $this->nzbSplitCollection(declaredFiles: 5, presentFiles: 5, createdAt: now()->subHours(3)->format('Y-m-d H:i:s'));

        $this->process(1);

        $this->assertStatus($id, CollectionFileCheckStatus::Sized);
    }

    public function test_incomplete_nzb_split_set_is_deleted_at_the_collection_timeout(): void
    {
        $stale = now()->subDays(10)->format('Y-m-d H:i:s');
        $id = $this->nzbSplitCollection(declaredFiles: 5, presentFiles: 3, createdAt: $stale);
        DB::table('collections')->where('id', $id)->update(['last_seen_at' => $stale]);

        $this->process(1);

        $this->assertFalse(DB::table('collections')->where('id', $id)->exists());
    }

    public function test_stale_collection_above_a_younger_reset_collection_is_still_reconciled(): void
    {
        // tmux:start --reset-collections moves dateadded forward on old rows,
        // so a young row can sit below a stale one in id order.
        $reset = $this->collection(1, now()->format('Y-m-d H:i:s'));
        $stale = $this->collection(1, now()->subHours(3)->format('Y-m-d H:i:s'));
        $this->assertGreaterThan($reset, $stale);

        $this->process(1);

        $this->assertStatus($stale, CollectionFileCheckStatus::Sized);
        $this->assertStatus($reset, CollectionFileCheckStatus::Default);
    }

    public function test_stuck_collections_use_last_seen_then_dateadded_then_added(): void
    {
        $old = now()->subDays(10)->format('Y-m-d H:i:s');
        $recent = now()->format('Y-m-d H:i:s');
        $cases = [
            'idle' => [$old, $recent, $recent, false],
            'active' => [$recent, $old, $old, true],
            'unseen_old' => [null, $old, $recent, false],
            'legacy_old' => [null, null, $old, false],
            'legacy_recent' => [null, null, $recent, true],
        ];
        $ids = [];
        foreach ($cases as $name => [$lastSeenAt, $dateadded, $added]) {
            $ids[$name] = $this->collection(1, null);
            DB::table('collections')->where('id', $ids[$name])->update([
                'last_seen_at' => $lastSeenAt, 'dateadded' => $dateadded, 'added' => $added,
            ]);
        }

        (new ReleaseProcessingService(binariesConfig: new BinariesConfig(reconcileBatchSize: 2)))
            ->processIncompleteCollections(1);

        foreach ($cases as $name => [, , , $kept]) {
            $this->assertSame($kept, DB::table('collections')->where('id', $ids[$name])->exists(), $name);
        }
    }

    public function test_incomplete_ordinary_set_still_takes_the_stale_path(): void
    {
        $id = $this->nzbSplitCollection(
            declaredFiles: 5,
            presentFiles: 3,
            createdAt: now()->subHours(3)->format('Y-m-d H:i:s'),
            subject: '[1/5] "Some.Show.S01E01.1080p.WEB-DL-GRP.part1.rar" yEnc',
        );

        $this->process(1);

        $this->assertStatus($id, CollectionFileCheckStatus::Sized);
        $this->assertSame(3, (int) DB::table('collections')->where('id', $id)->value('totalfiles'));
    }

    public function test_nzb_split_hold_can_be_disabled(): void
    {
        config(['nntmux.nzbsplit_require_complete_sets' => false]);
        $id = $this->nzbSplitCollection(declaredFiles: 5, presentFiles: 3, createdAt: now()->subHours(3)->format('Y-m-d H:i:s'));

        $this->process(1);

        $this->assertStatus($id, CollectionFileCheckStatus::Sized);
    }

    public function test_nzb_split_set_without_declared_file_count_takes_the_stale_path(): void
    {
        $id = $this->nzbSplitCollection(declaredFiles: 0, presentFiles: 3, createdAt: now()->subHours(3)->format('Y-m-d H:i:s'));

        $this->process(1);

        $this->assertStatus($id, CollectionFileCheckStatus::Sized);
    }

    public function test_legacy_incomplete_statuses_take_the_stale_path(): void
    {
        $ids = [];
        foreach ([CollectionFileCheckStatus::LegacyIncomplete, CollectionFileCheckStatus::TempComplete, CollectionFileCheckStatus::ZeroPart] as $status) {
            $id = $this->collection(1, now()->subHours(3)->format('Y-m-d H:i:s'));
            DB::table('collections')->where('id', $id)->update(['filecheck' => $status->value, 'totalfiles' => 5]);
            $ids[] = $id;
        }

        $this->process(1);

        foreach ($ids as $id) {
            $this->assertStatus($id, CollectionFileCheckStatus::Sized);
            $this->assertSame(1, (int) DB::table('collections')->where('id', $id)->value('totalfiles'));
        }
    }

    public function test_sized_collection_without_bytes_is_deleted_as_unwanted(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // The par2-only filter uses REGEXP; SQLite evaluates `X REGEXP Y` as regexp(Y, X).
            DB::connection()->getPdo()->sqliteCreateFunction(
                'REGEXP',
                static fn (?string $pattern, ?string $subject): int => $pattern !== null && $subject !== null
                    && preg_match('/'.str_replace('/', '\/', $pattern).'/i', $subject) === 1 ? 1 : 0,
                2
            );
        }
        if (! Schema::hasTable('usenet_groups')) {
            Schema::create('usenet_groups', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->bigInteger('minsizetoformrelease')->nullable();
                $table->integer('minfilestoformrelease')->nullable();
            });
        }
        DB::table('usenet_groups')->insertOrIgnore(['id' => 1, 'name' => 'alt.test']);

        $empty = $this->collection(1, now()->subHours(3)->format('Y-m-d H:i:s'));
        DB::table('collections')->where('id', $empty)->update(['filecheck' => CollectionFileCheckStatus::Sized->value, 'filesize' => 0]);
        $otherGroup = $this->collection(2, now()->subHours(3)->format('Y-m-d H:i:s'));
        DB::table('collections')->where('id', $otherGroup)->update(['filecheck' => CollectionFileCheckStatus::Sized->value, 'filesize' => 0]);

        (new ReleaseProcessingService(binariesConfig: new BinariesConfig(reconcileBatchSize: 2)))->deleteUnwantedCollections(1);

        $this->assertFalse(DB::table('collections')->where('id', $empty)->exists());
        $this->assertTrue(DB::table('collections')->where('id', $otherGroup)->exists());
    }

    private function process(?int $groupId): void
    {
        $processor = new ReleaseProcessingService(binariesConfig: new BinariesConfig(reconcileBatchSize: 2));
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

    /**
     * A collection whose binaries are all fully posted; only whole files are missing.
     */
    private function nzbSplitCollection(
        int $declaredFiles,
        int $presentFiles,
        string $createdAt,
        string $subject = '[1/5] "MLB__NZBSPLIT__d5e69bbf16e52f8bc43063b814526b7f__NZBSPLIT__MLB_RS_2026_Detroit_Tigers_vs_Chicago_White_Sox_20_09_720pEN60fps_DSN.7z.001" yEnc',
    ): int {
        $id = DB::table('collections')->insertGetId([
            'subject' => $subject,
            'groups_id' => 1,
            'dateadded' => $createdAt,
            'added' => $createdAt,
            'last_seen_at' => now(),
            'totalfiles' => $declaredFiles,
        ]);

        foreach (range(1, $presentFiles) as $file) {
            $binaryId = DB::table('binaries')->insertGetId(['collections_id' => $id, 'totalparts' => 1, 'filenumber' => $file]);
            DB::table('parts')->insert([
                'binaries_id' => $binaryId, 'partnumber' => 1, 'size' => 100,
                'number' => $binaryId, 'messageid' => '<split-'.$binaryId.'@example.test>',
            ]);
        }

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
