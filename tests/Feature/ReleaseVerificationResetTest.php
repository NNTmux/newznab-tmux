<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\ManifestStore;
use App\Services\Releases\Verification\ScratchInventory;
use App\Services\Releases\Verification\VerificationResetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ReleaseVerificationResetTest extends ReleaseVerificationCandidatesTest
{
    public function test_reset_truncate_clears_old_candidates_retries_capacity_and_artifacts(): void
    {
        $this->prepareCommandDatabase();
        $this->seedVerification();
        $root = (string) config('release_verification.storage_path');
        $lock = fopen($root.'/worker.lock', 'c');
        $inode = fileinode($root.'/worker.lock');
        fclose($lock);

        $this->artisan('nntmux:reset-truncate')->assertSuccessful();

        $this->assertResetState();
        $this->assertSame($inode, fileinode($root.'/worker.lock'));
        $this->assertDirectoryDoesNotExist($root.'/scratch');
        $this->assertDirectoryDoesNotExist($root.'/manifests');
        $this->assertFileDoesNotExist($root.'/status.json');
        $this->assertSame(1, DB::table('releases')->where('id', 1)->count());
        app(CandidateRepository::class)->enqueue(['identity' => sha1('after-reset'), 'groups_id' => 1, 'files' => []]);
        $this->assertNotNull(app(CandidateRepository::class)->claim());
    }

    public function test_full_database_reset_also_clears_verification_and_restarts_audit_from_zero(): void
    {
        $this->prepareCommandDatabase();
        $this->seedVerification();
        $this->app->detectEnvironment(static fn (): string => 'local');
        $nzbs = config('release_verification.storage_path').'/nzbs';
        mkdir($nzbs);
        config(['search.default' => 'manticore', 'nntmux_settings.path_to_nzbs' => $nzbs]);
        Search::shouldReceive('truncateIndex')->once();
        File::partialMock()->shouldReceive('allFiles')->andReturn([]);

        $this->artisan('nntmux:resetdb')
            ->expectsConfirmation('This command removes all releases, nzb files, samples, previews , nfos, truncates all article tables and resets all groups. Are you sure you want reset the DB?', 'yes')
            ->assertSuccessful();

        $this->assertResetState();
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertSame(0, DB::table('usenet_groups')->value('last_record'));
    }

    public function test_busy_worker_refuses_reset_before_any_group_or_candidate_changes(): void
    {
        $this->prepareCommandDatabase();
        $this->seedVerification();
        $lock = fopen(config('release_verification.storage_path').'/worker.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $this->artisan('nntmux:reset-truncate')->assertFailed();
            $this->assertSame(99, DB::table('usenet_groups')->value('last_record'));
            $this->assertSame(1, DB::table('release_verification_candidates')->count());
            $this->assertSame(1, DB::table('release_verification_audit_retries')->count());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function test_admission_is_refused_during_reset_and_original_headers_remain(): void
    {
        $id = $this->resetCollection('blocked@reset');
        app(VerificationResetService::class)->run(function () use ($id): void {
            try {
                app(CandidateRepository::class)->archive($id);
                $this->fail('Archive must not admit during reset.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('reset', $error->getMessage());
            }
            $this->assertSame(1, DB::table('collections')->count());
            $this->assertSame(1, DB::table('parts')->count());
            try {
                app(CandidateRepository::class)->enqueue(['identity' => sha1('blocked'), 'groups_id' => 1, 'files' => []]);
                $this->fail('Import must not admit during reset.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('reset', $error->getMessage());
            }
            $this->assertSame(0, DB::table('release_verification_candidates')->count());
        });
    }

    public function test_outer_admission_transaction_blocks_reset_until_commit(): void
    {
        DB::beginTransaction();
        app(CandidateRepository::class)->archive($this->resetCollection('outer@commit'));
        $probe = fopen(config('release_verification.storage_path').'/reset.lock', 'c');
        $this->assertFalse(flock($probe, LOCK_EX | LOCK_NB));
        fclose($probe);
        $called = false;
        try {
            app(VerificationResetService::class)->run(function () use (&$called): void {
                $called = true;
            });
            $this->fail('An uncommitted admission must block reset.');
        } catch (RuntimeException) {
            $this->assertFalse($called);
            $this->assertSame(1, DB::table('release_verification_candidates')->count());
        } finally {
            DB::commit();
        }
        app(VerificationResetService::class)->run(function () use (&$called): void {
            $called = true;
        });
        $this->assertTrue($called);
        $this->assertResetState();
    }

    public function test_reset_locks_are_released_after_legacy_reset_failure(): void
    {
        try {
            app(VerificationResetService::class)->run(static function (): void {
                throw new RuntimeException('legacy reset failed');
            });
            $this->fail('Expected legacy failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('legacy reset failed', $error->getMessage());
        }
        app(CandidateRepository::class)->enqueue(['identity' => sha1('retry'), 'groups_id' => 1, 'files' => []]);
        app(VerificationResetService::class)->run(static function (): void {});
        $this->assertResetState();
    }

    public function test_outer_admission_rollback_releases_reset_lock_and_removes_manifest(): void
    {
        DB::beginTransaction();
        app(CandidateRepository::class)->enqueue(['identity' => sha1('outer-rollback'), 'groups_id' => 1, 'files' => []]);
        $name = (string) DB::table('release_verification_candidates')->value('manifest_path');
        $probe = fopen(config('release_verification.storage_path').'/reset.lock', 'c');
        $this->assertFalse(flock($probe, LOCK_EX | LOCK_NB));
        DB::rollBack();
        $this->assertTrue(flock($probe, LOCK_EX | LOCK_NB));
        flock($probe, LOCK_UN);
        fclose($probe);
        $this->assertFileDoesNotExist(app(ManifestStore::class)->path($name));
        app(VerificationResetService::class)->run(static function (): void {});
        $this->assertResetState();
    }

    public function test_active_admission_refuses_reset_before_destructive_work(): void
    {
        $this->prepareCommandDatabase();
        $this->seedVerification();
        $lock = fopen(config('release_verification.storage_path').'/reset.lock', 'c');
        flock($lock, LOCK_SH);
        try {
            $this->artisan('nntmux:reset-truncate')->assertFailed();
            $this->assertSame(99, DB::table('usenet_groups')->value('last_record'));
            $this->assertSame(1, DB::table('release_verification_candidates')->count());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function test_reset_can_run_before_verification_migrations_exist(): void
    {
        Schema::drop('release_verification_audit_retries');
        Schema::drop('release_verification_candidates');
        Schema::drop('release_verification_state');
        $called = false;
        app(VerificationResetService::class)->run(function () use (&$called): void {
            $called = true;
        });
        $this->assertTrue($called);
        $this->assertFalse(Schema::hasTable('release_verification_state'));
    }

    public function test_reset_reinitializes_missing_state_without_retry_migration(): void
    {
        Schema::drop('release_verification_audit_retries');
        Schema::table('release_verification_state', function (Blueprint $table): void {
            $table->dropColumn('audit_retry_turn');
        });
        DB::table('release_verification_state')->delete();
        app(VerificationResetService::class)->run(static function (): void {});
        $this->assertSame(1, DB::table('release_verification_state')->count());
        $this->assertSame(0, (int) DB::table('release_verification_state')->value('audit_cursor'));
        $this->assertSame(0, (int) DB::table('release_verification_state')->value('pending_bytes'));
    }

    public function test_artifact_cleanup_failure_cannot_leave_old_payloads_claimable(): void
    {
        $this->seedVerification();
        $filesystem = new Filesystem;
        $scratch = config('release_verification.storage_path').'/scratch';
        File::partialMock()->shouldReceive('deleteDirectory')->andReturnUsing(static function (string $path) use ($filesystem, $scratch): bool {
            return $path === $scratch ? false : $filesystem->deleteDirectory($path);
        });
        $called = false;
        try {
            app(VerificationResetService::class)->run(function () use (&$called): void {
                $called = true;
            });
            $this->fail('Artifact failure must abort the legacy reset.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('artifacts', $error->getMessage());
        }
        $this->assertFalse($called);
        $this->assertResetState();
        $this->assertNull(app(CandidateRepository::class)->claim());
        app(CandidateRepository::class)->enqueue(['identity' => sha1('after-cleanup-failure'), 'groups_id' => 1, 'files' => []]);
        $this->assertNotNull(app(CandidateRepository::class)->claim());
    }

    private function resetCollection(string $messageId): int
    {
        return (new \ReflectionMethod(ReleaseVerificationCandidatesTest::class, 'collection'))->invoke($this, $messageId);
    }

    private function seedVerification(): void
    {
        app(CandidateRepository::class)->archive($this->resetCollection('old@article'));
        app(CandidateRepository::class)->claim();
        DB::table('release_verification_audit_retries')->insert(['release_id' => 999, 'next_attempt_at' => now(), 'reason' => 'NNTP unavailable']);
        DB::table('release_verification_state')->update(['audit_cursor' => 999, 'audit_retry_turn' => true]);
        $root = (string) config('release_verification.storage_path');
        app(ScratchInventory::class)->register(1, 1);
        mkdir($root.'/scratch/1-1', 0700, true);
        file_put_contents($root.'/scratch/1-1/payload', 'old downloaded bytes');
        file_put_contents($root.'/status.json', 'old status');
    }

    private function assertResetState(): void
    {
        $this->assertSame(0, DB::table('release_verification_scratch')->count());
        $this->assertSame(0, DB::table('release_verification_candidates')->count());
        $this->assertSame(0, DB::table('release_verification_audit_retries')->count());
        $state = DB::table('release_verification_state')->where('id', 1)->first();
        $this->assertNotNull($state);
        $this->assertSame(0, (int) $state->pending_bytes);
        $this->assertSame(0, (int) $state->audit_cursor);
        $this->assertFalse((bool) $state->audit_retry_turn);
    }

    private function prepareCommandDatabase(): void
    {
        Schema::table('usenet_groups', function (Blueprint $table): void {
            $table->integer('first_record')->default(99);
            $table->dateTime('first_record_postdate')->nullable();
            $table->integer('last_record')->default(99);
            $table->dateTime('last_record_postdate')->nullable();
            $table->dateTime('last_updated')->nullable();
        });
        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
            $table->integer('nzbstatus');
        });
        DB::table('releases')->insert(['id' => 1, 'nzbstatus' => 1]);
        $connection = DB::connection();
        DB::swap($manager = \Mockery::mock(DB::getFacadeRoot()));
        $manager->shouldReceive('statement')->andReturnUsing(static function (string $sql) use ($connection): bool {
            if (str_starts_with($sql, 'SET FOREIGN_KEY_CHECKS')) {
                return true;
            }
            if (preg_match('/\ATRUNCATE TABLE ([a-z0-9_]+)\z/', $sql, $match) === 1) {
                if (Schema::hasTable($match[1])) {
                    $connection->table($match[1])->delete();
                }

                return true;
            }

            return $connection->statement($sql);
        });
    }
}
