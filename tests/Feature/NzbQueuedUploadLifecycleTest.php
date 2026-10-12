<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NzbImportStatus;
use App\Services\NfoService;
use App\Services\Nzb\NfoImportService;
use App\Services\Nzb\NzbImportService;
use App\Services\Nzb\NzbUploadManifestService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NzbQueuedUploadLifecycleTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'release_verification.required' => true, 'nntmux.release_dedupe_enabled' => true, 'nntmux.echocli' => false]);
        DB::purge();
        $this->directory = sys_get_temp_dir().'/queued-upload-'.bin2hex(random_bytes(8));
        config(['release_verification.storage_path' => $this->directory.'/verification']);
        (require database_path('migrations/2026_10_11_154028_create_release_verification_candidates_table.php'))->up();
        Schema::create('releases', static function (Blueprint $table): void {
            $table->id();
            $table->string('guid');
            $table->integer('nfostatus')->default(-1);
        });
        Schema::create('release_nfos', static function (Blueprint $table): void {
            $table->integer('releases_id')->primary();
            $table->binary('nfo');
        });
        Schema::create('usenet_groups', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.test']);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_queued_callback_persists_candidate_identity_before_nzb_deletion_and_nfo_waits(): void
    {
        [$path, $manifests] = $this->upload('first');
        $service = new class(['Browser' => true]) extends NzbImportService
        {
            protected function getAllGroups(): bool
            {
                return true;
            }

            protected function scanNZBFile(mixed &$nzbXML, mixed $nzbFileName = '', mixed $source = ''): NzbImportStatus
            {
                return $this->insertNZB(['verificationXml' => $nzbXML->asXML(), 'groups_id' => 1, 'useFName' => 'Movie',
                    'subject' => 'Movie', 'from' => 'poster', 'postDate' => '2026-10-11 12:00:00', 'articleFingerprint' => 'same-articles']);
            }
        };
        $service->beginImport([$path], delete: true, resultCallback: function (array $result) use ($manifests): void {
            $this->assertFileExists($result['path']);
            $this->assertSame(0, DB::transactionLevel(), 'The queued result callback follows committed admission.');
            $this->assertTrue(DB::table('release_verification_candidates')->where('identity', $result['candidate_identity'])->exists());
            $manifests->recordNzbOutcome($result['path'], $result['status'], $result['release_id'], $result['release_guid'],
                candidateIdentity: $result['candidate_identity']);
        });
        $manifestPath = dirname($path).'/'.NzbUploadManifestService::FILENAME;
        $manifest = $manifests->read($manifestPath);
        $this->assertSame(sha1('import:same-articles'), $manifest['candidate_identity']);
        $this->assertSame(DB::table('release_verification_candidates')->value('identity'), $manifest['candidate_identity']);
        $this->assertSame(NzbUploadManifestService::STATE_NZB_PENDING, $manifest['state']);
        $this->assertFileDoesNotExist($path);
        $this->assertFileExists(dirname($path).'/info.nfo');
        $result = (new NfoImportService(new Filesystem, $manifests, new NfoService))->importManifest($manifestPath);
        $this->assertSame('skipped', $result['status']);
        $this->assertSame(0, DB::table('release_nfos')->count());
    }

    public function test_both_identical_upload_origins_complete_and_import_nfo_without_original_nzb(): void
    {
        $identity = sha1('import:same-articles');
        $this->candidate($identity, 'complete', 7);
        DB::table('releases')->insert(['id' => 7, 'guid' => 'published-guid']);
        foreach (['first', 'second'] as $origin) {
            [$path, $manifests] = $this->upload($origin);
            $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
            unlink($path);
            $manifestPath = dirname($path).'/'.NzbUploadManifestService::FILENAME;
            $result = (new NfoImportService(new Filesystem, $manifests, new NfoService))->importManifest($manifestPath);
            $this->assertSame('imported', $result['status'], $result['message']);
            $manifest = $manifests->read($manifestPath);
            $this->assertSame(NzbUploadManifestService::STATE_NFO_IMPORTED, $manifest['state']);
            $this->assertSame(7, $manifest['release_id']);
            $this->assertSame('published-guid', $manifest['release_guid']);
        }
        $this->assertSame(1, DB::table('release_verification_candidates')->count());
        $this->assertSame(1, DB::table('release_nfos')->count());
    }

    public function test_existing_nzb_import_cycle_reconciles_repaired_publication(): void
    {
        $identity = sha1('import:repaired');
        $this->candidate($identity, 'repaired', 7);
        DB::table('releases')->insert(['id' => 7, 'guid' => 'repaired-guid']);
        [$path, $manifests] = $this->upload('repaired');
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
        $this->assertFalse($manifests->shouldImportNzb($path));
        $manifest = $manifests->read(dirname($path).'/'.NzbUploadManifestService::FILENAME);
        $this->assertSame(NzbUploadManifestService::STATE_NZB_IMPORTED, $manifest['state']);
        $this->assertSame(7, $manifest['release_id']);
    }

    public function test_expired_candidate_is_terminal_and_cannot_create_an_import_retry_loop(): void
    {
        $identity = sha1('import:expired');
        $this->candidate($identity, 'expired');
        [$path, $manifests] = $this->upload('expired');
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
        $this->assertFalse($manifests->shouldImportNzb($path));
        $this->assertFalse($manifests->shouldImportNzb($path));
        $manifest = $manifests->read(dirname($path).'/'.NzbUploadManifestService::FILENAME);
        $this->assertSame('nzb_rejected', $manifest['state']);
        $this->assertNull($manifest['release_id']);
        $this->assertFileExists($path);
        $this->assertFileExists(dirname($path).'/info.nfo');
    }

    public function test_unrepairable_candidate_is_rejected_before_expiry_and_paired_nfo_only_deletes_on_explicit_failure_cleanup(): void
    {
        $identity = sha1('import:unrepairable');
        $this->candidate($identity, 'unrepairable');
        [$path, $manifests] = $this->upload('unrepairable');
        $manifestPath = dirname($path).'/'.NzbUploadManifestService::FILENAME;
        $nfoPath = dirname($path).'/info.nfo';
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
        unlink($path);
        $this->assertSame(0, Artisan::call('nntmux:import-nzbs', ['--folder' => dirname($path)]));
        $manifest = $manifests->read($manifestPath);
        $this->assertSame(NzbUploadManifestService::STATE_NZB_REJECTED, $manifest['state']);
        $this->assertStringContainsString('unrepairable', $manifest['last_error']);
        $this->assertNull($manifest['release_id']);
        $this->assertNull($manifest['release_guid']);
        $this->assertFalse($manifests->shouldImportNzb($path));
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
        $this->assertSame(NzbUploadManifestService::STATE_NZB_REJECTED, $manifests->read($manifestPath)['state']);
        $importer = new NfoImportService(new Filesystem, $manifests, new NfoService);
        $this->assertSame('skipped', $importer->importManifest($manifestPath, delete: true)['status']);
        $this->assertFileExists($nfoPath);
        $this->assertSame(0, DB::table('release_nfos')->count());
        $this->assertSame('unrepairable', DB::table('release_verification_candidates')->value('status'));
        $this->assertSame('skipped', $importer->importManifest($manifestPath, deleteFailed: true)['status']);
        $this->assertFileDoesNotExist($nfoPath);
    }

    public function test_unrepairable_status_cannot_assign_a_stale_published_release_identity_to_uploaded_nfo(): void
    {
        $identity = sha1('import:unrepairable-stale');
        $this->candidate($identity, 'unrepairable', 7);
        DB::table('release_verification_candidates')->update(['verified_at' => now()]);
        DB::table('releases')->insert(['id' => 7, 'guid' => 'stale-publication']);
        [$path, $manifests] = $this->upload('unrepairable-stale');
        $manifestPath = dirname($path).'/'.NzbUploadManifestService::FILENAME;
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
        $this->assertSame(NzbUploadManifestService::STATE_NZB_REJECTED, $manifests->reconcilePending($manifestPath)['state']);
        $this->assertNull($manifests->read($manifestPath)['release_id']);
        $this->assertNull($manifests->read($manifestPath)['release_guid']);
        $this->assertSame('skipped', (new NfoImportService(new Filesystem, $manifests, new NfoService))->importManifest($manifestPath)['status']);
        $this->assertSame(0, DB::table('release_nfos')->count());
        $this->assertFileExists(dirname($path).'/info.nfo');
    }

    public function test_unpublished_or_missing_release_is_never_given_to_nfo_importer(): void
    {
        foreach (['pending', 'unknown', 'verifying', 'complete'] as $status) {
            $identity = sha1('import:'.$status);
            $this->candidate($identity, $status, 999);
            [$path, $manifests] = $this->upload($status);
            $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
            $this->assertFalse($manifests->shouldImportNzb($path));
            $manifest = $manifests->read(dirname($path).'/'.NzbUploadManifestService::FILENAME);
            $this->assertNull($manifest['release_id']);
            $this->assertNull($manifest['release_guid']);
        }
        $this->assertSame(0, DB::table('release_nfos')->count());
    }

    public function test_queued_origin_rejects_arbitrary_path_as_candidate_identity(): void
    {
        [$path, $manifests] = $this->upload('unsafe');
        $this->expectException(\RuntimeException::class);
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: '../outside.json');
    }

    public function test_nzb_command_completes_upload_without_nfo_after_original_nzb_was_deleted(): void
    {
        $identity = sha1('import:no-nfo');
        $this->candidate($identity, 'complete', 7);
        DB::table('releases')->insert(['id' => 7, 'guid' => 'no-nfo-guid']);
        [$path, $manifests] = $this->upload('no-nfo');
        $manifests->create(dirname($path), 'no-nfo', 'Release.nzb', null);
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
        unlink($path);
        unlink(dirname($path).'/info.nfo');
        $this->assertSame(0, Artisan::call('nntmux:import-nzbs', ['--folder' => dirname($path), '--delete' => true]));
        $manifest = $manifests->read(dirname($path).'/'.NzbUploadManifestService::FILENAME);
        $this->assertSame(NzbUploadManifestService::STATE_NZB_IMPORTED, $manifest['state']);
        $this->assertSame(7, $manifest['release_id']);
        $this->assertSame('no-nfo-guid', $manifest['release_guid']);
        $this->assertNull($manifest['nfo']);
    }

    public function test_repeated_queued_callback_cannot_regress_a_completed_nfo_origin(): void
    {
        $identity = sha1('import:repeat');
        $this->candidate($identity, 'complete', 7);
        DB::table('releases')->insert(['id' => 7, 'guid' => 'repeat-guid']);
        [$path, $manifests] = $this->upload('repeat');
        $manifestPath = dirname($path).'/'.NzbUploadManifestService::FILENAME;
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
        $result = (new NfoImportService(new Filesystem, $manifests, new NfoService))->importManifest($manifestPath);
        $this->assertSame('imported', $result['status']);
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
        $this->assertSame(NzbUploadManifestService::STATE_NFO_IMPORTED, $manifests->read($manifestPath)['state']);
    }

    public function test_removed_candidate_is_terminal_without_original_nzb_and_preserves_paired_nfo_until_explicit_cleanup(): void
    {
        $identity = sha1('import:removed');
        $this->candidate($identity, 'pending');
        [$path, $manifests] = $this->upload('removed');
        $manifestPath = dirname($path).'/'.NzbUploadManifestService::FILENAME;
        $nfoPath = dirname($path).'/info.nfo';
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
        unlink($path);
        DB::table('release_verification_candidates')->where('identity', $identity)->delete();

        $this->assertSame(0, Artisan::call('nntmux:import-nzbs', ['--folder' => dirname($path), '--delete' => true]));
        $manifest = $manifests->read($manifestPath);
        $this->assertSame(NzbUploadManifestService::STATE_NZB_REJECTED, $manifest['state']);
        $this->assertSame($identity, $manifest['candidate_identity']);
        $this->assertNull($manifest['release_id']);
        $this->assertNull($manifest['release_guid']);
        $this->assertStringContainsString('candidate no longer exists', $manifest['last_error']);
        $this->assertFalse($manifests->shouldImportNzb($path));
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
        $this->assertSame(NzbUploadManifestService::STATE_NZB_REJECTED, $manifests->reconcilePending($manifestPath)['state']);
        $importer = new NfoImportService(new Filesystem, $manifests, new NfoService);
        $result = $importer->importManifest($manifestPath, delete: true);
        $this->assertSame('skipped', $result['status']);
        $this->assertStringContainsString('nzb_rejected', $result['message']);
        $this->assertFileExists($nfoPath);
        $this->assertSame(0, DB::table('release_nfos')->count());
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertSame(0, DB::table('release_verification_candidates')->count());
        $this->assertSame('skipped', $importer->importManifest($manifestPath, deleteFailed: true)['status']);
        $this->assertFileDoesNotExist($nfoPath);
        $this->assertSame(NzbUploadManifestService::STATE_NZB_REJECTED, $manifests->read($manifestPath)['state']);
    }

    public function test_outer_transaction_rollback_preserves_pending_upload_instead_of_uncommitted_terminal_outcome(): void
    {
        foreach (['missing', 'published'] as $kind) {
            $identity = sha1('import:outer-'.$kind);
            [$path, $manifests] = $this->upload('outer-'.$kind);
            $manifestPath = dirname($path).'/'.NzbUploadManifestService::FILENAME;
            $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
            DB::beginTransaction();
            try {
                if ($kind === 'published') {
                    $this->candidate($identity, 'complete', 7);
                    DB::table('releases')->insert(['id' => 7, 'guid' => 'uncommitted-publication']);
                }
                $this->assertSame(NzbUploadManifestService::STATE_NZB_PENDING, $manifests->reconcilePending($manifestPath)['state']);
                $this->assertSame(NzbUploadManifestService::STATE_NZB_PENDING, $manifests->read($manifestPath)['state']);
            } finally {
                DB::rollBack();
            }
            $this->assertSame(NzbUploadManifestService::STATE_NZB_PENDING, $manifests->read($manifestPath)['state']);
            $this->assertSame(NzbUploadManifestService::STATE_NZB_REJECTED, $manifests->reconcilePending($manifestPath)['state']);
            $this->assertFileExists(dirname($path).'/info.nfo');
        }
    }

    public function test_outer_transaction_commit_performs_fresh_durable_upload_reconciliation(): void
    {
        $identity = sha1('import:outer-commit');
        [$path, $manifests] = $this->upload('outer-commit');
        $manifestPath = dirname($path).'/'.NzbUploadManifestService::FILENAME;
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
        DB::beginTransaction();
        $this->candidate($identity, 'complete', 7);
        DB::table('releases')->insert(['id' => 7, 'guid' => 'committed-publication']);
        try {
            $this->assertSame(NzbUploadManifestService::STATE_NZB_PENDING, $manifests->reconcilePending($manifestPath)['state']);
            $this->assertSame(NzbUploadManifestService::STATE_NZB_PENDING, $manifests->read($manifestPath)['state']);
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        $manifest = $manifests->read($manifestPath);
        $this->assertSame(NzbUploadManifestService::STATE_NZB_IMPORTED, $manifest['state']);
        $this->assertSame(7, $manifest['release_id']);
        $this->assertSame('committed-publication', $manifest['release_guid']);
    }

    public function test_reconciliation_commit_failure_cannot_persist_terminal_upload_metadata(): void
    {
        [$path, $manifests] = $this->upload('commit-failure');
        $manifestPath = dirname($path).'/'.NzbUploadManifestService::FILENAME;
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: sha1('import:commit-failure'));
        $interrupted = false;
        DB::listen(function (QueryExecuted $event) use (&$interrupted): void {
            if (! $interrupted && str_contains($event->sql, 'from "release_verification_candidates" as "candidates"')) {
                $interrupted = true;
                DB::connection()->getPdo()->commit();
            }
        });
        try {
            $manifests->reconcilePending($manifestPath);
            $this->fail('Interrupted database commit must fail reconciliation.');
        } catch (\PDOException $error) {
            $this->assertStringContainsString('no active transaction', $error->getMessage());
        }
        $this->assertTrue($interrupted);
        $this->assertSame(NzbUploadManifestService::STATE_NZB_PENDING, $manifests->read($manifestPath)['state']);
        $this->assertFileExists(dirname($path).'/info.nfo');
    }

    public function test_reconciliation_failure_preserves_pending_state_and_uploaded_nfo_for_retry(): void
    {
        [$path, $manifests] = $this->upload('retry');
        $manifestPath = dirname($path).'/'.NzbUploadManifestService::FILENAME;
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: sha1('import:retry'));
        Schema::drop('release_verification_candidates');
        $result = (new NfoImportService(new Filesystem, $manifests, new NfoService))->importManifest($manifestPath, deleteFailed: true);
        $this->assertSame('failed', $result['status']);
        $this->assertSame(NzbUploadManifestService::STATE_NZB_PENDING, $manifests->read($manifestPath)['state']);
        $this->assertFileExists(dirname($path).'/info.nfo');
    }

    public function test_success_state_without_verification_timestamp_does_not_assign_release_identity(): void
    {
        $identity = sha1('import:unverified');
        $this->candidate($identity, 'complete', 7);
        DB::table('releases')->insert(['id' => 7, 'guid' => 'unverified-guid']);
        DB::table('release_verification_candidates')->update(['verified_at' => null]);
        [$path, $manifests] = $this->upload('unverified');
        $manifests->recordNzbOutcome($path, NzbImportStatus::Queued, null, null, candidateIdentity: $identity);
        $this->assertFalse($manifests->shouldImportNzb($path));
        $manifest = $manifests->read(dirname($path).'/'.NzbUploadManifestService::FILENAME);
        $this->assertSame('nzb_rejected', $manifest['state']);
        $this->assertNull($manifest['release_id']);
    }

    private function candidate(string $identity, string $status, ?int $releaseId = null): void
    {
        DB::table('release_verification_candidates')->insert(['identity' => $identity, 'source_identity' => $identity, 'groups_id' => 1,
            'manifest_path' => '', 'manifest_hash' => str_repeat('a', 64), 'manifest_bytes' => 0, 'status' => $status,
            'release_id' => $releaseId, 'verified_at' => in_array($status, ['complete', 'repaired'], true) ? now() : null,
            'last_unique_at' => now(), 'next_attempt_at' => now()]);
    }

    /** @return array{string, NzbUploadManifestService} */
    private function upload(string $origin): array
    {
        $filesystem = new Filesystem;
        $directory = $this->directory.'/'.$origin;
        $filesystem->makeDirectory($directory, 0700, true);
        $path = $directory.'/Release.nzb';
        $filesystem->put($path, '<nzb><file subject="Release [1/1] &quot;movie.mkv&quot; (1/1)"><groups><group>alt.test</group></groups><segments><segment bytes="5" number="1">a@b</segment></segments></file></nzb>');
        $filesystem->put($directory.'/info.nfo', 'paired uploaded NFO');
        $manifests = new NzbUploadManifestService($filesystem);
        $manifests->create($directory, $origin, 'Release.nzb', 'info.nfo');

        return [$path, $manifests];
    }
}
