<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\Media\FrameQualityScorer;
use App\Services\AdditionalProcessing\Media\ImageCandidateRanker;
use App\Services\AdditionalProcessing\Media\ImageInspector;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ImageSelectionTest extends TestCase
{
    private string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpPath = sys_get_temp_dir().'/image-selection-'.uniqid('', true).'/';
        (new Filesystem)->makeDirectory($this->tmpPath, 0777, true, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->tmpPath);
        parent::tearDown();
    }

    #[Test]
    public function it_ranks_screen_sheets_before_covers_before_scans_of_backs_and_discs(): void
    {
        $ranked = ImageCandidateRanker::sort(
            [
                ['"Movie.XXX.back.jpg" yEnc (1/3)', 300],
                ['"Movie.XXX.jpg" yEnc (1/3)', 900],
                ['"Movie.XXX.cover.jpg" yEnc (1/3)', 200],
                ['"Movie.XXX.CD1.screens.jpg" yEnc (1/3)', 100],
                ['Movie/proof.jpg', 999],
            ],
            static fn (array $image): string => $image[0],
            static fn (array $image): int => $image[1],
        );

        $this->assertSame(
            ['"Movie.XXX.CD1.screens.jpg" yEnc (1/3)', '"Movie.XXX.cover.jpg" yEnc (1/3)', '"Movie.XXX.jpg" yEnc (1/3)', 'Movie/proof.jpg', '"Movie.XXX.back.jpg" yEnc (1/3)'],
            array_column($ranked, 0),
        );
    }

    #[Test]
    public function it_accepts_only_complete_detailed_images_of_a_useful_size(): void
    {
        $inspector = new ImageInspector(new FrameQualityScorer(12, 24, 232), 12);
        $detailed = $this->jpeg('detailed.jpg', 400, 300, static fn (int $x, int $y): int => (($x * 7 + $y * 13) % 255) * 0x010101);

        $this->assertTrue($inspector->isUsable($detailed));

        file_put_contents($this->tmpPath.'cut.jpg', substr((string) file_get_contents($detailed), 0, -2000));
        $this->assertFalse($inspector->isUsable($this->tmpPath.'cut.jpg'), 'a download cut short');
        $this->assertFalse($inspector->isUsable($this->jpeg('blank.jpg', 400, 300, static fn (): int => 0)), 'a blank image');
        $this->assertFalse($inspector->isUsable($this->jpeg('icon.jpg', 64, 64, static fn (int $x): int => $x * 0x030303)), 'an icon');
        file_put_contents($this->tmpPath.'text.jpg', 'not an image at all');
        $this->assertFalse($inspector->isUsable($this->tmpPath.'text.jpg'));
    }

    /**
     * @param  callable(int, int): int  $color
     */
    private function jpeg(string $name, int $width, int $height, callable $color): string
    {
        $image = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                imagesetpixel($image, $x, $y, $color($x, $y));
            }
        }
        imagejpeg($image, $this->tmpPath.$name, 90);

        return $this->tmpPath.$name;
    }
}
