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
use Illuminate\Support\Facades\DB;
use Tests\Support\ConfigurationTestBuilder;
use Tests\TestCase;

class CbpCleanupServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_retention_keeps_active_collections_and_scopes_to_the_worker_group(): void
    {
        $this->insertBareCollection(300, 1, dateadded: now()->subHours(10), lastSeenAt: now()->subMinutes(5));
        $this->insertBareCollection(301, 1, dateadded: now()->subHours(10), lastSeenAt: now()->subHours(2));
        $this->insertBareCollection(302, 2, dateadded: now()->subHours(10), lastSeenAt: now()->subHours(2));
        $this->insertBareCollection(303, 1, dateadded: now()->subHours(30), lastSeenAt: now()->subMinutes(5));

        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false, 1);

        // 300: still receiving binaries; 301: idle past retention; 302: other
        // group, left for its own worker; 303: past the hard cutoff.
        $this->assertSame([300, 302], DB::table('collections')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_restarting_the_delay_skips_collections_retention_will_delete(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Ingestion, ['collection_delay_hours' => 2]);
        $this->travelTo(now()->startOfSecond());
        $this->insertBareCollection(400, 1, dateadded: now()->subHours(5), lastSeenAt: now()->subMinutes(10));
        $this->insertBareCollection(401, 1, dateadded: now()->subHours(5), lastSeenAt: now()->subHours(3));
        $this->insertBareCollection(402, 1, dateadded: now()->subHours(5), lastSeenAt: null);
        $this->insertBareCollection(403, 1, dateadded: now()->subMinutes(30), lastSeenAt: now()->subMinutes(5));
        $this->insertBareCollection(404, 2, dateadded: now()->subHours(5), lastSeenAt: now()->subMinutes(20));
        $this->insertBareCollection(405, 2, dateadded: now()->subHours(5), lastSeenAt: now()->subMinutes(30));

        $restarted = (new CollectionCleanupService(new BinariesConfig(sqlChunkSize: 2)))->restartCollectionDelay();

        // 401 is idle past the 1h retention and gets deleted either way; 403 is still inside its delay.
        $this->assertSame(4, $restarted);
        $this->assertSame(
            [400 => now()->toDateTimeString(), 401 => now()->subHours(5)->toDateTimeString(), 402 => now()->toDateTimeString(),
                403 => now()->subMinutes(30)->toDateTimeString(), 404 => now()->toDateTimeString(), 405 => now()->toDateTimeString()],
            DB::table('collections')->orderBy('id')->pluck('dateadded', 'id')->map(static fn ($date): string => (string) $date)->all(),
        );
    }

    public function test_orphan_sweep_checks_one_id_window_per_run_and_wraps_around(): void
    {
        config(['nntmux.cbp.orphan_collection_window' => 1000]);
        foreach ([10, 20, 5000, 9000] as $id) {
            $this->insertBareCollection($id, 1, dateadded: now(), lastSeenAt: now());
        }
        $this->insertBinary(1, 10);
        $this->insertBinary(2, 9000);
        $cleanup = app(CollectionCleanupService::class);

        $cleanup->deleteFinishedAndOrphans(false);
        $this->assertSame([10, 5000, 9000], $this->collectionIds());

        $cleanup->deleteFinishedAndOrphans(false);
        $this->assertSame([10, 9000], $this->collectionIds());

        // Passing the newest collection resets the cursor to the start.
        $cleanup->deleteFinishedAndOrphans(false);
        $this->insertBareCollection(30, 1, dateadded: now(), lastSeenAt: now());
        $cleanup->deleteFinishedAndOrphans(false);
        $this->assertSame([10, 9000], $this->collectionIds());
    }

    public function test_collections_missed_after_nzb_are_deleted_only_for_nzbd_releases(): void
    {
        DB::table('releases')->insert([
            ['id' => 1, 'name' => 'Done', 'nzbstatus' => 1],
            ['id' => 2, 'name' => 'Pending', 'nzbstatus' => 0],
        ]);
        foreach ([400 => 1, 401 => 2, 402 => null] as $id => $releaseId) {
            $this->insertBareCollection($id, 1, dateadded: now(), lastSeenAt: now());
            DB::table('collections')->where('id', $id)->update(['releases_id' => $releaseId]);
            $this->insertBinary($id, $id);
        }

        app(CollectionCleanupService::class)->deleteFinishedAndOrphans(false);

        $this->assertSame([401, 402], $this->collectionIds());
    }

    public function test_explicit_cleanup_also_deletes_collection_groups_of_deleted_collections(): void
    {
        DB::statement('CREATE TABLE collection_groups (collections_id INTEGER, group_name VARCHAR(255), PRIMARY KEY (collections_id, group_name))');
        DB::table('collection_groups')->insert([
            ['collections_id' => 200, 'group_name' => 'alt.test'],
            ['collections_id' => 200, 'group_name' => 'alt.other'],
            ['collections_id' => 201, 'group_name' => 'alt.test'],
        ]);

        $deleted = app(CollectionCleanupService::class)->deleteCollectionsAndDescendants([200]);

        $this->assertSame(0, $deleted);
        $this->assertSame([201], DB::table('collection_groups')->pluck('collections_id')->map(fn ($id): int => (int) $id)->all());
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

    public function test_release_creation_rolls_back_when_collection_was_already_claimed(): void
    {
        DB::table('collections')->insert([
            'id' => 310,
            'subject' => 'Claimed.By.Other.Worker',
            'fromname' => 'poster@example.com',
            'date' => now()->subHours(1)->format('Y-m-d H:i:s'),
            'dateadded' => now()->subHours(1)->format('Y-m-d H:i:s'),
            'xref' => 'alt.test:310',
            'groups_id' => 1,
            'totalfiles' => 1,
            'filesize' => 1000,
            'filecheck' => CollectionFileCheckStatus::Sized->value,
            'collectionhash' => 'claimed-hash',
            'collection_regexes_id' => 0,
            // Another run already attached its release.
            'releases_id' => 999,
            'noise' => '',
        ]);

        $service = new ReleaseCreationService(
            app(ReleaseCleaningService::class),
            app(CollectionCleanupService::class),
            app(ReleaseDuplicateFinder::class)
        );
        $result = $service->createReleases(null, 10, false);

        $this->assertSame(['added' => 0, 'dupes' => 0], $result);
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertSame(999, (int) DB::table('collections')->where('id', 310)->value('releases_id'));
    }

    public function test_release_creation_claims_the_collection_it_released(): void
    {
        DB::table('collections')->insert([
            'id' => 320,
            'subject' => 'Fresh.Release.2026.1080p.WEB.H264-GROUP',
            'fromname' => 'poster@example.com',
            'date' => now()->subHours(1)->format('Y-m-d H:i:s'),
            'dateadded' => now()->subHours(1)->format('Y-m-d H:i:s'),
            'xref' => 'alt.test:320',
            'groups_id' => 1,
            'totalfiles' => 1,
            'filesize' => 1000,
            'filecheck' => CollectionFileCheckStatus::Sized->value,
            'collectionhash' => 'fresh-hash',
            'collection_regexes_id' => 0,
            'releases_id' => null,
            'noise' => '',
        ]);

        $service = new ReleaseCreationService(
            app(ReleaseCleaningService::class),
            app(CollectionCleanupService::class),
            app(ReleaseDuplicateFinder::class)
        );
        $result = $service->createReleases(null, 10, false);

        $releaseId = (int) DB::table('releases')->value('id');
        $this->assertSame(['added' => 1, 'dupes' => 0], $result);
        $this->assertSame($releaseId, (int) DB::table('collections')->where('id', 320)->value('releases_id'));
        $this->assertSame(CollectionFileCheckStatus::Inserted->value, (int) DB::table('collections')->where('id', 320)->value('filecheck'));
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

    private function insertBareCollection(int $id, int $groupId, \DateTimeInterface $dateadded, ?\DateTimeInterface $lastSeenAt): void
    {
        DB::table('collections')->insert([
            'id' => $id,
            'subject' => 'Collection.'.$id,
            'fromname' => 'poster@example.com',
            'dateadded' => $dateadded->format('Y-m-d H:i:s'),
            'last_seen_at' => $lastSeenAt?->format('Y-m-d H:i:s'),
            'groups_id' => $groupId,
            'totalfiles' => 1,
            'filesize' => 0,
            'filecheck' => CollectionFileCheckStatus::Default->value,
            'collectionhash' => 'hash-'.$id,
            'collection_regexes_id' => 0,
            'noise' => '',
        ]);
    }

    private function insertBinary(int $id, int $collectionId): void
    {
        DB::table('binaries')->insert([
            'id' => $id,
            'name' => 'Binary.'.$id,
            'collections_id' => $collectionId,
            'totalparts' => 1,
        ]);
    }

    /**
     * @return list<int>
     */
    private function collectionIds(): array
    {
        return DB::table('collections')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    private function seedConfiguration(): void
    {
        ConfigurationTestBuilder::installDefaults();
        ConfigurationTestBuilder::update(ConfigurationDomain::Ingestion, [
            'part_retention_hours' => 1,
            'collection_timeout_hours' => 24,
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
        DB::statement('CREATE TABLE release_nzb_passwords (
            releases_id INTEGER PRIMARY KEY,
            password VARCHAR(255) NOT NULL
        )');
        DB::statement('CREATE TABLE collections (
            id INTEGER PRIMARY KEY,
            subject VARCHAR(255),
            fromname VARCHAR(255),
            date DATETIME NULL,
            dateadded DATETIME NULL,
            last_seen_at DATETIME NULL,
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
        DB::statement('CREATE TABLE release_regexes (releases_id INTEGER, collection_regex_id INTEGER, naming_regex_id INTEGER, PRIMARY KEY (releases_id, collection_regex_id, naming_regex_id))');
        DB::statement('CREATE TABLE releases_groups (releases_id INTEGER, groups_id INTEGER, PRIMARY KEY (releases_id, groups_id))');
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.test']);
        DB::table('categories')->insert(['id' => 1, 'title' => 'Misc', 'parent_categories_id' => null]);
    }
}
