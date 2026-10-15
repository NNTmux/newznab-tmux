<?php

namespace Tests\Unit\AdditionalProcessing;

use App\Models\Release;
use App\Services\AdditionalProcessing\ArchiveExtractionService;
use App\Services\AdditionalProcessing\SevenZipListingParser;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\Releases\ReleaseBrowseService;
use dariusiii\rarinfo\ArchiveInfo;
use dariusiii\rarinfo\Par2Info;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ArchiveExtractionServiceTest extends TestCase
{
    use BuildsArchiveFixtures;
    use CreatesProcessingConfiguration;

    private string $workDir = '';

    protected function tearDown(): void
    {
        Mockery::close();
        if ($this->workDir !== '') {
            array_map('unlink', glob($this->workDir.'/*') ?: []);
            rmdir($this->workDir);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_returns_file_data_directly_from_archive_info_when_available(): void
    {
        $archiveInfo = Mockery::mock(ArchiveInfo::class);
        $archiveInfo->shouldReceive('setData')->once()->with('ARCHIVE', true)->andReturn(true);
        $archiveInfo->shouldReceive('getArchiveFileList')->once()->andReturn([
            ['name' => 'cover.jpg', 'size' => 10, 'pass' => 0, 'compressed' => 0, 'range' => '20-29', 'source' => 'main'],
        ]);
        $archiveInfo->shouldReceive('getFileData')->once()->with('cover.jpg')->andReturn('IMAGE-DATA');

        $service = new ArchiveExtractionService(
            $this->makeConfig(),
            $archiveInfo,
            Mockery::mock(Par2Info::class)
        );

        $this->assertSame('IMAGE-DATA', $service->extractSpecificFile('ARCHIVE', 'cover.jpg', sys_get_temp_dir().'/'));
    }

    #[Test]
    public function it_inspects_an_archive_once_when_extracting_multiple_candidates(): void
    {
        $archiveInfo = Mockery::mock(ArchiveInfo::class);
        $archiveInfo->shouldReceive('setData')->once()->with('ARCHIVE', true)->andReturn(true);
        $archiveInfo->shouldReceive('getArchiveFileList')->once()->andReturn([
            ['name' => 'release.nfo', 'size' => 8, 'pass' => 0, 'compressed' => 0, 'range' => '20-27', 'source' => 'main'],
            ['name' => 'cover.jpg', 'size' => 10, 'pass' => 0, 'compressed' => 0, 'range' => '40-49', 'source' => 'main'],
        ]);
        $archiveInfo->shouldReceive('getFileData')->once()->with('release.nfo')->andReturn('NFO-DATA');
        $archiveInfo->shouldReceive('getFileData')->once()->with('cover.jpg')->andReturn('IMAGE-DATA');

        $service = new ArchiveExtractionService(
            $this->makeConfig(),
            $archiveInfo,
            Mockery::mock(Par2Info::class)
        );

        $this->assertSame([
            'release.nfo' => 'NFO-DATA',
            'cover.jpg' => 'IMAGE-DATA',
        ], $service->extractSpecificFiles('ARCHIVE', ['release.nfo', 'cover.jpg'], sys_get_temp_dir().'/'));
    }

    #[Test]
    public function it_copies_a_stored_entry_that_is_wholly_inside_the_downloaded_data(): void
    {
        $service = new ArchiveExtractionService($this->makeConfig());

        $this->assertSame(
            ['cover.jpg' => 'JPEG-BYTES'],
            $service->extractSpecificFiles($this->rar('cover.jpg', 'JPEG-BYTES'), ['cover.jpg'], sys_get_temp_dir().'/'),
        );
    }

    #[Test]
    public function it_does_not_copy_compressed_or_truncated_entries(): void
    {
        $service = new ArchiveExtractionService($this->makeConfig());
        $content = str_repeat('JPEG-BYTES', 10);

        $this->assertSame([], $service->extractSpecificFiles(
            $this->rar('cover.jpg', $content, method: 0x33),
            ['cover.jpg'],
            sys_get_temp_dir().'/',
        ));
        $this->assertSame([], $service->extractSpecificFiles(
            substr($this->rar('cover.jpg', $content), 0, -10),
            ['cover.jpg'],
            sys_get_temp_dir().'/',
        ));
    }

    #[Test]
    public function it_detects_common_standalone_video_signatures(): void
    {
        $service = new ArchiveExtractionService(
            $this->makeConfig(),
            Mockery::mock(ArchiveInfo::class),
            Mockery::mock(Par2Info::class)
        );

        $avi = 'RIFF'.str_repeat("\0", 4).'AVI '.str_repeat("\0", 16);
        $mp4 = str_repeat("\0", 4).'ftypisom'.str_repeat("\0", 16);

        $this->assertSame('avi', $service->detectStandaloneVideo($avi));
        $this->assertSame('mp4', $service->detectStandaloneVideo($mp4));
        $this->assertSame('mp4', $service->detectStandaloneVideo(str_repeat("\0", 4).'ftypmp71'.str_repeat("\0", 16)));
        $this->assertSame('ts', $service->detectStandaloneVideo(str_repeat("\x47".str_repeat("\0", 187), 3)));
        $this->assertSame('flv', $service->detectStandaloneVideo("FLV\x01".str_repeat("\0", 16)));
        $this->assertSame('ogv', $service->detectStandaloneVideo('OggS'.str_repeat("\0", 16)));
        $this->assertNull($service->detectStandaloneVideo('tiny'));
    }

    #[Test]
    public function it_treats_plain_media_as_unpassworded_without_archive_inspection(): void
    {
        $archiveInfo = Mockery::mock(ArchiveInfo::class);
        $archiveInfo->shouldNotReceive('setData');
        $service = new ArchiveExtractionService($this->makeConfig(), $archiveInfo, Mockery::mock(Par2Info::class));
        $context = new ReleaseProcessingContext(new Release(['id' => 1, 'guid' => 'fixture-guid']));
        $mkv = "\x1A\x45\xDF\xA3".str_repeat("\0", 60).$this->rar('inner.mkv', 'x');

        $result = $service->processCompressedData($mkv, $context, sys_get_temp_dir().'/');

        $this->assertSame('mkv', $result['standaloneVideoType']);
        $this->assertFalse($result['hasPassword']);
        $this->assertSame(ReleaseBrowseService::PASSWD_NONE, $result['passwordStatus']);
    }

    #[Test]
    #[DataProvider('archiveProtectionFixtures')]
    public function it_classifies_passworded_and_unpassworded_rar_and_zip_data(
        int $archiveType,
        string $expectedMarker,
        bool $encrypted,
    ): void {
        $archiveInfo = Mockery::mock(ArchiveInfo::class);
        $archiveInfo->error = '';
        $archiveInfo->shouldReceive('setData')->once()->with('ARCHIVE', true)->andReturn(true);
        $archiveInfo->shouldReceive('getSummary')->once()->with(true)->andReturn([
            'main_type' => $archiveType,
            'is_encrypted' => $encrypted ? 1 : 0,
            'archives' => [
                'nested.rar' => ['file_list' => [['name' => 'Nested.Release.2026.mkv']]],
            ],
        ]);

        if ($encrypted) {
            $archiveInfo->shouldNotReceive('getArchiveFileList');
        } else {
            $archiveInfo->shouldReceive('getArchiveFileList')->once()->andReturn([
                ['name' => 'nested.rar', 'size' => 100],
            ]);
        }

        $service = new ArchiveExtractionService(
            $this->makeConfig(),
            $archiveInfo,
            Mockery::mock(Par2Info::class)
        );
        $context = new ReleaseProcessingContext(new Release(['id' => 1, 'guid' => 'fixture-guid']));

        $result = $service->processCompressedData('ARCHIVE', $context, sys_get_temp_dir().'/');

        $this->assertSame($encrypted, $result['hasPassword']);
        $this->assertSame(
            $encrypted ? ReleaseBrowseService::PASSWD_RAR : ReleaseBrowseService::PASSWD_NONE,
            $result['passwordStatus'],
        );

        if (! $encrypted) {
            $this->assertTrue($result['success']);
            $this->assertSame($expectedMarker, $result['archiveMarker']);
            $this->assertSame(
                'Nested.Release.2026.mkv',
                $result['dataSummary']['archives']['nested.rar']['file_list'][0]['name'],
            );
        }
    }

    #[Test]
    public function it_lists_a_7z_from_its_first_bytes_and_its_tail(): void
    {
        $service = new ArchiveExtractionService($this->makeConfig());
        $archive = $this->sevenZip('Movie.2026.1080p.mkv', str_repeat('x', 100));
        $head = substr($archive, 0, 40);

        $this->assertTrue($service->needsSevenZipEndHeader($head));
        $this->assertFalse($service->needsSevenZipEndHeader($archive));
        $this->assertNull($service->withSevenZipEndHeader($head, str_repeat('y', 80)));

        $joined = $service->withSevenZipEndHeader($head, substr($archive, -80));
        $this->assertIsString($joined);

        $result = $service->processCompressedData($joined, $this->sevenZipContext(), sys_get_temp_dir().'/');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['hasPassword']);
        $this->assertSame('7', $result['archiveMarker']);
        $this->assertSame('Movie.2026.1080p.mkv', $result['files'][0]['name']);
        $this->assertSame(100, $result['files'][0]['size']);
        $this->assertSame(0, $result['files'][0]['pass']);
        $this->assertTrue($result['listingOnly']);
    }

    #[Test]
    public function it_lists_7z_without_recursion_or_preparing_extraction_directories(): void
    {
        $archiveInfo = Mockery::mock(ArchiveInfo::class);
        $archiveInfo->type = ArchiveInfo::TYPE_SZIP;
        $archiveInfo->error = '';
        $archiveInfo->shouldReceive('setData')->once()->with('ARCHIVE', true)->andReturnTrue();
        $archiveInfo->shouldReceive('getSummary')->once()->with(false)->andReturn(['main_type' => ArchiveInfo::TYPE_SZIP]);
        $archiveInfo->shouldReceive('getArchiveFileList')->once()->with(false)->andReturn([
            ['name' => 'nested.7z', 'size' => 20, 'pass' => 0],
        ]);
        $service = new ArchiveExtractionService($this->makeConfig(['extractUsingRarInfo' => false]), $archiveInfo);

        $result = $service->processCompressedData('ARCHIVE', $this->sevenZipContext(), '/unused/');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['listingOnly']);
        $this->assertSame('nested.7z', $result['files'][0]['name']);
    }

    #[Test]
    public function it_never_extracts_file_bytes_from_reconstructed_7z_headers(): void
    {
        $service = new ArchiveExtractionService($this->makeConfig());
        $archive = $this->sevenZip('release.nfo', str_repeat('A', 20));
        $joined = $service->withSevenZipEndHeader(substr($archive, 0, 40), substr($archive, -80));
        $this->assertIsString($joined);

        $result = $service->processCompressedData($joined, $this->sevenZipContext(), '/unused/');

        $this->assertTrue($result['success']);
        $this->assertSame('release.nfo', $result['files'][0]['name']);
        $this->assertSame([], $service->extractSpecificFiles($joined, ['release.nfo'], '/unused/'));
        $this->assertNull($service->extractSpecificFile($joined, 'release.nfo', '/unused/'));
    }

    #[Test]
    public function it_reports_a_7z_with_an_encrypted_header_as_passworded(): void
    {
        $service = new ArchiveExtractionService($this->makeConfig());
        $archive = $this->sevenZip('Movie.2026.1080p.mkv', str_repeat('x', 100), encryptedHeader: true);

        $joined = $service->withSevenZipEndHeader(substr($archive, 0, 40), substr($archive, -40));
        $this->assertIsString($joined);

        $result = $service->processCompressedData($joined, $this->sevenZipContext(), sys_get_temp_dir().'/');

        $this->assertTrue($result['hasPassword']);
        $this->assertSame(ReleaseBrowseService::PASSWD_RAR, $result['passwordStatus']);
    }

    #[Test]
    public function it_flags_encrypted_entries_of_a_7z(): void
    {
        $service = new ArchiveExtractionService($this->makeConfig());
        $archive = $this->sevenZip('Movie.2026.1080p.mkv', str_repeat('x', 100), encryptedFile: true);

        $joined = $service->withSevenZipEndHeader(substr($archive, 0, 40), substr($archive, -80));
        $this->assertIsString($joined);

        $result = $service->processCompressedData($joined, $this->sevenZipContext(), sys_get_temp_dir().'/');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['files'][0]['pass']);
    }

    #[Test]
    public function it_recognises_archive_signatures(): void
    {
        $this->assertTrue(ArchiveExtractionService::hasArchiveSignature($this->rar('a.mkv', 'x')));
        $this->assertTrue(ArchiveExtractionService::hasArchiveSignature("Rar!\x1A\x07\x01\x00".str_repeat("\0", 8)));
        $this->assertTrue(ArchiveExtractionService::hasArchiveSignature($this->zip('a.mkv', 'x')));
        $this->assertTrue(ArchiveExtractionService::hasArchiveSignature($this->sevenZip('a.mkv', 'x')));
        $this->assertFalse(ArchiveExtractionService::hasArchiveSignature("PAR2\0PKT".str_repeat("\0", 8)));
        $this->assertFalse(ArchiveExtractionService::hasArchiveSignature("\x1A\x45\xDF\xA3".str_repeat("\0", 8)));
    }

    #[Test]
    public function it_parses_a_recorded_7z_listing(): void
    {
        $files = ArchiveExtractionService::parseSevenZipListing((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/7z-list-slt.txt'));

        $this->assertSame(
            ['Extras/Some.Movie.nfo', 'Extras/naïve – notes.txt', 'Some.Movie.2024.1080p.WEB-DL.mkv'],
            array_column($files, 'name'),
        );
        $this->assertSame([4, 2, 2000], array_column($files, 'size'));
        $this->assertSame([0, 0, 1], array_column($files, 'pass'));
        $this->assertSame('b45776c8', $files[0]['crc32']);
        $this->assertSame(strtotime('2026-10-10 15:46:58'), $files[0]['date']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sevenZipLineEndings(): array
    {
        return ['LF' => ["\n"], 'CRLF' => ["\r\n"]];
    }

    #[Test]
    #[DataProvider('sevenZipLineEndings')]
    public function it_keeps_utf8_names_containing_byte_0x85_in_a_7z_listing(string $eol): void
    {
        // "Å" is C3 85 and "久" is E4 B9 85: 0x85 is NEL to byte-mode \R.
        $listing = $this->sevenZipListing(['Åland.mkv', 'Movie.久.mkv', 'Extras/naïve – notes.txt'], $eol);

        $files = ArchiveExtractionService::parseSevenZipListing($listing, $reliable);

        $this->assertSame(['Åland.mkv', 'Movie.久.mkv', 'Extras/naïve – notes.txt'], array_column($files, 'name'));
        $this->assertSame([10, 13, 27], array_column($files, 'size'));
        $this->assertSame(['5a5a5a5a', '5a5a5a5a', '5a5a5a5a'], array_column($files, 'crc32'));
        $this->assertTrue($reliable);
    }

    #[Test]
    public function it_parses_a_crlf_7z_listing_split_at_every_byte(): void
    {
        $listing = $this->sevenZipListing(['Åland.mkv', 'Movie.久.mkv'], "\r\n");
        $expected = ArchiveExtractionService::parseSevenZipListing($listing);
        $this->assertSame(['Åland.mkv', 'Movie.久.mkv'], array_column($expected, 'name'));

        for ($cut = 1; $cut < strlen($listing); $cut++) {
            $parser = new SevenZipListingParser;
            $parser->feed(substr($listing, 0, $cut));
            $parser->feed(substr($listing, $cut));
            $parser->finish();

            $this->assertSame($expected, $parser->files, 'split at byte '.$cut);
            $this->assertTrue($parser->reliable, 'split at byte '.$cut);
        }
    }

    #[Test]
    #[DataProvider('sevenZipLineEndings')]
    public function it_streams_utf8_names_containing_byte_0x85_from_the_7z_client(string $eol): void
    {
        $dir = $this->workDir();
        $listing = $this->sevenZipListing(['Åland.mkv', 'Movie.久.mkv'], $eol);
        // Flush a chunk ending inside the first name and, for CRLF, one between CR and LF.
        $cut = strpos($listing, "\x85");
        $cutEol = strpos($listing, $eol) + 1;
        $client = $this->sevenZipClientPrinting($dir, $this->printfBytes(substr($listing, 0, $cut)).'; sleep 0.2; '
            .$this->printfBytes(substr($listing, $cut, $cutEol - $cut)).'; sleep 0.2; '
            .$this->printfBytes(substr($listing, $cutEol)));
        $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $client]));
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);
        $context = $this->sevenZipContext();

        $result = $service->listEncodedSevenZip($archive, null, $context, $dir.'/');

        $this->assertIsArray($result);
        $this->assertSame(['Åland.mkv', 'Movie.久.mkv'], array_column($result['files'], 'name'));
        $this->assertSame([10, 13], array_column($result['files'], 'size'));
        $this->assertFalse($context->archiveListingIncomplete);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ambiguousSevenZipRecords(): array
    {
        $record = static fn (string $lines): string => $lines."Size = 5\nEncrypted = +\n\n";

        return [
            'tab in path' => [$record("Path = a\tb.mkv\n")],
            'control byte in path' => [$record("Path = a\x01b.mkv\n")],
            'DEL in path' => [$record("Path = a\x7Fb.mkv\n")],
            'C1 control in path' => [$record("Path = a\u{0085}b.mkv\n")],
            'bare CR in path' => [$record("Path = a\rb.mkv\n")],
            'duplicate path' => [$record("Path = a.mkv\nPath = b.mkv\n")],
            'duplicate size' => ["Path = a.mkv\nSize = 1\n".$record('')],
            'line break in path' => [$record("Path = a\nb.mkv\n")],
            'path without size' => ["Path = a.mkv\nEncrypted = +\n\n"],
            'properties without path' => [$record('')],
        ];
    }

    #[Test]
    #[DataProvider('ambiguousSevenZipRecords')]
    public function it_rejects_ambiguous_7z_listing_records(string $record): void
    {
        $listing = $this->sevenZipListing(['first.mkv'], "\n").$record.$this->sevenZipListing(['last.mkv'], "\n");

        $files = ArchiveExtractionService::parseSevenZipListing($listing, $reliable);

        $this->assertSame(['first.mkv', 'last.mkv'], array_column($files, 'name'));
        $this->assertSame([0, 0], array_column($files, 'pass'));
        $this->assertFalse($reliable);
    }

    #[Test]
    public function it_marks_a_streamed_7z_listing_with_an_ambiguous_record_incomplete(): void
    {
        $dir = $this->workDir();
        $listing = $this->sevenZipListing(['first.mkv'], "\r\n")."Path = a\x01b.mkv\r\nSize = 5\r\nEncrypted = +\r\n\r\n";
        $client = $this->sevenZipClientPrinting($dir, $this->printfBytes($listing));
        $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $client]));
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);
        $context = $this->sevenZipContext();

        $result = $service->listEncodedSevenZip($archive, null, $context, $dir.'/');

        $this->assertIsArray($result);
        $this->assertSame(['first.mkv'], array_column($result['files'], 'name'));
        $this->assertTrue($context->archiveListingIncomplete);
    }

    #[Test]
    #[DataProvider('ambiguousSevenZipRecords')]
    public function it_marks_the_context_when_every_streamed_7z_record_is_rejected(string $record): void
    {
        $dir = $this->workDir();
        $client = $this->sevenZipClientPrinting($dir, $this->printfBytes($record));
        $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $client]));
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);
        $context = $this->sevenZipContext();

        $this->assertNull($service->listEncodedSevenZip($archive, null, $context, $dir.'/'));
        $this->assertTrue($context->archiveListingIncomplete);
        $this->assertSame(0, $context->compressedFilesChecked);
    }

    #[Test]
    public function it_keeps_the_incomplete_mark_when_a_later_7z_listing_is_complete(): void
    {
        $dir = $this->workDir();
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);
        $context = $this->sevenZipContext();
        $rejected = new ArchiveExtractionService($this->makeConfig([
            'sevenZipPath' => $this->sevenZipClientPrinting($dir, $this->printfBytes("Path = a\x01b.mkv\nSize = 5\nEncrypted = +\n\n")),
        ]));
        $this->assertNull($rejected->listEncodedSevenZip($archive, null, $context, $dir.'/'));

        $complete = new ArchiveExtractionService($this->makeConfig([
            'sevenZipPath' => $this->sevenZipClientPrinting($dir, $this->printfBytes($this->sevenZipListing(['last.mkv'], "\n"))),
        ]));
        $result = $complete->listEncodedSevenZip($archive, null, $context, $dir.'/');

        $this->assertIsArray($result);
        $this->assertSame(['last.mkv'], array_column($result['files'], 'name'));
        $this->assertTrue($context->archiveListingIncomplete);
    }

    #[Test]
    public function it_does_not_mark_an_empty_or_failed_7z_listing_incomplete(): void
    {
        $dir = $this->workDir();
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);

        foreach (['true', 'exit 2'] as $script) {
            $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $this->sevenZipClientPrinting($dir, $script)]));
            $context = $this->sevenZipContext();

            $this->assertNull($service->listEncodedSevenZip($archive, null, $context, $dir.'/'), $script);
            $this->assertFalse($context->archiveListingIncomplete, $script);
        }
    }

    #[Test]
    public function it_lists_a_compressed_7z_header_through_a_sparse_copy_of_the_archive(): void
    {
        $dir = $this->workDir();
        $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $this->fakeSevenZipClient($dir)]));
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);
        $context = $this->sevenZipContext();

        $result = $service->listEncodedSevenZip(substr($archive, 0, 40), substr($archive, -60), $context, $dir.'/');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertTrue($result['listingOnly']);
        $this->assertSame('Some.Movie.2024.1080p.WEB-DL.mkv', $result['files'][2]['name']);
        $this->assertSame(1, $result['files'][2]['pass']);
        $this->assertSame(1, $context->compressedFilesChecked);
        $this->assertSame((string) strlen($archive), trim((string) file_get_contents($dir.'/listed-size')));
        $this->assertSame([$dir.'/7z', $dir.'/listed-size'], glob($dir.'/*'));
    }

    #[Test]
    public function it_gives_up_on_a_compressed_7z_header_whose_packed_stream_is_not_in_the_tail(): void
    {
        $dir = $this->workDir();
        $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $this->fakeSevenZipClient($dir)]));
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);
        $context = $this->sevenZipContext();

        $this->assertNull($service->listEncodedSevenZip(substr($archive, 0, 40), substr($archive, -40), $context, $dir.'/'));
        $this->assertFileDoesNotExist($dir.'/listed-size');
        $this->assertSame(0, $context->compressedFilesChecked);
    }

    #[Test]
    public function it_lists_a_compressed_7z_header_that_is_already_in_the_downloaded_data(): void
    {
        $dir = $this->workDir();
        $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $this->fakeSevenZipClient($dir)]));
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);
        $context = $this->sevenZipContext();

        $this->assertFalse($service->needsSevenZipEndHeader($archive));
        $result = $service->listEncodedSevenZip($archive, null, $context, $dir.'/');

        $this->assertIsArray($result);
        $this->assertCount(3, $result['files']);
        $this->assertSame((string) strlen($archive), trim((string) file_get_contents($dir.'/listed-size')));
        $this->assertNull($service->listEncodedSevenZip(substr($archive, 0, 40), null, $this->sevenZipContext(), $dir.'/'));
    }

    #[Test]
    public function it_stops_a_7z_listing_at_the_entry_cap(): void
    {
        $dir = $this->workDir();
        $client = $this->sevenZipClientPrinting($dir, "while :; do printf 'Path = f.bin\\nSize = 1\\n\\n'; done");
        $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $client]));
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);

        $result = $service->listEncodedSevenZip($archive, null, $this->sevenZipContext(), $dir.'/');

        $this->assertIsArray($result);
        $this->assertCount(ArchiveExtractionService::SEVENZIP_LISTING_MAX_ENTRIES, $result['files']);
        $this->assertSame([$dir.'/7z'], glob($dir.'/*'));
    }

    #[Test]
    public function it_stops_a_7z_listing_at_the_output_cap(): void
    {
        $dir = $this->workDir();
        $client = $this->sevenZipClientPrinting($dir, "name=\$(head -c 20000 /dev/zero | tr '\\0' a)\n"
            ."while :; do printf 'Path = %s\\nSize = 1\\n\\n' \"\$name\"; done");
        $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $client]));
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);

        $result = $service->listEncodedSevenZip($archive, null, $this->sevenZipContext(), $dir.'/');

        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($result['files']));
        $this->assertLessThan(intdiv(ArchiveExtractionService::SEVENZIP_LISTING_MAX_BYTES, 20000) + 5, count($result['files']));
        $this->assertSame(str_repeat('a', 20000), $result['files'][0]['name']);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function outOfRangeSevenZipStartHeaders(): array
    {
        return [
            'beyond the hard cap' => [ArchiveExtractionService::SEVENZIP_MAX_ARCHIVE_BYTES, 60],
            'overflowing offset plus size' => [PHP_INT_MAX - 40, 60],
        ];
    }

    #[Test]
    #[DataProvider('outOfRangeSevenZipStartHeaders')]
    public function it_rejects_7z_start_headers_beyond_the_archive_size_cap(int $nextOffset, int $nextSize): void
    {
        $dir = $this->workDir();
        $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $this->fakeSevenZipClient($dir)]));
        $archive = $this->withSevenZipStartHeader(
            $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true),
            $nextOffset,
            $nextSize,
        );

        $this->assertFalse($service->needsSevenZipEndHeader(substr($archive, 0, 40)));
        $this->assertNull($service->listEncodedSevenZip($archive, substr($archive, -60), $this->sevenZipContext(), $dir.'/'));
        $this->assertSame([$dir.'/7z'], glob($dir.'/*'));
    }

    #[Test]
    public function it_rejects_a_7z_start_header_describing_more_than_the_release_size(): void
    {
        $dir = $this->workDir();
        $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $this->fakeSevenZipClient($dir)]));
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);
        $endSize = unpack('P', $archive, 20)[1];
        $far = $this->withSevenZipStartHeader($archive, 1 << 30, $endSize);
        $context = new ReleaseProcessingContext(new Release(['id' => 1, 'guid' => 'fixture-guid', 'size' => 1 << 20]));

        $this->assertNull($service->listEncodedSevenZip($far, substr($archive, -$endSize), $context, $dir.'/'));
        $this->assertFileDoesNotExist($dir.'/listed-size');
        $this->assertSame([$dir.'/7z'], glob($dir.'/*'));

        $this->assertIsArray($service->listEncodedSevenZip($archive, null, $context, $dir.'/'));
    }

    #[Test]
    public function it_leaves_plain_and_encrypted_7z_headers_to_rarinfo(): void
    {
        $dir = $this->workDir();
        $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $this->fakeSevenZipClient($dir)]));
        $plain = $this->sevenZip('Movie.2026.1080p.mkv', str_repeat('x', 100));
        $encrypted = $this->sevenZip('Movie.2026.1080p.mkv', str_repeat('x', 100), encryptedHeader: true);

        $this->assertNull($service->listEncodedSevenZip(substr($plain, 0, 40), substr($plain, -80), $this->sevenZipContext(), $dir.'/'));
        $this->assertNull($service->listEncodedSevenZip(substr($encrypted, 0, 40), substr($encrypted, -60), $this->sevenZipContext(), $dir.'/'));
        $this->assertFileDoesNotExist($dir.'/listed-size');
    }

    #[Test]
    public function it_does_not_list_compressed_7z_headers_without_a_7zip_client(): void
    {
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);

        foreach ([false, '/nonexistent/7z'] as $client) {
            $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $client]));
            $context = $this->sevenZipContext();
            $this->assertNull($service->listEncodedSevenZip(substr($archive, 0, 40), substr($archive, -60), $context, sys_get_temp_dir().'/'));
            $this->assertFalse($context->archiveListingIncomplete);
        }
    }

    #[Test]
    public function it_lists_real_7z_archives_with_compressed_headers(): void
    {
        $client = '/usr/bin/7z';
        if (! is_executable($client)) {
            $this->markTestSkipped('7-Zip is not installed.');
        }

        $dir = $this->workDir();
        $service = new ArchiveExtractionService($this->makeConfig(['sevenZipPath' => $client]));
        file_put_contents($dir.'/Real.Movie.2026.mkv', random_bytes(300000));
        file_put_contents($dir.'/real.nfo', 'nfo');

        $list = function (string $switches) use ($client, $dir, $service): ?array {
            exec(escapeshellarg($client).' a -bd -mx=1 '.$switches.' '.escapeshellarg($dir.'/arc.7z')
                .' '.escapeshellarg($dir.'/Real.Movie.2026.mkv').' '.escapeshellarg($dir.'/real.nfo').' 2>&1', $output, $code);
            $this->assertSame(0, $code, implode("\n", $output));
            $archive = (string) file_get_contents($dir.'/arc.7z');
            unlink($dir.'/arc.7z');
            $nextHeader = 32 + unpack('P', $archive, 12)[1];
            $this->assertSame("\x17", $archive[$nextHeader]);

            return $service->listEncodedSevenZip(substr($archive, 0, 65536), substr($archive, -65536), $this->sevenZipContext(), $dir.'/');
        };

        $plain = $list('');
        $this->assertIsArray($plain);
        $this->assertSame(['Real.Movie.2026.mkv', 'real.nfo'], array_column($plain['files'], 'name'));
        $this->assertSame([300000, 3], array_column($plain['files'], 'size'));
        $this->assertSame([0, 0], array_column($plain['files'], 'pass'));

        $encryptedFiles = $list('-pSecret');
        $this->assertIsArray($encryptedFiles);
        $this->assertSame([1, 1], array_column($encryptedFiles['files'], 'pass'));

        $this->assertNull($list('-pSecret -mhe=on'));
        $this->assertSame([$dir.'/Real.Movie.2026.mkv', $dir.'/real.nfo'], glob($dir.'/*'));
    }

    /**
     * A `7z l -slt -ba` listing of plain stored files, sized by name length.
     *
     * @param  list<string>  $names
     */
    private function sevenZipListing(array $names, string $eol): string
    {
        $listing = '';
        foreach ($names as $name) {
            $listing .= implode($eol, [
                'Path = '.$name,
                'Size = '.strlen($name),
                'Packed Size = ',
                'Modified = 2026-10-10 15:46:58.4180048',
                'Attributes = A -rw-r--r--',
                'CRC = 5A5A5A5A',
                'Encrypted = -',
                'Method = Copy',
                'Block = 0',
            ]).$eol.$eol;
        }

        return $listing;
    }

    /**
     * A shell printf command writing these exact bytes, whatever the locale.
     */
    private function printfBytes(string $bytes): string
    {
        return "printf '".implode('', array_map(static fn (string $byte): string => sprintf('\\%03o', ord($byte)), str_split($bytes)))."'";
    }

    private function sevenZipClientPrinting(string $dir, string $script): string
    {
        $client = $dir.'/7z';
        file_put_contents($client, "#!/bin/sh\n".$script."\n");
        chmod($client, 0755);

        return $client;
    }

    /**
     * Point a 7z start header at another end-header offset and size, keeping its CRCs valid.
     */
    private function withSevenZipStartHeader(string $archive, int $nextOffset, int $nextSize): string
    {
        $start = pack('PP', $nextOffset, $nextSize).substr($archive, 28, 4);

        return substr($archive, 0, 8).pack('V', crc32($start)).$start.substr($archive, 32);
    }

    private function workDir(): string
    {
        $this->workDir = sys_get_temp_dir().'/'.uniqid('7z-test-', true);
        mkdir($this->workDir);

        return $this->workDir;
    }

    private function sevenZipContext(): ReleaseProcessingContext
    {
        return new ReleaseProcessingContext(new Release(['id' => 1, 'guid' => 'fixture-guid']));
    }

    /**
     * @return array<string, array{int, string, bool}>
     */
    public static function archiveProtectionFixtures(): array
    {
        return [
            'unpassworded RAR' => [ArchiveInfo::TYPE_RAR, 'r', false],
            'passworded RAR' => [ArchiveInfo::TYPE_RAR, 'r', true],
            'unpassworded ZIP' => [ArchiveInfo::TYPE_ZIP, 'z', false],
            'passworded ZIP' => [ArchiveInfo::TYPE_ZIP, 'z', true],
        ];
    }
}
