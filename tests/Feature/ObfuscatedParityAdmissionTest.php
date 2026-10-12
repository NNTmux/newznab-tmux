<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Releases\Verification\ArticleSource;
use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\CollectionReleasePolicy;
use App\Services\Releases\Verification\ManifestStore;
use App\Services\Releases\Verification\PayloadVerifier;
use App\Services\YencService;
use App\Support\Configuration\IngestionConfigurationData;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

class ObfuscatedParityAdmissionTest extends ReleaseVerificationCandidatesTest
{
    #[DataProvider('collectionStates')]
    public function test_masked_complete_header_retains_actual_par2_recovery_until_verified_min_files_policy(int $status): void
    {
        if ($status === 4) {
            Schema::table('collections', static function (Blueprint $table): void {
                $table->integer('releases_id')->nullable();
            });
            Schema::create('releases', static function (Blueprint $table): void {
                $table->id();
                $table->integer('nzbstatus');
            });
            DB::table('releases')->insert(['id' => 91, 'nzbstatus' => 0]);
        }
        Schema::table('usenet_groups', static function (Blueprint $table): void {
            $table->integer('minsizetoformrelease')->default(0);
            $table->integer('minfilestoformrelease')->default(0);
        });
        Schema::create('ingestion_configurations', static function (Blueprint $table): void {
            $table->id();
        });
        Cache::put('configuration:ingestion', new IngestionConfigurationData(...array_replace(IngestionConfigurationData::defaults()->toArray(),
            ['minSizeToFormRelease' => 0, 'maxSizeToFormRelease' => 0, 'minFilesToFormRelease' => 2])), 300);
        $fixture = (string) config('release_verification.storage_path').'/original';
        app('files')->ensureDirectoryExists($fixture);
        $sourceBytes = str_repeat('movie-data', 64);
        file_put_contents($fixture.'/movie.mkv', $sourceBytes);
        (new Process(['par2', 'c', '-q', '-t1', '-s64', '-r100', '-n1', '--', 'set.par2', 'movie.mkv'], $fixture))->mustRun();
        $volumePath = glob($fixture.'/*.vol*.par2')[0];
        $volumeName = basename($volumePath);
        $raw = (new YencService)->encode(file_get_contents($volumePath), $volumeName);
        $collection = DB::table('collections')->insertGetId(['collectionhash' => sha1('masked-parity', true), 'subject' => 'masked.rar',
            'fromname' => 'fixture', 'totalfiles' => 1, 'groups_id' => 1, 'collection_regexes_id' => 0, 'filecheck' => $status,
            'date' => now()->format('Y-m-d H:i:s'), 'filesize' => filesize($volumePath)]);
        if ($status === 4) {
            DB::table('collections')->where('id', $collection)->update(['releases_id' => 91]);
        }
        $binary = DB::table('binaries')->insertGetId(['collections_id' => $collection, 'binaryhash' => md5('masked-volume', true),
            'name' => 'masked.rar', 'totalparts' => 1]);
        DB::table('parts')->insert(['binaries_id' => $binary, 'partnumber' => 1, 'messageid' => 'recovery@fixture', 'size' => strlen($raw)]);
        $repository = app(CandidateRepository::class);
        try {
            $repository->archive($collection);
            $candidate = DB::table('release_verification_candidates')->first();
            $this->assertSame('pending', $candidate->status, 'A subject extension cannot prove that this article contains no recoverable PAR2 sources.');
            $manifest = app(ManifestStore::class)->read($candidate->manifest_path);
            $this->assertSame($status === 4 ? 0 : 1, $manifest['expected_files']);
            $this->assertSame('masked.rar', $manifest['files'][0]['subject']);
            $this->assertSame('recovery@fixture', $manifest['files'][0]['segments'][0]['messageid']);
            $claim = $repository->claim();
            $this->assertNotNull($claim);
            $source = new class($raw) implements ArticleSource
            {
                public function __construct(private readonly string $raw) {}

                public function fetch(string $messageId): string
                {
                    if ($messageId !== 'recovery@fixture') {
                        throw new \RuntimeException('Unexpected article request.');
                    }

                    return $this->raw;
                }
            };
            $directory = config('release_verification.storage_path').'/scratch/'.$claim->id.'-'.$claim->version;
            $result = (new PayloadVerifier($source, (array) config('release_verification')))->verify($manifest, $directory,
                static fn () => $repository->heartbeat((int) $claim->id, $claim->lease_token));
            $this->assertSame('repaired', $result->verdict, $result->reason);
            $this->assertSame($sourceBytes, file_get_contents($directory.'/data/movie.mkv'));
            $this->assertSame(['movie.mkv', $volumeName], $result->manifest['verified_file_names']);
            $this->assertSame(2, $result->manifest['verified_file_count']);
            $this->assertNull(app(CollectionReleasePolicy::class)->verifiedRejection($result->manifest));
        } finally {
            app('files')->deleteDirectory($fixture);
        }
    }

    /** @return array<string, array{int}> */
    public static function collectionStates(): array
    {
        return ['fresh declared inventory' => [3], 'legacy observed inventory' => [4]];
    }
}
