<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Services\NNTP\NNTPService;
use App\Services\ReleaseCleaningService;
use App\Services\ReleaseCreationService;
use App\Services\ReleaseProcessingService;
use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\ManifestStore;
use App\Services\Releases\Verification\ReleaseMetadataResolver;
use App\Services\Releases\Verification\ScratchInventory;
use App\Services\Releases\Verification\VerificationResult;
use App\Services\Releases\Verification\VerifiedReleasePublisher;
use App\Services\YencService;
use App\Support\Configuration\IngestionConfigurationData;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class VerifiedCollectionPolicyTest extends ReleaseVerificationCandidatesTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('usenet_groups', function (Blueprint $table): void {
            $table->integer('minsizetoformrelease')->default(0);
            $table->integer('minfilestoformrelease')->default(0);
        });
        Schema::create('ingestion_configurations', function (Blueprint $table): void {
            $table->id();
        });
        $this->limits();
    }

    /** @return array<string, array{int, int, int, int, int}> */
    public static function headerLimits(): array
    {
        return [
            'site minimum size' => [10, 0, 0, 0, 0],
            'group minimum size' => [0, 0, 0, 10, 0],
            'site minimum files' => [0, 0, 2, 0, 0],
            'group minimum files' => [0, 0, 0, 0, 2],
        ];
    }

    #[DataProvider('headerLimits')]
    public function test_below_limit_headers_wait_for_download_then_verified_policy_filters_them(int $min, int $max, int $files, int $groupMin, int $groupFiles): void
    {
        config(['release_verification.free_bytes' => 0, 'release_verification.scratch_bytes' => 2147483648, 'release_verification.bytes_per_second' => 0]);
        $this->limits($min, $max, $files, $groupMin, $groupFiles);
        $id = $this->collectionForPolicy();
        app(ReleaseCreationService::class)->createReleases(1, 10, false);
        $candidate = DB::table('release_verification_candidates')->first();
        $this->assertSame('pending', $candidate->status);
        $this->assertFalse(DB::table('collections')->where('id', $id)->exists());
        $this->assertGreaterThan(0, (int) DB::table('release_verification_state')->value('pending_bytes'));
        $this->assertFileExists(app(ManifestStore::class)->path($candidate->manifest_path));
        $this->publicationSchema();
        $this->mock(NNTPService::class, function ($mock): void {
            $mock->shouldReceive('doConnect')->twice()->with(false)->andReturn(false, true);
            $mock->shouldReceive('verificationArticle')->once()->with('a@b', 16777216)->andReturn((new YencService)->encode('hello', 'movie.mkv'));
            $mock->shouldReceive('doQuit')->andReturn(null);
        });
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertSame('unknown', DB::table('release_verification_candidates')->value('status'));
        $this->travel(16)->minutes();
        $this->limits($min, $max, $files, $groupMin, $groupFiles);
        $this->artisan('releases:verify-candidates')->expectsOutputToContain('filtered — Verified')->assertSuccessful();
        $this->assertSame('filtered', DB::table('release_verification_candidates')->value('status'));
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertSame(0, (int) DB::table('release_verification_state')->value('pending_bytes'));
    }

    public function test_processing_filter_preserves_unverified_headers_before_release_creation(): void
    {
        $this->limits(files: 2);
        $id = $this->collectionForPolicy();
        $this->assertSame([], app(ReleaseProcessingService::class)->deleteUnwantedCollections(1));
        $this->assertTrue(DB::table('collections')->where('id', $id)->exists());
        $this->assertSame(0, DB::table('release_verification_candidates')->count());
    }

    public function test_verification_creation_scopes_candidate_queueing_to_the_supplied_window(): void
    {
        $this->limits(files: 2);
        $rejected = $this->collectionForPolicy();
        $partial = $this->collectionForPolicy(totalParts: 2);
        $tail = $this->collectionForPolicy();
        $window = [$rejected, $partial];
        $rejectedIds = app(ReleaseProcessingService::class)->deleteUnwantedCollections(1, $window);
        $this->assertSame([], $rejectedIds);
        $this->assertTrue(DB::table('collections')->where('id', $tail)->exists());
        $allowed = array_values(array_diff($window, $rejectedIds));
        $result = app(ReleaseCreationService::class)->createReleases(1, 10, false, $allowed);
        $this->assertSame(0, $result['added']);
        $this->assertSame(2, DB::table('release_verification_candidates')->where('status', 'pending')->count());
        $this->assertFalse(DB::table('collections')->where('id', $rejected)->exists());
        $this->assertFalse(DB::table('collections')->where('id', $partial)->exists());
        $this->assertTrue(DB::table('collections')->where('id', $tail)->exists());
    }

    public function test_below_policy_windows_do_not_starve_later_partial_candidates(): void
    {
        $this->limits(files: 2);
        $settings = Cache::get('configuration:ingestion');
        Cache::put('configuration:ingestion', new IngestionConfigurationData(...array_replace($settings->toArray(), ['maxReleasesCreated' => 1])), 300);
        $first = $this->collectionForPolicy();
        $partial = $this->collectionForPolicy(totalParts: 2);
        $tail = $this->collectionForPolicy();
        $this->publicationSchema();
        $tailWasPreservedDuringFirstPass = false;
        $totals = app(ReleaseProcessingService::class)->processReleaseCreationBatches(1, function () use ($first, $partial, $tail, &$tailWasPreservedDuringFirstPass): void {
            if (DB::table('collections')->where('id', $partial)->exists() && ! DB::table('collections')->where('id', $first)->exists()) {
                $tailWasPreservedDuringFirstPass = DB::table('collections')->where('id', $tail)->exists();
            }
        });
        $this->assertTrue($tailWasPreservedDuringFirstPass);
        $this->assertSame(0, $totals['releases']);
        $this->assertSame(3, $totals['iterations']);
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertSame(0, DB::table('release_verification_candidates')->where('status', 'filtered')->count());
        $this->assertSame(3, DB::table('release_verification_candidates')->where('status', 'pending')->count());
    }

    public function test_partial_and_parity_only_inputs_remain_eligible_for_download_proof(): void
    {
        $this->limits(min: 1000, files: 5);
        $partial = $this->collectionForPolicy(totalParts: 2);
        $parity = $this->collectionForPolicy(name: 'movie.par2');
        app(ReleaseCreationService::class)->createReleases(1, 10, false);
        $this->assertSame(2, DB::table('release_verification_candidates')->where('status', 'pending')->count());
        $this->assertFalse(DB::table('collections')->whereIn('id', [$partial, $parity])->exists());
    }

    public function test_encoded_header_bytes_above_maximum_wait_for_actual_decoded_size(): void
    {
        $this->limits(max: 4);
        app(CandidateRepository::class)->archive($this->collectionForPolicy());
        $this->assertNotNull(app(CandidateRepository::class)->claim());
    }

    /** @return array<string, array{int, int, int, int, int, int, list<string>}> */
    public static function finalLimits(): array
    {
        return [
            'site min size' => [10, 0, 0, 0, 0, 5, ['movie.mkv']],
            'group min size' => [0, 0, 0, 10, 0, 5, ['movie.mkv']],
            'site max size' => [0, 4, 0, 0, 0, 5, ['movie.mkv']],
            'site min files' => [0, 0, 2, 0, 0, 5, ['movie.mkv']],
            'group min files' => [0, 0, 0, 0, 2, 5, ['movie.mkv']],
            'parity without proved sources' => [0, 0, 0, 0, 0, 5, ['movie.par2', 'movie.vol0+1.par2']],
        ];
    }

    #[DataProvider('finalLimits')]
    public function test_verified_inventory_outside_collection_policy_cannot_publish_and_is_terminal(int $min, int $max, int $files, int $groupMin, int $groupFiles, int $bytes, array $names): void
    {
        $repository = app(CandidateRepository::class);
        $repository->archive($this->collectionForPolicy(totalParts: 2));
        $claim = $repository->claim();
        $manifest = app(ManifestStore::class)->read($claim->manifest_path);
        $manifest['verified_bytes'] = $bytes;
        $manifest['verified_file_names'] = $names;
        $manifest['verified_file_count'] = count($names);
        $this->limits($min, $max, $files, $groupMin, $groupFiles);
        $this->publicationSchema();
        app(ScratchInventory::class)->register((int) $claim->id, (int) $claim->version);
        $scratch = config('release_verification.storage_path').'/scratch/'.$claim->id.'-'.$claim->version;
        app('files')->ensureDirectoryExists($scratch);
        file_put_contents($scratch.'/payload', 'verified bytes');
        $id = app(VerifiedReleasePublisher::class)->publish($claim, new VerificationResult('complete', 'Verified payload.', $manifest));
        $this->assertNull($id);
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertSame('filtered', DB::table('release_verification_candidates')->value('status'));
        $this->assertSame(0, (int) DB::table('release_verification_state')->value('pending_bytes'));
        $this->assertFileDoesNotExist(app(ManifestStore::class)->path($claim->manifest_path));
        $this->assertFileExists($scratch.'/payload');
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($scratch);
        $this->travel(16)->minutes();
        $this->assertNull($repository->claim());
    }

    public function test_reconstructed_sources_satisfy_policy_even_when_headers_only_contained_parity(): void
    {
        $this->limits(min: 10, files: 2);
        app(CandidateRepository::class)->archive($this->collectionForPolicy(name: 'movie.par2'));
        $claim = app(CandidateRepository::class)->claim();
        $manifest = app(ManifestStore::class)->read($claim->manifest_path);
        $manifest += ['verified_bytes' => 100, 'verified_file_names' => ['movie.par2', 'reconstructed.mkv'], 'verified_file_count' => 2];
        $this->publicationSchema();
        Search::shouldReceive('updateRelease')->once();
        $id = app(VerifiedReleasePublisher::class)->publish($claim, new VerificationResult('repaired', 'PAR2 sources verified.', $manifest));
        $this->assertIsInt($id);
        $this->assertSame(2, (int) DB::table('releases')->where('id', $id)->value('totalpart'));
    }

    public function test_manual_import_preserves_legacy_bypass_of_collection_size_and_count_filters(): void
    {
        $manifest = ['identity' => sha1('manual'), 'metadata_source' => 'import', 'subject' => 'Movie', 'fromname' => 'poster',
            'date' => '2026-10-11 12:00:00', 'groups_id' => 1, 'groups' => ['alt.test'], 'regex_id' => 0, 'expected_files' => 1,
            'files' => [['key' => 'file', 'subject' => 'movie.mkv', 'totalparts' => 1, 'segments' => [['number' => 1, 'messageid' => 'a@b', 'bytes' => 5]]]],
            'verified_bytes' => 5, 'verified_file_names' => ['movie.mkv'], 'verified_file_count' => 1];
        $this->limits(min: 1000, max: 1, files: 10);
        app(CandidateRepository::class)->enqueue($manifest);
        $claim = app(CandidateRepository::class)->claim();
        $this->publicationSchema();
        Search::shouldReceive('updateRelease')->once();
        $id = app(VerifiedReleasePublisher::class)->publish($claim, new VerificationResult('complete', 'Verified manual import.', $manifest));
        $this->assertIsInt($id);
    }

    public function test_passed_collection_without_authoritative_inventory_fails_closed(): void
    {
        app(CandidateRepository::class)->archive($this->collectionForPolicy());
        $claim = app(CandidateRepository::class)->claim();
        $manifest = app(ManifestStore::class)->read($claim->manifest_path);
        $this->publicationSchema();
        try {
            app(VerifiedReleasePublisher::class)->publish($claim, new VerificationResult('complete', 'Missing inventory.', $manifest));
            $this->fail('Publication must require authoritative verified inventory.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Authoritative verified', $error->getMessage());
        }
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertSame('verifying', DB::table('release_verification_candidates')->value('status'));
    }

    /** @return array<string, array{bool}> */
    public static function cleanupFailures(): array
    {
        return ['normal cleanup' => [false], 'after commit cleanup failure' => [true]];
    }

    #[DataProvider('cleanupFailures')]
    public function test_worker_reports_durable_filtered_verdict_after_real_download_proof(bool $cleanupFails): void
    {
        config(['release_verification.free_bytes' => 0, 'release_verification.scratch_bytes' => 2147483648, 'release_verification.bytes_per_second' => 0]);
        app(CandidateRepository::class)->archive($this->collectionForPolicy());
        $this->limits(max: 1);
        $this->publicationSchema();
        if ($cleanupFails) {
            DB::listen(static function (QueryExecuted $event): void {
                if (str_starts_with($event->sql, 'update "release_verification_candidates"') && in_array('filtered', $event->bindings, true)) {
                    DB::afterCommit(static function (): void {
                        throw new \RuntimeException('Forced post-commit cleanup failure.');
                    });
                }
            });
        }
        $this->mock(NNTPService::class, function ($mock): void {
            $mock->shouldReceive('doConnect')->once()->with(false)->andReturn(true);
            $mock->shouldReceive('verificationArticle')->once()->with('a@b', 16777216)->andReturn((new YencService)->encode('hello', 'movie.mkv'));
            $mock->shouldReceive('doQuit')->andReturn(null);
        });
        $this->artisan('releases:verify-candidates')->expectsOutputToContain('filtered — Verified payload exceeds')->assertSuccessful();
        $this->assertSame('filtered', DB::table('release_verification_candidates')->value('status'));
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertSame(0, (int) DB::table('release_verification_state')->value('pending_bytes'));
    }

    private function limits(int $min = 0, int $max = 0, int $files = 0, int $groupMin = 0, int $groupFiles = 0): void
    {
        Cache::put('configuration:ingestion', new IngestionConfigurationData(...array_replace(IngestionConfigurationData::defaults()->toArray(),
            ['minSizeToFormRelease' => $min, 'maxSizeToFormRelease' => $max, 'minFilesToFormRelease' => $files])), 300);
        DB::table('usenet_groups')->where('id', 1)->update(['minsizetoformrelease' => $groupMin, 'minfilestoformrelease' => $groupFiles]);
    }

    private function collectionForPolicy(string $name = 'movie.mkv', int $totalParts = 1): int
    {
        $id = DB::table('collections')->insertGetId(['collectionhash' => sha1(random_bytes(8), true), 'subject' => 'Movie', 'fromname' => 'poster',
            'totalfiles' => 1, 'groups_id' => 1, 'date' => '2026-10-11 12:00:00', 'filecheck' => 3]);
        $binary = DB::table('binaries')->insertGetId(['collections_id' => $id, 'binaryhash' => md5('file', true), 'name' => $name, 'totalparts' => $totalParts]);
        DB::table('parts')->insert(['binaries_id' => $binary, 'partnumber' => 1, 'messageid' => 'a@b', 'size' => 5]);

        return $id;
    }

    private function publicationSchema(): void
    {
        config(['nntmux.release_dedupe_enabled' => false, 'nntmux_settings.path_to_nzbs' => config('release_verification.storage_path').'/nzbs']);
        app('files')->ensureDirectoryExists(config('nntmux_settings.path_to_nzbs'));
        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
            foreach (['name', 'searchname', 'guid', 'leftguid', 'fromname', 'postdate', 'adddate'] as $column) {
                $table->string($column);
            }
            foreach (['totalpart', 'groups_id', 'size', 'passwordstatus', 'haspreview', 'categories_id', 'nfostatus', 'nzbstatus', 'isrenamed', 'iscategorized', 'predb_id'] as $column) {
                $table->integer($column);
            }
        });
        Schema::create('release_regexes', function (Blueprint $table): void {
            $table->integer('releases_id');
            $table->integer('collection_regex_id');
            $table->integer('naming_regex_id');
        });
        Schema::create('releases_groups', function (Blueprint $table): void {
            $table->integer('releases_id');
            $table->integer('groups_id');
        });
        $this->app->instance(ReleaseMetadataResolver::class, new class(app(ReleaseCleaningService::class)) extends ReleaseMetadataResolver
        {
            public function resolve(array $manifest): array
            {
                return ['name' => 'Movie', 'searchname' => 'Movie', 'fromname' => 'poster', 'predb_id' => 0, 'isrenamed' => 1, 'categories_id' => 2000, 'naming_regex_id' => 0];
            }
        });
    }
}
