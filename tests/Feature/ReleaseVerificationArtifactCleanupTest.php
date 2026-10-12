<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Services\ReleaseCleaningService;
use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\ManifestStore;
use App\Services\Releases\Verification\ReleaseMetadataResolver;
use App\Services\Releases\Verification\ScratchInventory;
use App\Services\Releases\Verification\VerificationResult;
use App\Services\Releases\Verification\VerifiedReleasePublisher;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class ReleaseVerificationArtifactCleanupTest extends ReleaseVerificationCandidatesTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('collections', function (Blueprint $table): void {
            $table->dateTime('last_seen_at')->nullable();
        });
    }

    public function test_manifest_reference_lookup_uses_an_index_and_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_10_11_205937_add_manifest_path_index_to_release_verification_candidates_table.php');
        $migration->up();
        $plan = DB::select('EXPLAIN QUERY PLAN SELECT id FROM release_verification_candidates WHERE manifest_path = ? LIMIT 1', [str_repeat('a', 64).'.json.gz']);
        $details = implode(' ', array_map(static fn (object $row): string => $row->detail, $plan));
        $this->assertStringContainsString('COVERING INDEX verification_manifest_path', $details);
        $migration->down();
        $this->assertFalse(Schema::hasIndex('release_verification_candidates', 'verification_manifest_path'));
    }

    public function test_rolled_back_archive_removes_new_manifest_and_restores_original_headers(): void
    {
        $id = $this->arrival();
        DB::beginTransaction();
        app(CandidateRepository::class)->archive($id);
        $path = app(ManifestStore::class)->path(DB::table('release_verification_candidates')->value('manifest_path'));
        $this->assertFileExists($path);
        DB::rollBack();

        $this->assertFileDoesNotExist($path);
        $this->assertSame(0, DB::table('release_verification_candidates')->count());
        $this->assertSame(1, DB::table('collections')->where('id', $id)->count());
    }

    public function test_rolled_back_replacement_preserves_original_manifest_only(): void
    {
        $repository = app(CandidateRepository::class);
        $store = app(ManifestStore::class);
        $repository->archive($this->arrival());
        $original = DB::table('release_verification_candidates')->value('manifest_path');
        DB::beginTransaction();
        $repository->archive($this->arrival('second@article', 2));
        $replacement = DB::table('release_verification_candidates')->value('manifest_path');
        $this->assertNotSame($original, $replacement);
        DB::rollBack();

        $this->assertFileDoesNotExist($store->path($replacement));
        $this->assertFileExists($store->path($original));
        $this->assertSame($original, DB::table('release_verification_candidates')->value('manifest_path'));
    }

    public function test_nested_rollback_cleanup_retains_manifest_readmitted_before_outer_commit(): void
    {
        $store = app(ManifestStore::class);
        $manifest = ['identity' => sha1('nested-import'), 'groups_id' => 1, 'files' => []];
        DB::beginTransaction();
        DB::beginTransaction();
        app(CandidateRepository::class)->enqueue($manifest);
        $path = DB::table('release_verification_candidates')->value('manifest_path');
        DB::rollBack();
        app(CandidateRepository::class)->enqueue($manifest);
        DB::commit();

        $this->assertFileExists($store->path($path));
        $this->assertSame($manifest, $store->read($path));
    }

    public function test_candidate_insert_failure_removes_manifest_after_automatic_rollback(): void
    {
        $id = $this->arrival();
        DB::unprepared("CREATE TRIGGER reject_candidate BEFORE INSERT ON release_verification_candidates BEGIN SELECT RAISE(ABORT, 'fixture insert failed'); END");
        try {
            app(CandidateRepository::class)->archive($id);
            $this->fail('Candidate insertion must fail.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('fixture insert failed', $error->getMessage());
        }

        $this->assertSame([], glob(config('release_verification.storage_path').'/manifests/*.json.gz'));
        $this->assertSame(1, DB::table('collections')->where('id', $id)->count());
        $this->assertSame(0, (int) DB::table('release_verification_state')->value('pending_bytes'));
    }

    /** @return iterable<string, array{string}> */
    public static function terminalTransitions(): iterable
    {
        yield 'policy rejection' => ['reject'];
        yield 'candidate expiry' => ['expire'];
        yield 'already expired hot archive' => ['archive-expired'];
    }

    /** @return iterable<string, array{string}> */
    public static function reopenableTransitions(): iterable
    {
        yield 'candidate expiry' => ['expire'];
        yield 'already expired hot archive' => ['archive-expired'];
    }

    #[DataProvider('terminalTransitions')]
    public function test_terminal_callbacks_leave_registered_cleanup_to_the_worker(string $transition): void
    {
        $repository = app(CandidateRepository::class);
        $repository->archive($this->arrival());
        $claim = $repository->claim();
        $scratch = $this->scratch((int) $claim->id, (int) $claim->version);
        $external = dirname($scratch).'/'.$claim->id.'-0';
        mkdir($external);
        file_put_contents($external.'/payload', 'unregistered inventory');

        $this->terminateCandidate($claim, $transition);

        $this->assertFileExists($scratch.'/payload');
        $this->assertFileExists($external.'/payload');
        $this->assertSame(0, (int) DB::table('release_verification_state')->value('pending_bytes'));
        $this->assertFileDoesNotExist(app(ManifestStore::class)->path($claim->manifest_path));
        $this->assertSame(1, DB::table('release_verification_scratch')->count());
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($scratch);
        $this->assertFileExists($external.'/payload');
        $this->assertSame(0, DB::table('release_verification_scratch')->count());
    }

    #[DataProvider('terminalTransitions')]
    public function test_terminal_callbacks_never_follow_a_linked_scratch_parent(string $transition): void
    {
        $repository = app(CandidateRepository::class);
        $repository->archive($this->arrival());
        $claim = $repository->claim();
        $scratch = $this->scratch((int) $claim->id, (int) $claim->version);
        $root = (string) config('release_verification.storage_path');
        rename($root.'/scratch', $root.'/outside');
        symlink($root.'/outside', $root.'/scratch');

        $this->terminateCandidate($claim, $transition);

        $this->assertFileExists($scratch.'/payload');
        $this->assertSame(1, DB::table('release_verification_scratch')->count());
        $this->artisan('releases:verify-candidates')->assertFailed();
        $this->assertFileExists($root.'/outside/'.$claim->id.'-'.$claim->version.'/payload');
    }

    private function terminateCandidate(object $claim, string $transition): void
    {
        $repository = app(CandidateRepository::class);
        if ($transition === 'reject') {
            $repository->rejectByPolicy($claim, 'Filtered fixture.');

            return;
        }
        $repository->finish($claim, new VerificationResult('unknown', 'Retry later.'));
        $this->travel(49)->hours();
        if ($transition === 'expire') {
            $repository->expire();
        } else {
            $id = $this->arrival();
            DB::table('collections')->where('id', $id)->update(['last_seen_at' => now()->subHours(49)]);
            $repository->archive($id);
        }
    }

    #[DataProvider('reopenableTransitions')]
    public function test_deferred_terminal_cleanup_preserves_readmitted_manifest_and_newer_scratch(string $transition): void
    {
        $repository = app(CandidateRepository::class);
        $store = app(ManifestStore::class);
        $repository->archive($this->arrival());
        $claim = $repository->claim();
        $oldScratch = $this->scratch((int) $claim->id, (int) $claim->version);
        $repository->finish($claim, new VerificationResult('unknown', 'Retry later.'));
        $this->travel(49)->hours();

        DB::beginTransaction();
        if ($transition === 'expire') {
            $repository->expire();
        } else {
            $id = $this->arrival();
            DB::table('collections')->where('id', $id)->update(['last_seen_at' => now()->subHours(49)]);
            $repository->archive($id);
        }
        $repository->archive($this->arrival());
        $current = DB::table('release_verification_candidates')->sole();
        $newScratch = $this->scratch((int) $current->id, (int) $current->version);
        $this->assertSame($claim->manifest_path, $current->manifest_path);
        $this->assertGreaterThan($claim->version, $current->version);
        DB::commit();

        $this->assertSame('pending', $current->status);
        $this->assertFileExists($store->path($current->manifest_path));
        $this->assertSame('first@article', $store->read($current->manifest_path)['files'][0]['segments'][0]['messageid']);
        $this->assertFileExists($oldScratch.'/payload');
        $this->assertFileExists($newScratch.'/payload');
    }

    public function test_deferred_replacement_cleanup_preserves_a_reused_old_manifest(): void
    {
        $repository = app(CandidateRepository::class);
        $store = app(ManifestStore::class);
        $repository->archive($this->arrival());
        $old = DB::table('release_verification_candidates')->sole();

        DB::beginTransaction();
        $repository->archive($this->arrival('second@article', 2));
        $replacement = $repository->claim();
        $this->assertNotSame($old->manifest_path, $replacement->manifest_path);
        $repository->finish($replacement, new VerificationResult('unknown', 'Retry later.'));
        $this->travel(49)->hours();
        $repository->expire();
        $repository->archive($this->arrival());
        DB::commit();

        $current = DB::table('release_verification_candidates')->sole();
        $this->assertSame($old->manifest_path, $current->manifest_path);
        $this->assertFileExists($store->path($current->manifest_path));
        $this->assertFileDoesNotExist($store->path($replacement->manifest_path));
    }

    public function test_deferred_publication_cleanup_preserves_a_new_generation_reusing_the_manifest(): void
    {
        $this->publicationSchema();
        $repository = app(CandidateRepository::class);
        $store = app(ManifestStore::class);
        $repository->archive($this->arrival());
        $claim = $repository->claim();
        $manifest = $store->read($claim->manifest_path);
        $manifest += ['verified_bytes' => 5, 'verified_file_names' => ['movie.mkv']];

        DB::beginTransaction();
        $releaseId = app(VerifiedReleasePublisher::class)->publish($claim, new VerificationResult('complete', 'Fixture verified.', $manifest));
        $repository->archive($this->arrival());
        $current = DB::table('release_verification_candidates')->whereNull('release_id')->sole();
        $this->assertNotSame($claim->id, $current->id);
        $this->assertSame($claim->manifest_path, $current->manifest_path);
        DB::commit();

        $this->assertNotNull($releaseId);
        $this->assertFileExists($store->path($current->manifest_path));
        $this->assertSame('Movie', $store->read($current->manifest_path)['subject']);
    }

    /** @return iterable<string, array{string}> */
    public static function admissions(): iterable
    {
        yield 'hot collection' => ['archive'];
        yield 'import' => ['enqueue'];
    }

    #[DataProvider('admissions')]
    public function test_capacity_failure_does_not_delete_another_candidates_manifest(string $admission): void
    {
        $repository = app(CandidateRepository::class);
        $store = app(ManifestStore::class);
        $repository->archive($this->arrival());
        $existing = DB::table('release_verification_candidates')->sole();
        $manifest = $store->read($existing->manifest_path);
        DB::table('release_verification_candidates')->where('id', $existing->id)
            ->update(['identity' => sha1('other-reference'), 'source_identity' => null]);
        config(['release_verification.pending_bytes' => 0]);

        try {
            if ($admission === 'archive') {
                $repository->archive($this->arrival());
            } else {
                $repository->enqueue($manifest);
            }
            $this->fail('Capacity must reject another admission.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('capacity is full', $exception->getMessage());
        }

        $this->assertFileExists($store->path($existing->manifest_path));
        $this->assertSame($manifest, $store->read($existing->manifest_path));
    }

    public function test_capacity_cleanup_preserves_a_reference_restored_by_outer_rollback(): void
    {
        $repository = app(CandidateRepository::class);
        $store = app(ManifestStore::class);
        $repository->archive($this->arrival());
        $original = DB::table('release_verification_candidates')->sole();

        DB::beginTransaction();
        $repository->archive($this->arrival('second@article', 2));
        DB::table('release_verification_candidates')->update(['identity' => sha1('other-reference'), 'source_identity' => null]);
        config(['release_verification.pending_bytes' => 0]);
        try {
            $repository->archive($this->arrival());
            $this->fail('Capacity must reject another admission.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('capacity is full', $exception->getMessage());
        }
        DB::rollBack();

        $restored = DB::table('release_verification_candidates')->sole();
        $this->assertSame($original->manifest_path, $restored->manifest_path);
        $this->assertFileExists($store->path($restored->manifest_path));
    }

    public function test_capacity_failure_removes_a_manifest_without_any_committed_reference(): void
    {
        config(['release_verification.pending_bytes' => 0]);
        try {
            app(CandidateRepository::class)->archive($this->arrival());
            $this->fail('Capacity must reject admission.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('capacity is full', $exception->getMessage());
        }

        $this->assertSame([], glob(config('release_verification.storage_path').'/manifests/*.json.gz'));
        $this->assertSame(1, DB::table('collections')->count());
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
        Search::shouldReceive('updateRelease')->once();
        $this->app->instance(ReleaseMetadataResolver::class, new class(app(ReleaseCleaningService::class)) extends ReleaseMetadataResolver
        {
            public function resolve(array $manifest): array
            {
                return ['name' => 'Movie', 'searchname' => 'Movie', 'fromname' => 'poster', 'predb_id' => 0,
                    'isrenamed' => 1, 'categories_id' => 2000, 'naming_regex_id' => 0];
            }
        });
    }

    private function arrival(string $messageId = 'first@article', int $part = 1): int
    {
        $id = DB::table('collections')->insertGetId(['collectionhash' => sha1('artifact-cleanup', true), 'subject' => 'Movie',
            'fromname' => 'poster', 'totalfiles' => 1, 'groups_id' => 1, 'date' => '2026-10-11 12:00:00', 'last_seen_at' => now()]);
        $binary = DB::table('binaries')->insertGetId(['collections_id' => $id, 'binaryhash' => md5('movie', true), 'name' => 'movie.mkv', 'totalparts' => 2]);
        DB::table('parts')->insert(['binaries_id' => $binary, 'partnumber' => $part, 'messageid' => $messageId, 'size' => 5]);

        return $id;
    }

    private function scratch(int $candidateId, int $version): string
    {
        app(ScratchInventory::class)->register($candidateId, $version);
        $directory = config('release_verification.storage_path').'/scratch/'.$candidateId.'-'.$version;
        app('files')->ensureDirectoryExists($directory);
        file_put_contents($directory.'/payload', 'retained bytes');

        return $directory;
    }
}
