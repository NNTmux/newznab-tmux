<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Releases\Verification\ArticleSource;
use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\ManifestStore;
use App\Services\Releases\Verification\NzbManifestReader;
use App\Services\Releases\Verification\PayloadVerifier;
use App\Services\YencService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class LegacyVerificationInventoryTest extends ReleaseVerificationCandidatesTest
{
    #[DataProvider('legacyDeclarations')]
    public function test_legacy_inventory_uses_declarations_not_observed_counts(string $subject, int $expected, string $verdict): void
    {
        Schema::table('collections', static function (Blueprint $table): void {
            $table->integer('releases_id')->nullable();
        });
        Schema::create('releases', static function (Blueprint $table): void {
            $table->id();
            $table->integer('nzbstatus');
        });
        DB::table('releases')->insert(['id' => 91, 'nzbstatus' => 0]);
        $id = $this->inventoryCollection(1, 'legacy@fixture', 1);
        DB::table('collections')->where('id', $id)->update(['filecheck' => 4, 'releases_id' => 91]);
        DB::table('binaries')->where('collections_id', $id)->update(['name' => $subject]);
        app(CandidateRepository::class)->archive($id);
        $candidate = DB::table('release_verification_candidates')->first();
        $this->assertNotNull($candidate);
        $manifest = app(ManifestStore::class)->read($candidate->manifest_path);
        $this->assertSame($expected, $manifest['expected_files']);
        $source = new class implements ArticleSource
        {
            public function fetch(string $messageId): string
            {
                return (new YencService)->encode('hello', 'movie.mkv');
            }
        };
        config(['release_verification.free_bytes' => 0, 'release_verification.bytes_per_second' => 0]);
        $result = (new PayloadVerifier($source, (array) config('release_verification')))->verify(
            $manifest, config('release_verification.storage_path').'/scratch/legacy-inventory', static function (): void {},
        );
        $this->assertSame($verdict, $result->verdict, $result->reason);
        if ($verdict === 'unknown') {
            $this->assertStringContainsString('inventory', $result->reason);
        }
        $this->assertSame(1, DB::table('releases')->count());
        $this->assertSame(0, (int) DB::table('releases')->value('nzbstatus'));
    }

    /** @return array<string, array{string, int, string}> */
    public static function legacyDeclarations(): array
    {
        return ['observed count remains unknown' => ['movie.mkv', 0, 'unknown'],
            'explicit complete inventory' => ['Movie [1/1] "movie.mkv" (1/1)', 1, 'complete'],
            'zero based file inventory' => ['Movie [0/1] "movie.mkv" (1/1)', 1, 'complete'],
            'single quoted complete inventory' => ["Movie [1/1] 'movie.mkv' (1/1)", 1, 'complete'],
            'explicit missing file' => ['Movie [1/2] "movie.mkv" (1/1)', 2, 'unknown'],
            'filename counter ignored' => ['Movie "movie[1/1].mkv" (1/1)', 0, 'unknown'],
            'invalid file index ignored' => ['Movie [2/1] "movie.mkv" (1/1)', 0, 'unknown'],
            'article count is not file count' => ['Movie "movie.mkv" (1/20)', 0, 'unknown']];
    }

    #[DataProvider('filenameSubjects')]
    public function test_filename_counter_is_not_a_declared_release_inventory(string $subject): void
    {
        $xml = '<nzb><file subject="'.htmlspecialchars($subject, ENT_XML1 | ENT_QUOTES, 'UTF-8').'"><groups><group>alt.test</group></groups><segments><segment bytes="5" number="1">movie@fixture</segment></segments></file></nzb>';
        $manifest = app(NzbManifestReader::class)->read($xml, (object) ['searchname' => 'Movie', 'fromname' => 'poster',
            'postdate' => now()->format('Y-m-d H:i:s'), 'groups_id' => 1]);
        $source = new class implements ArticleSource
        {
            public function fetch(string $messageId): string
            {
                return (new YencService)->encode('hello', 'movie[1/1].mkv');
            }
        };
        config(['release_verification.free_bytes' => 0, 'release_verification.bytes_per_second' => 0]);
        $result = (new PayloadVerifier($source, (array) config('release_verification')))->verify(
            $manifest, config('release_verification.storage_path').'/scratch/filename-inventory', static function (): void {},
        );
        $this->assertSame(0, $manifest['expected_files']);
        $this->assertSame('unknown', $result->verdict);
    }

    /** @return array<string, array{string}> */
    public static function filenameSubjects(): array
    {
        return ['double quotes' => ['"movie[1/1].mkv" (1/1)'],
            'single quotes' => ["'movie[1/1].mkv' (1/1)"],
            'embedded unquoted filename' => ['movie[1/1].mkv (1/1)']];
    }

    #[DataProvider('inventoryTransitions')]
    public function test_later_inventory_evidence_preserves_known_bounds(int $first, int $second, bool $conflict, int $expected): void
    {
        $repository = app(CandidateRepository::class);
        $repository->archive($this->inventoryCollection($first, 'first@fixture', 1, 2));
        $before = DB::table('release_verification_candidates')->first();
        $repository->archive($this->inventoryCollection($second, 'second@fixture', 2, 2));
        $after = DB::table('release_verification_candidates')->first();
        $manifest = app(ManifestStore::class)->read($after->manifest_path);
        $this->assertSame($conflict, $manifest['conflict']);
        $this->assertSame($expected, $manifest['expected_files']);
        $this->assertCount(2, $manifest['files'][0]['segments']);
        $this->assertGreaterThan($before->version, $after->version);
    }

    /** @return array<string, array{int, int, bool, int}> */
    public static function inventoryTransitions(): array
    {
        return ['unknown becomes known' => [0, 2, false, 2],
            'unknown cannot erase known' => [2, 0, false, 2],
            'contradictory known counts stay blocked' => [2, 1, true, 2]];
    }

    private function inventoryCollection(int $expected, string $messageId, int $part, int $parts = 1): int
    {
        $id = DB::table('collections')->insertGetId(['collectionhash' => sha1('legacy-inventory', true), 'subject' => 'Movie',
            'fromname' => 'poster', 'totalfiles' => $expected, 'groups_id' => 1, 'date' => now()->format('Y-m-d H:i:s')]);
        $binary = DB::table('binaries')->insertGetId(['collections_id' => $id, 'binaryhash' => md5('movie', true),
            'name' => 'movie.mkv', 'totalparts' => $parts]);
        DB::table('parts')->insert(['binaries_id' => $binary, 'partnumber' => $part, 'messageid' => $messageId, 'size' => 5]);

        return $id;
    }
}
