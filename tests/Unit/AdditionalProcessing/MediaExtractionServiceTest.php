<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\MediaExtractionService;
use App\Services\Categorization\CategorizationService;
use App\Services\ReleaseExtraService;
use App\Services\ReleaseImageService;
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
}
