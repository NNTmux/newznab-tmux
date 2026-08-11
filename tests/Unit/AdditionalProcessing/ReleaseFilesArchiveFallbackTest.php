<?php

namespace Tests\Unit\AdditionalProcessing;

use App\Models\Release;
use App\Models\ReleaseFile;
use App\Services\AdditionalProcessing\AdditionalWorkPlanner;
use App\Services\AdditionalProcessing\ArchiveExtractionService;
use App\Services\AdditionalProcessing\ConsoleOutputService;
use App\Services\AdditionalProcessing\MediaExtractionService;
use App\Services\AdditionalProcessing\ReleaseFileManager;
use App\Services\AdditionalProcessing\ReleaseFilesArchiveFallback;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\AdditionalProcessing\UsenetDownloadService;
use App\Services\NNTP\NNTPService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReleaseFilesArchiveFallbackTest extends TestCase
{
    use CreatesProcessingConfiguration;

    private string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Application(sys_get_temp_dir());
        $container->instance('files', new Filesystem);
        Facade::setFacadeApplication($container);

        $capsule = new Capsule($container);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setEventDispatcher(new Dispatcher($container));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $capsule->schema()->create('release_files', function ($table): void {
            $table->integer('releases_id');
            $table->string('name');
            $table->integer('size')->default(0);
            $table->timestamps();
        });

        $this->tmpPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'fallback_'.uniqid('', true).DIRECTORY_SEPARATOR;
        (new Filesystem)->makeDirectory($this->tmpPath, 0777, true, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->tmpPath);
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_extracts_webp_candidates_from_an_archive_file_list(): void
    {
        $archive = Mockery::mock(ArchiveExtractionService::class);
        $archive->shouldReceive('extractSpecificFiles')
            ->once()
            ->with('ARCHIVE', ['cover.webp'], Mockery::type('string'))
            ->andReturn(['cover.webp' => 'WEBP-DATA']);

        $media = Mockery::mock(MediaExtractionService::class);
        $media->shouldReceive('isValidImage')->once()->andReturn(true);
        $media->shouldReceive('getJPGSample')->once()->andReturn(true);

        $output = Mockery::mock(ConsoleOutputService::class);
        $output->shouldReceive('echoJpgSaved')->once()->andReturnNull();

        $fallback = new ReleaseFilesArchiveFallback(
            $archive,
            $media,
            Mockery::mock(UsenetDownloadService::class),
            Mockery::mock(ReleaseFileManager::class),
            $output
        );

        $context = $this->makeContext();
        $fallback->processJpgFromArchiveFileList('ARCHIVE', [['name' => 'cover.webp', 'size' => 99]], $context);

        $this->assertTrue($context->foundJPGSample);
    }

    #[Test]
    public function it_uses_stored_release_files_to_recover_an_nfo(): void
    {
        ReleaseFile::query()->create([
            'releases_id' => 12,
            'name' => 'release.nfo',
            'size' => 200,
        ]);

        $archive = Mockery::mock(ArchiveExtractionService::class);
        $archive->shouldReceive('extractSpecificFiles')
            ->once()
            ->with('ARCHIVE', ['release.nfo'], Mockery::type('string'))
            ->andReturn(['release.nfo' => 'NFO DATA']);

        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldReceive('download')->once()->andReturn([
            'success' => true,
            'data' => 'ARCHIVE',
            'groupUnavailable' => false,
            'error' => null,
        ]);
        $download->shouldReceive('getNNTP')->once()->andReturn(Mockery::mock(NNTPService::class));

        $manager = Mockery::mock(ReleaseFileManager::class);
        $manager->shouldReceive('processNfoFile')->once()->andReturnUsing(function (string $path, ReleaseProcessingContext $context): bool {
            $this->assertFileExists($path);
            $context->releaseHasNoNFO = false;

            return true;
        });

        $output = Mockery::mock(ConsoleOutputService::class);
        $output->shouldReceive('echoNfoFound')->once()->andReturnNull();

        $config = $this->makeConfig();
        $fallback = new ReleaseFilesArchiveFallback(
            $archive,
            Mockery::mock(MediaExtractionService::class),
            $download,
            $manager,
            $output
        );

        $context = $this->makeContext(12);
        $context->releaseGroupName = 'alt.binaries.test';
        $context->releaseHasNoNFO = true;
        $context->nzbContents = [['title' => 'archive.part01.rar', 'segments' => ['<1>', '<2>']]];
        $context->workPlan = (new AdditionalWorkPlanner($config))->plan(
            $context->nzbContents,
            $context->releaseGroupName,
        );

        $fallback->processNfoFromReleaseFiles($context);

        $this->assertFalse($context->releaseHasNoNFO);
    }

    #[Test]
    public function it_recovers_an_nfo_from_an_already_downloaded_archive_without_another_download(): void
    {
        $files = [['name' => 'release.nfo', 'size' => 200]];

        $archive = Mockery::mock(ArchiveExtractionService::class);
        $archive->shouldReceive('sortFilesWithNfoPriority')->once()->with($files)->andReturn($files);
        $archive->shouldReceive('isNfoFile')->once()->with('release.nfo')->andReturn(true);
        $archive->shouldReceive('extractSpecificFiles')
            ->once()
            ->with('ARCHIVE', ['release.nfo'], Mockery::type('string'))
            ->andReturn(['release.nfo' => 'NFO DATA']);

        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldNotReceive('download');
        $download->shouldReceive('getNNTP')->once()->andReturn(Mockery::mock(NNTPService::class));

        $manager = Mockery::mock(ReleaseFileManager::class);
        $manager->shouldReceive('processNfoFile')->once()->andReturnUsing(
            function (string $path, ReleaseProcessingContext $context): bool {
                $this->assertSame('NFO DATA', file_get_contents($path));
                $context->releaseHasNoNFO = false;

                return true;
            }
        );

        $output = Mockery::mock(ConsoleOutputService::class);
        $output->shouldReceive('echoNfoFound')->once()->andReturnNull();

        $fallback = new ReleaseFilesArchiveFallback(
            $archive,
            Mockery::mock(MediaExtractionService::class),
            $download,
            $manager,
            $output
        );

        $context = $this->makeContext();
        $context->releaseHasNoNFO = true;
        $this->assertTrue($context->rememberDownloadedArchive('release.part01.rar', 'ARCHIVE', $files));

        $fallback->processNfoFromDownloadedArchives($context);

        $this->assertFalse($context->releaseHasNoNFO);
    }

    #[Test]
    public function it_extracts_archive_candidates_in_one_batch(): void
    {
        $files = [
            ['name' => 'first.nfo', 'size' => 200],
            ['name' => 'second.nfo', 'size' => 200],
        ];

        $archive = Mockery::mock(ArchiveExtractionService::class);
        $archive->shouldReceive('sortFilesWithNfoPriority')->once()->with($files)->andReturn($files);
        $archive->shouldReceive('isNfoFile')->twice()->andReturn(true);
        $archive->shouldNotReceive('extractSpecificFile');
        $archive->shouldReceive('extractSpecificFiles')
            ->once()
            ->with('ARCHIVE', ['first.nfo', 'second.nfo'], Mockery::type('string'))
            ->andReturn([
                'first.nfo' => '',
                'second.nfo' => 'NFO DATA',
            ]);

        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldNotReceive('download');
        $download->shouldReceive('getNNTP')->once()->andReturn(Mockery::mock(NNTPService::class));

        $manager = Mockery::mock(ReleaseFileManager::class);
        $manager->shouldReceive('processNfoFile')->once()->andReturnUsing(
            function (string $path, ReleaseProcessingContext $context): bool {
                $this->assertSame('NFO DATA', file_get_contents($path));
                $context->releaseHasNoNFO = false;

                return true;
            }
        );

        $output = Mockery::mock(ConsoleOutputService::class);
        $output->shouldReceive('echoNfoFound')->once()->andReturnNull();

        $fallback = new ReleaseFilesArchiveFallback(
            $archive,
            Mockery::mock(MediaExtractionService::class),
            $download,
            $manager,
            $output,
        );

        $context = $this->makeContext();
        $context->releaseHasNoNFO = true;
        $this->assertTrue($context->rememberDownloadedArchive('release.part01.rar', 'ARCHIVE', $files));

        $fallback->processNfoFromDownloadedArchives($context);

        $this->assertFalse($context->releaseHasNoNFO);
    }

    #[Test]
    public function it_bounds_and_releases_retained_archive_payloads(): void
    {
        $context = $this->makeContext();
        $files = [['name' => 'release.nfo', 'size' => 10]];

        $this->assertTrue($context->rememberDownloadedArchive('first.rar', 'FIRST', $files));
        $this->assertTrue($context->rememberDownloadedArchive('second.rar', 'SECOND', $files));
        $this->assertFalse($context->rememberDownloadedArchive('third.rar', 'THIRD', $files));
        $this->assertCount(2, $context->downloadedArchives);

        $context->releaseDownloadedArchives();

        $this->assertSame([], $context->downloadedArchives);
    }

    private function makeContext(int $releaseId = 12): ReleaseProcessingContext
    {
        $context = new ReleaseProcessingContext(new Release([
            'id' => $releaseId,
            'guid' => 'guid-'.$releaseId,
            'groups_id' => 22,
        ]));
        $context->tmpPath = $this->tmpPath;

        return $context;
    }
}
