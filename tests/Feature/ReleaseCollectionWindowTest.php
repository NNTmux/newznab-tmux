<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ProcessReleasesCommand;
use App\Console\Commands\UpdatePerGroup;
use App\Enums\CollectionFileCheckStatus;
use App\Enums\ConfigurationDomain;
use App\Models\Release;
use App\Services\CollectionCleanupService;
use App\Services\Nzb\NzbCreationCandidateQuery;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseCleaningService;
use App\Services\ReleaseCreationService;
use App\Services\ReleaseProcessingService;
use App\Services\Releases\ReleaseDuplicateFinder;
use App\Support\Data\NzbCreationResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Support\ConfigurationTestBuilder;
use Tests\TestCase;

class ReleaseCollectionWindowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'nntmux.echocli' => false]);
        DB::purge();
        DB::reconnect();
        DB::connection()->getPdo()->sqliteCreateFunction('REGEXP', static fn (string $pattern, ?string $value): int => (int) preg_match('~'.str_replace('\\\\', '\\', $pattern).'~', (string) $value));
        DB::statement('CREATE TABLE usenet_groups (id INTEGER PRIMARY KEY, name TEXT, active INTEGER DEFAULT 1, minsizetoformrelease INTEGER DEFAULT 1000, minfilestoformrelease INTEGER DEFAULT 2)');
        DB::statement('CREATE TABLE collections (id INTEGER PRIMARY KEY, groups_id INTEGER, filecheck INTEGER DEFAULT 3, filesize INTEGER DEFAULT 5000, totalfiles INTEGER DEFAULT 2, subject TEXT DEFAULT "Example.Release", fromname TEXT DEFAULT "poster@example.test", date DATETIME, dateadded DATETIME, added DATETIME, xref TEXT DEFAULT "", collection_regexes_id INTEGER DEFAULT 0, releases_id INTEGER NULL)');
        DB::statement('CREATE TABLE binaries (id INTEGER PRIMARY KEY, collections_id INTEGER, name TEXT DEFAULT "Example.rar", totalparts INTEGER DEFAULT 1, currentparts INTEGER DEFAULT 1, partsize INTEGER DEFAULT 5000, partcheck INTEGER DEFAULT 1)');
        DB::statement('CREATE TABLE parts (binaries_id INTEGER, size INTEGER DEFAULT 5000)');
        DB::statement('CREATE TABLE root_categories (id INTEGER PRIMARY KEY, title TEXT, status INTEGER DEFAULT 1)');
        DB::statement('CREATE TABLE categories (id INTEGER PRIMARY KEY, title TEXT, root_categories_id INTEGER)');
        DB::statement('CREATE TABLE releases (id INTEGER PRIMARY KEY, guid TEXT, name TEXT, searchname TEXT, groups_id INTEGER, categories_id INTEGER DEFAULT 1, postdate DATETIME, nzbstatus INTEGER DEFAULT 0, predb_id INTEGER DEFAULT 0, size INTEGER DEFAULT 5000, fromname TEXT)');
        DB::table('usenet_groups')->insert([['id' => 1, 'name' => 'alt.test.one'], ['id' => 2, 'name' => 'alt.test.two']]);
        DB::table('root_categories')->insert(['id' => 1, 'title' => 'Other']);
        DB::table('categories')->insert(['id' => 1, 'title' => 'Misc', 'root_categories_id' => 1]);
        ConfigurationTestBuilder::update(ConfigurationDomain::Ingestion, [
            'max_releases_created' => 3, 'min_size_to_form_release' => 500,
            'max_size_to_form_release' => 10000, 'min_files_to_form_release' => 1,
        ]);
        NzbCreationCandidateQuery::flushCapabilityCache();
    }

    protected function tearDown(): void
    {
        NzbCreationCandidateQuery::flushCapabilityCache();
        parent::tearDown();
    }

    /** @return array<string, array{?int, list<int>}> */
    public static function groupScopes(): array
    {
        return ['one group' => [1, [5, 9]], 'all groups' => [null, [5, 7, 9]]];
    }

    #[DataProvider('groupScopes')]
    public function test_invalid_only_windows_do_not_stop_later_valid_releases_or_drain_every_invalid_row_first(?int $groupId, array $expected): void
    {
        $this->seedMixedCollections();
        $creation = new WindowTestReleaseCreationService;
        $service = $this->service($creation);
        $createdBeforeTailCleanup = false;
        $totals = $service->processReleaseCreationBatches($groupId, function () use (&$createdBeforeTailCleanup): void {
            if (DB::table('releases')->where('id', 5)->exists() && DB::table('collections')->where('id', 10)->exists()) {
                $createdBeforeTailCleanup = true;
            }
        });

        $this->assertSame($expected, DB::table('releases')->orderBy('id')->pluck('id')->all());
        $this->assertSame(count($expected), $totals['releases']);
        $this->assertTrue($createdBeforeTailCleanup);
        $this->assertSame(CollectionFileCheckStatus::Sized->value, (int) DB::table('collections')->where('id', 8)->value('filecheck'));
        $this->assertSame(0, DB::table('collections')->whereIn('id', [1, 2, 3, 4, 6, 10])->count());
        if ($groupId !== null) {
            $this->assertSame(CollectionFileCheckStatus::Sized->value, (int) DB::table('collections')->where('id', 7)->value('filecheck'));
        }
        foreach ($creation->windows as $window) {
            $this->assertLessThanOrEqual(3, count($window));
        }
    }

    public function test_rejected_ids_are_returned_even_when_only_some_deletes_succeed(): void
    {
        $this->seedMixedCollections();
        $cleanup = new class extends CollectionCleanupService
        {
            public function deleteCollectionsAndDescendants(array $collectionIds, string $label = 'CBP cleanup', bool $echoCLI = false): int
            {
                return parent::deleteCollectionsAndDescendants([reset($collectionIds)], $label, $echoCLI);
            }
        };
        $service = $this->service(new WindowTestReleaseCreationService, $cleanup);

        $rejected = $service->deleteUnwantedCollections(1, [1, 6, 10]);

        sort($rejected);
        $this->assertSame([1, 6, 10], $rejected);
        $this->assertSame(2, DB::table('collections')->whereIn('id', [1, 6, 10])->count());
        $this->assertTrue(DB::table('collections')->where('id', 2)->exists(), 'Other unwanted collections are outside this window.');
    }

    public function test_failed_deletes_never_create_invalid_releases_and_failed_inserts_do_not_spin(): void
    {
        $this->seedMixedCollections();
        $cleanup = new class extends CollectionCleanupService
        {
            public function deleteCollectionsAndDescendants(array $collectionIds, string $label = 'CBP cleanup', bool $echoCLI = false): int
            {
                return 0;
            }
        };
        $creation = new WindowTestReleaseCreationService(failedId: 5);
        $totals = $this->service($creation, $cleanup)->processReleaseCreationBatches(1);

        $this->assertSame([9], DB::table('releases')->pluck('id')->all());
        $this->assertSame(1, $totals['releases']);
        $this->assertSame(6, DB::table('collections')->whereIn('id', [1, 2, 3, 4, 6, 10])->count());
        $this->assertSame(1, count(array_filter($creation->windows, static fn (array $ids): bool => in_array(5, $ids, true))));
    }

    public function test_initial_max_id_keeps_new_incoming_collections_for_the_next_pass(): void
    {
        $this->insertCollection(1);
        $creation = new WindowTestReleaseCreationService;
        $inserted = false;
        $this->service($creation)->processReleaseCreationBatches(1, function () use (&$inserted): void {
            if (! $inserted) {
                $this->insertCollection(100);
                $inserted = true;
            }
        });

        $this->assertSame([1], DB::table('releases')->pluck('id')->all());
        $this->assertSame(CollectionFileCheckStatus::Sized->value, (int) DB::table('collections')->where('id', 100)->value('filecheck'));
    }

    /** @return array<string, array{?int, string}> */
    public static function longPrefixes(): array
    {
        return [
            'group invalid' => [1, 'invalid'], 'global invalid' => [null, 'invalid'],
            'group zero' => [1, 'zero'], 'global zero' => [null, 'zero'],
            'group failed inserts' => [1, 'failed'], 'global failed inserts' => [null, 'failed'],
        ];
    }

    #[DataProvider('longPrefixes')]
    public function test_collection_work_is_capped_and_resumes_past_long_unproductive_prefixes(?int $groupId, string $kind): void
    {
        foreach (range(1, 60) as $id) {
            $this->insertCollection($id, size: match ($kind) {
                'invalid' => 10, 'zero' => 0, default => 5000
            });
        }
        $this->insertCollection(61);
        $firstCreation = new WindowTestReleaseCreationService(failAll: $kind === 'failed');
        $first = $this->service($firstCreation)->processReleaseCreationBatches($groupId);

        $this->assertSame(0, $first['releases']);
        $this->assertCount(20, $firstCreation->windows);
        $this->assertTrue(DB::table('collections')->where('id', 61)->exists());

        $secondCreation = new WindowTestReleaseCreationService;
        $second = $this->service($secondCreation)->processReleaseCreationBatches($groupId);

        $this->assertSame(1, $second['releases']);
        $this->assertSame([61], DB::table('releases')->pluck('id')->all());
        $this->assertSame([[61]], $secondCreation->windows);
    }

    public function test_group_and_global_cursors_are_independent_of_other_groups_and_orphan_cleanup(): void
    {
        foreach (range(101, 160) as $id) {
            $this->insertCollection($id, size: 0);
        }
        $this->insertCollection(161);
        $this->insertCollection(1, group: 2);
        Cache::forever(CollectionCleanupService::ORPHAN_SWEEP_CURSOR, 9999);
        $creation = new WindowTestReleaseCreationService;

        $this->assertSame(0, $this->service($creation)->processReleaseCreationBatches(1)['releases']);
        $this->assertSame(1, $this->service($creation)->processReleaseCreationBatches(null)['releases']);
        $this->assertSame([1], DB::table('releases')->pluck('id')->all(), 'Global processing must start independently of the group cursor.');
        $this->assertSame(1, $this->service($creation)->processReleaseCreationBatches(1)['releases']);
        $this->assertSame([1, 161], DB::table('releases')->orderBy('id')->pluck('id')->all());
        $this->assertSame(9999, Cache::get(CollectionCleanupService::ORPHAN_SWEEP_CURSOR));
    }

    /** @return array<string, array{?int}> */
    public static function snapshotScopes(): array
    {
        return ['group' => [1], 'global' => [null]];
    }

    #[DataProvider('snapshotScopes')]
    public function test_new_arrivals_cannot_extend_an_unfinished_snapshot_and_starve_failed_rows(?int $groupId): void
    {
        $this->insertCollection(1);
        foreach (range(2, 60) as $id) {
            $this->insertCollection($id, size: 0);
        }
        $this->insertCollection(61);
        $first = $this->service(new WindowTestReleaseCreationService(failedId: 1))->processReleaseCreationBatches($groupId);
        $this->assertSame(0, $first['releases']);

        foreach (range(62, 180) as $id) {
            $this->insertCollection($id, size: 0);
        }
        $secondCreation = new WindowTestReleaseCreationService;
        $second = $this->service($secondCreation)->processReleaseCreationBatches($groupId);

        $this->assertSame(1, $second['releases']);
        $this->assertSame([[61]], $secondCreation->windows, 'The resumed pass must finish its original ceiling before accepting new arrivals.');

        foreach (range(181, 300) as $id) {
            $this->insertCollection($id, size: 0);
        }
        $third = $this->service(new WindowTestReleaseCreationService)->processReleaseCreationBatches($groupId);

        $this->assertSame(1, $third['releases']);
        $this->assertSame([1, 61], DB::table('releases')->orderBy('id')->pluck('id')->all(), 'A completed epoch must wrap and retry the old failed row despite continued arrivals.');
    }

    public function test_malformed_cached_window_state_starts_a_safe_new_snapshot(): void
    {
        $this->insertCollection(1);
        Cache::forever('cbp:release-window:cursor:group:1', ['last_id' => 9999, 'max_id' => []]);

        $totals = $this->service(new WindowTestReleaseCreationService)->processReleaseCreationBatches(1);

        $this->assertSame(1, $totals['releases']);
        $this->assertSame([1], DB::table('releases')->pluck('id')->all());
    }

    public function test_cursor_wrap_revisits_failed_creations_and_deletions_after_the_snapshot_finishes(): void
    {
        $this->seedMixedCollections();
        $cleanup = new class extends CollectionCleanupService
        {
            public function deleteCollectionsAndDescendants(array $collectionIds, string $label = 'CBP cleanup', bool $echoCLI = false): int
            {
                return 0;
            }
        };
        $this->service(new WindowTestReleaseCreationService(failedId: 5), $cleanup)->processReleaseCreationBatches(1);
        $this->assertSame([9], DB::table('releases')->pluck('id')->all());

        $totals = $this->service(new WindowTestReleaseCreationService)->processReleaseCreationBatches(1);

        $this->assertSame(1, $totals['releases']);
        $this->assertSame([5, 9], DB::table('releases')->orderBy('id')->pluck('id')->all());
        $this->assertSame(0, DB::table('collections')->whereIn('id', [1, 2, 3, 4, 6, 10])->count());
    }

    public function test_nzb_only_backlog_runs_all_full_batches_and_both_hooks(): void
    {
        foreach (range(1, 7) as $id) {
            DB::table('releases')->insert(['id' => $id, 'guid' => 'existing-'.$id, 'groups_id' => 1, 'postdate' => now()]);
        }
        $creation = new WindowTestReleaseCreationService;
        $createdHooks = $finishedHooks = 0;
        $totals = $this->service($creation)->processReleaseCreationBatches(1,
            function () use (&$createdHooks): void {
                $createdHooks++;
            },
            function () use (&$finishedHooks): void {
                $finishedHooks++;
            },
        );

        $this->assertSame(7, $totals['nzbs']);
        $this->assertSame(3, $createdHooks);
        $this->assertSame(3, $finishedHooks);
        $this->assertSame([[], [], []], $creation->windows);
        $this->assertSame(0, DB::table('releases')->where('nzbstatus', 0)->count());
    }

    public function test_creation_allowlist_and_explicit_empty_list_never_fall_back_to_other_collections(): void
    {
        $this->insertCollection(1);
        $this->insertCollection(2);
        DB::table('releases')->insert(['id' => 50, 'guid' => 'duplicate', 'name' => 'Duplicate', 'searchname' => 'Duplicate', 'predb_id' => 1]);
        $cleaner = $this->createMock(ReleaseCleaningService::class);
        $cleaner->expects($this->once())->method('releaseCleaner')->willReturn(['cleansubject' => 'Duplicate', 'predb' => 1, 'properlynamed' => true]);
        $creation = new ReleaseCreationService($cleaner, new CollectionCleanupService, new ReleaseDuplicateFinder);

        $this->assertSame(['added' => 0, 'dupes' => 0], $creation->createReleases(1, 3, false, []));
        $this->assertSame(2, DB::table('collections')->count());
        $this->assertSame(['added' => 0, 'dupes' => 1], $creation->createReleases(1, 3, false, [2]));
        $this->assertSame([1], DB::table('collections')->pluck('id')->all());
    }

    /** @return array<string, array{class-string}> */
    public static function processingCommands(): array
    {
        return ['release worker' => [ProcessReleasesCommand::class], 'update group worker' => [UpdatePerGroup::class]];
    }

    #[DataProvider('processingCommands')]
    public function test_group_command_paths_create_a_valid_window_before_the_invalid_tail_is_drained(string $commandClass): void
    {
        $this->seedMixedCollections();
        $creation = new WindowTestReleaseCreationService;
        $creation->checkTailAtCreation = true;
        $command = new $commandClass($this->service($creation));
        $method = $command instanceof ProcessReleasesCommand ? 'processReleasesForGroup' : 'processReleases';
        (new ReflectionMethod($command, $method))->invoke($command, '1');

        $this->assertTrue($creation->createdBeforeTailCleanup);
        $this->assertSame([5, 9], DB::table('releases')->orderBy('id')->pluck('id')->all());
    }

    private function service(WindowTestReleaseCreationService $creation, ?CollectionCleanupService $cleanup = null): ReleaseProcessingService
    {
        return (new ReleaseProcessingService(
            nzb: new WindowTestNzbService(new CollectionCleanupService),
            releaseCreationService: $creation,
            collectionCleanupService: $cleanup ?? new CollectionCleanupService,
        ))->setEchoCLI(false);
    }

    private function seedMixedCollections(): void
    {
        $this->insertCollection(1, size: 10);
        $this->insertCollection(2, binaryName: 'Example.par2');
        $this->insertCollection(3, size: 20000);
        $this->insertCollection(4, files: 1);
        $this->insertCollection(5);
        $this->insertCollection(6, size: 10);
        $this->insertCollection(7, group: 2);
        $this->insertCollection(8, size: 0);
        $this->insertCollection(9);
        $this->insertCollection(10, size: 10);
    }

    private function insertCollection(int $id, int $size = 5000, int $files = 2, int $group = 1, string $binaryName = 'Example.rar'): void
    {
        DB::table('collections')->insert(['id' => $id, 'groups_id' => $group, 'filesize' => $size, 'totalfiles' => $files, 'dateadded' => now(), 'added' => now()]);
        DB::table('binaries')->insert(['id' => $id, 'collections_id' => $id, 'name' => $binaryName]);
    }
}

