<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Models\Release;
use App\Services\AdditionalProcessing\ConsoleOutputService;
use App\Services\AdditionalProcessing\DTO\AdditionalWorkPlan;
use App\Services\AdditionalProcessing\DTO\ArchivedMediaEntry;
use App\Services\AdditionalProcessing\DTO\MediaSourceHints;
use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\AdditionalProcessing\Enums\SourcePosition;
use App\Services\AdditionalProcessing\Enums\VideoContainer;
use App\Services\AdditionalProcessing\Media\ArchivedMediaSlicer;
use App\Services\AdditionalProcessing\Media\MediaArticleFetcher;
use App\Services\AdditionalProcessing\Media\MediaSourceStage;
use App\Services\AdditionalProcessing\MediaExtractionService;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\AdditionalProcessing\UsenetDownloadService;
use App\Services\Yenc\YencPart;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MediaSourceStageTest extends TestCase
{
    use CreatesProcessingConfiguration;

    private const string MKV_HEAD = "\x1A\x45\xDF\xA3".'HEADERS'."\x1F\x43\xB6\x75".'OPENING';

    private string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Application(sys_get_temp_dir());
        $container->instance('files', new Filesystem);
        Facade::setFacadeApplication($container);

        $this->tmpPath = sys_get_temp_dir().'/media-stage-'.uniqid('', true).'/';
        (new Filesystem)->makeDirectory($this->tmpPath, 0777, true, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->tmpPath);
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_completes_an_archived_sample_from_later_articles_of_its_volume(): void
    {
        $context = $this->context([['title' => 'movie.part01.rar', 'segments' => ['<v1>', '<v2>', '<v3>'], 'size' => 300]]);
        $context->rememberArchivedMedia(new ArchivedMediaEntry(
            name: 'Sample/movie-sample.mkv',
            realSize: strlen(self::MKV_HEAD),
            sourceIndex: 0,
            headBytes: substr(self::MKV_HEAD, 0, 10),
            rangeStart: 90,
            inVolumeEnd: 90 + strlen(self::MKV_HEAD) - 1,
            volumeBytesDownloaded: 100,
            volumeSegmentsDownloaded: 1,
            isSample: true,
            container: VideoContainer::Matroska,
        ));

        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldReceive('downloadArticle')->once()->with(DownloadKind::MediaSource, '<v2>', 'alt.binaries.test', 1)->andReturn(
            $this->article(substr(self::MKV_HEAD, 10).str_repeat('Z', 100), 100),
        );

        $media = Mockery::mock(MediaExtractionService::class);
        $media->shouldReceive('getSample')->once()->withArgs(
            fn (string $path, string $tmp, string $guid, MediaSourceHints $hints): bool => file_get_contents($path) === self::MKV_HEAD
                && $hints->position === SourcePosition::Complete,
        )->andReturnTrue();
        $media->shouldReceive('getMediaInfo')->once()->andReturnTrue();
        $media->shouldReceive('getVideo')->once()->andReturnTrue();

        $stage = $this->stage($download, $media);
        $this->assertTrue($stage->hasWork($context));
        $stage->run($context);

        $this->assertTrue($context->foundSample);
        $this->assertTrue($context->foundVideo);
        $this->assertSame([], glob($this->tmpPath.'media_*'), 'the work directory is removed');
    }

    #[Test]
    public function it_takes_a_preview_from_the_start_of_a_full_length_video_when_there_is_no_sample(): void
    {
        $context = $this->context(
            [['title' => '"Movie.2024.mkv" yEnc (1/40)', 'segments' => ['<m1>', '<m2>'], 'size' => 2000]],
            mainVideos: [0],
        );

        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldReceive('downloadArticle')->once()->with(DownloadKind::MediaSource, '<m1>', 'alt.binaries.test', 1)
            ->andReturn($this->article(self::MKV_HEAD, 0));
        $download->shouldReceive('downloadArticle')->once()->with(DownloadKind::MediaSource, '<m2>', 'alt.binaries.test', 1)
            ->andReturn($this->article('MORE', strlen(self::MKV_HEAD)));

        $media = Mockery::mock(MediaExtractionService::class);
        $media->shouldReceive('getSample')->once()->withArgs(
            fn (string $path, string $tmp, string $guid, MediaSourceHints $hints): bool => str_ends_with($path, '.mkv')
                && $hints->position === SourcePosition::Head
                && $hints->realSize === 1940,
        )->andReturnTrue();
        $media->shouldReceive('getMediaInfo')->once()->andReturnFalse();
        $media->shouldNotReceive('getVideo');

        $this->stage($download, $media, ['mediaMainVideoFallback' => true])->run($context);

        $this->assertTrue($context->foundSample);
    }

    #[Test]
    public function it_tries_the_middle_of_the_video_when_its_opening_gives_no_usable_frame(): void
    {
        $segments = ['<m1>', '<m2>', '<m3>', '<m4>', '<m5>', '<m6>'];
        $context = $this->context(
            [['title' => '"Movie.2024.mkv" yEnc (1/6)', 'segments' => $segments, 'size' => 6000]],
            mainVideos: [0],
        );

        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldReceive('downloadArticle')->once()->with(DownloadKind::MediaSource, '<m1>', 'alt.binaries.test', 1)
            ->andReturn($this->article(self::MKV_HEAD, 0));
        $download->shouldReceive('downloadArticle')->once()->with(DownloadKind::MediaSource, '<m2>', 'alt.binaries.test', 1)
            ->andReturn(['success' => false, 'data' => null, 'part' => null, 'groupUnavailable' => false, 'error' => 'missing']);
        $download->shouldReceive('downloadArticle')->once()->with(DownloadKind::MediaSource, '<m4>', 'alt.binaries.test', 1)
            ->andReturn($this->article('block-end'."\x1F\x43\xB6\x75".'MIDDLE', 3000));
        $download->shouldReceive('downloadArticle')->once()->with(DownloadKind::MediaSource, '<m5>', 'alt.binaries.test', 1)
            ->andReturn(['success' => false, 'data' => null, 'part' => null, 'groupUnavailable' => false, 'error' => 'missing']);

        $media = Mockery::mock(MediaExtractionService::class);
        $media->shouldReceive('getSample')->once()->withArgs(
            fn (string $path, string $tmp, string $guid, MediaSourceHints $hints): bool => $hints->position === SourcePosition::Head,
        )->andReturnFalse();
        $media->shouldReceive('getMediaInfo')->once()->andReturnFalse();
        $media->shouldReceive('getSample')->once()->withArgs(
            fn (string $path, string $tmp, string $guid, MediaSourceHints $hints): bool => $hints->position === SourcePosition::Mid
                && file_get_contents($path) === "\x1A\x45\xDF\xA3".'HEADERS'."\x1F\x43\xB6\x75".'MIDDLE',
        )->andReturnTrue();

        $this->stage($download, $media, ['mediaMainVideoFallback' => true, 'mediaMidSources' => true])->run($context);

        $this->assertTrue($context->foundSample);
    }

    #[Test]
    public function it_has_nothing_to_do_once_a_preview_exists_or_the_release_is_passworded(): void
    {
        $stage = $this->stage(Mockery::mock(UsenetDownloadService::class), Mockery::mock(MediaExtractionService::class), ['mediaMainVideoFallback' => true]);
        $context = $this->context([['title' => '"Movie.mkv" yEnc (1/2)', 'segments' => ['<m1>'], 'size' => 2000]], mainVideos: [0]);

        $this->assertTrue($stage->hasWork($context));
        $context->foundSample = true;
        $this->assertFalse($stage->hasWork($context));
        $context->foundSample = false;
        $context->releaseHasPassword = true;
        $this->assertFalse($stage->hasWork($context));
    }

    /**
     * @param  list<array<string, mixed>>  $nzb
     * @param  list<int>  $mainVideos
     */
    private function context(array $nzb, array $mainVideos = []): ReleaseProcessingContext
    {
        $context = new ReleaseProcessingContext(new Release(['id' => 1, 'guid' => 'guid-1']));
        $context->tmpPath = $this->tmpPath;
        $context->releaseGroupName = 'alt.binaries.test';
        $context->nzbContents = $nzb;
        $context->workPlan = new AdditionalWorkPlan(mainVideoSourceIndexes: $mainVideos);

        return $context;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function stage(UsenetDownloadService $download, MediaExtractionService $media, array $config = []): MediaSourceStage
    {
        $config = $this->makeConfig(['processThumbnails' => true, 'mediaArchiveSlicing' => true, ...$config]);

        return new MediaSourceStage(
            $config,
            $media,
            new MediaArticleFetcher($download),
            new ArchivedMediaSlicer($config),
            Mockery::mock(ConsoleOutputService::class)->shouldIgnoreMissing(),
        );
    }

    /**
     * @return array{success: true, data: string, part: YencPart, groupUnavailable: false, error: null}
     */
    private function article(string $data, int $offset): array
    {
        return [
            'success' => true,
            'data' => $data,
            'part' => new YencPart('movie', 100000, null, $offset + 1, $offset + strlen($data)),
            'groupUnavailable' => false,
            'error' => null,
        ];
    }
}
