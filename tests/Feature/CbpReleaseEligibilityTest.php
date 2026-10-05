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
