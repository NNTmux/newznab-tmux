<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NzbImportStatus;
use App\Services\Nzb\NzbImportService;
use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\ManifestStore;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ReleaseVerificationCandidatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['release_verification.required' => true, 'nntmux.echocli' => false, 'release_verification.storage_path' => storage_path('framework/testing/verify-'.bin2hex(random_bytes(6)))]);
        $this->configureVerificationDatabase();
        $migration = require database_path('migrations/2026_10_11_154028_create_release_verification_candidates_table.php');
        $migration->up();
        (require database_path('migrations/2026_10_12_004819_create_release_verification_scratch_table.php'))->up();
        (require database_path('migrations/2026_10_12_053000_add_shared_verification_scratch_budget.php'))->up();
        (require database_path('migrations/2026_10_11_175742_create_release_verification_audit_retries_table.php'))->up();
        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->binary('collectionhash');
            $table->string('subject');
            $table->string('fromname');
            $table->integer('totalfiles');
            $table->integer('groups_id');
            $table->integer('collection_regexes_id')->default(0);
            $table->dateTime('date');
            $table->integer('filecheck')->default(3);
            $table->integer('filesize')->default(5);
        });
        Schema::create('binaries', function (Blueprint $table): void {
            $table->id();
            $table->integer('collections_id');
            $table->binary('binaryhash');
            $table->string('name');
            $table->integer('totalparts');
        });
        Schema::create('parts', function (Blueprint $table): void {
            $table->integer('binaries_id');
            $table->integer('partnumber');
            $table->string('messageid');
            $table->integer('size');
        });
        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.test']);
    }

    protected function configureVerificationDatabase(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
    }

    protected function tearDown(): void
    {
        $path = (string) config('release_verification.storage_path');
        if (is_dir($path)) {
            app('files')->deleteDirectory($path);
        }
        parent::tearDown();
    }

    public function test_archive_preserves_declared_counts_before_removing_hot_rows(): void
    {
        $id = $this->collection('a@b');
        app(CandidateRepository::class)->archive($id);
        $candidate = DB::table('release_verification_candidates')->first();
        $manifest = app(ManifestStore::class)->read($candidate->manifest_path);
        $this->assertSame(2, $manifest['expected_files']);
        $this->assertSame('a@b', $manifest['files'][0]['segments'][0]['messageid']);
        $this->assertSame(0, DB::table('collections')->count());
        $this->assertSame(0, DB::table('parts')->count());
    }

    public function test_snapshot_batches_part_reads_and_preserves_empty_files_and_segment_order(): void
    {
        $id = $this->collection('seed@article');
        DB::table('parts')->delete();
        DB::table('binaries')->delete();
        $expected = [];
        for ($file = 0; $file < 101; $file++) {
            $key = md5('file:'.$file);
            $binaryId = DB::table('binaries')->insertGetId(['collections_id' => $id,
                'binaryhash' => hex2bin($key), 'name' => 'file'.$file.'.rar', 'totalparts' => 2]);
            $segments = [];
            if ($file !== 50) {
                foreach ([2, 1] as $number) {
                    $messageId = 'file'.$file.'.part'.$number.'@article';
                    DB::table('parts')->insert(['binaries_id' => $binaryId, 'partnumber' => $number,
                        'messageid' => $messageId, 'size' => $number * 5]);
                    $segments[$number] = ['number' => $number, 'messageid' => $messageId, 'bytes' => $number * 5];
                }
                ksort($segments);
            }
            $expected[$key] = ['key' => $key, 'subject' => 'file'.$file.'.rar', 'totalparts' => 2,
                'segments' => array_values($segments)];
        }
        ksort($expected);
        $partReads = 0;
        DB::listen(static function (QueryExecuted $query) use (&$partReads): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from "parts"')) {
                $partReads++;
            }
        });

        $manifest = (new ReflectionMethod(CandidateRepository::class, 'snapshot'))->invoke(
            app(CandidateRepository::class), DB::table('collections')->where('id', $id)->first());

        $this->assertSame(array_values($expected), $manifest['files']);
        $this->assertSame(2, $partReads);
    }

    public function test_batched_snapshot_rejects_oversized_metadata_without_removing_headers(): void
    {
        $id = $this->collection('seed@article');
        DB::table('parts')->update(['messageid' => str_repeat('x', 16777216)]);
        try {
            app(CandidateRepository::class)->archive($id);
            $this->fail('Oversized metadata must not be archived.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Candidate exceeds metadata budget; headers retained.', $exception->getMessage());
        }

        $this->assertSame(1, DB::table('collections')->count());
        $this->assertSame(1, DB::table('binaries')->count());
        $this->assertSame(1, DB::table('parts')->count());
        $this->assertSame(0, DB::table('release_verification_candidates')->count());
        $this->assertSame(0, (int) DB::table('release_verification_state')->value('pending_bytes'));
    }

    public function test_late_segments_invalidate_a_claim_without_losing_earlier_articles(): void
    {
        $repository = app(CandidateRepository::class);
        $repository->archive($this->collection('a@b'));
        $claim = $repository->claim();
        $repository->archive($this->collection('c@d', 2));
        $candidate = DB::table('release_verification_candidates')->first();
        $manifest = app(ManifestStore::class)->read($candidate->manifest_path);
        $this->assertCount(2, $manifest['files'][0]['segments']);
        $this->assertNotSame($claim->version, $candidate->version);
        $this->expectException(\RuntimeException::class);
        $repository->heartbeat($claim->id, $claim->lease_token);
    }

    public function test_duplicate_arrivals_do_not_extend_expiry(): void
    {
        $repository = app(CandidateRepository::class);
        $repository->archive($this->collection('a@b'));
        $before = DB::table('release_verification_candidates')->value('last_unique_at');
        $this->travel(1)->hours();
        $repository->archive($this->collection('a@b'));
        $this->assertSame($before, DB::table('release_verification_candidates')->value('last_unique_at'));
    }

    public function test_published_collection_hash_does_not_discard_a_different_repost(): void
    {
        $repository = app(CandidateRepository::class);
        $repository->archive($this->collection('old@article'));
        DB::table('release_verification_candidates')->update(['release_id' => 123, 'status' => 'complete']);
        $repository->archive($this->collection('new@article'));
        $this->assertSame(2, DB::table('release_verification_candidates')->count());
        $pending = DB::table('release_verification_candidates')->whereNull('release_id')->first();
        $manifest = app(ManifestStore::class)->read($pending->manifest_path);
        $this->assertSame('new@article', $manifest['files'][0]['segments'][0]['messageid']);
    }

    public function test_import_queues_without_creating_a_public_release(): void
    {
        config(['release_verification.required' => true]);
        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
        });
        $xml = '<nzb><file subject="Release [1/1] &quot;movie.mkv&quot; (1/1)"><groups><group>alt.test</group></groups><segments><segment bytes="5" number="1">a@b</segment></segments></file></nzb>';
        $service = app(NzbImportService::class);
        $status = (new ReflectionMethod($service, 'insertNZB'))->invoke($service, ['verificationXml' => $xml,
            'groups_id' => 1, 'useFName' => 'Movie', 'subject' => 'Movie', 'from' => 'poster', 'postDate' => '2026-10-11 12:00:00', 'articleFingerprint' => sha1('a@b')]);
        $this->assertSame(NzbImportStatus::Queued, $status);
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertSame(1, DB::table('release_verification_candidates')->count());
    }

    public function test_duplicate_hot_articles_do_not_invalidate_a_running_verification(): void
    {
        $repository = app(CandidateRepository::class);
        $repository->archive($this->collection('a@b'));
        $claim = $repository->claim();
        $this->travel(1)->hours();
        $id = $this->collection('a@b');
        DB::transaction(fn () => $repository->invalidateHotCollections([$id]));
        $candidate = DB::table('release_verification_candidates')->first();
        $this->assertSame($claim->lease_token, $candidate->lease_token);
        $this->assertSame($claim->last_unique_at, $candidate->last_unique_at);
    }

    public function test_new_hot_article_invalidates_lease_and_is_eligible_for_immediate_archival(): void
    {
        $repository = app(CandidateRepository::class);
        $repository->archive($this->collection('a@b'));
        $claim = $repository->claim();
        $id = $this->collection('new@article', 2);
        DB::table('collections')->where('id', $id)->update(['filecheck' => 0]);
        DB::transaction(fn () => $repository->invalidateHotCollections([$id]));
        $this->assertSame(3, (int) DB::table('collections')->where('id', $id)->value('filecheck'));
        $this->assertNull(DB::table('release_verification_candidates')->value('lease_token'));
        $repository->archive($id);
        $this->assertNotNull($repository->claim());
    }

    public function test_conflicting_declared_part_count_invalidates_lease_without_a_new_article(): void
    {
        $repository = app(CandidateRepository::class);
        $repository->archive($this->collection('a@b'));
        $claim = $repository->claim();
        $id = $this->collection('a@b');
        DB::table('binaries')->where('collections_id', $id)->update(['totalparts' => 3]);
        DB::transaction(fn () => $repository->invalidateHotCollections([$id]));
        $this->assertNull(DB::table('release_verification_candidates')->value('lease_token'));
        $this->assertGreaterThan($claim->version, DB::table('release_verification_candidates')->value('version'));
    }

    private function collection(string $messageId, int $part = 1): int
    {
        $id = DB::table('collections')->insertGetId(['collectionhash' => sha1('test', true), 'subject' => 'Movie', 'fromname' => 'poster',
            'totalfiles' => 2, 'groups_id' => 1, 'date' => '2026-10-11 12:00:00']);
        $binary = DB::table('binaries')->insertGetId(['collections_id' => $id, 'binaryhash' => md5('file:1', true), 'name' => 'movie.mkv', 'totalparts' => 2]);
        DB::table('parts')->insert(['binaries_id' => $binary, 'partnumber' => $part, 'messageid' => $messageId, 'size' => 5]);

        return $id;
    }
}
