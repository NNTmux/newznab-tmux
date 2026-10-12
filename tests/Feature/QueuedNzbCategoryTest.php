<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NzbImportStatus;
use App\Facades\Search;
use App\Models\Category;
use App\Services\BlacklistService;
use App\Services\Categorization\CategorizationService;
use App\Services\Nzb\NzbImportService;
use App\Services\ReleaseCleaningService;
use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\ManifestStore;
use App\Services\Releases\Verification\NzbManifestReader;
use App\Services\Releases\Verification\ReleaseMetadataResolver;
use App\Services\Releases\Verification\VerificationResult;
use App\Services\Releases\Verification\VerifiedReleasePublisher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class QueuedNzbCategoryTest extends ReleaseVerificationCandidatesTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('categories', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('title');
            $table->integer('status');
        });
        DB::table('categories')->insert([
            ['id' => 2040, 'title' => 'Movie HD', 'status' => Category::STATUS_ACTIVE],
            ['id' => 3040, 'title' => 'Lossless', 'status' => Category::STATUS_INACTIVE],
            ['id' => 6040, 'title' => 'X264', 'status' => Category::STATUS_DISABLED],
            ['id' => 7010, 'title' => 'Other', 'status' => Category::STATUS_ACTIVE],
        ]);
    }

    /** @return array<string, array{string, ?int}> */
    public static function categories(): array
    {
        return [
            'active id' => ['2040', 2040],
            'active title' => [' movie HD ', 2040],
            'inactive id' => ['3040', null],
            'disabled title' => ['X264', null],
            'unknown id' => ['9999', null],
            'unknown title' => ['No Such Category', null],
            'blank' => ['', null],
        ];
    }

    #[DataProvider('categories')]
    public function test_scanned_import_persists_only_a_resolved_active_category(string $category, ?int $expected): void
    {
        $manifest = $this->queue($category);
        $this->assertSame('import', $manifest['metadata_source'] ?? null);
        $this->assertSame($expected, $manifest['categories_id'] ?? null);
        $claim = app(CandidateRepository::class)->claim();
        $this->assertNotNull($claim);
        $this->assertSame($manifest, app(ManifestStore::class)->read($claim->manifest_path));
    }

    public function test_direct_audit_reader_does_not_take_category_override_from_xml(): void
    {
        $manifest = app(NzbManifestReader::class)->read($this->xml('2040'), (object) [
            'searchname' => 'Movie', 'fromname' => 'poster', 'postdate' => '2026-10-11 12:00:00', 'groups_id' => 1,
            'categories_id' => 2040,
            'import_name_override' => true,
            'original_subject' => 'Untrusted replacement',
        ]);
        $this->assertSame('audit', $manifest['metadata_source'] ?? null);
        $this->assertArrayNotHasKey('categories_id', $manifest);
        $this->assertArrayNotHasKey('import_name_override', $manifest);
        $this->assertArrayNotHasKey('original_subject', $manifest);
    }

    /** @return array<string, array{string}> */
    public static function selectedFilenames(): array
    {
        return ['selected custom name' => ['My handpicked label'], 'selected zero' => ['0'], 'original subject' => ['']];
    }

    #[DataProvider('selectedFilenames')]
    public function test_selected_custom_filename_matches_legacy_import_after_verified_publication(string $selectedName): void
    {
        $this->publicationSchema();
        $rawSubject = 'Raw #posted @subject [1/1] yEnc (1/1)';
        $originalSubject = 'Raw #posted @subject [1/1] yEnc';
        $poster = "'poster'";
        $xml = str_replace(['poster="poster"', 'Release [1/1] &quot;movie.mkv&quot; (1/1)'],
            ['poster="&apos;poster&apos;"', $rawSubject], $this->xml('2040'));
        $manifest = $this->queue('2040', $selectedName, $xml);
        $manifest['identity_hints'] = [['name' => 'Different.Movie.2026.1080p-GRP.mkv', 'source' => 'yenc']];
        Search::shouldReceive('matchPredbExact')->andReturnNull();
        Search::shouldReceive('updateRelease')->twice();
        $cleaner = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner', 'releaseNameFromFile']);
        $cleaner->expects($this->exactly($selectedName === '' ? 2 : 0))->method('releaseCleaner')
            ->with($originalSubject, $poster, 'alt.test')->willReturn(['cleansubject' => 'Cleaner replacement', 'properlynamed' => true]);
        $cleaner->expects($this->never())->method('releaseNameFromFile');
        config(['release_verification.required' => false]);
        $service = app(NzbImportService::class);
        (new \ReflectionProperty($service, 'releaseCleaner'))->setValue($service, $cleaner);
        $status = (new \ReflectionMethod($service, 'insertNZB'))->invoke($service, [
            'subject' => $rawSubject, 'useFName' => $selectedName, 'from' => $poster, 'postDate' => '2026-10-11 12:00:00',
            'groups_id' => 1, 'groupName' => 'alt.test', 'totalFiles' => 1, 'totalSize' => 5, 'nzbCategoryId' => 2040,
        ]);
        $this->assertSame(NzbImportStatus::Inserted, $status);
        $legacy = DB::table('releases')->first();
        $this->assertSame($selectedName !== '' ? $selectedName : 'Cleaner replacement', $legacy->searchname);
        $this->assertSame($originalSubject, $legacy->name);
        $this->assertSame($poster, $legacy->fromname);
        $this->assertSame(1, (int) $legacy->isrenamed);
        config(['release_verification.required' => true]);
        $categorization = $this->createStub(CategorizationService::class);
        $this->app->instance(ReleaseMetadataResolver::class, new ReleaseMetadataResolver($cleaner, null, $categorization));
        $claim = app(CandidateRepository::class)->claim();
        $id = app(VerifiedReleasePublisher::class)->publish($claim, new VerificationResult('complete', 'Verified payload.', $manifest));
        $published = DB::table('releases')->where('id', $id)->first();
        $this->assertSame($legacy->searchname, $published->searchname);
        $this->assertSame((int) $legacy->isrenamed, (int) $published->isrenamed);
        $this->assertSame($legacy->name, $published->name);
        $this->assertSame($legacy->fromname, $published->fromname);
        $this->assertSame($selectedName !== '', $manifest['import_name_override'] ?? false);
        $this->assertSame($originalSubject, $manifest['original_subject']);
    }

    #[DataProvider('categories')]
    public function test_verified_publication_preserves_the_override_or_uses_automatic_fallback(string $category, ?int $expected): void
    {
        $manifest = $this->queue($category);
        $this->publicationSchema();
        $cleaner = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
        $cleaner->expects($this->once())->method('releaseCleaner')->willReturn(['cleansubject' => 'Movie', 'properlynamed' => true]);
        $categorization = $this->createMock(CategorizationService::class);
        $categorization->expects($this->exactly($expected === null ? 1 : 0))->method('determineCategory')->willReturn(['categories_id' => 7010]);
        $this->app->instance(ReleaseMetadataResolver::class, new ReleaseMetadataResolver($cleaner, null, $categorization));
        Search::shouldReceive('matchPredbExact')->andReturnNull();
        Search::shouldReceive('updateRelease')->once();
        $claim = app(CandidateRepository::class)->claim();
        $id = app(VerifiedReleasePublisher::class)->publish($claim, new VerificationResult('complete', 'Verified payload.', $manifest));
        $this->assertSame($expected ?? 7010, (int) DB::table('releases')->where('id', $id)->value('categories_id'));
    }

    /** @return array<string, mixed> */
    private function queue(string $category, string $filename = '', ?string $xmlSource = null): array
    {
        $service = new class(['Browser' => true]) extends NzbImportService
        {
            public function queueForTest(\SimpleXMLElement $xml, string $filename): NzbImportStatus
            {
                $this->allGroups = ['alt.test' => 1];
                $this->blacklistService = new class extends BlacklistService
                {
                    public function getBlacklist(bool $activeOnly = true, int|string $opType = -1, string $groupName = '', bool $groupRegex = false): array
                    {
                        return [];
                    }
                };

                return $this->scanNZBFile($xml, $filename);
            }
        };
        $xml = simplexml_load_string($xmlSource ?? $this->xml($category));
        $this->assertSame(NzbImportStatus::Queued, $service->queueForTest($xml, $filename));
        $candidate = DB::table('release_verification_candidates')->first();

        return app(ManifestStore::class)->read($candidate->manifest_path);
    }

    private function xml(string $category): string
    {
        return '<nzb xmlns="http://www.newzbin.com/DTD/2003/nzb"><head><meta type="category">'.htmlspecialchars($category, ENT_XML1).'</meta></head>'
            .'<file poster="poster" date="1791720000" subject="Release [1/1] &quot;movie.mkv&quot; (1/1)"><groups><group>alt.test</group></groups>'
            .'<segments><segment bytes="5" number="1">first@article</segment></segments></file></nzb>';
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
    }
}
