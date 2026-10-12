<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ReleaseProcessingService;
use App\Services\Releases\Verification\CandidateRepository;
use App\Support\Configuration\IngestionConfigurationData;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerificationArchiveWindowFailureTest extends ReleaseVerificationCandidatesTest
{
    public function test_failed_archival_retains_headers_without_starving_later_windows_and_wraps_for_retry(): void
    {
        Schema::create('ingestion_configurations', static function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('releases', static function (Blueprint $table): void {
            $table->id();
            $table->integer('nzbstatus')->default(0);
        });
        Cache::put('configuration:ingestion', new IngestionConfigurationData(...array_replace(IngestionConfigurationData::defaults()->toArray(),
            ['maxReleasesCreated' => 1])), 300);
        $failed = $this->windowCollection('oversized');
        $tail = $this->windowCollection('tail');
        $wrapper = new class(app(CandidateRepository::class), $failed)
        {
            /** @var list<int> */
            public array $attempts = [];

            public bool $failureActive = true;

            public function __construct(private readonly CandidateRepository $repository, private readonly int $failedId) {}

            public function archive(int $collectionId): void
            {
                $this->attempts[] = $collectionId;
                if ($collectionId === $this->failedId && $this->failureActive) {
                    throw new \RuntimeException('Candidate exceeds metadata budget; headers retained.');
                }
                $this->repository->archive($collectionId);
            }
        };
        $this->app->instance(CandidateRepository::class, $wrapper);
        $totals = app(ReleaseProcessingService::class)->processReleaseCreationBatches(1);
        $this->assertSame([$failed, $tail], $wrapper->attempts);
        $this->assertSame(2, $totals['iterations']);
        $this->assertSame(0, $totals['releases']);
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertTrue(DB::table('collections')->where('id', $failed)->exists());
        $this->assertSame(1, DB::table('binaries')->where('collections_id', $failed)->count());
        $this->assertSame(1, DB::table('parts')->count());
        $this->assertFalse(DB::table('collections')->where('id', $tail)->exists());
        $this->assertSame(1, DB::table('release_verification_candidates')->where('status', 'pending')->count());
        $this->assertNull(Cache::get('cbp:release-window:cursor:group:1'), 'Completing the snapshot must wrap to retained failed rows.');
        $wrapper->failureActive = false;
        app(ReleaseProcessingService::class)->processReleaseCreationBatches(1);
        $this->assertSame([$failed, $tail, $failed], $wrapper->attempts);
        $this->assertFalse(DB::table('collections')->where('id', $failed)->exists());
        $this->assertSame(2, DB::table('release_verification_candidates')->where('status', 'pending')->count());
        $this->assertSame(0, DB::table('releases')->count());
    }

    private function windowCollection(string $label): int
    {
        $id = DB::table('collections')->insertGetId(['collectionhash' => sha1($label, true), 'subject' => $label.'.rar', 'fromname' => 'fixture',
            'totalfiles' => 1, 'groups_id' => 1, 'collection_regexes_id' => 0, 'filecheck' => 3,
            'date' => now()->format('Y-m-d H:i:s'), 'filesize' => 5]);
        $binary = DB::table('binaries')->insertGetId(['collections_id' => $id, 'binaryhash' => md5($label, true), 'name' => $label.'.rar', 'totalparts' => 1]);
        DB::table('parts')->insert(['binaries_id' => $binary, 'partnumber' => 1, 'messageid' => $label.'@fixture', 'size' => 5]);

        return $id;
    }
}
