<?php

namespace Tests\Unit\AdditionalProcessing;

use App\Enums\ImageAssetProfile;
use App\Models\Release;
use App\Services\AdditionalProcessing\AdditionalWorkPlanner;
use App\Services\AdditionalProcessing\ArchiveExtractionService;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\ConsoleOutputService;
use App\Services\AdditionalProcessing\DTO\DownloadMetrics;
use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\AdditionalProcessing\Enums\ProcessingOutcome;
use App\Services\AdditionalProcessing\Enums\ProcessingStage;
use App\Services\AdditionalProcessing\MediaExtractionService;
use App\Services\AdditionalProcessing\NzbContentParser;
use App\Services\AdditionalProcessing\ReleaseFileManager;
use App\Services\AdditionalProcessing\ReleaseFilesArchiveFallback;
use App\Services\AdditionalProcessing\ReleaseProcessor;
use App\Services\AdditionalProcessing\ReleaseSearchSyncCoordinator;
use App\Services\AdditionalProcessing\State\PersistenceMetricsCollector;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\AdditionalProcessing\UsenetDownloadService;
use App\Services\Categorization\CategorizationService;
use App\Services\ReleaseExtraService;
use App\Services\ReleaseImageService;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\TempWorkspaceService;
use App\Support\Data\ImageProcessingResult;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReleaseProcessorTest extends TestCase
{
    use BuildsArchiveFixtures;
    use CreatesProcessingConfiguration;

    /** @var list<string> */
    private array $temporaryDirectories = [];

    private string $workDir = '';

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            (new Filesystem)->deleteDirectory($directory);
        }
        Mockery::close();
        if ($this->workDir !== '') {
            array_map('unlink', glob($this->workDir.'/*') ?: []);
            rmdir($this->workDir);
        }
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
                ->shouldReceive('createReleaseTempFolder')->once()->andReturn('/tmp/ap-release/')
                ->shouldReceive('clearDirectory')->once()->with('/tmp/ap-release/', false)->andReturnNull()->getMock(),
            output: Mockery::mock(ConsoleOutputService::class)
                ->shouldReceive('echoReleaseStart')->once()->andReturnNull()
                ->shouldReceive('setProcessTitle')->once()->andReturnNull()
                ->shouldReceive('warning')->once()->with('broken nzb')->andReturnNull()->getMock()
        );

        $result = $processor->process($this->makeContext(), '/tmp/main/');

        $this->assertSame(ProcessingOutcome::DeletedBrokenNzb, $result->outcome);
        $this->assertTrue($result->outcome->isDeleted());
        $this->assertFalse($result->isSuccessful());
        $this->assertSame('broken nzb', $result->reason);
        $this->assertGreaterThan(0.0, $result->elapsedSeconds);
        $this->assertArrayHasKey(ProcessingStage::WorkspacePreparation->value, $result->stageDurations);
        $this->assertArrayHasKey(ProcessingStage::NzbParsing->value, $result->stageDurations);
        $this->assertArrayHasKey(ProcessingStage::WorkspaceCleanup->value, $result->stageDurations);
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
        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $persistenceMetrics = new PersistenceMetricsCollector;
        $synchronizedReleaseIds = [];
        $searchSyncCoordinator = new ReleaseSearchSyncCoordinator(
            $persistenceMetrics,
            static function (int $releaseId) use (&$synchronizedReleaseIds): void {
                $synchronizedReleaseIds[] = $releaseId;
            },
        );
        $releaseManager->shouldReceive('finalizeRelease')
            ->once()
            ->andReturnUsing(static function () use ($searchSyncCoordinator): void {
                $searchSyncCoordinator->request(1);
                $searchSyncCoordinator->request(1);
            });

        $tempWorkspace = Mockery::mock(TempWorkspaceService::class);
        $tempWorkspace->shouldReceive('createReleaseTempFolder')->once()->andReturn('/tmp/ap-release/');
        $tempWorkspace->shouldReceive('clearDirectory')->once()->with('/tmp/ap-release/', false)->andReturnNull();

        $output = Mockery::mock(ConsoleOutputService::class);
        $output->shouldReceive('echoReleaseStart')->once()->andReturnNull();
        $output->shouldReceive('setProcessTitle')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            $this->scopedDownloadService(),
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $tempWorkspace,
            $output,
            $searchSyncCoordinator,
            $persistenceMetrics,
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $result = $processor->process($context, '/tmp/main/');

        $this->assertSame(ProcessingOutcome::NoUsefulArtifacts, $result->outcome);
        $this->assertTrue($result->isSuccessful());
        $this->assertFalse($result->artifactsCreated);
        $this->assertSame('Processing completed without creating useful artifacts.', $result->reason);
        $this->assertSame(0, $result->releaseFilesAdded);
        $this->assertSame([1], $synchronizedReleaseIds);
        $this->assertSame(2, $result->persistenceMetrics?->searchSyncRequests);
        $this->assertSame(1, $result->persistenceMetrics?->searchSyncExecutions);
        $this->assertGreaterThan(0.0, $result->elapsedSeconds);
        $this->assertSame([
            ProcessingStage::WorkspacePreparation->value,
            ProcessingStage::NzbParsing->value,
            ProcessingStage::ReleaseInitialization->value,
            ProcessingStage::MessageIdSelection->value,
            ProcessingStage::Finalization->value,
            ProcessingStage::WorkspaceCleanup->value,
        ], array_keys($result->stageDurations));
    }

    #[Test]
    public function it_always_uses_the_v2_work_plan_for_message_id_selection(): void
    {
        $config = $this->makeConfig();
        $nzbContents = [['title' => 'file.nzb', 'segments' => ['<message>']]];
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->with('guid-1')->andReturn([
            'error' => null,
            'contents' => $nzbContents,
        ]);
        $nzbParser->shouldNotReceive('extractMessageIDs');

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            $this->scopedDownloadService(),
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $this->successfulTempWorkspace(),
            Mockery::mock(ConsoleOutputService::class)
                ->shouldReceive('echoReleaseStart')->once()->andReturnNull()
                ->shouldReceive('setProcessTitle')->once()->andReturnNull()
                ->getMock(),
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $result = $processor->process($context, '/tmp/main/');

        $this->assertSame(ProcessingOutcome::NoUsefulArtifacts, $result->outcome);
        $this->assertNotNull($context->workPlan);
        $this->assertSame(0, $result->duplicateMessageIdCount);
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
        $tempWorkspace->shouldReceive('createReleaseTempFolder')->once()->andReturn('/tmp/ap-release/');
        $tempWorkspace->shouldReceive('clearDirectory')->twice()->with('/tmp/ap-release/', false)->andReturnNull();

        $output = Mockery::mock(ConsoleOutputService::class);
        $output->shouldReceive('echoReleaseStart')->once()->andReturnNull();
        $output->shouldReceive('setProcessTitle')->once()->andReturnNull();
        $output->shouldReceive('echoReleaseTimeout')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            $this->scopedDownloadService(),
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $tempWorkspace,
            $output
        );

        $context = $this->makeContext();
        $context->startTime = hrtime(true) - 2_000_000_000;

        $result = $processor->process($context, '/tmp/main/');

        $this->assertSame(ProcessingOutcome::TimedOut, $result->outcome);
        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('exceeded', $result->reason);
        $this->assertArrayHasKey(ProcessingStage::TimeoutHandling->value, $result->stageDurations);
    }

    #[Test]
    public function it_reports_a_deleted_timeout_separately(): void
    {
        $config = $this->makeConfig(['releaseProcessingTimeout' => 1, 'maxPpTimeoutCount' => 1]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn(['error' => null, 'contents' => []]);

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('handleReleaseTimeout')->once()->andReturn(true);
        $releaseManager->shouldNotReceive('finalizeRelease');

        $tempWorkspace = Mockery::mock(TempWorkspaceService::class);
        $tempWorkspace->shouldReceive('createReleaseTempFolder')->once()->andReturn('/tmp/ap-release/');
        $tempWorkspace->shouldReceive('clearDirectory')->twice()->with('/tmp/ap-release/', false)->andReturnNull();

        $output = Mockery::mock(ConsoleOutputService::class);
        $output->shouldReceive('echoReleaseStart')->once()->andReturnNull();
        $output->shouldReceive('setProcessTitle')->once()->andReturnNull();
        $output->shouldReceive('echoReleaseTimeoutDeleted')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            $this->scopedDownloadService(),
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $tempWorkspace,
            $output
        );

        $context = $this->makeContext();
        $context->startTime = hrtime(true) - 2_000_000_000;

        $result = $processor->process($context, '/tmp/main/');

        $this->assertSame(ProcessingOutcome::DeletedAfterTimeout, $result->outcome);
        $this->assertTrue($result->outcome->isDeleted());
        $this->assertFalse($result->isSuccessful());
    }

    #[Test]
    public function it_reports_a_temporary_workspace_failure(): void
    {
        $tempWorkspace = Mockery::mock(TempWorkspaceService::class);
        $tempWorkspace->shouldReceive('createReleaseTempFolder')
            ->once()
            ->andThrow(new \RuntimeException('workspace is not writable'));

        $output = Mockery::mock(ConsoleOutputService::class);
        $output->shouldReceive('echoReleaseStart')->once()->andReturnNull();
        $output->shouldReceive('setProcessTitle')->once()->andReturnNull();
        $output->shouldReceive('warning')->once()->with('Unable to prepare release temp directory: workspace is not writable')->andReturnNull();

        $processor = $this->makeProcessor(tempWorkspace: $tempWorkspace, output: $output);

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $result = $processor->process($context, '/tmp/main/');

        $this->assertSame(ProcessingOutcome::TemporaryWorkspaceUnavailable, $result->outcome);
        $this->assertFalse($result->isSuccessful());
        $this->assertSame('workspace is not writable', $result->reason);
    }

    #[Test]
    public function it_reports_password_detection_as_a_successful_outcome(): void
    {
        $config = $this->makeConfig(['processPasswords' => true]);
        $nzbParser = $this->compressedNzbParser();

        $archiveService = Mockery::mock(ArchiveExtractionService::class);
        $archiveService->shouldReceive('processCompressedData')->once()->andReturn([
            'success' => false,
            'files' => [],
            'hasPassword' => true,
            'passwordStatus' => ReleaseBrowseService::PASSWD_RAR,
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<archive>'], '', 1, 'archive.part01.rar')
            ->andReturn(['success' => true, 'data' => 'ARCHIVE', 'groupUnavailable' => false, 'error' => null]);

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            $archiveService,
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $this->successfulTempWorkspace(),
            $this->passwordOutput()
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $result = $processor->process($context, '/tmp/main/');

        $this->assertSame(ProcessingOutcome::Passworded, $result->outcome);
        $this->assertTrue($result->isSuccessful());
        $this->assertFalse($result->artifactsCreated);
    }

    #[Test]
    public function it_reads_a_7z_file_list_from_the_last_volume_tail(): void
    {
        $config = $this->makeConfig(['processPasswords' => true, 'processJPGSample' => true]);
        $archive = $this->sevenZip('release.nfo', str_repeat('x', 100));
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [
                ['title' => '"G9AJxkjPdN0iBdyG.7z.001" yEnc (1/2)', 'segments' => ['<v1-1>', '<v1-2>']],
                ['title' => '"G9AJxkjPdN0iBdyG.7z.002" yEnc (1/2)', 'segments' => ['<v2-1>', '<v2-2>']],
            ],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<v1-1>', '<v1-2>'], '', 1, '"G9AJxkjPdN0iBdyG.7z.001" yEnc (1/2)')
            ->andReturn(['success' => true, 'data' => substr($archive, 0, 40), 'groupUnavailable' => false, 'error' => null]);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<v2-2>'], '', 1)
            ->andReturn(['success' => true, 'data' => substr($archive, -80), 'groupUnavailable' => false, 'error' => null]);

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('addFileInfo')
            ->once()
            ->with(Mockery::on(static fn (array $file): bool => $file['name'] === 'release.nfo' && $file['pass'] === 0), Mockery::any(), Mockery::any())
            ->andReturnUsing(static function (array $file, ReleaseProcessingContext $context): bool {
                $context->totalFileInfo++;

                return true;
            });
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $archiveFallback = Mockery::mock(ReleaseFilesArchiveFallback::class)->shouldIgnoreMissing();
        $archiveFallback->shouldNotReceive('processJpgFromArchiveFileList');
        $archiveFallback->shouldReceive('processNfoFromDownloadedArchives')->once()
            ->with(Mockery::on(static fn (ReleaseProcessingContext $context): bool => $context->downloadedArchives === []));

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            $archiveFallback,
            $this->tempWorkspaceWithoutFiles(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing()
        );

        $context = $this->makeContext();
        $result = $processor->process($context, '/tmp/main/');

        $this->assertNotSame(ProcessingOutcome::Passworded, $result->outcome);
        $this->assertFalse($context->releaseHasPassword);
    }

    #[Test]
    public function it_lists_a_compressed_7z_header_with_the_7zip_client(): void
    {
        $this->workDir = sys_get_temp_dir().'/'.uniqid('7z-release-', true);
        mkdir($this->workDir);
        $context = $this->processCompressedSevenZipHeader($this->fakeSevenZipClient($this->workDir), [
            'Extras/Some.Movie.nfo',
            'Extras/naïve – notes.txt',
            'Some.Movie.2024.1080p.WEB-DL.mkv',
        ]);

        $this->assertTrue($context->releaseHasPassword);
        $this->assertSame(ReleaseBrowseService::PASSWD_RAR, $context->passwordStatus);
        $this->assertFileExists($this->workDir.'/listed-size');
    }

    #[Test]
    public function it_lists_a_single_article_7z_whose_compressed_header_is_already_downloaded(): void
    {
        $this->workDir = sys_get_temp_dir().'/'.uniqid('7z-release-', true);
        mkdir($this->workDir);
        $context = $this->processCompressedSevenZipHeader($this->fakeSevenZipClient($this->workDir), [
            'Extras/Some.Movie.nfo',
            'Extras/naïve – notes.txt',
            'Some.Movie.2024.1080p.WEB-DL.mkv',
        ], singleArticle: true);

        $this->assertTrue($context->releaseHasPassword);
        $this->assertFileExists($this->workDir.'/listed-size');
    }

    #[Test]
    public function it_leaves_a_compressed_7z_header_unlisted_without_the_7zip_client(): void
    {
        $context = $this->processCompressedSevenZipHeader('/nonexistent/7z', []);

        $this->assertFalse($context->releaseHasPassword);
        $this->assertSame(0, $context->totalFileInfo);
    }

    #[Test]
    public function it_reports_a_7z_with_an_encrypted_header_as_passworded(): void
    {
        $config = $this->makeConfig(['processPasswords' => true]);
        $archive = $this->sevenZip('Movie.2026.1080p.mkv', str_repeat('x', 100), encryptedHeader: true);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [['title' => '"Some.Release.7z" yEnc (1/3)', 'segments' => ['<s-1>', '<s-2>', '<s-3>', '<s-4>']]],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<s-1>', '<s-2>', '<s-3>'], '', 1, '"Some.Release.7z" yEnc (1/3)')
            ->andReturn(['success' => true, 'data' => substr($archive, 0, 40), 'groupUnavailable' => false, 'error' => null]);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<s-4>'], '', 1)
            ->andReturn(['success' => true, 'data' => substr($archive, -40), 'groupUnavailable' => false, 'error' => null]);

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $this->successfulTempWorkspace(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing()
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $result = $processor->process($context, '/tmp/main/');

        $this->assertSame(ProcessingOutcome::Passworded, $result->outcome);
        $this->assertSame(ReleaseBrowseService::PASSWD_RAR, $context->passwordStatus);
    }

    #[Test]
    public function it_reports_group_unavailability_as_an_unsuccessful_outcome(): void
    {
        $config = $this->makeConfig(['processPasswords' => true]);
        $nzbParser = $this->compressedNzbParser();

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')->once()->andReturn([
            'success' => false,
            'data' => null,
            'groupUnavailable' => true,
            'error' => 'Group unavailable',
        ]);

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $output = Mockery::mock(ConsoleOutputService::class);
        $output->shouldReceive('echoReleaseStart')->once()->andReturnNull();
        $output->shouldReceive('setProcessTitle')->once()->andReturnNull();
        $output->shouldReceive('echoGroupUnavailable')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $this->successfulTempWorkspace(),
            $output
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $result = $processor->process($context, '/tmp/main/');

        $this->assertSame(ProcessingOutcome::GroupUnavailable, $result->outcome);
        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('unavailable', $result->reason);
    }

    #[Test]
    public function it_deletes_a_release_that_only_contains_par2_data(): void
    {
        $config = $this->makeConfig(['processPasswords' => true]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [['title' => '"KlUC4yTqeaIpcTbOIYdzhqqWF" yEnc (1/103)', 'segments' => ['<probe>'], 'filecount' => 1]],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<probe>'], '', 1)
            ->andReturn(['success' => true, 'data' => "PAR2\0PKT".str_repeat("\0", 56), 'groupUnavailable' => false, 'error' => null]);

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('deleteRelease')->once()->andReturnNull();
        $releaseManager->shouldNotReceive('finalizeRelease');

        $output = Mockery::mock(ConsoleOutputService::class);
        $output->shouldReceive('echoReleaseStart')->once()->andReturnNull();
        $output->shouldReceive('setProcessTitle')->once()->andReturnNull();
        $output->shouldReceive('warning')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $this->successfulTempWorkspace(),
            $output
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $result = $processor->process($context, '/tmp/main/');

        $this->assertSame(ProcessingOutcome::DeletedPar2Only, $result->outcome);
        $this->assertTrue($result->outcome->isDeleted());
    }

    #[Test]
    public function it_probes_for_par2_with_every_download_option_off(): void
    {
        $config = $this->makeConfig();
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [['title' => '"KlUC4yTqeaIpcTbOIYdzhqqWF" yEnc (1/103)', 'segments' => ['<probe>'], 'filecount' => 1]],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<probe>'], '', 1)
            ->andReturn(['success' => true, 'data' => "PAR2\0PKT".str_repeat("\0", 56), 'groupUnavailable' => false, 'error' => null]);

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('deleteRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $this->successfulTempWorkspace(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing()
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;

        $this->assertSame(ProcessingOutcome::DeletedPar2Only, $processor->process($context, '/tmp/main/')->outcome);
    }

    #[Test]
    public function it_reports_an_unavailable_group_from_the_par2_probe(): void
    {
        $config = $this->makeConfig(['processPasswords' => true]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [['title' => '"KlUC4yTqeaIpcTbOIYdzhqqWF" yEnc (1/103)', 'segments' => ['<probe>'], 'filecount' => 1]],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<probe>'], '', 1)
            ->andReturn(['success' => false, 'data' => null, 'groupUnavailable' => true, 'error' => 'no such group']);

        $releaseManager = Mockery::mock(ReleaseFileManager::class)->shouldIgnoreMissing();
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldNotReceive('deleteRelease');

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $this->successfulTempWorkspace(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing()
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;

        $this->assertSame(ProcessingOutcome::GroupUnavailable, $processor->process($context, '/tmp/main/')->outcome);
    }

    #[Test]
    public function it_keeps_a_probed_release_whose_data_is_not_par2(): void
    {
        $config = $this->makeConfig(['processPasswords' => true]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [['title' => '"KlUC4yTqeaIpcTbOIYdzhqqWF" yEnc (1/103)', 'segments' => ['<probe>'], 'filecount' => 1]],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<probe>'], '', 1)
            ->andReturn(['success' => true, 'data' => "\x1A\x45\xDF\xA3".str_repeat("\0", 60), 'groupUnavailable' => false, 'error' => null]);

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldNotReceive('deleteRelease');
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $output = Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $this->successfulTempWorkspace(),
            $output
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $result = $processor->process($context, '/tmp/main/');

        $this->assertNotSame(ProcessingOutcome::DeletedPar2Only, $result->outcome);
    }

    /**
     * @param  list<string>  $tailIds
     */
    #[Test]
    #[DataProvider('probedArchives')]
    public function it_inspects_an_archive_found_by_the_probe(string $type, array $tailIds): void
    {
        $config = $this->makeConfig(['processPasswords' => true]);
        $archive = match ($type) {
            'rar' => $this->rar('Movie.2026.1080p.mkv', str_repeat('x', 100)),
            'zip' => $this->zip('Movie.2026.1080p.mkv', str_repeat('x', 100)),
            default => $this->sevenZip('Movie.2026.1080p.mkv', str_repeat('x', 100)),
        };
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [['title' => '"KlUC4yTqeaIpcTbOIYdzhqqWF" yEnc (1/103)', 'segments' => ['<probe>', '<last>'], 'filecount' => 1]],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<probe>'], '', 1)
            ->andReturn(['success' => true, 'data' => substr($archive, 0, 80), 'groupUnavailable' => false, 'error' => null]);
        if ($tailIds !== []) {
            $downloadService->shouldReceive('download')
                ->once()
                ->with(DownloadKind::Compressed, $tailIds, '', 1)
                ->andReturn(['success' => true, 'data' => substr($archive, -80), 'groupUnavailable' => false, 'error' => null]);
        } else {
            $downloadService->shouldReceive('download')
                ->once()
                ->with(DownloadKind::Compressed, ['<last>'], '', 1)
                ->andReturn(['success' => true, 'data' => substr($archive, 80), 'groupUnavailable' => false, 'error' => null]);
        }

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldNotReceive('deleteRelease');
        $releaseManager->shouldReceive('addFileInfo')
            ->once()
            ->with(Mockery::on(static fn (array $file): bool => $file['name'] === 'Movie.2026.1080p.mkv'), Mockery::any(), Mockery::any())
            ->andReturnTrue();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class)->shouldIgnoreMissing(),
            $this->tempWorkspaceWithoutFiles(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing()
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $processor->process($context, '/tmp/main/');

        $this->assertTrue($context->nzbHasCompressedFile);
    }

    #[Test]
    public function it_inspects_an_archive_found_in_a_later_file_of_an_obfuscated_release(): void
    {
        $config = $this->makeConfig(['processPasswords' => true]);
        $archive = $this->rar('Movie.2026.1080p.mkv', str_repeat('x', 100));
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [
                ['title' => '"aB3dE5fG7hJ9" yEnc (1/3)', 'segments' => ['<par2-1>', '<par2-2>'], 'size' => 900, 'filecount' => 1],
                ['title' => '"kL2mN4pQ6rS8" yEnc (1/2)', 'segments' => ['<rar-1>', '<rar-2>'], 'size' => 800, 'filecount' => 1],
                ['title' => '"tT1uU2vV3wW4" yEnc (1/2)', 'segments' => ['<other-1>'], 'size' => 700, 'filecount' => 1],
            ],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<par2-1>'], '', 1)
            ->andReturn(['success' => true, 'data' => "PAR2\0PKT".str_repeat("\0", 56), 'groupUnavailable' => false, 'error' => null]);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<rar-1>'], '', 1)
            ->andReturn(['success' => true, 'data' => substr($archive, 0, 60), 'groupUnavailable' => false, 'error' => null]);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<rar-2>'], '', 1)
            ->andReturn(['success' => true, 'data' => substr($archive, 60), 'groupUnavailable' => false, 'error' => null]);

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldNotReceive('deleteRelease');
        $releaseManager->shouldReceive('addFileInfo')
            ->once()
            ->with(Mockery::on(static fn (array $file): bool => $file['name'] === 'Movie.2026.1080p.mkv'), Mockery::any(), Mockery::any())
            ->andReturnTrue();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class)->shouldIgnoreMissing(),
            $this->tempWorkspaceWithoutFiles(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing()
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $processor->process($context, '/tmp/main/');

        $this->assertTrue($context->nzbHasCompressedFile);
    }

    #[Test]
    public function it_lists_a_compressed_7z_header_found_by_the_multi_file_probe(): void
    {
        $this->workDir = sys_get_temp_dir().'/'.uniqid('7z-release-', true);
        mkdir($this->workDir);
        $tmpPath = $this->workDir.'/';
        $config = $this->makeConfig(['processPasswords' => true, 'sevenZipPath' => $this->fakeSevenZipClient($this->workDir)]);
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [
                ['title' => '"aB3dE5fG7hJ9" yEnc (1/2)', 'segments' => ['<par2-1>', '<par2-2>'], 'size' => 900, 'filecount' => 1],
                ['title' => '"kL2mN4pQ6rS8" yEnc (1/3)', 'segments' => ['<7z-1>', '<7z-2>', '<7z-3>'], 'size' => 800, 'filecount' => 1],
            ],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<par2-1>'], '', 1)
            ->andReturn(['success' => true, 'data' => "PAR2\0PKT".str_repeat("\0", 56), 'groupUnavailable' => false, 'error' => null]);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<7z-1>'], '', 1)
            ->andReturn(['success' => true, 'data' => substr($archive, 0, 40), 'groupUnavailable' => false, 'error' => null]);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<7z-3>'], '', 1)
            ->andReturn(['success' => true, 'data' => substr($archive, -60), 'groupUnavailable' => false, 'error' => null]);

        $listed = [];
        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldNotReceive('deleteRelease');
        $releaseManager->shouldReceive('addFileInfo')
            ->andReturnUsing(static function (array $file, ReleaseProcessingContext $context) use (&$listed): bool {
                $listed[] = $file['name'];
                $context->totalFileInfo++;

                return true;
            });
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class)->shouldIgnoreMissing(),
            Mockery::mock(TempWorkspaceService::class)
                ->shouldReceive('createReleaseTempFolder')->once()->andReturn($tmpPath)
                ->shouldReceive('listFiles')->andReturn([])
                ->shouldReceive('clearDirectory')->once()->with($tmpPath, false)->andReturnNull()
                ->getMock(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing()
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $processor->process($context, '/tmp/main/');

        $this->assertSame(['Extras/Some.Movie.nfo', 'Extras/naïve – notes.txt', 'Some.Movie.2024.1080p.WEB-DL.mkv'], $listed);
        $this->assertFileExists($this->workDir.'/listed-size');
    }

    #[Test]
    public function it_probes_at_most_the_configured_number_of_files_and_never_deletes_a_multi_file_release(): void
    {
        $config = $this->makeConfig(['processPasswords' => true, 'archiveProbeFiles' => 2]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => array_map(
                static fn (int $i): array => ['title' => '"obfuscated'.$i.'" yEnc (1/1)', 'segments' => ['<file-'.$i.'>'], 'size' => 100, 'filecount' => 1],
                range(1, 4),
            ),
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        foreach (['<file-1>', '<file-2>'] as $messageId) {
            $downloadService->shouldReceive('download')
                ->once()
                ->with(DownloadKind::Compressed, [$messageId], '', 1)
                ->andReturn(['success' => true, 'data' => "PAR2\0PKT".str_repeat("\0", 56), 'groupUnavailable' => false, 'error' => null]);
        }

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldNotReceive('deleteRelease');
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $result = $this->multiFileProbeProcessor($config, $nzbParser, $downloadService, $releaseManager)
            ->process($this->makeContext(), '/tmp/main/');

        $this->assertNotSame(ProcessingOutcome::DeletedPar2Only, $result->outcome);
    }

    #[Test]
    public function it_stops_probing_files_when_the_group_is_unavailable(): void
    {
        $config = $this->makeConfig(['processPasswords' => true]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [
                ['title' => '"aB3dE5fG7hJ9" yEnc (1/1)', 'segments' => ['<one>'], 'filecount' => 1],
                ['title' => '"kL2mN4pQ6rS8" yEnc (1/1)', 'segments' => ['<two>'], 'filecount' => 1],
            ],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<one>'], '', 1)
            ->andReturn(['success' => false, 'data' => null, 'groupUnavailable' => true, 'error' => 'No such group']);

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $result = $this->multiFileProbeProcessor($config, $nzbParser, $downloadService, $releaseManager)
            ->process($this->makeContext(), '/tmp/main/');

        $this->assertSame(ProcessingOutcome::GroupUnavailable, $result->outcome);
    }

    #[Test]
    public function it_reads_media_info_from_the_downloaded_sample_instead_of_fetching_its_first_article_again(): void
    {
        $tmpPath = $this->useRealFilesystem();
        $config = $this->makeConfig(['processThumbnails' => true, 'processMediaInfo' => true]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [['title' => '"Movie.2024.sample.mkv" yEnc (1/2)', 'segments' => ['<s1>', '<s2>'], 'filecount' => 1]],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Sample, ['<s1>', '<s2>'], '', 1)
            ->andReturn(['success' => true, 'data' => str_repeat('V', 64), 'groupUnavailable' => false, 'error' => null]);
        $downloadService->shouldReceive('meetsMinimumSize')->andReturnTrue();

        $media = Mockery::mock(MediaExtractionService::class);
        $media->shouldReceive('getSample')->once()->andReturnFalse();
        $media->shouldReceive('getMediaInfo')->once()->with(Mockery::pattern('/sample_\d+\.avi$/'), 1)->andReturnTrue();

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            $media,
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            Mockery::mock(TempWorkspaceService::class)
                ->shouldReceive('createReleaseTempFolder')->once()->andReturn($tmpPath)
                ->shouldReceive('clearDirectory')->once()->andReturnNull()
                ->getMock(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing(),
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $processor->process($context, '/tmp/main/');

        $this->assertTrue($context->foundMediaInfo);
    }

    #[Test]
    public function it_downloads_a_sample_whose_name_only_the_par2_index_reveals(): void
    {
        $tmpPath = $this->useRealFilesystem();
        $config = $this->makeConfig(['processThumbnails' => true, 'par2MediaMapping' => true, 'archiveProbeFiles' => 0]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [
                ['title' => '"c0ffee01" yEnc (1/1)', 'segments' => ['<index>'], 'size' => 700, 'filecount' => 1],
                ['title' => '"c0ffee02" yEnc (1/3)', 'segments' => ['<s1>', '<s2>', '<s3>'], 'size' => 3000, 'filecount' => 1],
                ['title' => '"c0ffee03" yEnc (1/50)', 'segments' => ['<m1>', '<m2>'], 'size' => 50000, 'filecount' => 1],
            ],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Par2Index, ['<index>'], '', 1, '"c0ffee01" yEnc (1/1)', false)
            ->andReturn(['success' => true, 'data' => $this->par2Index([
                ['Sample/movie-sample.mkv', 2900, 'MKV-SAMPLE'],
                ['movie.mkv', 48500, 'MKV-MAIN'],
            ]), 'groupUnavailable' => false, 'error' => null]);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Sample, ['<s1>', '<s2>'], '', 1)
            ->andReturn(['success' => true, 'data' => str_repeat('V', 64), 'groupUnavailable' => false, 'error' => null]);
        $downloadService->shouldReceive('meetsMinimumSize')->andReturnTrue();

        $media = Mockery::mock(MediaExtractionService::class);
        $media->shouldReceive('getSample')->once()->andReturnTrue();

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            $media,
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            Mockery::mock(TempWorkspaceService::class)
                ->shouldReceive('createReleaseTempFolder')->once()->andReturn($tmpPath)
                ->shouldReceive('clearDirectory')->once()->andReturnNull()
                ->getMock(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing(),
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $result = $processor->process($context, '/tmp/main/');

        $this->assertTrue($context->foundSample);
        $this->assertSame(['Sample/movie-sample.mkv', 'movie.mkv'], array_values(array_map(
            static fn ($mapped): string => $mapped->file->name,
            $context->mediaPlan?->mappedFiles ?? [],
        )));
        $this->assertArrayHasKey(ProcessingStage::MediaSourcePlanning->value, $result->stageDurations);
    }

    #[Test]
    public function it_takes_a_preview_from_a_sample_stored_in_an_archive_without_extracting_it(): void
    {
        if (! is_executable('/usr/bin/ffmpeg') || ! is_executable('/usr/bin/ffprobe')) {
            $this->markTestSkipped('ffmpeg and ffprobe are not installed.');
        }

        $tmpPath = $this->useRealFilesystem();
        exec('ffmpeg -v error -y -f lavfi -i testsrc=size=320x240:rate=10:duration=4 -c:v mjpeg '.escapeshellarg($tmpPath.'source.mkv'));
        $sample = (string) file_get_contents($tmpPath.'source.mkv');
        unlink($tmpPath.'source.mkv');

        $config = $this->makeConfig([
            'processThumbnails' => true,
            'extractUsingRarInfo' => true,
            'mediaArchiveSlicing' => true,
            'ffmpegPath' => '/usr/bin/ffmpeg',
            'ffprobePath' => '/usr/bin/ffprobe',
        ]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [['title' => '"movie.rar" yEnc (1/1)', 'segments' => ['<rar>'], 'size' => strlen($sample) + 200, 'filecount' => 1]],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<rar>'], '', 1, '"movie.rar" yEnc (1/1)')
            ->andReturn(['success' => true, 'data' => $this->rar('Sample/movie-sample.mkv', $sample), 'groupUnavailable' => false, 'error' => null]);

        $releaseImage = Mockery::mock(ReleaseImageService::class);
        $releaseImage->imgSavePath = '/covers/preview/';
        $releaseImage->shouldReceive('saveLocalImage')
            ->once()
            ->with('guid-1_thumb', Mockery::type('string'), '/covers/preview/', ImageAssetProfile::Preview)
            ->andReturn(ImageProcessingResult::success('/covers/preview/guid-1_thumb.webp', 320, 240, 'image/webp'));
        $media = new MediaExtractionService(
            $config,
            $releaseImage,
            Mockery::mock(ReleaseExtraService::class),
            Mockery::mock(CategorizationService::class),
        );

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('addFileInfo')->once()->andReturnUsing(static function (array $file, ReleaseProcessingContext $context): bool {
            $context->totalFileInfo++;

            return true;
        });
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            $media,
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class)->shouldIgnoreMissing(),
            Mockery::mock(TempWorkspaceService::class)
                ->shouldReceive('createReleaseTempFolder')->once()->andReturn($tmpPath)
                ->shouldReceive('listFiles')->andReturn([])
                ->shouldReceive('clearDirectory')->once()->andReturnNull()
                ->getMock(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing(),
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $result = $processor->process($context, '/tmp/main/');

        $this->assertTrue($context->foundSample);
        $this->assertArrayHasKey(ProcessingStage::MediaSources->value, $result->stageDurations);
    }

    #[Test]
    public function it_tries_the_next_image_when_the_best_one_is_unusable(): void
    {
        $tmpPath = $this->useRealFilesystem();
        $config = $this->makeConfig(['processJPGSample' => true]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [
                ['title' => '"movie.cover.jpg" yEnc (1/1)', 'segments' => ['<cover>'], 'size' => 400000, 'filecount' => 1],
                ['title' => '"movie.screens.jpg" yEnc (1/2)', 'segments' => ['<s1>', '<s2>'], 'size' => 1200000, 'filecount' => 1],
            ],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')->once()->ordered()->with(DownloadKind::Jpg, ['<s1>', '<s2>'], '', 1)
            ->andReturn(['success' => true, 'data' => 'BROKEN', 'groupUnavailable' => false, 'error' => null]);
        $downloadService->shouldReceive('download')->once()->ordered()->with(DownloadKind::Jpg, ['<cover>'], '', 1)
            ->andReturn(['success' => true, 'data' => 'GOOD', 'groupUnavailable' => false, 'error' => null]);

        $media = Mockery::mock(MediaExtractionService::class);
        $media->shouldReceive('isUsableImage')->twice()->andReturnUsing(static fn (string $path): bool => file_get_contents($path) === 'GOOD');
        $media->shouldReceive('getJPGSample')->once()->andReturnTrue();

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            $media,
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            Mockery::mock(TempWorkspaceService::class)
                ->shouldReceive('createReleaseTempFolder')->once()->andReturn($tmpPath)
                ->shouldReceive('clearDirectory')->once()->andReturnNull()
                ->getMock(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing(),
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $processor->process($context, '/tmp/main/');

        $this->assertTrue($context->foundJPGSample);
    }

    #[Test]
    public function it_never_reads_the_par2_index_when_mapping_is_disabled(): void
    {
        $config = $this->makeConfig(['processThumbnails' => true, 'archiveProbeFiles' => 0]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [
                ['title' => '"c0ffee01" yEnc (1/1)', 'segments' => ['<index>'], 'size' => 700, 'filecount' => 1],
                ['title' => '"c0ffee02" yEnc (1/3)', 'segments' => ['<s1>'], 'size' => 3000, 'filecount' => 1],
                ['title' => '"c0ffee03" yEnc (1/50)', 'segments' => ['<m1>'], 'size' => 50000, 'filecount' => 1],
            ],
        ]);

        $downloadService = $this->scopedDownloadService();
        $downloadService->shouldNotReceive('download');

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $this->successfulTempWorkspace(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing(),
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $processor->process($context, '/tmp/main/');

        $this->assertNull($context->mediaPlan);
    }

    #[Test]
    public function it_lists_a_later_rar_volume_when_the_first_lacks_its_header_segment(): void
    {
        $config = $this->makeConfig(['processPasswords' => true]);
        $volume = $this->rar('Movie.2026.1080p.mkv', str_repeat('x', 100), laterVolume: true);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [
                ['title' => '"release.part01.rar" yEnc (2/2)', 'segments' => ['<p1-2>'], 'firstsegment' => false],
                ['title' => '"release.part02.rar" yEnc (1/2)', 'segments' => ['<p2-1>'], 'firstsegment' => true],
            ],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<p2-1>'], '', 1, '"release.part02.rar" yEnc (1/2)')
            ->andReturn(['success' => true, 'data' => $volume, 'groupUnavailable' => false, 'error' => null]);

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('addFileInfo')
            ->once()
            ->with(Mockery::on(static fn (array $file): bool => $file['name'] === 'Movie.2026.1080p.mkv'), Mockery::any(), Mockery::any())
            ->andReturnUsing(static function (array $file, ReleaseProcessingContext $context): bool {
                $context->totalFileInfo++;

                return true;
            });
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class)->shouldIgnoreMissing(),
            $this->tempWorkspaceWithoutFiles(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing()
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $processor->process($context, '/tmp/main/');

        $this->assertSame(1, $context->totalFileInfo);
    }

    #[Test]
    #[DataProvider('splitVideoPayloads')]
    public function it_decides_a_split_video_by_its_data_with_only_password_checks(string $payload): void
    {
        $config = $this->makeConfig(['processPasswords' => true]);
        $data = match ($payload) {
            'video' => "\x1A\x45\xDF\xA3".str_repeat("\x00", 200),
            'archive' => $this->rar('Movie.2026.1080p.mkv', str_repeat('x', 100)),
            'encrypted archive' => $this->rar('Movie.2026.1080p.mkv', str_repeat('x', 100), encryptedFile: true),
        };
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [
                ['title' => '"Movie.2026.sample.mkv" yEnc (1/5)', 'segments' => ['<s-1>']],
                ['title' => '"Movie.2026.mkv.001" yEnc (1/80)', 'segments' => ['<m1-1>', '<m1-2>']],
                ['title' => '"Movie.2026.mkv.002" yEnc (1/80)', 'segments' => ['<m2-1>', '<m2-2>']],
            ],
        ]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Compressed, ['<m1-1>'], '', 1, '"Movie.2026.mkv.001" yEnc (1/80)')
            ->andReturn(['success' => true, 'data' => $data, 'groupUnavailable' => false, 'error' => null]);

        $tmpPath = sys_get_temp_dir().'/split-video-'.uniqid().'/';
        mkdir($tmpPath);
        $workspace = Mockery::mock(TempWorkspaceService::class);
        $workspace->shouldReceive('createReleaseTempFolder')->once()->andReturn($tmpPath);
        $workspace->shouldReceive('listFiles')->andReturn([]);
        $workspace->shouldReceive('clearDirectory')->once()->with($tmpPath, false)
            ->andReturnUsing(static function () use ($tmpPath): void {
                array_map('unlink', glob($tmpPath.'*') ?: []);
                rmdir($tmpPath);
            });

        $mediaService = Mockery::mock(MediaExtractionService::class);
        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();
        if ($payload === 'video') {
            $mediaService->shouldIgnoreMissing(false);
            $releaseManager->shouldNotReceive('addFileInfo');
        } else {
            $mediaService->shouldNotReceive('getMediaInfo');
            $releaseManager->shouldReceive('addFileInfo')
                ->once()
                ->with(Mockery::on(static fn (array $file): bool => $file['name'] === 'Movie.2026.1080p.mkv'), Mockery::any(), Mockery::any())
                ->andReturnUsing(static function (array $file, ReleaseProcessingContext $context): bool {
                    if ($file['pass']) {
                        $context->releaseHasPassword = true;
                        $context->passwordStatus = ReleaseBrowseService::PASSWD_RAR;

                        return false;
                    }

                    return true;
                });
        }

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            $mediaService,
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class)->shouldIgnoreMissing(),
            $workspace,
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing()
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $processor->process($context, '/tmp/main/');

        $this->assertSame($payload === 'encrypted archive', $context->releaseHasPassword);
        $this->assertSame($payload === 'video', $context->compressedFilesAreMedia());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function splitVideoPayloads(): array
    {
        return [
            'video data' => ['video'],
            'archive data' => ['archive'],
            'encrypted archive data' => ['encrypted archive'],
        ];
    }

    #[Test]
    public function it_lists_the_first_volume_of_several_archive_sets_for_a_season_pack(): void
    {
        $downloaded = [];

        $context = $this->processSeasonPackSets(4, $downloaded);

        $this->assertSame(['E01.part01', 'E02.part01', 'E03.part01'], $downloaded);
        $this->assertTrue($context->seasonPack);
        $this->assertSame(3, $context->totalFileInfo);
    }

    #[Test]
    public function it_continues_with_the_next_archive_set_when_one_fails_to_download(): void
    {
        $downloaded = [];

        $this->processSeasonPackSets(4, $downloaded, failing: ['E01.part01']);

        $this->assertSame(['E01.part01', 'E02.part01', 'E03.part01', 'E04.part01'], $downloaded);
    }

    #[Test]
    public function it_stops_reading_archive_sets_once_a_password_is_found(): void
    {
        $downloaded = [];

        $context = $this->processSeasonPackSets(4, $downloaded, passworded: 'Show.S01E02.1080p.WEB-DL-GRP.mkv');

        $this->assertSame(['E01.part01', 'E02.part01'], $downloaded);
        $this->assertTrue($context->releaseHasPassword);
    }

    #[Test]
    public function it_still_stops_at_the_first_listed_archive_set_of_a_movie(): void
    {
        $config = $this->makeConfig(['processPasswords' => true]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn(['error' => null, 'contents' => [
            ['title' => '"Movie.2026.1080p.BluRay-GRP.cd1.rar" yEnc (1/2)', 'segments' => ['<cd1>']],
            ['title' => '"Movie.2026.1080p.BluRay-GRP.cd2.rar" yEnc (1/2)', 'segments' => ['<cd2>']],
        ]]);
        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')->once()
            ->andReturn(['success' => true, 'data' => $this->rar('Movie.2026.1080p.BluRay-GRP.cd1.mkv', str_repeat('x', 100)), 'groupUnavailable' => false, 'error' => null]);

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $this->seasonPackProcessor($config, $nzbParser, $downloadService, $this->countingReleaseManager())->process($context, '/tmp/main/');

        $this->assertFalse($context->seasonPack);
        $this->assertSame(1, $context->totalFileInfo);
    }

    #[Test]
    public function it_runs_the_held_back_predb_match_when_only_one_episode_was_listed(): void
    {
        $config = $this->makeConfig(['processPasswords' => true]);
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn(['error' => null, 'contents' => [
            ['title' => '"a1b2c3d4.part01.rar" yEnc (1/2)', 'segments' => ['<episode>']],
            ['title' => '"e5f6a7b8.rar" yEnc (1/1)', 'segments' => ['<extras>']],
        ]]);
        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')->twice()->andReturnUsing(fn (DownloadKind $kind, array $ids): array => [
            'success' => true,
            'data' => $this->rar($ids === ['<episode>'] ? 'Show.S01E05.1080p.WEB-DL-GRP.mkv' : 'Show.S01E05.1080p.WEB-DL-GRP.en.srt', str_repeat('x', 100)),
            'groupUnavailable' => false,
            'error' => null,
        ]);
        $releaseManager = $this->countingReleaseManager();
        $releaseManager->shouldReceive('matchFileNamesToPreDb')->once()
            ->with(Mockery::on(static fn (array $files): bool => $files[0]['name'] === 'Show.S01E05.1080p.WEB-DL-GRP.mkv'), Mockery::type(ReleaseProcessingContext::class));

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $this->seasonPackProcessor($config, $nzbParser, $downloadService, $releaseManager)->process($context, '/tmp/main/');

        $this->assertFalse($context->seasonPack);
        $this->assertFalse($context->seasonPackProbable);
    }

    /**
     * Run a season pack packed one RAR set (part01 + part02) per episode.
     *
     * @param  list<string>  $downloaded  Filled with the volumes downloaded, e.g. "E01.part01"
     * @param  list<string>  $failing  Volumes whose download fails
     */
    private function processSeasonPackSets(
        int $episodes,
        array &$downloaded,
        array $failing = [],
        string $passworded = '',
    ): ReleaseProcessingContext {
        $config = $this->makeConfig(['processPasswords' => true]);
        $contents = [];
        for ($episode = 1; $episode <= $episodes; $episode++) {
            foreach ([1, 2] as $part) {
                $contents[] = [
                    'title' => sprintf('"Show.S01E%02d.1080p.WEB-DL-GRP.part%02d.rar" yEnc (1/2)', $episode, $part),
                    'segments' => [sprintf('<e%d-p%d>', $episode, $part)],
                ];
            }
        }
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $nzbParser->shouldReceive('parseNzb')->once()->andReturn(['error' => null, 'contents' => $contents]);

        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        $downloadService->shouldReceive('download')
            ->andReturnUsing(function (DownloadKind $kind, array $messageIds, string $group, int $releaseId, string $title) use (&$downloaded, $failing): array {
                preg_match('/(E\d{2})\.1080p\.WEB-DL-GRP\.(part\d{2})/', $title, $match);
                $volume = $match[1].'.'.$match[2];
                $downloaded[] = $volume;
                if (in_array($volume, $failing, true)) {
                    return ['success' => false, 'data' => null, 'groupUnavailable' => false, 'error' => 'missing'];
                }

                return ['success' => true, 'data' => $this->rar('Show.S01'.$match[1].'.1080p.WEB-DL-GRP.mkv', str_repeat('x', 100)), 'groupUnavailable' => false, 'error' => null];
            });

        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('addFileInfo')
            ->andReturnUsing(static function (array $file, ReleaseProcessingContext $context) use ($passworded): bool {
                $context->totalFileInfo++;
                if ($file['name'] === $passworded) {
                    $context->releaseHasPassword = true;
                }

                return true;
            });
        $releaseManager->shouldReceive('matchFileNamesToPreDb')->never();
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $this->seasonPackProcessor($config, $nzbParser, $downloadService, $releaseManager)->process($context, '/tmp/main/');

        return $context;
    }

    private function countingReleaseManager(): ReleaseFileManager&Mockery\MockInterface
    {
        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('addFileInfo')->andReturnUsing(static function (array $file, ReleaseProcessingContext $context): bool {
            $context->totalFileInfo++;

            return true;
        });
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        return $releaseManager;
    }

    private function seasonPackProcessor(
        ProcessingConfiguration $config,
        NzbContentParser $nzbParser,
        UsenetDownloadService $downloadService,
        ReleaseFileManager $releaseManager,
    ): ReleaseProcessor {
        return new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            Mockery::mock(MediaExtractionService::class)->shouldIgnoreMissing(false),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class)->shouldIgnoreMissing(),
            $this->tempWorkspaceWithoutFiles(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing(),
        );
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function probedArchives(): array
    {
        return [
            'RAR' => ['rar', []],
            'ZIP' => ['zip', []],
            '7z' => ['7z', ['<last>']],
        ];
    }

    /**
     * @param  list<string>  $expectedNames
     */
    private function processCompressedSevenZipHeader(
        string $client,
        array $expectedNames,
        bool $singleArticle = false,
    ): ReleaseProcessingContext {
        $config = $this->makeConfig(['processPasswords' => true, 'sevenZipPath' => $client]);
        $archive = $this->sevenZip('ignored.mkv', str_repeat('x', 100), compressedHeader: true);
        $tmpPath = $this->workDir !== '' ? $this->workDir.'/' : '/tmp/ap-release/';
        $nzbParser = Mockery::mock(NzbContentParser::class);
        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);
        if ($singleArticle) {
            $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
                'error' => null,
                'contents' => [['title' => '"G9AJxkjPdN0iBdyG.7z" yEnc (1/1)', 'segments' => ['<s-1>']]],
            ]);
            $downloadService->shouldReceive('download')
                ->once()
                ->with(DownloadKind::Compressed, ['<s-1>'], '', 1, '"G9AJxkjPdN0iBdyG.7z" yEnc (1/1)')
                ->andReturn(['success' => true, 'data' => $archive, 'groupUnavailable' => false, 'error' => null]);
        } else {
            $nzbParser->shouldReceive('parseNzb')->once()->andReturn([
                'error' => null,
                'contents' => [
                    ['title' => '"G9AJxkjPdN0iBdyG.7z.001" yEnc (1/2)', 'segments' => ['<v1-1>', '<v1-2>']],
                    ['title' => '"G9AJxkjPdN0iBdyG.7z.002" yEnc (1/2)', 'segments' => ['<v2-1>', '<v2-2>']],
                ],
            ]);
            $downloadService->shouldReceive('download')
                ->once()
                ->with(DownloadKind::Compressed, ['<v1-1>', '<v1-2>'], '', 1, '"G9AJxkjPdN0iBdyG.7z.001" yEnc (1/2)')
                ->andReturn(['success' => true, 'data' => substr($archive, 0, 40), 'groupUnavailable' => false, 'error' => null]);
            $downloadService->shouldReceive('download')
                ->once()
                ->with(DownloadKind::Compressed, ['<v2-2>'], '', 1)
                ->andReturn(['success' => true, 'data' => substr($archive, -60), 'groupUnavailable' => false, 'error' => null]);
        }

        $listed = [];
        $releaseManager = Mockery::mock(ReleaseFileManager::class);
        $releaseManager->shouldReceive('processReleaseNameFromNzbContents')->once()->andReturnFalse();
        $releaseManager->shouldReceive('addFileInfo')
            ->andReturnUsing(static function (array $file, ReleaseProcessingContext $context) use (&$listed): bool {
                $listed[] = $file['name'];
                if ($file['pass'] === 1) {
                    $context->releaseHasPassword = true;
                    $context->passwordStatus = ReleaseBrowseService::PASSWD_RAR;

                    return false;
                }
                $context->totalFileInfo++;

                return true;
            });
        $releaseManager->shouldReceive('finalizeRelease')->once()->andReturnNull();

        $processor = new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class)->shouldIgnoreMissing(),
            Mockery::mock(TempWorkspaceService::class)
                ->shouldReceive('createReleaseTempFolder')->once()->andReturn($tmpPath)
                ->shouldReceive('listFiles')->andReturn([])
                ->shouldReceive('clearDirectory')->once()->with($tmpPath, false)->andReturnNull()
                ->getMock(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing()
        );

        $context = $this->makeContext();
        $context->release->nfostatus = 1;
        $processor->process($context, '/tmp/main/');

        $this->assertSame($expectedNames, $listed);

        return $context;
    }

    private function multiFileProbeProcessor(
        ProcessingConfiguration $config,
        NzbContentParser $nzbParser,
        UsenetDownloadService $downloadService,
        ReleaseFileManager $releaseManager,
    ): ReleaseProcessor {
        return new ReleaseProcessor(
            $config,
            $nzbParser,
            new AdditionalWorkPlanner($config),
            new ArchiveExtractionService($config),
            Mockery::mock(MediaExtractionService::class),
            $downloadService,
            $releaseManager,
            Mockery::mock(ReleaseFilesArchiveFallback::class)->shouldIgnoreMissing(),
            $this->tempWorkspaceWithoutFiles(),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing()
        );
    }

    private function makeProcessor(
        ?NzbContentParser $nzbParser = null,
        ?ReleaseFileManager $releaseManager = null,
        ?TempWorkspaceService $tempWorkspace = null,
        ?ConsoleOutputService $output = null
    ): ReleaseProcessor {
        $config = $this->makeConfig();

        return new ReleaseProcessor(
            $config,
            $nzbParser ?? Mockery::mock(NzbContentParser::class),
            new AdditionalWorkPlanner($config),
            Mockery::mock(ArchiveExtractionService::class),
            Mockery::mock(MediaExtractionService::class),
            $this->scopedDownloadService(),
            $releaseManager ?? Mockery::mock(ReleaseFileManager::class),
            Mockery::mock(ReleaseFilesArchiveFallback::class),
            $tempWorkspace ?? Mockery::mock(TempWorkspaceService::class),
            $output ?? Mockery::mock(ConsoleOutputService::class)
        );
    }

    /**
     * Point the File facade at the real filesystem and return an empty temp directory.
     */
    private function useRealFilesystem(): string
    {
        $container = new Application(sys_get_temp_dir());
        $container->instance('files', new Filesystem);
        Facade::setFacadeApplication($container);

        $tmpPath = sys_get_temp_dir().'/release-processor-'.uniqid('', true).'/';
        (new Filesystem)->makeDirectory($tmpPath, 0777, true, true);
        $this->temporaryDirectories[] = $tmpPath;

        return $tmpPath;
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

    private function compressedNzbParser(): NzbContentParser
    {
        $parser = Mockery::mock(NzbContentParser::class);
        $parser->shouldReceive('parseNzb')->once()->andReturn([
            'error' => null,
            'contents' => [['title' => 'archive.part01.rar', 'segments' => ['<archive>']]],
        ]);

        return $parser;
    }

    private function scopedDownloadService(): UsenetDownloadService
    {
        $downloadService = Mockery::mock(UsenetDownloadService::class);
        $this->expectDownloadScope($downloadService);

        return $downloadService;
    }

    private function expectDownloadScope(UsenetDownloadService $downloadService): void
    {
        $downloadService->shouldReceive('beginReleaseScope')->once()->andReturnNull();
        $downloadService->shouldReceive('finishReleaseScope')->once()->andReturn(new DownloadMetrics);
    }

    private function successfulTempWorkspace(): TempWorkspaceService
    {
        return Mockery::mock(TempWorkspaceService::class)
            ->shouldReceive('createReleaseTempFolder')->once()->andReturn('/tmp/ap-release/')
            ->shouldReceive('clearDirectory')->once()->with('/tmp/ap-release/', false)->andReturnNull()
            ->getMock();
    }

    private function tempWorkspaceWithoutFiles(): TempWorkspaceService
    {
        return Mockery::mock(TempWorkspaceService::class)
            ->shouldReceive('createReleaseTempFolder')->once()->andReturn('/tmp/ap-release/')
            ->shouldReceive('listFiles')->andReturn([])
            ->shouldReceive('clearDirectory')->once()->with('/tmp/ap-release/', false)->andReturnNull()
            ->getMock();
    }

    private function passwordOutput(): ConsoleOutputService
    {
        return Mockery::mock(ConsoleOutputService::class)
            ->shouldReceive('echoReleaseStart')->once()->andReturnNull()
            ->shouldReceive('setProcessTitle')->once()->andReturnNull()
            ->shouldReceive('echoCompressedDownload')->once()->andReturnNull()
            ->getMock();
    }
}