class WindowTestReleaseCreationService extends ReleaseCreationService
{
    /** @var list<list<int>> */
    public array $windows = [];

    public bool $checkTailAtCreation = false;

    public bool $createdBeforeTailCleanup = false;

    public function __construct(private readonly ?int $failedId = null, private readonly bool $failAll = false) {}

    public function createReleases(int|string|null $groupID, int $limit, bool $echoCLI, ?array $allowedIds = null): array
    {
        $this->windows[] = $allowedIds ?? [];
        $query = DB::table('collections')->where('filecheck', 3)->where('filesize', '>', 0)->orderBy('id')->limit($limit);
        if ($groupID !== null && $groupID !== '') {
            $query->where('groups_id', $groupID);
        }
        if ($allowedIds !== null) {
            $query->whereIn('id', $allowedIds);
        }
        $added = 0;
        foreach ($query->get() as $collection) {
            if ($this->failAll || $collection->id === $this->failedId) {
                continue;
            }
            DB::table('releases')->insert(['id' => $collection->id, 'name' => 'Release.'.$collection->id, 'guid' => 'release-'.$collection->id, 'groups_id' => $collection->groups_id, 'postdate' => now()]);
            DB::table('collections')->where('id', $collection->id)->update(['filecheck' => 4, 'releases_id' => $collection->id]);
            $added++;
            if ($this->checkTailAtCreation && $collection->id === 5 && DB::table('collections')->where('id', 10)->exists()) {
                $this->createdBeforeTailCleanup = true;
            }
        }

        return ['added' => $added, 'dupes' => 0];
    }
}

class WindowTestNzbService extends NzbService
{
    public function createNzbForRelease(Release $release): NzbCreationResult
    {
        DB::table('releases')->where('id', $release->id)->update(['nzbstatus' => NzbService::NZB_ADDED]);

        return NzbCreationResult::success('unused.nzb.gz', []);
    }
}
