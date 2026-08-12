<?php

namespace Tests\Unit\AdditionalProcessing;

use App\Models\Release;
use App\Services\AdditionalProcessing\ArchiveExtractionService;
use App\Services\AdditionalProcessing\ConsoleOutputService;
use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\AdditionalProcessing\MediaExtractionService;
use App\Services\AdditionalProcessing\NzbContentParser;
use App\Services\AdditionalProcessing\ReleaseFileManager;
use App\Services\AdditionalProcessing\ReleaseFilesArchiveFallback;
use App\Services\AdditionalProcessing\ReleaseProcessor;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\AdditionalProcessing\UsenetDownloadService;
use App\Services\TempWorkspaceService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReleaseProcessorTest extends TestCase
{
    use CreatesProcessingConfiguration;

    private string $releaseTempPath;

    private string $mainTempPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Unique per test: these only ever reach mocked collaborators, but a shared
        // literal would still make two concurrent runs indistinguishable in failures.
        $this->releaseTempPath = $this->makeTempPath('nntmux-ap-release').'/';
        $this->mainTempPath = $this->makeTempPath('nntmux-ap-main').'/';
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_deletes_release_when_nzb_parsing_fails(): void
    {
        $processor = $this->makeProcessor(
            nzbParser: Mockery::mock(NzbContentParser::class)
                ->shouldReceive('parseNzb')->once()->with('guid-1')->andReturn(['error' => 'broken nzb', 'contents' => []])->getMock(),
            releaseManager: Mockery::mock(ReleaseFileManager::class)
                ->shouldReceive('deleteRelease')->once()->andReturnNull()->getMock(),
            tempWorkspace: Mockery::mock(TempWorkspaceService::class)
                ->shouldReceive('createReleaseTempFolder')->once()->andReturn($this->releaseTempPath)
                ->shouldReceive('clearDirectory')->once()->with($this->releaseTempPath, false)->andReturnNull()->getMock(),
            output: Mockery::mock(ConsoleOutputService::class)
                ->shouldReceive('echoReleaseStart')->once()->andReturnNull()
                ->shouldReceive('setProcessTitle')->once()->andReturnNull()
                ->shouldReceive('warning')->once()->with('broken nzb')->andReturnNull()->getMock()
        );

        $processor->process($this->makeContext(), $this->mainTempPath);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_finalizes_a_release_after_basic_successful_processing(): void
    {
        $config = $this->makeConfig();
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->with('guid-1')->andReturn([
            'error' => null,
            'contents' => [['title' => 'file.nzb', 'segments' => []]],
        ]);
        $nzbParser->shouldReceive('extractMessageIDs')->once()->andReturn([
            'hasCompressedFile' => false,
            'sampleMessageIDs' => [],
            'jpgMessageIDs' => [],
            'mediaInfoMessageID' => '',
            'audioInfoMessageID' => '',
            'audioInfoExtension' => '',
            'bookFileCount' => 0,
        ]);

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $tempWorkspace = Mockery::mock(TempWorkspaceService::class);
        $tempWorkspace->shouldReceive('createReleaseTempFolder')->once()->andReturn($this->releaseTempPath);
        $tempWorkspace->shouldReceive('clearDirectory')->once()->with($this->releaseTempPath, false)->andReturnNull();

        $output = Mockery::mock(ConsoleOutputService::class);
        $output->shouldReceive('echoReleaseStart')->once()->andReturnNull();
        $output->shouldReceive('setProcessTitle')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            Mockery::mock(UsenetDownloadService::class),
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $tempWorkspace,
            $output
        );

        $processor->process($this->makeContext(), $this->mainTempPath);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_marks_release_timeout_before_full_processing_continues(): void
    {
        $config = $this->makeConfig(['releaseProcessingTimeout' => 1, 'maxPpTimeoutCount' => 2]);

        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->with('guid-1')->andReturn([
            'error' => null,
            'contents' => [],
        ]);

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('handleReleaseTimeout')->once()->andReturn(false);
        $releaseManager->shouldNotReceive('finalizeRelease');

        $tempWorkspace = Mockery::mock(TempWorkspaceService::class);
        $tempWorkspace->shouldReceive('createReleaseTempFolder')->once()->andReturn($this->releaseTempPath);
        $tempWorkspace->shouldReceive('clearDirectory')->twice()->with($this->releaseTempPath, false)->andReturnNull();

        $output = Mockery::mock(ConsoleOutputService::class);
        $output->shouldReceive('echoReleaseStart')->once()->andReturnNull();
        $output->shouldReceive('setProcessTitle')->once()->andReturnNull();
        $output->shouldReceive('echoReleaseTimeout')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            Mockery::mock(UsenetDownloadService::class),
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $tempWorkspace,
            $output
        );

        $context = $this->makeContext();
        $context->startTime = hrtime(true) - 2_000_000_000;

        $processor->process($context, $this->mainTempPath);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_processes_media_info_when_password_inspection_is_disabled(): void
    {
        $tmpPath = $this->makeTempDirectory('nntmux-media-info').'/';

        $config = $this->makeConfig([
            'processPasswords' => false,
            'processMediaInfo' => true,
        ]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [['title' => 'video.mkv', 'segments' => ['media-message-id']]],
        ]);
        $nzbParser->shouldReceive('extractMessageIDs')->once()->andReturn([
            'hasCompressedFile' => false,
            'sampleMessageIDs' => [],
            'jpgMessageIDs' => [],
            'mediaInfoMessageID' => ['media-message-id'],
            'audioInfoMessageID' => '',
            'audioInfoExtension' => '',
            'bookFileCount' => 0,
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::MediaInfo, ['media-message-id'], '', 1)
            ->andReturn([
                'success' => true,
                'data' => str_repeat('x', 41),
                'groupUnavailable' => false,
                'error' => null,
            ]);
        $downloadService->shouldReceive('meetsMinimumSize')->once()->andReturnTrue();

        $mediaService = Mockery::mock(MediaExtractionService::class);
        $mediaService->shouldReceive('getMediaInfo')->once()->andReturnTrue();

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('finalizeRelease')->once()->with(Mockery::type(ReleaseProcessingContext::class), false);

        $tempWorkspace = Mockery::mock(TempWorkspaceService::class);
        $tempWorkspace->shouldReceive('createReleaseTempFolder')->once()->andReturn($tmpPath);
        $tempWorkspace->shouldReceive('clearDirectory')->once()->andReturnNull();

        $output = Mockery::mock(ConsoleOutputService::class);
        $output->shouldReceive('echoReleaseStart')->once();
        $output->shouldReceive('setProcessTitle')->once();
        $output->shouldReceive('echoMediaInfoDownload')->once();
        $output->shouldReceive('echoMediaInfoAdded')->once();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            Mockery::mock(ArchiveExtractionService::class),
            $mediaService,
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $tempWorkspace,
            $output,
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;

        try {
            $processor->process($context, $tmpPath);

            $this->assertTrue($context->foundMediaInfo);
        } finally {
            @unlink($tmpPath.'media.avi');
            @rmdir($tmpPath);
        }
    }

    private function makeProcessor(
        ?NzbContentParser $nzbParser = null,
        ?ReleaseFileManager $releaseManager = null,
        ?TempWorkspaceService $tempWorkspace = null,
        ?ConsoleOutputService $output = null
    ): ReleaseProcessor {
        return new ReleaseProcessor(
            $this->makeConfig(),
            $nzbParser ?? Mockery::mock(NzbContentParser::class),
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            Mockery::mock(UsenetDownloadService::class),
            $releaseManager ?? Mockery::mock(ReleaseFileManager::class),
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $tempWorkspace ?? Mockery::mock(TempWorkspaceService::class),
            $output ?? Mockery::mock(ConsoleOutputService::class)
        );
    }

    private function makeContext(): ReleaseProcessingContext
    {
        return new ReleaseProcessingContext(new Release([
            'id' => 1,
            'guid' => 'guid-1',
            'size' => 1024,
            'groups_id' => 10,
            'nfostatus' => -1,
            'pp_timeout_count' => 0,
        ]));
    }
}
