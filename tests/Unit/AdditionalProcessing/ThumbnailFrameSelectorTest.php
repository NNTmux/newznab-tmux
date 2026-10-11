<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\DTO\MediaSourceHints;
use App\Services\AdditionalProcessing\Enums\VideoContainer;
use App\Services\AdditionalProcessing\Media\FfmpegFrameGrabber;
use App\Services\AdditionalProcessing\Media\FrameQualityScorer;
use App\Services\AdditionalProcessing\Media\MediaToolFactory;
use App\Services\AdditionalProcessing\Media\ThumbnailFrameSelector;
use App\Services\AdditionalProcessing\Media\VideoFrameGrabber;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ThumbnailFrameSelectorTest extends TestCase
{
    use CreatesProcessingConfiguration;
    use GeneratesVideoFixtures;

    private string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpPath = sys_get_temp_dir().'/frame-selector-'.uniqid('', true).'/';
        (new Filesystem)->makeDirectory($this->tmpPath, 0777, true, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->tmpPath);
        parent::tearDown();
    }

    #[Test]
    public function it_rejects_blank_frames_and_prefers_normal_brightness(): void
    {
        $scorer = new FrameQualityScorer(12, 24, 232);
        $black = $this->image('black.jpg', static fn (): int => 0);
        $grey = $this->image('grey.jpg', static fn (): int => 0x808080);
        $dark = $this->image('dark.jpg', static fn (int $x, int $y): int => ($x + $y) % 2 === 0 ? 0x000000 : 0x2A2A2A);
        $detailed = $this->image('detailed.jpg', static fn (int $x, int $y): int => (($x * 7 + $y * 13) % 255) * 0x010101);

        $this->assertNull($scorer->best([$black, $grey]));
        $this->assertSame($dark, $scorer->best([$black, $dark]), 'a dark but detailed frame beats none');
        $this->assertSame($detailed, $scorer->best([$dark, $black, $detailed]));
    }

    #[Test]
    public function it_starts_a_quarter_into_the_downloaded_part_of_the_video(): void
    {
        $video = $this->tmpPath.'video.mkv';
        file_put_contents($video, str_repeat('x', 1000));
        $selector = $this->selector(new FakeFrameGrabber(duration: 100.0, bitRate: 8000));

        // The four frames share what is left after the first quarter.
        $this->assertSame([25.0, 18.75], $selector->window($video, null, MediaSourceHints::complete()));
        // A tenth of the file, less a margin for headers: 100 s × 0.1 × 0.85 = 8.5 s playable.
        $this->assertSame([2.125, 1.594], $selector->window($video, null, MediaSourceHints::head(10000, 1000)));
        // No real size: 1000 bytes at 8000 bit/s play for one second.
        $this->assertSame([0.213, 0.159], $selector->window($video, null, MediaSourceHints::head(null, 1000)));
        $this->assertSame([0.0, 0.0], $selector->window($video, null, MediaSourceHints::mid()));
    }

    #[Test]
    public function it_tries_again_from_the_start_when_every_later_frame_is_blank(): void
    {
        $video = $this->tmpPath.'video.mkv';
        file_put_contents($video, "\x1A\x45\xDF\xA3".str_repeat("\0", 60));
        $black = $this->image('black.jpg', static fn (): int => 0);
        $detailed = $this->image('detailed.jpg', static fn (int $x, int $y): int => (($x * 7 + $y * 13) % 255) * 0x010101);
        $grabber = new FakeFrameGrabber(duration: 40.0, frames: [[$black], [$detailed]]);

        $frame = $this->selector($grabber)->select($video, $this->tmpPath, MediaSourceHints::complete());

        $this->assertSame($detailed, $frame);
        $this->assertSame([[VideoContainer::Matroska, 10.0, 7.5], [VideoContainer::Matroska, 0.0, 0.0]], $grabber->grabs);
    }

    #[Test]
    public function it_refuses_playlists_disguised_as_video(): void
    {
        $video = $this->tmpPath.'video.mkv';
        file_put_contents($video, "#EXTM3U\n#EXT-X-MEDIA-SEQUENCE:0\nfile:///etc/passwd\n");
        $grabber = new FakeFrameGrabber;

        $this->assertNull($this->selector($grabber)->select($video, $this->tmpPath));
        $this->assertSame([], $grabber->grabs);
    }

    #[Test]
    public function it_takes_a_picture_frame_instead_of_a_black_opening(): void
    {
        $black = $this->generateVideo($this->tmpPath.'black.mkv', 'color=c=black', 3);
        $pattern = $this->generateVideo($this->tmpPath.'pattern.mkv', 'testsrc', 3);
        $list = $this->tmpPath.'list.txt';
        file_put_contents($list, "file '".$black."'\nfile '".$pattern."'\n");
        $video = $this->tmpPath.'opening.mkv';
        exec('ffmpeg -v error -y -f concat -safe 0 -i '.escapeshellarg($list).' -c copy '.escapeshellarg($video));

        $config = $this->makeConfig(['ffmpegPath' => '/usr/bin/ffmpeg', 'ffprobePath' => '/usr/bin/ffprobe']);
        $selector = new ThumbnailFrameSelector($config, new FfmpegFrameGrabber(new MediaToolFactory($config)), new FrameQualityScorer(12, 24, 232));
        $frame = $selector->select($video, $this->tmpPath, MediaSourceHints::head(null, (int) filesize($video)));

        $this->assertNotNull($frame);
        $this->assertGreaterThan(12, (new FrameQualityScorer(12, 24, 232))->measure($frame)['stddev'] ?? 0);
    }

    private function selector(VideoFrameGrabber $grabber): ThumbnailFrameSelector
    {
        return new ThumbnailFrameSelector($this->makeConfig(), $grabber, new FrameQualityScorer(12, 24, 232));
    }

    /**
     * @param  callable(int, int): int  $color
     */
    private function image(string $name, callable $color): string
    {
        $image = imagecreatetruecolor(64, 48);
        for ($y = 0; $y < 48; $y++) {
            for ($x = 0; $x < 64; $x++) {
                imagesetpixel($image, $x, $y, $color($x, $y));
            }
        }
        imagepng($image, $this->tmpPath.$name);

        return $this->tmpPath.$name;
    }
}

final class FakeFrameGrabber implements VideoFrameGrabber
{
    /** @var list<array{0: VideoContainer|null, 1: float, 2: float}> */
    public array $grabs = [];

    /**
     * @param  list<list<string>>  $frames  frames returned by successive grabs
     */
    public function __construct(
        private readonly ?float $duration = null,
        private readonly ?int $bitRate = null,
        private array $frames = [],
    ) {}

    public function grab(string $videoPath, ?VideoContainer $container, float $from, float $spacing, int $count, string $outputDirectory): array
    {
        $this->grabs[] = [$container, $from, $spacing];

        return array_shift($this->frames) ?? [];
    }

    public function probe(string $videoPath, ?VideoContainer $container): array
    {
        return ['duration' => $this->duration, 'bitRate' => $this->bitRate];
    }
}
