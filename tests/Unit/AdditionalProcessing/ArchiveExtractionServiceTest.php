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

    protected function tearDown(): void
    {
        Mockery::close();
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
