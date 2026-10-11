<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Enums\ImageAssetProfile;
use App\Services\AdditionalProcessing\DTO\MediaSourceHints;
use App\Services\AdditionalProcessing\Media\MediaToolFactory;
use App\Services\AdditionalProcessing\MediaExtractionService;
use App\Services\Categorization\CategorizationService;
use App\Services\ReleaseExtraService;
use App\Services\ReleaseImageService;
use App\Support\Data\ImageProcessingResult;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MediaExtractionServiceTest extends TestCase
{
    use CreatesProcessingConfiguration;

    private string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Application(sys_get_temp_dir());
        $container->instance('files', new Filesystem);
        Facade::setFacadeApplication($container);

        $this->tmpPath = sys_get_temp_dir().'/additional-media-'.uniqid('', true).'/';
        (new Filesystem)->makeDirectory($this->tmpPath, 0777, true, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->tmpPath);
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_recognizes_jpeg_png_and_webp_samples_by_signature(): void
    {
        $fixtures = [
            'sample.jpg' => "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00",
            'sample.png' => "\x89PNG\r\n\x1A\n\x00\x00\x00\rIHDR",
            'sample.webp' => "RIFF\x1A\x00\x00\x00WEBPVP8 ",
        ];
        $service = new MediaExtractionService(
            $this->makeConfig(),
            Mockery::mock(ReleaseImageService::class),
            Mockery::mock(ReleaseExtraService::class),
            Mockery::mock(CategorizationService::class),
        );

        foreach ($fixtures as $filename => $contents) {
            $path = $this->tmpPath.$filename;
            file_put_contents($path, $contents);

            $this->assertTrue($service->isValidImage($path), $filename.' should be recognized');
        }
    }

    #[Test]
    public function it_hands_the_configured_binaries_and_timeout_to_ffmpeg(): void
    {
        $tools = new MediaToolFactory($this->makeConfig([
            'ffmpegPath' => '/opt/ffmpeg/bin/ffmpeg',
            'ffprobePath' => '/opt/ffmpeg/bin/ffprobe',
            'mediaFfmpegTimeout' => 17,
        ]));

        $this->assertSame([
            'timeout' => 17,
            'ffmpeg.binaries' => '/opt/ffmpeg/bin/ffmpeg',
            'ffprobe.binaries' => '/opt/ffmpeg/bin/ffprobe',
        ], $tools->driverConfiguration());
    }

    #[Test]
    public function it_reads_the_duration_of_a_video(): void
    {
        $video = $this->makeVideo('clip.mkv', 4);

        $this->assertEqualsWithDelta(4.0, $this->mediaService()->getMediaDuration($video), 0.2);
        $this->assertNull($this->mediaService()->getMediaDuration($this->tmpPath.'missing.mkv'));
    }

    #[Test]
    public function it_takes_a_thumbnail_from_a_download_shorter_than_three_seconds(): void
    {
        $video = $this->makeVideo('short.mkv', 2);
        $releaseImage = Mockery::mock(ReleaseImageService::class);
        $releaseImage->imgSavePath = '/covers/preview/';
        $releaseImage->shouldReceive('saveLocalImage')
            ->once()
            ->withArgs(fn (string $name, string $source, string $directory, ImageAssetProfile $profile): bool => $name === 'guid_thumb'
                && filesize($source) > 0
                && $directory === '/covers/preview/'
                && $profile === ImageAssetProfile::Preview)
            ->andReturn(ImageProcessingResult::success('/covers/preview/guid_thumb.webp', 320, 240, 'image/webp'));

        $this->assertTrue($this->mediaService($releaseImage)->getSample($video, $this->tmpPath, 'guid'));
    }

    #[Test]
    public function it_cuts_thirty_seconds_from_past_the_intro_of_a_long_track(): void
    {
        $this->requireFfmpeg();
        $track = $this->tmpPath.'track.wav';
        exec('ffmpeg -v error -y -f lavfi -i '.escapeshellarg('sine=frequency=440:duration=70').' '.escapeshellarg($track));
        $service = $this->mediaService();

        $this->assertTrue($service->saveAudioClip($track, $this->tmpPath.'clip.ogg'));
        $this->assertEqualsWithDelta(30.0, $service->getMediaDuration($this->tmpPath.'clip.ogg'), 0.5);
    }

    #[Test]
    public function it_makes_no_video_clip_from_the_middle_of_a_file(): void
    {
        $video = $this->tmpPath.'mid.mkv';
        file_put_contents($video, 'not decoded');

        $this->assertFalse($this->mediaService()->getVideo($video, $this->tmpPath, 'guid', MediaSourceHints::mid()));
    }

    private function mediaService(?ReleaseImageService $releaseImage = null): MediaExtractionService
    {
        return new MediaExtractionService(
            $this->makeConfig([
                'processThumbnails' => true,
                'processVideo' => true,
                'ffmpegPath' => '/usr/bin/ffmpeg',
                'ffprobePath' => '/usr/bin/ffprobe',
            ]),
            $releaseImage ?? Mockery::mock(ReleaseImageService::class),
            Mockery::mock(ReleaseExtraService::class),
            Mockery::mock(CategorizationService::class),
        );
    }

    private function makeVideo(string $name, int $seconds): string
    {
        $this->requireFfmpeg();
        $path = $this->tmpPath.$name;
        exec('ffmpeg -v error -y -f lavfi -i '.escapeshellarg('testsrc=size=320x240:rate=10:duration='.$seconds)
            .' -c:v mjpeg '.escapeshellarg($path));

        return $path;
    }

    private function requireFfmpeg(): void
    {
        if (! is_executable('/usr/bin/ffmpeg') || ! is_executable('/usr/bin/ffprobe')) {
            $this->markTestSkipped('ffmpeg and ffprobe are not installed.');
        }
    }
}
