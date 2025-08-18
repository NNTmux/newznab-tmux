<?php

namespace Tests\Unit;

use App\Models\Release as ReleaseModel;
use App\Services\MediaProcessingService;
use Blacklight\Categorize;
use Blacklight\ElasticSearchSiteSearch;
use Blacklight\ManticoreSearch;
use Blacklight\ReleaseExtra;
use Blacklight\ReleaseImage;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use Mhor\MediaInfo\MediaInfo;
use Mockery;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;

class MediaProcessingServiceTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        // Minimal Facade container for File facade
        $container = new Container;
        $container->instance('files', new Filesystem);
        Facade::setFacadeApplication($container);

        $this->tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'mps_'.uniqid().DIRECTORY_SEPARATOR;
        \Illuminate\Support\Facades\File::makeDirectory($this->tmpDir, 0777, true, true);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tmpDir)) {
            File::deleteDirectory($this->tmpDir);
        }
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(
        ?FFMpeg $ffmpeg = null,
        ?FFProbe $ffprobe = null,
        ?MediaInfo $mediaInfo = null,
        ?ReleaseImage $releaseImage = null,
        ?ReleaseExtra $releaseExtra = null,
        ?ManticoreSearch $manticore = null,
        ?ElasticSearchSiteSearch $elastic = null,
        ?Categorize $categorize = null
    ): MediaProcessingService {
        $ffmpeg ??= Mockery::mock(FFMpeg::class);
        $ffprobe ??= Mockery::mock(FFProbe::class);
        $mediaInfo ??= Mockery::mock(MediaInfo::class);
        $releaseImage ??= Mockery::mock(ReleaseImage::class);
        $releaseExtra ??= Mockery::mock(ReleaseExtra::class);
        $manticore ??= Mockery::mock(ManticoreSearch::class);
        $elastic ??= Mockery::mock(ElasticSearchSiteSearch::class);
        $categorize ??= Mockery::mock(Categorize::class);

        return new MediaProcessingService($ffmpeg, $ffprobe, $mediaInfo, $releaseImage, $releaseExtra, $manticore, $elastic, $categorize);
    }

    #[WithoutErrorHandler]
    public function test_get_video_time_parses_duration_string(): void
    {
        $ffprobe = Mockery::mock(FFProbe::class);
        $ffprobe->shouldReceive('isValid')->once()->andReturn(true);
        $format = new class
        {
            public function get($key)
            {
                return 'time=00:05.10 bitrate=800k';
            }
        };
        $ffprobe->shouldReceive('format')->once()->andReturn($format);
        $svc = $this->makeService(null, $ffprobe);
        $out = $svc->getVideoTime($this->tmpDir.'vid.avi');
        $this->assertSame('00:00:05.09', $out);
    }

    #[WithoutErrorHandler]
    public function test_create_sample_image_returns_true_when_saved(): void
    {
        $videoFile = $this->tmpDir.'video.avi';
        File::put($videoFile, 'fake');

        $ffprobe = Mockery::mock(FFProbe::class);
        $ffprobe->shouldReceive('isValid')->andReturn(true);
        $format = new class
        {
            public function get($key)
            {
                return 'time=00:03.10 bitrate=800k';
            }
        };
        $ffprobe->shouldReceive('format')->andReturn($format);

        $frameMock = new class
        {
            public function save($path)
            {
                \file_put_contents($path, 'x');
            }
        };
        $openMock = new class($frameMock)
        {
            public function __construct(private $frame) {}

            public function frame($tc)
            {
                return $this->frame;
            }
        };
        $ffmpeg = Mockery::mock(FFMpeg::class);
        $ffmpeg->shouldReceive('open')->andReturn($openMock);

        $releaseImage = Mockery::mock(ReleaseImage::class);
        $releaseImage->imgSavePath = $this->tmpDir;
        $releaseImage->shouldReceive('saveImage')->andReturn(1);

        $svc = $this->makeService($ffmpeg, $ffprobe, null, $releaseImage);
        $ok = $svc->createSampleImage('guid123', $videoFile, $this->tmpDir, true);
        $this->assertTrue($ok);
    }

    #[WithoutErrorHandler]
    public function test_add_video_media_info_false_if_file_missing(): void
    {
        $svc = $this->makeService();
        $this->assertFalse($svc->addVideoMediaInfo(1, $this->tmpDir.'nope.avi'));
    }

    #[WithoutErrorHandler]
    public function test_add_audio_info_and_sample_returns_true_when_disabled(): void
    {
        $svc = $this->makeService();
        $release = new ReleaseModel;
        $release->id = 1;
        $release->guid = 'g';
        $release->predb_id = 0;
        $release->categories_id = 0;
        $release->groups_id = 0;
        $release->fromname = '';
        $res = $svc->addAudioInfoAndSample($release, $this->tmpDir.'nofile.mp3', 'MP3', false, false, $this->tmpDir);
        $this->assertTrue($res['info']);
        $this->assertTrue($res['sample']);
    }
}
