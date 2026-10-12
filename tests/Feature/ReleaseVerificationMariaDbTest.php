<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NzbImportStatus;
use App\Services\CollectionCleanupService;
use App\Services\Nzb\NzbUploadManifestService;
use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\ManifestStore;
use App\Services\Releases\Verification\VerificationResetService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReleaseVerificationMariaDbTest extends ReleaseVerificationCandidatesTest
{
    private string $fixtureDatabase = '';

    private bool $fixtureCreated = false;

    protected function setUp(): void
    {
        try {
            parent::setUp();
        } catch (Throwable $error) {
            $this->dropOwnedFixture();

            throw $error;
        }
    }

    protected function configureVerificationDatabase(): void
    {
        if (getenv('VERIFICATION_MARIADB_TEST') !== '1') {
            $this->markTestSkipped('Requires the isolated verification MariaDB test database.');
        }
        $endpoint = [
            'driver' => 'mysql', 'url' => null, 'host' => 'nntmux-verify-db', 'port' => 3306,
            'database' => 'mysql', 'username' => 'root', 'password' => '', 'unix_socket' => '',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
            'strict' => false, 'engine' => 'InnoDB ROW_FORMAT=DYNAMIC', 'options' => [],
        ];
        config(['database.connections.verification_fixture_admin' => $endpoint]);
        DB::purge('verification_fixture_admin');
        $this->fixtureDatabase = 'verification_fixture_'.bin2hex(random_bytes(12));
        DB::connection('verification_fixture_admin')->statement(
            'CREATE DATABASE `'.$this->fixtureDatabase.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        $this->fixtureCreated = true;
        $endpoint['database'] = $this->fixtureDatabase;
        config(['database.default' => 'mysql', 'database.connections.mysql' => $endpoint]);
        DB::purge('mysql');
        $this->assertSame($this->fixtureDatabase, DB::selectOne('SELECT DATABASE() AS name')->name);
    }

    protected function tearDown(): void
    {
        try {
            $this->dropOwnedFixture();
        } finally {
            parent::tearDown();
        }
    }

    private function dropOwnedFixture(): void
    {
        if (! $this->fixtureCreated) {
            DB::purge('verification_fixture_admin');

            return;
        }
        $rollbackError = null;
        foreach (DB::getConnections() as $name => $connection) {
            if ($connection->getConfig('database') !== $this->fixtureDatabase) {
                continue;
            }
            try {
                if ($connection->transactionLevel() > 0) {
                    $connection->rollBack(0);
                }
            } catch (Throwable $error) {
                $rollbackError ??= $error;
            } finally {
                DB::purge($name);
            }
        }
        try {
            DB::connection('verification_fixture_admin')->statement('DROP DATABASE `'.$this->fixtureDatabase.'`');
            $this->fixtureCreated = false;
        } finally {
            DB::purge('verification_fixture_admin');
        }
        if ($rollbackError !== null) {
            throw $rollbackError;
        }
    }

    public function test_upload_reconciliation_uses_current_admission_reads_inside_an_older_snapshot(): void
    {
        $identity = sha1('import:current-upload');
        [$manifests, $manifestPath] = $this->pendingUploadManifest($identity);
        config(['database.connections.verification_upload_other' => config('database.connections.mysql')]);
        DB::purge('verification_upload_other');
        $other = DB::connection('verification_upload_other');
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        DB::beginTransaction();
        try {
            $this->assertNull(DB::table('release_verification_candidates')->where('identity', $identity)->value('id'));
            $other->transaction(function () use ($other, $identity): void {
                $other->table('release_verification_state')->where('id', 1)->lockForUpdate()->first();
                $other->table('releases')->insert(['id' => 7, 'guid' => 'committed-upload']);
                $other->table('release_verification_candidates')->insert($this->pendingUploadCandidate($identity) + [
                    'status' => 'complete', 'release_id' => 7, 'verified_at' => now(),
                ]);
            });
            $this->assertNull(DB::table('release_verification_candidates')->where('identity', $identity)->value('id'), 'The consistent read retains its pre-admission snapshot.');
            $resolved = (new \ReflectionMethod($manifests, 'resolveCandidate'))->invoke($manifests, $manifests->read($manifestPath));
            $this->assertSame(NzbUploadManifestService::STATE_NZB_IMPORTED, $resolved['state']);
            $this->assertSame(7, $resolved['release_id']);
            $this->assertSame(NzbUploadManifestService::STATE_NZB_PENDING, $manifests->read($manifestPath)['state']);
            $this->assertSame(NzbUploadManifestService::STATE_NZB_PENDING, $manifests->reconcilePending($manifestPath)['state']);
            DB::commit();
            $manifest = $manifests->read($manifestPath);
            $this->assertSame(NzbUploadManifestService::STATE_NZB_IMPORTED, $manifest['state']);
            $this->assertSame(7, $manifest['release_id']);
            $this->assertSame('committed-upload', $manifest['release_guid']);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('verification_upload_other');
        }
    }

    public function test_uncommitted_upload_admission_defers_absence_rejection_until_ledger_is_available(): void
    {
        $identity = sha1('import:uncommitted-upload');
        [$manifests, $manifestPath] = $this->pendingUploadManifest($identity);
        config(['database.connections.verification_upload_other' => config('database.connections.mysql')]);
        DB::purge('verification_upload_other');
        $other = DB::connection('verification_upload_other');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        $other->beginTransaction();
        try {
            $other->table('release_verification_state')->where('id', 1)->lockForUpdate()->first();
            $other->table('release_verification_candidates')->insert($this->pendingUploadCandidate($identity) + ['status' => 'pending']);
            $this->assertNull(DB::table('release_verification_candidates')->where('identity', $identity)->value('id'));
            try {
                $manifests->reconcilePending($manifestPath);
                $this->fail('An in-progress admission must hold the reconciliation ledger until commit.');
            } catch (QueryException $error) {
                $this->assertSame(1205, (int) $error->errorInfo[1]);
            }
            $this->assertSame(NzbUploadManifestService::STATE_NZB_PENDING, $manifests->read($manifestPath)['state']);
            $this->assertNull($manifests->read($manifestPath)['last_error']);
            $other->commit();
            $this->assertSame(NzbUploadManifestService::STATE_NZB_PENDING, $manifests->reconcilePending($manifestPath)['state']);
            $this->assertTrue(DB::table('release_verification_candidates')->where('identity', $identity)->exists());
            $this->assertFileExists(dirname($manifestPath).'/info.nfo');
        } finally {
            if ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::purge('verification_upload_other');
        }
    }

    /** @return array{NzbUploadManifestService, string} */
    private function pendingUploadManifest(string $identity): array
    {
        Schema::create('releases', static function (Blueprint $table): void {
            $table->id();
            $table->string('guid');
        });
        $directory = config('release_verification.storage_path').'/upload';
        app('files')->ensureDirectoryExists($directory);
        file_put_contents($directory.'/Release.nzb', '<nzb/>');
        file_put_contents($directory.'/info.nfo', 'paired NFO');
        $manifests = new NzbUploadManifestService(new Filesystem);
        $manifests->create($directory, 'upload-fixture', 'Release.nzb', 'info.nfo');
        $manifests->recordNzbOutcome($directory.'/Release.nzb', NzbImportStatus::Queued, null, null, candidateIdentity: $identity);

        return [$manifests, $directory.'/'.NzbUploadManifestService::FILENAME];
    }

    /** @return array<string, mixed> */
    private function pendingUploadCandidate(string $identity): array
    {
        return ['identity' => $identity, 'source_identity' => $identity, 'groups_id' => 1, 'manifest_path' => '',
            'manifest_hash' => str_repeat('a', 64), 'manifest_bytes' => 0, 'last_unique_at' => now(), 'next_attempt_at' => now()];
    }

    public function test_second_connection_cannot_claim_or_publish_while_lease_is_active(): void
    {
        $store = app(ManifestStore::class);
        $manifest = ['identity' => sha1('lease'), 'groups_id' => 1, 'files' => [], 'expected_files' => 0];
        app(CandidateRepository::class)->enqueue($manifest);
        $claim = app(CandidateRepository::class)->claim();
        config(['database.connections.verification_other' => config('database.connections.mysql')]);
        DB::purge('verification_other');
        $row = DB::connection('verification_other')->table('release_verification_candidates')->first();
        $this->assertSame($claim->lease_token, $row->lease_token);
        $this->assertNull(app(CandidateRepository::class)->claim());
        app(CandidateRepository::class)->heartbeat($claim->id, $claim->lease_token);
        $this->assertSame('verifying', DB::connection('verification_other')->table('release_verification_candidates')->value('status'));
    }

    public function test_reset_keeps_admissions_excluded_across_real_mysql_truncates(): void
    {
        $repository = app(CandidateRepository::class);
        $manifest = ['identity' => sha1('pre-reset'), 'groups_id' => 1, 'files' => [], 'expected_files' => 0];
        $repository->enqueue($manifest);
        $stored = DB::table('release_verification_candidates')->sole()->manifest_path;
        DB::table('release_verification_audit_retries')->insert(['release_id' => 99, 'next_attempt_at' => now(), 'reason' => 'old outage']);
        DB::table('release_verification_state')->update(['audit_cursor' => 99, 'audit_retry_turn' => true]);
        app(VerificationResetService::class)->run(function () use ($repository, $manifest): void {
            foreach (['parts', 'binaries', 'collections'] as $table) {
                DB::statement('TRUNCATE TABLE '.$table);
                $this->assertSame(0, DB::transactionLevel(), 'MySQL TRUNCATE must run outside a transaction.');
                try {
                    $repository->enqueue($manifest);
                    $this->fail('An implicit commit must not release the admission exclusion.');
                } catch (\RuntimeException $error) {
                    $this->assertStringContainsString('active', $error->getMessage());
                }
            }
        });
        $this->assertSame(0, DB::table('release_verification_candidates')->count());
        $this->assertSame(0, DB::table('release_verification_audit_retries')->count());
        $state = DB::table('release_verification_state')->sole();
        $this->assertSame(0, (int) $state->pending_bytes);
        $this->assertSame(0, (int) $state->audit_cursor);
        $this->assertFalse((bool) $state->audit_retry_turn);
        $this->assertFileDoesNotExist(app(ManifestStore::class)->path($stored));
        $this->assertNull($repository->claim());
        $repository->enqueue($manifest);
        $this->assertNotNull($repository->claim(), 'Fresh admissions must work after reset unlocks.');
    }

    public function test_cleanup_uses_current_reads_for_new_parts_inside_an_older_repeatable_read_snapshot(): void
    {
        DB::table('collections')->insert(['id' => 1, 'collectionhash' => sha1('cleanup-race', true), 'subject' => 'Movie', 'fromname' => 'poster',
            'totalfiles' => 1, 'groups_id' => 1, 'date' => now(), 'filecheck' => 3]);
        DB::table('binaries')->insert(['id' => 1, 'collections_id' => 1, 'binaryhash' => md5('file', true), 'name' => 'movie.mkv', 'totalparts' => 1]);
        DB::table('parts')->insert(['binaries_id' => 1, 'partnumber' => 1, 'messageid' => 'new@example.test', 'size' => 0]);
        config(['database.connections.verification_other' => config('database.connections.mysql')]);
        DB::purge('verification_other');
        $other = DB::connection('verification_other');
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        DB::beginTransaction();
        try {
            $cleanup = app(CollectionCleanupService::class);
            $this->assertSame([], $cleanup->verificationEligibleCollections()->where('id', 1)->pluck('id')->all());
            $other->transaction(static function () use ($other): void {
                $other->table('collections')->where('id', 1)->lockForUpdate()->first();
                $other->table('parts')->where('binaries_id', 1)->update(['size' => 5]);
            });
            $this->assertSame(0, (int) DB::table('parts')->value('size'), 'The consistent read still has the pre-arrival snapshot.');
            $this->assertSame(0, $cleanup->deleteCollectionsAndDescendants([1]));
            $this->assertSame(5, (int) $other->table('parts')->value('size'));
            $this->assertTrue($other->table('collections')->where('id', 1)->exists());
            $other->statement('SET SESSION innodb_lock_wait_timeout = 1');
            try {
                $other->transaction(static fn () => $other->table('collections')->where('id', 1)->lockForUpdate()->first());
                $this->fail('Header insertion must wait for the deletion guard parent lock.');
            } catch (QueryException $error) {
                $this->assertSame(1205, (int) $error->errorInfo[1]);
            }
        } finally {
            DB::rollBack();
            DB::purge('verification_other');
        }
    }
}
