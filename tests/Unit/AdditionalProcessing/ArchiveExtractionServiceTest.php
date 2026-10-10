<?php

namespace Tests\Unit\AdditionalProcessing;

use App\Models\Release;
use App\Services\AdditionalProcessing\ArchiveExtractionService;
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
        $this->assertNull($service->detectStandaloneVideo('tiny'));
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
            $this->assertNull($service->listEncodedSevenZip(substr($archive, 0, 40), substr($archive, -60), $this->sevenZipContext(), sys_get_temp_dir().'/'));
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
