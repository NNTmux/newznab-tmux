<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\Enums\VideoContainer;
use App\Services\AdditionalProcessing\Media\ContainerSplicer;
use App\Services\AdditionalProcessing\Media\FfmpegFrameGrabber;
use App\Services\AdditionalProcessing\Media\MediaToolFactory;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContainerSplicerTest extends TestCase
{
    use CreatesProcessingConfiguration;
    use GeneratesVideoFixtures;

    private const string CLUSTER = "\x1F\x43\xB6\x75";

    private string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpPath = sys_get_temp_dir().'/splicer-'.uniqid('', true).'/';
        (new Filesystem)->makeDirectory($this->tmpPath, 0777, true, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->tmpPath);
        parent::tearDown();
    }

    #[Test]
    public function it_gives_a_matroska_chunk_the_headers_before_the_first_cluster(): void
    {
        $head = "\x1A\x45\xDF\xA3HEADERS".self::CLUSTER.'OPENING';
        $mid = 'tail-of-a-block'.self::CLUSTER.'MIDDLE';

        $this->assertSame(
            "\x1A\x45\xDF\xA3HEADERS".self::CLUSTER.'MIDDLE',
            (new ContainerSplicer)->splice($head, $mid, VideoContainer::Matroska),
        );
        $this->assertNull((new ContainerSplicer)->splice($head, 'no cluster here', VideoContainer::Matroska));
    }

    #[Test]
    public function it_starts_a_transport_stream_chunk_at_a_packet_boundary(): void
    {
        $packets = str_repeat("\x47".str_repeat("\x11", 187), 6);

        $this->assertSame($packets, (new ContainerSplicer)->splice('', 'junk!'.$packets, VideoContainer::MpegTransport));
    }

    #[Test]
    public function it_does_not_splice_containers_that_keep_their_index_elsewhere(): void
    {
        $this->assertNull((new ContainerSplicer)->splice('head', 'mid', VideoContainer::Mp4));
        $this->assertFalse(VideoContainer::Mp4->canResumeMidStream());
    }

    #[Test]
    public function it_recognises_containers_by_signature_and_by_name(): void
    {
        $this->assertSame(VideoContainer::Matroska, VideoContainer::detect("\x1A\x45\xDF\xA3".str_repeat("\0", 20)));
        $this->assertSame(VideoContainer::Avi, VideoContainer::detect("RIFF\0\0\0\0AVI LIST"));
        $this->assertSame(VideoContainer::Mp4, VideoContainer::detect("\0\0\0\x20ftypisom\0\0"));
        $this->assertSame(VideoContainer::MpegTransport, VideoContainer::detect(str_repeat("\x47".str_repeat("\0", 187), 5)));
        $this->assertNull(VideoContainer::detect('#EXTM3U playlist text'));
        $this->assertSame(VideoContainer::Matroska, VideoContainer::fromFileName('"Movie.2024.mkv" yEnc (1/9)'));
        $this->assertSame(VideoContainer::MpegTransport, VideoContainer::fromFileName('Show.m2ts'));
        $this->assertNull(VideoContainer::fromFileName('Movie.part01.rar'));
    }

    /**
     * @param  list<string>  $encoder
     */
    #[Test]
    #[DataProvider('resumableVideos')]
    public function it_makes_a_middle_chunk_decodable(string $extension, VideoContainer $container, array $encoder): void
    {
        $video = (string) file_get_contents(
            $this->generateVideo($this->tmpPath.'movie.'.$extension, 'testsrc', 20, $encoder),
        );
        $head = substr($video, 0, intdiv(strlen($video), 5));
        $mid = substr($video, intdiv(strlen($video), 2), intdiv(strlen($video), 4));

        $spliced = (new ContainerSplicer)->splice($head, $mid, $container);
        $this->assertNotNull($spliced);
        file_put_contents($this->tmpPath.'mid.'.$extension, $spliced);

        $config = $this->makeConfig(['ffmpegPath' => '/usr/bin/ffmpeg', 'ffprobePath' => '/usr/bin/ffprobe']);
        $frames = (new FfmpegFrameGrabber(new MediaToolFactory($config)))
            ->grab($this->tmpPath.'mid.'.$extension, $container, 0.0, 0.0, 1, $this->tmpPath);

        $this->assertCount(1, $frames);
    }

    /**
     * @return array<string, array{string, VideoContainer, list<string>}>
     */
    public static function resumableVideos(): array
    {
        return [
            'Matroska' => ['mkv', VideoContainer::Matroska, ['-c:v', 'libx264', '-g', '10', '-pix_fmt', 'yuv420p']],
            'transport stream' => ['ts', VideoContainer::MpegTransport, ['-c:v', 'libx264', '-g', '10', '-pix_fmt', 'yuv420p']],
            'program stream' => ['mpg', VideoContainer::MpegProgram, ['-c:v', 'mpeg2video', '-g', '10']],
        ];
    }
}
