<?php

namespace Tests\Feature;

use App\Enums\CollectionFileCheckStatus;
use App\Enums\ConfigurationDomain;
use App\Models\Release;
use App\Services\Binaries\BinariesConfig;
use App\Services\CollectionCleanupService;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseCleaningService;
use App\Services\ReleaseCreationService;
use App\Services\Releases\ReleaseDuplicateFinder;
use App\Services\Releases\Verification\ManifestStore;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\ConfigurationTestBuilder;
use Tests\TestCase;

class CbpCleanupServiceTest extends TestCase
{
    private ?string $verificationDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['release_verification.required' => false]);

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();
        DB::connection()->getPdo()->sqliteCreateFunction('UNIX_TIMESTAMP', static fn (?string $value): int => strtotime((string) $value));
        DB::connection()->getPdo()->sqliteCreateFunction(
            'REGEXP',
            static function (?string $subject, ?string $pattern): int {
                if ($subject === null || $pattern === null || $pattern === '') {
                    return 0;
                }
                set_error_handler(static fn (): true => true);
                $ok = @preg_match($pattern, $subject);
                restore_error_handler();

                return $ok ? 1 : 0;
            },
            2
        );

        $this->createTables();
        $this->seedConfiguration();
    }

    protected function tearDown(): void
    {
        if ($this->verificationDirectory !== null) {
            app('files')->deleteDirectory($this->verificationDirectory);
        }
        parent::tearDown();
    }

    public function test_retention_discards_unusable_and_terminal_collections_without_queueing_them(): void
    {
        $this->enableVerification();
        $this->insertStalePayload(1, CollectionFileCheckStatus::Delete->value);
        $this->insertStalePayload(2, CollectionFileCheckStatus::Inserted->value);
        $this->insertStalePayload(3, 99);
        $this->insertStalePayload(4, CollectionFileCheckStatus::Sized->value, bytes: 0);
        $this->insertStalePayload(5, CollectionFileCheckStatus::Default->value, messageId: '');
        $this->insertStalePayload(6, CollectionFileCheckStatus::Default->value);
        DB::table('parts')->where('binaries_id', 6)->delete();
        $this->insertRecentCollection(7);
        DB::table('collections')->where('id', 7)->update(['dateadded' => now()->subHours(2)]);

        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);

        $this->assertSame(0, DB::table('release_verification_candidates')->count());
        $this->assertSame(0, DB::table('collections')->count());
        $this->assertSame(0, DB::table('binaries')->count());
        $this->assertSame(0, DB::table('parts')->count());
    }

    public function test_retention_preserves_incomplete_and_parity_only_payloads_for_verification(): void
    {
        $this->enableVerification();
        $this->insertStalePayload(1, CollectionFileCheckStatus::Default->value);
        $this->insertStalePayload(2, CollectionFileCheckStatus::ZeroPart->value, subject: 'Recovery.vol00+01.par2');
        DB::table('collections')->update(['filesize' => 0, 'totalfiles' => 10]);
        DB::table('binaries')->update(['totalparts' => 20]);

        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);

        $candidates = DB::table('release_verification_candidates')->orderBy('id')->get();
        $this->assertCount(2, $candidates);
        foreach ($candidates as $candidate) {
            $manifest = app(ManifestStore::class)->read($candidate->manifest_path);
            $this->assertSame('pending', $candidate->status);
            $this->assertSame(10, $manifest['expected_files']);
            $this->assertSame(20, $manifest['files'][0]['totalparts']);
            $this->assertCount(1, $manifest['files'][0]['segments']);
        }
        $this->assertSame(0, DB::table('collections')->count());
    }

    public function test_retention_verification_handoff_is_bounded_per_pass(): void
    {
        $this->enableVerification();
        foreach (range(1, 5) as $id) {
            $this->insertStalePayload($id, CollectionFileCheckStatus::Default->value);
        }
        $cleanup = new CollectionCleanupService(new BinariesConfig(sqlChunkSize: 2));

        $cleanup->deleteFinishedAndOrphans(false);

        $this->assertSame(2, DB::table('release_verification_candidates')->count());
        $this->assertSame([3, 4, 5], $this->collectionIds());
    }

    public function test_retention_keeps_source_articles_when_verification_handoff_fails(): void
    {
        $this->enableVerification();
        config(['release_verification.pending_bytes' => 0]);
        $this->insertStalePayload(1, CollectionFileCheckStatus::Default->value);

        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);

        $this->assertSame([1], $this->collectionIds());
        $this->assertSame(1, DB::table('binaries')->count());
        $this->assertSame(1, DB::table('parts')->count());
        $this->assertSame(0, DB::table('release_verification_candidates')->count());
        $lock = Cache::lock('cbp:verification-old-sweep:lock', 60);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_permanent_archival_failure_keeps_original_articles_and_continues_later_stale_inputs(): void
    {
        $this->enableVerification();
        $this->insertStalePayload(1, CollectionFileCheckStatus::Default->value);
        $this->insertStalePayload(2, CollectionFileCheckStatus::Default->value);
        $this->breakCandidateManifest(1);

        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);

        $this->assertSame([1], $this->collectionIds());
        $this->assertSame([1], DB::table('parts')->pluck('binaries_id')->all());
        $candidate = DB::table('release_verification_candidates')->where('source_identity', bin2hex('orphan-hash-2'))->first();
        $this->assertNotNull($candidate);
        $this->assertFileExists(app(ManifestStore::class)->path($candidate->manifest_path));
    }

    public function test_retained_low_id_cannot_monopolize_stale_windows_and_snapshot_ceiling_stays_fixed(): void
    {
        $this->enableVerification();
        foreach (range(1, 3) as $id) {
            $this->insertStalePayload($id, CollectionFileCheckStatus::Default->value);
        }
        $this->breakCandidateManifest(1);
        $cleanup = new CollectionCleanupService(new BinariesConfig(sqlChunkSize: 1));
        $cleanup->deleteFinishedAndOrphans(false);
        $this->assertSame(['last_id' => 1, 'max_id' => 3], Cache::get('cbp:verification-old-sweep:cursor'));
        $this->insertStalePayload(4, CollectionFileCheckStatus::Default->value);
        $cleanup->deleteFinishedAndOrphans(false);
        $this->assertSame([1, 3, 4], $this->collectionIds());
        $this->assertSame(['last_id' => 2, 'max_id' => 3], Cache::get('cbp:verification-old-sweep:cursor'));
        $cleanup->deleteFinishedAndOrphans(false);
        $this->assertSame([1, 4], $this->collectionIds());
        $this->assertNull(Cache::get('cbp:verification-old-sweep:cursor'));
        $cleanup->deleteFinishedAndOrphans(false);
        $this->assertSame([1, 4], $this->collectionIds());
        $this->assertSame(['last_id' => 1, 'max_id' => 4], Cache::get('cbp:verification-old-sweep:cursor'));
        $cleanup->deleteFinishedAndOrphans(false);
        $this->assertSame([1], $this->collectionIds());
    }

    public function test_global_stale_handoff_skips_when_another_worker_holds_lock_then_releases_its_lock(): void
    {
        $this->enableVerification();
        $this->insertStalePayload(1, CollectionFileCheckStatus::Default->value);
        $lock = Cache::lock('cbp:verification-old-sweep:lock', 60);
        $this->assertTrue($lock->get());
        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);
        $this->assertSame([1], $this->collectionIds());
        $this->assertSame(0, DB::table('release_verification_candidates')->count());
        $lock->release();
        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);
        $this->assertSame([], $this->collectionIds());
        $nextLock = Cache::lock('cbp:verification-old-sweep:lock', 60);
        $this->assertTrue($nextLock->get());
        $nextLock->release();
    }

    public function test_stale_handoff_uses_a_small_page_even_when_sql_chunk_size_is_large(): void
    {
        $this->enableVerification();
        foreach (range(1, 26) as $id) {
            $this->insertStalePayload($id, CollectionFileCheckStatus::Default->value);
        }
        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);
        $this->assertSame(25, DB::table('release_verification_candidates')->count());
        $this->assertSame([26], $this->collectionIds());
    }

    public function test_failed_retrievable_input_is_not_deleted_by_legacy_nzb_missed_cleanup(): void
    {
        $this->enableVerification();
        $this->insertStalePayload(1, CollectionFileCheckStatus::Sized->value);
        $this->breakCandidateManifest(1);
        DB::table('releases')->insert(['id' => 7, 'name' => 'Legacy NZB', 'nzbstatus' => NzbService::NZB_ADDED]);
        DB::table('collections')->where('id', 1)->update(['releases_id' => 7]);

        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);

        $this->assertSame([1], $this->collectionIds());
        $this->assertSame(1, DB::table('binaries')->count());
        $this->assertSame(1, DB::table('parts')->count());
    }

    private function breakCandidateManifest(int $collectionId): void
    {
        $identity = bin2hex((string) DB::table('collections')->where('id', $collectionId)->value('collectionhash'));
        DB::table('release_verification_candidates')->insert(['identity' => $identity, 'source_identity' => $identity, 'groups_id' => 1,
            'manifest_path' => 'invalid-reference.json.gz', 'manifest_hash' => str_repeat('a', 64), 'manifest_bytes' => 0,
            'last_unique_at' => now(), 'next_attempt_at' => now()]);
    }

    public function test_new_usable_part_after_stale_eligibility_selection_is_preserved_before_plain_deletion(): void
    {
        $this->enableVerification();
        $this->insertStalePayload(1, CollectionFileCheckStatus::Default->value, bytes: 0);
        $injected = false;
        DB::listen(static function (QueryExecuted $event) use (&$injected): void {
            if (! $injected && str_starts_with($event->sql, 'select "id" from "collections"')
                && str_contains($event->sql, '"parts" as "p"')) {
                $injected = true;
                DB::table('parts')->where('binaries_id', 1)->update(['size' => 5]);
            }
        });
        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);
        $this->assertTrue($injected);
        $this->assertSame([1], $this->collectionIds());
        $this->assertSame(5, (int) DB::table('parts')->value('size'));
    }

    public function test_new_binary_and_part_after_orphan_selection_are_preserved(): void
    {
        $this->enableVerification();
        $this->insertRecentCollection(1);
        $injected = false;
        DB::listen(static function (QueryExecuted $event) use (&$injected): void {
            if (! $injected && str_starts_with($event->sql, 'select "c"."id" from "collections" as "c"')
                && str_contains($event->sql, 'not exists') && str_contains($event->sql, '"binaries" as "b"')) {
                $injected = true;
                DB::table('binaries')->insert(['id' => 1, 'collections_id' => 1, 'name' => 'new.rar', 'totalparts' => 1, 'binaryhash' => 'new-binary']);
                DB::table('parts')->insert(['binaries_id' => 1, 'number' => 1, 'messageid' => 'new@example.test', 'partnumber' => 1, 'size' => 5]);
            }
        });
        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);
        $this->assertTrue($injected);
        $this->assertSame([1], $this->collectionIds());
        $this->assertSame('new@example.test', DB::table('parts')->value('messageid'));
    }

    public function test_new_eligible_state_after_missed_nzb_selection_is_preserved(): void
    {
        $this->enableVerification();
        $this->insertStalePayload(1, CollectionFileCheckStatus::Delete->value, bytes: 0);
        DB::table('collections')->where('id', 1)->update(['dateadded' => now(), 'releases_id' => 7]);
        DB::table('releases')->insert(['id' => 7, 'name' => 'Published legacy release', 'nzbstatus' => NzbService::NZB_ADDED]);
        $injected = false;
        DB::listen(static function (QueryExecuted $event) use (&$injected): void {
            if (! $injected && str_starts_with($event->sql, 'select "c"."id" from "collections" as "c"')
                && str_contains($event->sql, '"releases" as "r"')) {
                $injected = true;
                DB::table('collections')->where('id', 1)->update(['filecheck' => CollectionFileCheckStatus::Sized->value]);
                DB::table('parts')->where('binaries_id', 1)->update(['size' => 5]);
            }
        });
        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);
        $this->assertTrue($injected);
        $this->assertSame([1], $this->collectionIds());
        $this->assertSame(1, DB::table('parts')->count());
    }

    public function test_release_creation_skips_unusable_sized_rows_before_applying_the_batch_limit(): void
    {
        $this->enableVerification();
        $this->insertStalePayload(1, CollectionFileCheckStatus::Sized->value, bytes: 0);
        $this->insertStalePayload(2, CollectionFileCheckStatus::Sized->value, messageId: '');
        $this->insertStalePayload(3, CollectionFileCheckStatus::Sized->value);
        DB::table('collections')->where('id', 3)->update(['filesize' => 0]);

        $result = app(ReleaseCreationService::class)->createReleases(1, 1, false);

        $this->assertSame(['added' => 0, 'dupes' => 0], $result);
        $candidate = DB::table('release_verification_candidates')->sole();
        $manifest = app(ManifestStore::class)->read($candidate->manifest_path);
        $this->assertSame('Orphan.3', $manifest['subject']);
        $this->assertSame([1, 2], $this->collectionIds());
        $this->assertSame(0, DB::table('releases')->count());
    }

    private function enableVerification(): void
    {
        $this->verificationDirectory = sys_get_temp_dir().'/nntmux-cleanup-'.bin2hex(random_bytes(12));
        config(['release_verification.required' => true, 'release_verification.storage_path' => $this->verificationDirectory]);
        (require database_path('migrations/2026_10_11_154028_create_release_verification_candidates_table.php'))->up();
        DB::statement('ALTER TABLE binaries ADD COLUMN binaryhash VARCHAR(255)');
    }

    private function insertStalePayload(int $id, int $status, int $bytes = 100, string $messageId = 'payload@example.com', string $subject = 'Payload.part01.rar'): void
    {
        $this->insertRecentCollection($id);
        DB::table('collections')->where('id', $id)->update(['dateadded' => now()->subHours(2), 'added' => now()->subHours(2), 'filecheck' => $status]);
        DB::table('binaries')->insert(['id' => $id, 'collections_id' => $id, 'binaryhash' => 'binary-'.$id, 'name' => $subject, 'totalparts' => 1]);
        DB::table('parts')->insert(['binaries_id' => $id, 'number' => $id, 'messageid' => $messageId, 'partnumber' => 1, 'size' => $bytes]);
    }

    public function test_retention_cleanup_deletes_parts_binaries_and_collections_without_fk_cascades(): void
    {
        DB::table('collections')->insert([
            'id' => 100,
            'subject' => 'Retention.Release',
            'fromname' => 'poster@example.com',
            'date' => now()->subHours(10)->format('Y-m-d H:i:s'),
            'dateadded' => now()->subHours(10)->format('Y-m-d H:i:s'),
            'added' => now()->subHours(10)->format('Y-m-d H:i:s'),
            'xref' => 'alt.test:123',
            'groups_id' => 1,
            'totalfiles' => 1,
            'filesize' => 500,
            'filecheck' => CollectionFileCheckStatus::Sized->value,
            'collectionhash' => 'retention-hash',
            'collection_regexes_id' => 0,
            'releases_id' => null,
            'noise' => '',
        ]);
        DB::table('binaries')->insert([
            'id' => 1000,
            'name' => 'Retention.Release.par2',
            'collections_id' => 100,
            'totalparts' => 1,
        ]);
        DB::table('parts')->insert([
            'binaries_id' => 1000,
            'number' => 1,
            'messageid' => '<retention-1@example.com>',
            'partnumber' => 1,
            'size' => 10,
        ]);

        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);

        $this->assertSame(0, DB::table('parts')->count());
        $this->assertSame(0, DB::table('binaries')->count());
        $this->assertSame(0, DB::table('collections')->count());
    }

    public function test_orphan_sweep_checks_one_id_window_per_pass_and_wraps_to_the_start(): void
    {
        config(['nntmux.cbp.orphan_scan_window' => 1000]);
        foreach ([500, 600, 1500, 2500] as $id) {
            $this->insertRecentCollection($id);
        }
        DB::table('binaries')->insert(['id' => 6000, 'name' => 'Kept.par2', 'collections_id' => 600, 'totalparts' => 1]);
        $cleanup = app(CollectionCleanupService::class);

        $cleanup->deleteFinishedAndOrphans(false);
        $this->assertSame([600, 1500, 2500], $this->collectionIds());
        $this->assertSame(1000, Cache::get(CollectionCleanupService::ORPHAN_SWEEP_CURSOR));

        $cleanup->deleteFinishedAndOrphans(false);
        $this->assertSame([600, 2500], $this->collectionIds());
        $this->assertSame(2000, Cache::get(CollectionCleanupService::ORPHAN_SWEEP_CURSOR));

        $cleanup->deleteFinishedAndOrphans(false);
        $this->assertSame([600], $this->collectionIds());
        $this->assertSame(0, Cache::get(CollectionCleanupService::ORPHAN_SWEEP_CURSOR));
    }

    public function test_cleanup_deletes_only_collections_whose_release_already_has_an_nzb(): void
    {
        DB::table('releases')->insert([
            ['id' => 1, 'name' => 'Nzb.Done', 'nzbstatus' => NzbService::NZB_ADDED],
            ['id' => 2, 'name' => 'Nzb.Pending', 'nzbstatus' => NzbService::NZB_NONE],
        ]);
        $this->insertRecentCollection(100, releasesId: 1);
        $this->insertRecentCollection(200, releasesId: 2);
        $this->insertRecentCollection(300);
        foreach ([100, 200, 300] as $collectionId) {
            DB::table('binaries')->insert(['name' => 'File.'.$collectionId, 'collections_id' => $collectionId, 'totalparts' => 1]);
        }

        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);

        $this->assertSame([200, 300], $this->collectionIds());
    }

    public function test_orphan_sweep_is_skipped_while_another_worker_holds_the_lock(): void
    {
        $this->insertRecentCollection(500);
        $lock = Cache::lock(CollectionCleanupService::ORPHAN_SWEEP_LOCK, 60);
        $this->assertTrue($lock->get());

        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);
        $this->assertSame([500], $this->collectionIds());

        $lock->release();
        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);
        $this->assertSame([], $this->collectionIds());
    }

    public function test_nzb_creation_cleans_up_collection_binary_and_parts_explicitly(): void
    {
        DB::table('releases')->insert([
            'id' => 1,
            'name' => 'Nzb.Release',
            'searchname' => 'Nzb.Release',
            'totalpart' => 1,
            'groups_id' => 1,
            'adddate' => now()->format('Y-m-d H:i:s'),
            'guid' => str_repeat('a', 36),
            'leftguid' => 'a',
            'postdate' => now()->format('Y-m-d H:i:s'),
            'fromname' => 'poster@example.com',
            'size' => 500,
            'passwordstatus' => 0,
            'haspreview' => -1,
            'categories_id' => 1,
            'nfostatus' => -1,
            'nzbstatus' => NzbService::NZB_NONE,
            'isrenamed' => 1,
            'iscategorized' => 1,
            'predb_id' => 0,
            'source' => null,
        ]);

        DB::table('collections')->insert([
            'id' => 200,
            'subject' => 'Nzb.Release',
            'fromname' => 'poster@example.com',
            'date' => now()->subHours(1)->format('Y-m-d H:i:s'),
            'dateadded' => now()->subHours(1)->format('Y-m-d H:i:s'),
            'added' => now()->subHours(1)->format('Y-m-d H:i:s'),
            'xref' => 'alt.test:200',
            'groups_id' => 1,
            'totalfiles' => 1,
            'filesize' => 500,
            'filecheck' => CollectionFileCheckStatus::Inserted->value,
            'collectionhash' => 'nzb-hash',
            'collection_regexes_id' => 0,
            'releases_id' => 1,
            'noise' => '',
        ]);
        DB::table('binaries')->insert([
            'id' => 2000,
            'name' => 'Nzb.Release yEnc',
            'collections_id' => 200,
            'totalparts' => 1,
        ]);
        DB::table('parts')->insert([
            'binaries_id' => 2000,
            'number' => 1,
            'messageid' => '<nzb-1@example.com>',
            'partnumber' => 1,
            'size' => 10,
        ]);

        $release = Release::query()->findOrFail(1);
        $release->setRelation('category', (object) ['title' => 'Misc', 'parent' => (object) ['title' => 'Other']]);

        $written = app(NzbService::class)->writeNzbForReleaseId($release);

        $this->assertTrue($written);
        $this->assertSame(0, DB::table('parts')->count());
        $this->assertSame(0, DB::table('binaries')->count());
        $this->assertSame(0, DB::table('collections')->count());
        $this->assertSame(1, (int) DB::table('releases')->where('id', 1)->value('nzbstatus'));
    }

    public function test_duplicate_release_path_cleans_up_collection_binary_and_parts(): void
    {
        DB::table('releases')->insert([
            'id' => 2,
            'name' => 'Duplicate.Release',
            'searchname' => 'Duplicate.Release',
            'totalpart' => 1,
            'groups_id' => 1,
            'adddate' => now()->format('Y-m-d H:i:s'),
            'guid' => str_repeat('b', 36),
            'leftguid' => 'b',
            'postdate' => now()->format('Y-m-d H:i:s'),
            'fromname' => 'poster@example.com',
            'size' => 1000,
            'passwordstatus' => 0,
            'haspreview' => -1,
            'categories_id' => 1,
            'nfostatus' => -1,
            'nzbstatus' => NzbService::NZB_NONE,
            'isrenamed' => 1,
            'iscategorized' => 1,
            'predb_id' => 0,
            'source' => null,
        ]);

        DB::table('collections')->insert([
            'id' => 300,
            'subject' => 'Duplicate.Release',
            'fromname' => 'poster@example.com',
            'date' => now()->subHours(1)->format('Y-m-d H:i:s'),
            'dateadded' => now()->subHours(1)->format('Y-m-d H:i:s'),
            'added' => now()->subHours(1)->format('Y-m-d H:i:s'),
            'xref' => 'alt.test:300',
            'groups_id' => 1,
            'totalfiles' => 1,
            'filesize' => 1000,
            'filecheck' => CollectionFileCheckStatus::Sized->value,
            'collectionhash' => 'duplicate-hash',
            'collection_regexes_id' => 0,
            'releases_id' => null,
            'noise' => '',
        ]);
        DB::table('binaries')->insert([
            'id' => 3000,
            'name' => 'Duplicate.Release yEnc',
            'collections_id' => 300,
            'totalparts' => 1,
        ]);
        DB::table('parts')->insert([
            'binaries_id' => 3000,
            'number' => 1,
            'messageid' => '<duplicate-1@example.com>',
            'partnumber' => 1,
            'size' => 10,
        ]);

        $service = new ReleaseCreationService(
            app(ReleaseCleaningService::class),
            app(CollectionCleanupService::class),
            app(ReleaseDuplicateFinder::class)
        );
        $result = $service->createReleases(null, 10, false);

        $this->assertSame(['added' => 0, 'dupes' => 1], $result);
        $this->assertSame(0, DB::table('parts')->count());
        $this->assertSame(0, DB::table('binaries')->count());
        $this->assertSame(0, DB::table('collections')->count());
    }

    public function test_release_duplicate_finder_matches_searchname_within_size_band(): void
    {
        DB::table('releases')->insert([
            'id' => 20,
            'name' => 'raw-obfuscated-a',
            'searchname' => 'Unified.Scene.S01E01.1080p',
            'totalpart' => 1,
            'groups_id' => 1,
            'adddate' => now()->format('Y-m-d H:i:s'),
            'guid' => str_repeat('c', 36),
            'leftguid' => 'c',
            'postdate' => now()->format('Y-m-d H:i:s'),
            'fromname' => 'poster-a@example.com',
            'size' => 1_000_000,
            'passwordstatus' => 0,
            'haspreview' => -1,
            'categories_id' => 1,
            'nfostatus' => -1,
            'nzbstatus' => NzbService::NZB_NONE,
            'isrenamed' => 1,
            'iscategorized' => 1,
            'predb_id' => 0,
            'source' => null,
        ]);

        $finder = app(ReleaseDuplicateFinder::class);
        [$dup, $reason] = $finder->findDuplicate(
            'raw-obfuscated-b',
            'Unified.Scene.S01E01.1080p',
            0,
            1_020_000
        );

        $this->assertNotNull($dup);
        $this->assertSame('searchname_match', $reason);
    }

    public function test_release_duplicate_finder_matches_predb_id_when_searchname_differs(): void
    {
        DB::table('releases')->insert([
            'id' => 21,
            'name' => 'old',
            'searchname' => 'Old Style Name',
            'totalpart' => 1,
            'groups_id' => 1,
            'adddate' => now()->format('Y-m-d H:i:s'),
            'guid' => str_repeat('d', 36),
            'leftguid' => 'd',
            'postdate' => now()->format('Y-m-d H:i:s'),
            'fromname' => 'p@example.com',
            'size' => 2_000_000,
            'passwordstatus' => 0,
            'haspreview' => -1,
            'categories_id' => 1,
            'nfostatus' => -1,
            'nzbstatus' => NzbService::NZB_NONE,
            'isrenamed' => 1,
            'iscategorized' => 1,
            'predb_id' => 9001,
            'source' => null,
        ]);

        $finder = app(ReleaseDuplicateFinder::class);
        [$dup, $reason] = $finder->findDuplicate(
            'new',
            'New Style Name',
            9001,
            2_050_000
        );

        $this->assertNotNull($dup);
        $this->assertSame('predb_id_match', $reason);
    }

    public function test_release_duplicate_finder_does_not_match_outside_size_tolerance(): void
    {
        config(['nntmux.release_dedupe_size_tolerance' => 0.05]);

        DB::table('releases')->insert([
            'id' => 22,
            'name' => 'x',
            'searchname' => 'Same.Search',
            'totalpart' => 1,
            'groups_id' => 1,
            'adddate' => now()->format('Y-m-d H:i:s'),
            'guid' => str_repeat('e', 36),
            'leftguid' => 'e',
            'postdate' => now()->format('Y-m-d H:i:s'),
            'fromname' => 'p@example.com',
            'size' => 1_000_000,
            'passwordstatus' => 0,
            'haspreview' => -1,
            'categories_id' => 1,
            'nfostatus' => -1,
            'nzbstatus' => NzbService::NZB_NONE,
            'isrenamed' => 1,
            'iscategorized' => 1,
            'predb_id' => 0,
            'source' => null,
        ]);

        $finder = app(ReleaseDuplicateFinder::class);
        [$dup] = $finder->findDuplicate('x', 'Same.Search', 0, 1_200_000);

        $this->assertNull($dup);
    }

    public function test_release_duplicate_finder_falls_back_to_name_when_searchname_empty(): void
    {
        DB::table('releases')->insert([
            'id' => 23,
            'name' => 'fallback.unique',
            'searchname' => '',
            'totalpart' => 1,
            'groups_id' => 1,
            'adddate' => now()->format('Y-m-d H:i:s'),
            'guid' => str_repeat('f', 36),
            'leftguid' => 'f',
            'postdate' => now()->format('Y-m-d H:i:s'),
            'fromname' => 'p@example.com',
            'size' => 500,
            'passwordstatus' => 0,
            'haspreview' => -1,
            'categories_id' => 1,
            'nfostatus' => -1,
            'nzbstatus' => NzbService::NZB_NONE,
            'isrenamed' => 1,
            'iscategorized' => 1,
            'predb_id' => 0,
            'source' => null,
        ]);

        $finder = app(ReleaseDuplicateFinder::class);
        [$dup, $reason] = $finder->findDuplicate('fallback.unique', '', 0, 500);

        $this->assertNotNull($dup);
        $this->assertSame('name_match_fallback', $reason);
    }

    private function insertRecentCollection(int $id, ?int $releasesId = null): void
    {
        DB::table('collections')->insert([
            'id' => $id,
            'subject' => 'Orphan.'.$id,
            'fromname' => 'poster@example.com',
            'date' => now()->format('Y-m-d H:i:s'),
            'dateadded' => now()->format('Y-m-d H:i:s'),
            'added' => now()->format('Y-m-d H:i:s'),
            'xref' => 'alt.test:'.$id,
            'groups_id' => 1,
            'totalfiles' => 1,
            'filesize' => 500,
            'filecheck' => CollectionFileCheckStatus::Default->value,
            'collectionhash' => 'orphan-hash-'.$id,
            'collection_regexes_id' => 0,
            'releases_id' => $releasesId,
            'noise' => '',
        ]);
    }

    /**
     * @return list<int>
     */
    private function collectionIds(): array
    {
        return DB::table('collections')->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    private function seedConfiguration(): void
    {
        ConfigurationTestBuilder::installDefaults();
        ConfigurationTestBuilder::update(ConfigurationDomain::Ingestion, [
            'part_retention_hours' => 1,
            'nzb_split_level' => 1,
            'categorize_foreign' => true,
            'categorize_web_dl' => true,
        ]);
    }

    private function createTables(): void
    {
        DB::statement('CREATE TABLE usenet_groups (id INTEGER PRIMARY KEY, name VARCHAR(255))');
        DB::statement('CREATE TABLE categories (id INTEGER PRIMARY KEY, title VARCHAR(255), parent_categories_id INTEGER NULL)');
        DB::statement('CREATE TABLE releases (
            id INTEGER PRIMARY KEY,
            name VARCHAR(255),
            searchname VARCHAR(255),
            totalpart INTEGER,
            groups_id INTEGER,
            adddate DATETIME NULL,
            guid VARCHAR(64),
            leftguid VARCHAR(1),
            postdate DATETIME NULL,
            fromname VARCHAR(255),
            size INTEGER,
            passwordstatus INTEGER,
            haspreview INTEGER,
            categories_id INTEGER,
            nfostatus INTEGER,
            nzbstatus INTEGER,
            isrenamed INTEGER,
            iscategorized INTEGER,
            predb_id INTEGER,
            source VARCHAR(255) NULL
        )');
        DB::statement('CREATE TABLE collections (
            id INTEGER PRIMARY KEY,
            subject VARCHAR(255),
            fromname VARCHAR(255),
            date DATETIME NULL,
            dateadded DATETIME NULL,
            added DATETIME NULL,
            xref TEXT,
            groups_id INTEGER,
            totalfiles INTEGER,
            filesize INTEGER,
            filecheck INTEGER,
            collectionhash VARCHAR(255),
            collection_regexes_id INTEGER,
            releases_id INTEGER NULL,
            noise VARCHAR(64)
        )');
        DB::statement('CREATE TABLE binaries (
            id INTEGER PRIMARY KEY,
            name VARCHAR(255),
            collections_id INTEGER,
            totalparts INTEGER
        )');
        DB::statement('CREATE TABLE parts (
            binaries_id INTEGER,
            number INTEGER,
            messageid VARCHAR(255),
            partnumber INTEGER,
            size INTEGER
        )');
        DB::statement('CREATE TABLE release_naming_regexes (
            id INTEGER PRIMARY KEY,
            group_regex VARCHAR(255),
            regex VARCHAR(255),
            status INTEGER DEFAULT 1,
            ordinal INTEGER DEFAULT 0
        )');
        DB::statement('CREATE TABLE collection_regexes (
            id INTEGER PRIMARY KEY,
            group_regex VARCHAR(255),
            regex VARCHAR(255),
            status INTEGER DEFAULT 1,
            ordinal INTEGER DEFAULT 0
        )');
        DB::statement('CREATE TABLE predb (
            id INTEGER PRIMARY KEY,
            title VARCHAR(255),
            filename VARCHAR(255)
        )');
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.test']);
        DB::table('categories')->insert(['id' => 1, 'title' => 'Misc', 'parent_categories_id' => null]);
    }
}
