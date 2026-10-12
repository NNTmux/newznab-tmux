<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CollectionFileCheckStatus;
use App\Services\CollectionCleanupService;
use App\Services\Nzb\NzbCreationCandidateQuery;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseCreationService;
use App\Services\ReleaseProcessingService;
use App\Services\Releases\Verification\ManifestStore;
use App\Services\Tmux\CollectionReconciliationBarrier;
use App\Support\Configuration\IngestionConfigurationData;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyVerificationBacklogTest extends ReleaseVerificationCandidatesTest
{
    protected function setUp(): void
    {
        parent::setUp();
        NzbCreationCandidateQuery::flushCapabilityCache();
        Schema::table('collections', function (Blueprint $table): void {
            $table->unsignedBigInteger('releases_id')->nullable();
            $table->dateTime('dateadded')->nullable();
            $table->dateTime('added')->nullable();
            $table->dateTime('last_seen_at')->nullable();
        });
    }

    public function test_only_retrievable_inserted_collections_with_a_current_unpublished_release_are_eligible(): void
    {
        $this->legacyCollection(1);
        $this->legacyCollection(2, releaseId: 11);
        $this->legacyCollection(3, releaseId: 99);
        $this->legacyCollection(4, releaseId: null);
        $this->legacyCollection(5, bytes: 0);
        $this->legacyCollection(6, status: CollectionFileCheckStatus::Sized->value, releaseId: null);
        $this->legacyCollection(7, status: CollectionFileCheckStatus::Default->value, releaseId: null);
        $this->assertSame([1, 6, 7], app(CollectionCleanupService::class)->verificationEligibleCollections()->orderBy('id')->pluck('id')->all());
    }

    public function test_eligibility_falls_back_safely_when_legacy_schema_capabilities_are_absent(): void
    {
        $this->legacyCollection(1);
        $this->legacyCollection(2, status: CollectionFileCheckStatus::Sized->value, releaseId: null);
        Schema::drop('releases');
        $this->assertSame([2], app(CollectionCleanupService::class)->verificationEligibleCollections()->orderBy('id')->pluck('id')->all());
        Schema::table('collections', function (Blueprint $table): void {
            $table->dropColumn('releases_id');
        });
        $this->assertSame([2], app(CollectionCleanupService::class)->verificationEligibleCollections()->orderBy('id')->pluck('id')->all());
    }

    public function test_required_creation_archives_only_allowed_legacy_ids_without_publishing_or_deleting_releases(): void
    {
        $this->legacyCollection(1);
        $this->legacyCollection(2);
        $result = app(ReleaseCreationService::class)->createReleases(1, 10, false, [2]);
        $this->assertSame(['added' => 0, 'dupes' => 0], $result);
        $this->assertSame([1], DB::table('collections')->orderBy('id')->pluck('id')->all());
        $candidate = DB::table('release_verification_candidates')->sole();
        $this->assertSame('pending', $candidate->status);
        $manifest = app(ManifestStore::class)->read($candidate->manifest_path);
        $this->assertSame('article-2@example.test', $manifest['files'][0]['segments'][0]['messageid']);
        $this->assertSame(0, $manifest['expected_files']);
        $this->assertSame(10, $manifest['legacy_release_id']);
        $this->assertSame([10, 11], DB::table('releases')->orderBy('id')->pluck('id')->all());
        $this->assertSame(NzbService::NZB_NONE, (int) DB::table('releases')->where('id', 10)->value('nzbstatus'));
    }

    public function test_stale_unpublished_inserted_headers_are_archived_while_published_inserted_rows_clean_normally(): void
    {
        $this->legacyCollection(1);
        $this->legacyCollection(2, releaseId: 11);
        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);
        $this->assertSame(0, DB::table('collections')->count());
        $candidate = DB::table('release_verification_candidates')->sole();
        $this->assertSame('pending', $candidate->status);
        $manifest = app(ManifestStore::class)->read($candidate->manifest_path);
        $this->assertSame('article-1@example.test', $manifest['files'][0]['segments'][0]['messageid']);
        $this->assertSame([10, 11], DB::table('releases')->orderBy('id')->pluck('id')->all());
    }

    public function test_required_background_windows_resume_past_a_failed_legacy_handoff_without_legacy_publication(): void
    {
        Schema::create('ingestion_configurations', function (Blueprint $table): void {
            $table->id();
        });
        Cache::put('configuration:ingestion', new IngestionConfigurationData(...array_replace(IngestionConfigurationData::defaults()->toArray(), ['maxReleasesCreated' => 1])), 300);
        foreach (range(1, 21) as $id) {
            $this->legacyCollection($id);
        }
        $identity = bin2hex(sha1('legacy-1', true));
        DB::table('release_verification_candidates')->insert(['identity' => $identity, 'source_identity' => $identity, 'groups_id' => 1,
            'manifest_path' => 'invalid-reference.json.gz', 'manifest_hash' => str_repeat('a', 64), 'manifest_bytes' => 0,
            'last_unique_at' => now(), 'next_attempt_at' => now()]);
        $first = app(ReleaseProcessingService::class)->processReleaseCreationBatches(1);
        $this->assertSame(20, $first['iterations']);
        $this->assertSame(0, $first['releases']);
        $this->assertSame([1, 21], DB::table('collections')->orderBy('id')->pluck('id')->all());
        $this->assertSame(['last_id' => 20, 'max_id' => 21], Cache::get('cbp:release-window:cursor:group:1'));
        $second = app(ReleaseProcessingService::class)->processReleaseCreationBatches(1);
        $this->assertSame(1, $second['iterations']);
        $this->assertSame(0, $second['releases']);
        $this->assertSame([1], DB::table('collections')->pluck('id')->all());
        $this->assertSame([10, 11], DB::table('releases')->orderBy('id')->pluck('id')->all());
        $this->assertSame(NzbService::NZB_NONE, (int) DB::table('releases')->where('id', 10)->value('nzbstatus'));
    }

    public function test_required_legacy_backlog_is_archived_before_preparation_and_fresh_headers_are_reconciled(): void
    {
        Schema::table('binaries', function (Blueprint $table): void {
            $table->integer('currentparts')->default(0);
            $table->integer('partsize')->default(0);
            $table->integer('partcheck')->default(0);
        });
        $this->legacyCollection(1);
        $this->legacyCollection(2, status: CollectionFileCheckStatus::Default->value, releaseId: null);
        DB::table('collections')->where('id', 2)->update(['totalfiles' => 3]);
        $barrier = new class($this) implements CollectionReconciliationBarrier
        {
            public int $calls = 0;

            public function __construct(private LegacyVerificationBacklogTest $test) {}

            public function withReconciliationBarrier(int $delayHours, callable $work): mixed
            {
                $this->calls++;
                $this->test->assertFalse(DB::table('collections')->where('id', 1)->exists(), 'Eligible legacy headers must be archived before preparation begins.');
                $this->test->assertTrue(DB::table('release_verification_candidates')->where('source_identity', bin2hex(sha1('legacy-1', true)))->exists());
                $this->test->assertSame(CollectionFileCheckStatus::Default->value, DB::table('collections')->where('id', 2)->value('filecheck'));

                return $work();
            }
        };
        $this->app->instance(CollectionReconciliationBarrier::class, $barrier);

        $totals = app(ReleaseProcessingService::class)->processCollectionsAndReleases(1);

        $this->assertSame(1, $barrier->calls);
        $this->assertSame(0, $totals['releases']);
        $this->assertSame(0, DB::table('collections')->count());
        $this->assertSame(2, DB::table('release_verification_candidates')->count());
        $fresh = DB::table('release_verification_candidates')->where('source_identity', bin2hex(sha1('legacy-2', true)))->sole();
        $manifest = app(ManifestStore::class)->read($fresh->manifest_path);
        $this->assertSame(3, $manifest['expected_files'], 'Reconciliation must preserve the declared inventory for repair verification.');
        $this->assertSame('article-2@example.test', $manifest['files'][0]['segments'][0]['messageid']);
        $this->assertNull($manifest['legacy_release_id']);
        $this->assertSame([10, 11], DB::table('releases')->orderBy('id')->pluck('id')->all());
    }

    private function legacyCollection(int $id, int $status = 4, ?int $releaseId = 10, int $bytes = 5): void
    {
        if (! Schema::hasTable('releases')) {
            Schema::create('releases', function (Blueprint $table): void {
                $table->id();
                $table->string('guid')->default('legacy-guid');
                $table->string('name')->default('Legacy release');
                $table->integer('nzbstatus')->default(NzbService::NZB_NONE);
                $table->integer('groups_id')->default(1);
            });
            DB::table('releases')->insert([['id' => 10, 'nzbstatus' => NzbService::NZB_NONE], ['id' => 11, 'nzbstatus' => NzbService::NZB_ADDED]]);
        }
        DB::table('collections')->insert(['id' => $id, 'collectionhash' => sha1('legacy-'.$id, true), 'subject' => 'Legacy.'.$id,
            'fromname' => 'poster', 'totalfiles' => 1, 'groups_id' => 1, 'date' => '2026-10-11 12:00:00', 'filecheck' => $status,
            'releases_id' => $releaseId, 'dateadded' => now()->subHours(73), 'added' => now()->subHours(2), 'last_seen_at' => now()->subHours(2)]);
        DB::table('binaries')->insert(['id' => $id, 'collections_id' => $id, 'binaryhash' => md5('file-'.$id, true), 'name' => 'masked.rar', 'totalparts' => 1]);
        DB::table('parts')->insert(['binaries_id' => $id, 'partnumber' => 1, 'messageid' => 'article-'.$id.'@example.test', 'size' => $bytes]);
    }
}
