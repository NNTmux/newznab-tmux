<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ImageAssetProfile;
use App\Services\ReleaseImageService;
use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReleaseImageServiceTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'image.default' => 'gd',
            'image.output_format' => 'webp',
            'image.output_quality' => 82,
            'image.max_source_bytes' => 20 * 1024 * 1024,
            'image.max_source_pixels' => 40_000_000,
        ]);

        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'release-image-'.uniqid('', true).DIRECTORY_SEPARATOR;
        File::makeDirectory($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_it_converts_and_proportionally_resizes_a_local_image_to_webp(): void
    {
        $source = $this->createPng('source.png', 400, 200);

        $result = (new ReleaseImageService)->saveLocalImage(
            'cover',
            $source,
            $this->temporaryDirectory,
            ImageAssetProfile::MetadataCover,
        );

        $this->assertTrue($result->success);
        $this->assertSame($this->temporaryDirectory.'cover.webp', $result->path);
        $this->assertSame('image/webp', File::mimeType($result->path));
        $this->assertImageDimensions($result->path, 250, 125);
    }

    public function test_it_does_not_upscale_a_small_image(): void
    {
        $source = $this->createPng('small.png', 40, 20);

        $result = (new ReleaseImageService)->saveLocalImage(
            'cover',
            $source,
            $this->temporaryDirectory,
            ImageAssetProfile::MetadataCover,
        );

        $this->assertTrue($result->success);
        $this->assertImageDimensions($result->path, 40, 20);
    }

    public function test_compatibility_wrapper_preserves_custom_bounds_and_thumbnail_name(): void
    {
        $source = $this->createPng('source.png', 200, 100);
        $service = new ReleaseImageService;

        $result = $service->saveImage('cover', $source, $this->temporaryDirectory, 100, 100, true);

        $this->assertSame(1, $result);
        $this->assertImageDimensions($this->temporaryDirectory.'cover.webp', 100, 50);
        $this->assertImageDimensions($this->temporaryDirectory.'cover_thumb.webp', 100, 50);
    }

    public function test_it_rejects_invalid_basename_missing_invalid_and_oversized_inputs(): void
    {
        $service = new ReleaseImageService;
        $source = $this->createPng('source.png', 100, 50);
        $invalid = $this->temporaryDirectory.'invalid.png';
        File::put($invalid, 'not an image');

        $this->assertFalse($service->saveLocalImage('../cover', $source, $this->temporaryDirectory)->success);
        $this->assertFalse($service->saveLocalImage('missing', $this->temporaryDirectory.'missing.png', $this->temporaryDirectory)->success);
        $this->assertFalse($service->saveLocalImage('invalid', $invalid, $this->temporaryDirectory)->success);

        config(['image.max_source_bytes' => 2]);
        $this->assertFalse($service->saveLocalImage('large', $source, $this->temporaryDirectory)->success);
    }

    public function test_failed_processing_preserves_an_existing_asset(): void
    {
        $destination = $this->temporaryDirectory.'cover.webp';
        File::put($destination, 'existing-image');
        $invalid = $this->temporaryDirectory.'invalid.png';
        File::put($invalid, 'not an image');

        $result = (new ReleaseImageService)->saveLocalImage('cover', $invalid, $this->temporaryDirectory);

        $this->assertFalse($result->success);
        $this->assertSame('existing-image', File::get($destination));
        $this->assertSame([], File::glob($this->temporaryDirectory.'.cover.*.tmp.webp'));
    }

    public function test_it_fetches_a_public_remote_image_with_bounded_http_client(): void
    {
        $source = $this->createPng('remote.png', 60, 30);
        $bytes = File::get($source);
        Http::fake([
            'https://images.example.test/cover.png' => Http::response($bytes, 200, [
                'Content-Length' => (string) strlen($bytes),
            ]),
        ]);
        $service = new ReleaseImageService(static fn (string $host): array => ['93.184.216.34']);

        $result = $service->saveRemoteImage(
            'remote',
            'https://images.example.test/cover.png',
            $this->temporaryDirectory,
        );

        $this->assertTrue($result->success);
        $this->assertSame('image/webp', File::mimeType($result->path));
        Http::assertSentCount(1);
    }

    public function test_it_rejects_remote_hosts_that_resolve_to_private_addresses(): void
    {
        Http::fake();
        $service = new ReleaseImageService(static fn (string $host): array => ['127.0.0.1']);

        $result = $service->saveRemoteImage(
            'private',
            'http://internal.example.test/image.jpg?token=secret',
            $this->temporaryDirectory,
        );

        $this->assertFalse($result->success);
        Http::assertNothingSent();
    }

    public function test_release_paths_keep_the_existing_storage_relationship(): void
    {
        $service = new ReleaseImageService;

        $this->assertSame(storage_path('covers/preview/'), $service->imgSavePath);
        $this->assertSame(storage_path('covers/sample/'), $service->jpgSavePath);
        $this->assertSame(storage_path('covers/movies/'), $service->movieImgSavePath);
    }

    public function test_jpeg_output_remains_available_as_a_rollback_setting(): void
    {
        config(['image.output_format' => 'jpg']);
        $source = $this->createPng('rollback.png', 30, 15);

        $result = (new ReleaseImageService)->saveLocalImage('rollback', $source, $this->temporaryDirectory);

        $this->assertTrue($result->success);
        $this->assertSame($this->temporaryDirectory.'rollback.jpg', $result->path);
        $this->assertSame('image/jpeg', File::mimeType($result->path));
    }

    public function test_delete_removes_webp_and_legacy_release_images(): void
    {
        $service = new ReleaseImageService;
        $service->imgSavePath = $this->temporaryDirectory.'preview/';
        $service->jpgSavePath = $this->temporaryDirectory.'sample/';
        $guid = 'delete-'.uniqid();
        $files = [
            $service->imgSavePath.$guid.'_thumb.webp',
            $service->imgSavePath.$guid.'_thumb.jpg',
            $service->jpgSavePath.$guid.'_thumb.webp',
            $service->jpgSavePath.$guid.'_thumb.jpg',
        ];
        foreach ($files as $file) {
            File::ensureDirectoryExists(dirname($file));
            File::put($file, 'image');
        }

        $service->delete($guid);

        foreach ($files as $file) {
            $this->assertFileDoesNotExist($file);
        }
    }

    private function createPng(string $filename, int $width, int $height): string
    {
        $path = $this->temporaryDirectory.$filename;
        $image = imagecreatetruecolor($width, $height);

        $this->assertInstanceOf(GdImage::class, $image);

        $color = imagecolorallocate($image, 50, 100, 150);
        imagefill($image, 0, 0, $color);
        imagepng($image, $path);

        return $path;
    }

    private function assertImageDimensions(?string $path, int $width, int $height): void
    {
        $this->assertNotNull($path);
        $dimensions = getimagesize($path);

        $this->assertIsArray($dimensions);
        $this->assertSame($width, $dimensions[0]);
        $this->assertSame($height, $dimensions[1]);
    }
}
