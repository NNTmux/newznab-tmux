<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ProcessReleasesCommand;
use App\Console\Commands\UpdatePerGroup;
use App\Enums\CollectionFileCheckStatus;
use App\Enums\ConfigurationDomain;
use App\Models\Release;
use App\Services\Binaries\BinariesConfig;
use App\Services\CollectionCleanupService;
use App\Services\NNTP\NNTPService;
use App\Services\Nzb\NzbCreationCandidateQuery;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseCleaningService;
use App\Services\ReleaseCreationService;
use App\Services\ReleaseProcessingService;
use App\Services\Releases\ReleaseDuplicateFinder;
use App\Services\Tmux\CollectionDowntimeCompensator;
use App\Services\Tmux\CollectionReconciliationBarrier;
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
    public function test_collection_work_is_capped_while_priority_and_background_resume_independently(?int $groupId, string $kind): void
    {
        foreach (range(1, 60) as $id) {
            $this->insertCollection($id, size: match ($kind) {
                'invalid' => 10, 'zero' => 0, default => 5000
            });
        }
        $this->insertCollection(61);
        $firstCreation = new WindowTestReleaseCreationService(failAll: $kind === 'failed');
        $first = $this->service($firstCreation)->processReleaseCreationBatches($groupId);

        $this->assertSame($kind === 'failed' ? 0 : 1, $first['releases']);
        $this->assertCount(20, $firstCreation->windows);
        $this->assertTrue(DB::table('collections')->where('id', 61)->exists());

        if ($kind !== 'failed') {
            $this->assertSame([61], DB::table('releases')->pluck('id')->all());
            DB::table('collections')->where('id', 60)->update(['filesize' => 5000]);
        }
        $secondCreation = new WindowTestReleaseCreationService;
        $second = $this->service($secondCreation)->processReleaseCreationBatches($groupId);

        if ($kind === 'failed') {
            $this->assertSame(4, $second['releases']);
            $this->assertSame([58, 59, 60, 61], DB::table('releases')->orderBy('id')->pluck('id')->all());
            $this->assertSame([61], $secondCreation->windows[0]);
            $this->service(new WindowTestReleaseCreationService)->processReleaseCreationBatches($groupId);
            $this->assertSame(61, DB::table('releases')->count(), 'Completed epochs must revisit earlier failed inserts.');
        } else {
            $this->assertSame(1, $second['releases']);
            $this->assertSame([60, 61], DB::table('releases')->orderBy('id')->pluck('id')->all());
            $this->assertLessThan(20, count($secondCreation->windows), 'Background cleanup resumes rather than rescanning the zero-sized prefix.');
        }
    }

    public function test_group_and_global_cursors_are_independent_of_other_groups_and_orphan_cleanup(): void
    {
        foreach (range(101, 160) as $id) {
            $this->insertCollection($id, size: 0);
        }
        $this->insertCollection(161);
        foreach (range(1, 6) as $id) {
            $this->insertCollection($id, group: 2);
        }
        Cache::forever(CollectionCleanupService::ORPHAN_SWEEP_CURSOR, 9999);
        $creation = new WindowTestReleaseCreationService;

        $this->assertSame(1, $this->service($creation)->processReleaseCreationBatches(1)['releases']);
        $this->assertSame(6, $this->service($creation)->processReleaseCreationBatches(null)['releases']);
        $this->assertSame([1, 2, 3, 4, 5, 6, 161], DB::table('releases')->orderBy('id')->pluck('id')->all(), 'Global priority must start independently of the group cursor.');
        DB::table('collections')->where('id', 160)->update(['filesize' => 5000]);
        $resumedCreation = new WindowTestReleaseCreationService;
        $this->assertSame(1, $this->service($resumedCreation)->processReleaseCreationBatches(1)['releases']);
        $this->assertSame([[160], [158, 159]], $resumedCreation->windows, 'The group background cursor must not be replaced by the global cursor.');
        $this->assertSame(9999, Cache::get(CollectionCleanupService::ORPHAN_SWEEP_CURSOR));
    }

    /** @return array<string, array{?int}> */
    public static function snapshotScopes(): array
    {
        return ['group' => [1], 'global' => [null]];
    }

    #[DataProvider('snapshotScopes')]
    public function test_numeric_eligible_collections_beyond_twenty_invalid_windows_are_created_before_cleanup(?int $groupId): void
    {
        foreach (range(1, 63) as $id) {
            $this->insertCollection($id, size: 10);
        }
        $this->insertCollection(64);
        $createdBeforeCleanup = false;
        $totals = $this->service(new WindowTestReleaseCreationService)->processReleaseCreationBatches($groupId, function () use (&$createdBeforeCleanup): void {
            if (DB::table('releases')->where('id', 64)->exists() && DB::table('collections')->where('id', 1)->exists()) {
                $createdBeforeCleanup = true;
            }
        });

        $this->assertSame(1, $totals['releases']);
        $this->assertSame([64], DB::table('releases')->pluck('id')->all());
        $this->assertTrue($createdBeforeCleanup);
        $this->assertGreaterThan(0, DB::table('collections')->where('filesize', 10)->count(), 'Background cleanup must leave a bounded remainder.');
        $this->assertLessThan(63, DB::table('collections')->where('filesize', 10)->count(), 'Background cleanup must also advance.');
    }

    #[DataProvider('snapshotScopes')]
    public function test_size_order_does_not_strand_an_older_larger_candidate_after_processing_the_highest_id(?int $groupId): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Ingestion, ['max_releases_created' => 1]);
        foreach (range(1, 63) as $id) {
            $this->insertCollection($id, size: 10);
        }
        $this->insertCollection(64, size: 9000);
        $this->insertCollection(65, size: 5000);

        $totals = $this->service(new WindowTestReleaseCreationService)->processReleaseCreationBatches($groupId);

        $this->assertSame(2, $totals['releases']);
        $this->assertSame([64, 65], DB::table('releases')->orderBy('id')->pluck('id')->all());
    }

    /** @return array<string, array{?int}> */
    public static function inactiveFlags(): array
    {
        return ['inactive' => [0], 'null flag' => [null]];
    }

    #[DataProvider('inactiveFlags')]
    public function test_global_priority_preserves_inactive_groups_numeric_cleanup_exemption(?int $active): void
    {
        DB::table('usenet_groups')->where('id', 2)->update(['active' => $active, 'minsizetoformrelease' => 30000, 'minfilestoformrelease' => 5]);
        foreach (range(1, 63) as $id) {
            $this->insertCollection($id, size: 10);
        }
        $this->insertCollection(64, size: 20000, files: 1, group: 2);

        $totals = $this->service(new WindowTestReleaseCreationService)->processReleaseCreationBatches(null);

        $this->assertSame(1, $totals['releases']);
        $this->assertSame([64], DB::table('releases')->pluck('id')->all());
    }

    public function test_priority_keeps_exact_numeric_boundaries_and_still_rejects_failed_rules(): void
    {
        $this->insertCollection(1, size: 999);
        $this->insertCollection(2, size: 1000);
        $this->insertCollection(3, size: 10000);
        $this->insertCollection(4, size: 10001);
        $this->insertCollection(5, files: 1);

        $totals = $this->service(new WindowTestReleaseCreationService)->processReleaseCreationBatches(1);

        $this->assertSame(2, $totals['releases']);
        $this->assertSame([2, 3], DB::table('releases')->orderBy('id')->pluck('id')->all());
        $this->assertSame(0, DB::table('collections')->whereIn('id', [1, 4, 5])->count());
    }

    public function test_global_priority_bounds_empty_group_probes_and_resumes_its_frozen_group_list(): void
    {
        foreach (range(3, 22) as $id) {
            DB::table('usenet_groups')->insert(['id' => $id, 'name' => 'alt.test.'.$id]);
        }
        foreach (range(1, 60) as $id) {
            $this->insertCollection($id, size: 10);
        }
        foreach (range(2, 21) as $groupId) {
            $this->insertCollection(100 + $groupId, size: 10, group: $groupId);
        }
        $this->insertCollection(10000, group: 22);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $first = $this->service(new WindowTestReleaseCreationService)->processReleaseCreationBatches(null);
            $firstQueries = DB::getQueryLog();
            DB::flushQueryLog();
            $second = $this->service(new WindowTestReleaseCreationService)->processReleaseCreationBatches(null);
            $secondQueries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
        $priorityQueries = static fn (array $queries): array => array_values(array_filter($queries, static fn (array $query): bool => str_contains($query['query'], 'order by "filesize" asc')));
        $firstProbes = $priorityQueries($firstQueries);
        $secondProbes = $priorityQueries($secondQueries);

        $this->assertSame(0, $first['releases']);
        $this->assertCount(20, $firstProbes);
        $this->assertSame(1, $second['releases']);
        $this->assertSame([10000], DB::table('releases')->pluck('id')->all());
        $this->assertCount(2, $secondProbes);
        $this->assertSame([21, 22], array_map(static fn (array $query): int => (int) $query['bindings'][0], $secondProbes));
    }

    #[DataProvider('snapshotScopes')]
    public function test_new_arrivals_cannot_extend_an_unfinished_snapshot_and_starve_failed_rows(?int $groupId): void
    {
        foreach (range(1, 63) as $id) {
            $this->insertCollection($id, size: 4000);
        }
        $this->insertCollection(64, size: 9000);
        $this->insertCollection(65, size: 5000);
        $first = $this->service(new WindowTestReleaseCreationService(failAll: true))->processReleaseCreationBatches($groupId);
        $this->assertSame(0, $first['releases']);

        foreach (range(66, 185) as $id) {
            $this->insertCollection($id, size: 4500);
        }
        $secondCreation = new WindowTestReleaseCreationService;
        $second = $this->service($secondCreation)->processReleaseCreationBatches($groupId);

        $this->assertGreaterThan(0, $second['releases']);
        $this->assertTrue(DB::table('releases')->where('id', 64)->exists(), 'The old larger candidate must be processed before new smaller arrivals.');
        $this->assertTrue(DB::table('releases')->where('id', 65)->exists());
        $this->assertSame(0, DB::table('releases')->where('id', '>', 65)->count(), 'Both readers must finish their original frozen ceiling before accepting arrivals.');

        foreach (range(186, 305) as $id) {
            $this->insertCollection($id, size: 4500);
        }
        $third = $this->service(new WindowTestReleaseCreationService)->processReleaseCreationBatches($groupId);

        $this->assertGreaterThan(0, $third['releases']);
        $this->assertTrue(DB::table('releases')->where('id', 1)->exists(), 'A completed epoch must wrap and retry old failed rows despite continued arrivals.');
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

    #[DataProvider('processingCommands')]
    public function test_ready_releases_are_created_before_incomplete_collection_work(string $commandClass): void
    {
        $this->insertCollection(5);
        $test = $this;
        $this->app->instance(CollectionReconciliationBarrier::class, new class($test) implements CollectionReconciliationBarrier
        {
            public function __construct(private ReleaseCollectionWindowTest $test) {}

            public function withReconciliationBarrier(int $delayHours, callable $work): mixed
            {
                $this->test->assertTrue(DB::table('releases')->where('id', 5)->exists(), 'Ready releases must not wait for cleanup or reconciliation.');

                return $work();
            }
        });
        $command = new $commandClass($this->service(new WindowTestReleaseCreationService));
        $method = $command instanceof ProcessReleasesCommand ? 'processReleasesForGroup' : 'processReleases';
        (new ReflectionMethod($command, $method))->invoke($command, '1');

        $this->assertSame(1, DB::table('releases')->where('id', 5)->value('nzbstatus'));
    }

    public function test_reconciled_pages_create_releases_outside_the_barrier_before_the_next_page(): void
    {
        foreach ([1, 2, 3] as $id) {
            $this->insertCollection($id);
            DB::table('collections')->where('id', $id)->update(['filecheck' => 2]);
            DB::table('parts')->insert(['binaries_id' => $id, 'size' => 5000]);
        }
        $barrier = new class($this) implements CollectionReconciliationBarrier
        {
            public bool $locked = false;

            private int $pages = 0;

            public function __construct(private ReleaseCollectionWindowTest $test) {}

            public function withReconciliationBarrier(int $delayHours, callable $work): mixed
            {
                if ($this->pages++ >= 2) {
                    $this->test->assertTrue(DB::table('releases')->where('id', 1)->exists(), 'The first reconciled page must create releases before the next page starts.');
                }
                $this->locked = true;
                try {
                    return $work();
                } finally {
                    $this->locked = false;
                }
            }
        };
        $this->app->instance(CollectionReconciliationBarrier::class, $barrier);
        $totals = $this->service(new WindowTestReleaseCreationService, binariesConfig: new BinariesConfig(reconcileBatchSize: 2))
            ->processCollectionsAndReleases(1, function () use ($barrier): void {
                $this->assertFalse($barrier->locked, 'Release creation callbacks must run after releasing the ingestion barrier.');
            });

        $this->assertSame([1, 2, 3], DB::table('releases')->orderBy('id')->pluck('id')->all());
        $this->assertSame(3, $totals['releases']);
        $this->assertSame(3, $totals['nzbs']);
    }

    public function test_fresh_pages_validate_all_rules_even_when_priority_cursors_are_ahead(): void
    {
        foreach ([1, 2, 3, 4] as $id) {
            $this->insertCollection($id, binaryName: $id === 2 ? 'Example.par2' : 'Example.rar');
            DB::table('collections')->where('id', $id)->update(['filecheck' => 2]);
            DB::table('parts')->insert(['binaries_id' => $id, 'size' => $id === 3 ? 10 : 5000]);
        }
        $this->insertCollection(100, size: 10);
        Cache::put('cbp:release-window:cursor:group:1', ['last_id' => 50, 'max_id' => 100], 3600);
        Cache::put('cbp:release-priority-window:cursor:group:1', [
            'max_id' => 100, 'group_ids' => [1], 'position' => 0,
            'cursors' => [1 => ['last_size' => 9999, 'last_id' => 50]], 'exhausted' => [],
        ], 3600);
        $creation = new WindowTestReleaseCreationService;
        $seenDuringReconciliation = false;
        $totals = $this->service($creation, binariesConfig: new BinariesConfig(reconcileBatchSize: 2))
            ->processCollectionsAndReleases(1, function () use (&$seenDuringReconciliation): void {
                if (DB::table('releases')->where('id', 1)->exists()
                    && DB::table('collections')->where('id', 4)->value('filecheck') === 2) {
                    $seenDuringReconciliation = true;
                }
            });

        $this->assertTrue($seenDuringReconciliation);
        $this->assertSame([1, 4], DB::table('releases')->orderBy('id')->pluck('id')->all());
        $this->assertSame(2, $totals['releases']);
        $this->assertSame(0, DB::table('collections')->whereIn('id', [2, 3])->count());
        foreach ($creation->windows as $window) {
            $this->assertLessThanOrEqual(3, count($window));
        }
    }

    public function test_preparation_failure_does_not_size_or_create_unreconciled_collections(): void
    {
        $this->insertCollection(1);
        $this->insertCollection(2);
        DB::table('collections')->where('id', 2)->update(['filecheck' => 2]);
        $this->app->instance(CollectionReconciliationBarrier::class, new class implements CollectionReconciliationBarrier
        {
            public function withReconciliationBarrier(int $delayHours, callable $work): mixed
            {
                throw new \RuntimeException('reconciliation unavailable');
            }
        });
        try {
            $this->service(new WindowTestReleaseCreationService)->processCollectionsAndReleases(1);
            $this->fail('Preparation errors must propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('reconciliation unavailable', $e->getMessage());
        }

        $this->assertSame([1], DB::table('releases')->pluck('id')->all());
        $this->assertSame(2, DB::table('collections')->where('id', 2)->value('filecheck'));
    }

    public function test_legacy_service_compensates_waiting_rows_before_initial_retention_cleanup(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Ingestion, [
            'collection_delay_hours' => 2, 'part_retention_hours' => 24,
        ]);
        DB::statement('ALTER TABLE collections ADD COLUMN last_seen_at DATETIME NULL');
        DB::statement('ALTER TABLE releases ADD COLUMN iscategorized INTEGER DEFAULT 1');
        $this->insertCollection(1);
        $this->insertCollection(2);
        DB::table('collections')->where('id', 2)->update([
            'filecheck' => 0, 'dateadded' => now()->subDays(4)->subHour(), 'added' => now()->subDays(4)->subHour(),
        ]);
        $marker = tempnam(sys_get_temp_dir(), 'release-downtime-');
        file_put_contents($marker, (string) now()->subDays(4)->getTimestamp());
        $this->app->instance(CollectionReconciliationBarrier::class, new CollectionDowntimeCompensator($marker));
        $service = $this->service(new WindowTestReleaseCreationService);
        try {
            $totals = (new ReflectionMethod($service, 'runReleaseCreationLoop'))->invoke($service, 1, 1, 0, new NNTPService);

            $this->assertSame(1, $totals['releases']);
            $this->assertSame(0, DB::table('collections')->where('id', 2)->value('filecheck'));
            $this->assertGreaterThan(now()->subHours(2)->format('Y-m-d H:i:s'), DB::table('collections')->where('id', 2)->value('dateadded'));
            $this->assertTrue(DB::table('binaries')->where('collections_id', 2)->exists());
        } finally {
            foreach (glob($marker.'*') as $file) {
                unlink($file);
            }
        }
    }

    public function test_retention_callbacks_wait_for_old_complete_parts_in_later_pages(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Ingestion, ['part_retention_hours' => 24]);
        foreach ([1, 2, 3] as $id) {
            $this->insertCollection($id);
            DB::table('collections')->where('id', $id)->update([
                'filecheck' => 2, 'dateadded' => now()->subDays(4), 'added' => now()->subDays(4),
            ]);
            DB::table('parts')->insert(['binaries_id' => $id, 'size' => 5000]);
        }
        $service = $this->service(new WindowTestReleaseCreationService, binariesConfig: new BinariesConfig(reconcileBatchSize: 2));
        $totals = $service->processCollectionsAndReleases(1, afterPass: fn () => $service->deleteCollections(1));

        $this->assertSame([1, 2, 3], DB::table('releases')->orderBy('id')->pluck('id')->all());
        $this->assertSame(3, $totals['releases']);
        $this->assertSame(0, DB::table('collections')->count());
    }

    public function test_idle_pipeline_runs_legacy_callbacks_once(): void
    {
        $categorized = 0;
        $postProcessed = 0;
        $totals = $this->service(new WindowTestReleaseCreationService)->processCollectionsAndReleases(1,
            function () use (&$categorized): void {
                $categorized++;
            },
            function () use (&$postProcessed): void {
                $postProcessed++;
            },
        );

        $this->assertSame(1, $categorized);
        $this->assertSame(1, $postProcessed);
        $this->assertSame(1, $totals['iterations']);
    }

    public function test_prepared_release_does_not_add_empty_callback_passes(): void
    {
        $this->insertCollection(1);
        DB::table('collections')->where('id', 1)->update(['filecheck' => 2]);
        DB::table('parts')->insert(['binaries_id' => 1, 'size' => 5000]);
        $passes = 0;
        $totals = $this->service(new WindowTestReleaseCreationService)->processCollectionsAndReleases(1,
            afterPass: function () use (&$passes): void {
                $passes++;
            },
        );

        $this->assertSame(1, $totals['releases']);
        $this->assertSame(1, $passes);
        $this->assertSame(1, $totals['iterations']);
    }

    public function test_pipeline_still_drains_nzb_only_batches_without_collections(): void
    {
        foreach (range(1, 7) as $id) {
            DB::table('releases')->insert(['id' => $id, 'guid' => 'release-'.$id, 'name' => 'Release.'.$id, 'groups_id' => 1, 'postdate' => now()]);
        }
        $totals = $this->service(new WindowTestReleaseCreationService)->processCollectionsAndReleases(1);

        $this->assertSame(0, $totals['releases']);
        $this->assertSame(7, $totals['nzbs']);
        $this->assertSame(7, DB::table('releases')->where('nzbstatus', 1)->count());
    }

    public function test_ready_pass_creates_valid_releases_without_draining_undersized_collections_or_other_groups(): void
    {
        $this->insertCollection(1, size: 10);
        $this->insertCollection(2, group: 2);
        $this->insertCollection(3, binaryName: 'Example.par2');
        $this->insertCollection(4);
        $totals = $this->service(new WindowTestReleaseCreationService)->processReadyReleaseWindow(1);

        $this->assertSame(1, $totals['releases']);
        $this->assertSame(1, $totals['nzbs']);
        $this->assertSame([4], DB::table('releases')->pluck('id')->all());
        $this->assertSame(3, (int) DB::table('collections')->where('id', 1)->value('filecheck'));
        $this->assertSame(3, (int) DB::table('collections')->where('id', 2)->value('filecheck'));
        $this->assertFalse(DB::table('collections')->where('id', 3)->exists());
    }

    public function test_ready_pass_limits_nzb_work_and_the_full_pass_still_drains_the_rest(): void
    {
        foreach (range(1, 7) as $id) {
            DB::table('releases')->insert(['id' => $id, 'guid' => 'release-'.$id, 'name' => 'Release.'.$id, 'groups_id' => 1, 'postdate' => now()]);
        }
        $service = $this->service(new WindowTestReleaseCreationService);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $ready = $service->processReadyReleaseWindow(1);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
        $this->assertFalse(collect($queries)->contains(static fn (array $query): bool => preg_match('/select count\(\*\).*from ["`]?releases/i', $query['query']) === 1));
        $this->assertSame(3, $ready['nzbs']);
        $this->assertSame(4, DB::table('releases')->where('nzbstatus', 0)->count());
        $full = $service->processReleaseCreationBatches(1);
        $this->assertSame(4, $full['nzbs']);
        $this->assertSame(7, DB::table('releases')->where('nzbstatus', 1)->count());
    }

    public function test_ready_only_command_does_not_start_reconciliation_or_cleanup(): void
    {
        $this->insertCollection(1, size: 10);
        $this->insertCollection(2);
        $this->app->instance(ReleaseProcessingService::class, $this->service(new WindowTestReleaseCreationService));
        $this->artisan('releases:process', ['groupId' => '1', '--ready-only' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('releases')->where('id', 2)->where('nzbstatus', 1)->count());
        $this->assertSame(3, (int) DB::table('collections')->where('id', 1)->value('filecheck'));
    }

    public function test_ready_pass_caps_both_creation_and_nzbs_at_fifty_and_full_processing_preserves_remaining_work(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Ingestion, ['max_releases_created' => 100]);
        foreach (range(1, 60) as $id) {
            $this->insertCollection($id);
        }
        $service = $this->service(new WindowTestReleaseCreationService);
        $ready = $service->processReadyReleaseWindow(1);
        $this->assertSame(50, $ready['releases']);
        $this->assertSame(50, $ready['nzbs']);
        $this->assertSame(10, DB::table('collections')->where('filecheck', 3)->count());
        $full = $service->processReleaseCreationBatches(1);
        $this->assertSame(10, $full['releases']);
        $this->assertSame(10, $full['nzbs']);
    }

    public function test_ready_window_publishes_new_releases_before_newer_nzb_backlog(): void
    {
        $this->insertCollection(100);
        foreach (range(1, 3) as $id) {
            DB::table('releases')->insert(['id' => $id, 'guid' => 'backlog-'.$id, 'name' => 'Backlog', 'groups_id' => 1, 'postdate' => now()->addDay()]);
        }
        $result = $this->service(new WindowTestReleaseCreationService)->processReadyReleaseWindow(1);
        $this->assertSame(1, $result['releases']);
        $this->assertSame(1, (int) DB::table('releases')->where('id', 100)->value('nzbstatus'));
        $this->assertSame(3, $result['nzbs']);
    }

    public function test_ready_snapshot_advances_past_failed_rows_and_new_smaller_arrivals(): void
    {
        foreach (range(1, 4) as $id) {
            $this->insertCollection($id);
        }
        $this->service(new WindowTestReleaseCreationService(failAll: true))->processReadyReleaseWindow(1);
        foreach (range(100, 102) as $id) {
            $this->insertCollection($id, size: 2000);
        }
        $next = $this->service(new WindowTestReleaseCreationService)->processReadyReleaseWindow(1);
        $this->assertSame(1, $next['releases']);
        $this->assertSame([4], DB::table('releases')->pluck('id')->all());
        $retry = $this->service(new WindowTestReleaseCreationService)->processReadyReleaseWindow(1);
        $this->assertSame(3, $retry['releases']);
        $this->assertSame(3, DB::table('releases')->whereIn('id', [100, 101, 102])->count());
    }

    public function test_empty_saved_snapshot_clears_before_processing_new_arrivals(): void
    {
        foreach (range(1, 4) as $id) {
            $this->insertCollection($id);
        }
        $this->service(new WindowTestReleaseCreationService(failAll: true))->processReadyReleaseWindow(1);
        DB::table('collections')->delete();
        $this->assertSame(0, $this->service(new WindowTestReleaseCreationService)->processReadyReleaseWindow(1)['releases']);
        $this->insertCollection(100, size: 2000);
        $this->assertSame(1, $this->service(new WindowTestReleaseCreationService)->processReadyReleaseWindow(1)['releases']);
    }

    private function service(WindowTestReleaseCreationService $creation, ?CollectionCleanupService $cleanup = null, ?BinariesConfig $binariesConfig = null): ReleaseProcessingService
    {
        return (new ReleaseProcessingService(
            nzb: new WindowTestNzbService(new CollectionCleanupService),
            releaseCreationService: $creation,
            collectionCleanupService: $cleanup ?? new CollectionCleanupService,
            binariesConfig: $binariesConfig,
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
