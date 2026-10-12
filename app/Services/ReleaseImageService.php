<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ImageAssetProfile;
use App\Support\Data\ImageProcessingResult;
use Closure;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Image\Image as LaravelImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetch, normalize, store, locate, and delete release images.
 *
 * Destination directories are deliberately supplied by callers. This keeps
 * the existing storage_path/public_path/COVERS_PATH ownership unchanged.
 */
class ReleaseImageService
{
    /** Path to save ogg audio samples. */
    public string $audSavePath;

    /** Path to save video preview pictures. */
    public string $imgSavePath;

    /** Path to save downloaded sample pictures. */
    public string $jpgSavePath;

    /** Path to save movie covers. */
    public string $movieImgSavePath;

    /** Path to save video samples. */
    public string $vidSavePath;

    /** @var Closure(string): list<string> */
    private Closure $hostResolver;

    /**
     * @param  (Closure(string): list<string>)|null  $hostResolver
     */
    public function __construct(?Closure $hostResolver = null)
    {
        $this->audSavePath = storage_path('covers/audiosample/');
        $this->imgSavePath = storage_path('covers/preview/');
        $this->jpgSavePath = storage_path('covers/sample/');
        $this->movieImgSavePath = storage_path('covers/movies/');
        $this->vidSavePath = storage_path('covers/video/');
        $this->hostResolver = $hostResolver ?? $this->defaultHostResolver(...);
    }

    public function saveRemoteImage(
        string $imgName,
        string $url,
        string $destinationDirectory,
        ImageAssetProfile $profile = ImageAssetProfile::Original,
    ): ImageProcessingResult {
        $fetched = $this->fetchRemoteBytes($url);

        if (! $fetched['success']) {
            return ImageProcessingResult::failure($fetched['reason']);
        }

        return $this->processBytes(
            $imgName,
            $fetched['contents'],
            $destinationDirectory,
            $profile->maxWidth(),
            $profile->maxHeight(),
        );
    }

    public function saveLocalImage(
        string $imgName,
        string $sourcePath,
        string $destinationDirectory,
        ImageAssetProfile $profile = ImageAssetProfile::Original,
    ): ImageProcessingResult {
        if (! File::isFile($sourcePath) || ! File::isReadable($sourcePath)) {
            return ImageProcessingResult::failure('Image source is not a readable local file.');
        }

        $maxBytes = $this->maxSourceBytes();
        $sourceSize = File::size($sourcePath);
        if ($sourceSize === false || $sourceSize <= 0 || $sourceSize > $maxBytes) {
            return ImageProcessingResult::failure('Image source size is invalid or exceeds the configured limit.');
        }

        try {
            return $this->processBytes(
                $imgName,
                File::get($sourcePath),
                $destinationDirectory,
                $profile->maxWidth(),
                $profile->maxHeight(),
            );
        } catch (Throwable $e) {
            Log::debug('Unable to read local image source.', [
                'path' => $sourcePath,
                'error' => $e->getMessage(),
            ]);

            return ImageProcessingResult::failure('Unable to read the local image source.');
        }
    }

    public function saveUploadedImage(
        string $imgName,
        UploadedFile $upload,
        string $destinationDirectory,
        ImageAssetProfile $profile = ImageAssetProfile::Original,
    ): ImageProcessingResult {
        if (! $upload->isValid()) {
            return ImageProcessingResult::failure('The uploaded image is invalid.');
        }

        $path = $upload->getRealPath();
        if ($path === false) {
            return ImageProcessingResult::failure('The uploaded image has no readable temporary path.');
        }

        return $this->saveLocalImage($imgName, $path, $destinationDirectory, $profile);
    }

    /**
     * Backwards-compatible adapter while callers move to explicit source APIs.
     *
     * @return int 1 on success, 0 on failure
     */
    public function saveImage(
        string $imgName,
        string $imgLoc,
        string $imgSavePath,
        int $imgMaxWidth = 0,
        int $imgMaxHeight = 0,
        bool $saveThumb = false,
    ): int {
        if ($imgLoc === '') {
            return 0;
        }

        $scheme = strtolower((string) parse_url($imgLoc, PHP_URL_SCHEME));
        $source = in_array($scheme, ['http', 'https'], true)
            ? $this->fetchRemoteBytes($imgLoc)
            : $this->readLocalBytes($imgLoc);

        if (! $source['success']) {
            return 0;
        }

        $result = $this->processBytes(
            $imgName,
            $source['contents'],
            $imgSavePath,
            $imgMaxWidth > 0 ? $imgMaxWidth : null,
            $imgMaxHeight > 0 ? $imgMaxHeight : null,
        );

        if ($result->success && $saveThumb && $result->path !== null) {
            $thumbPath = $imgSavePath.$imgName.'_thumb.'.$this->outputExtension();
            if (! File::copy($result->path, $thumbPath)) {
                return 0;
            }
        }

        return $result->success ? 1 : 0;
    }

    public function imagePath(string $directory, string $basename): ?string
    {
        foreach (array_unique([$this->outputExtension(), 'webp', 'jpg', 'jpeg']) as $extension) {
            $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$basename.'.'.$extension;
            if (File::isReadable($path)) {
                return $path;
            }
        }

        return null;
    }

    public function imageExists(string $directory, string $basename): bool
    {
        return $this->imagePath($directory, $basename) !== null;
    }

    public function outputExtension(): string
    {
        return $this->outputFormat() === 'webp' ? 'webp' : 'jpg';
    }

    /** Delete all generated assets for a release, including legacy images. */
    public function delete(string $guid): void
    {
        File::delete($this->releaseFiles($guid));
    }

    /** @return list<string> */
    public function releaseFiles(string $guid): array
    {
        $files = [
            $this->audSavePath.$guid.'.ogg',
            $this->vidSavePath.$guid.'.ogv',
        ];

        foreach (['webp', 'jpg', 'jpeg'] as $extension) {
            $files[] = $this->imgSavePath.$guid.'_thumb.'.$extension;
            $files[] = $this->jpgSavePath.$guid.'_thumb.'.$extension;
        }

        return $files;
    }

    /** @return array{success: bool, contents: string, reason: string} */
    private function readLocalBytes(string $path): array
    {
        if (! File::isFile($path) || ! File::isReadable($path)) {
            return ['success' => false, 'contents' => '', 'reason' => 'Image source is not a readable local file.'];
        }

        $size = File::size($path);
        if ($size === false || $size <= 0 || $size > $this->maxSourceBytes()) {
            return ['success' => false, 'contents' => '', 'reason' => 'Image source size is invalid or exceeds the configured limit.'];
        }

        try {
            return ['success' => true, 'contents' => File::get($path), 'reason' => ''];
        } catch (Throwable) {
            return ['success' => false, 'contents' => '', 'reason' => 'Unable to read the local image source.'];
        }
    }

    /** @return array{success: bool, contents: string, reason: string} */
    private function fetchRemoteBytes(string $url): array
    {
        $currentUrl = $url;
        $maxRedirects = max(0, (int) config('image.fetch_max_redirects', 5));

        try {
            for ($redirects = 0; $redirects <= $maxRedirects; $redirects++) {
                $this->assertSafeRemoteUrl($currentUrl);

                $response = Http::withHeaders([
                    'Accept' => 'image/avif,image/webp,image/png,image/jpeg,*/*;q=0.5',
                    'User-Agent' => 'NNTmux Image Fetcher',
                ])->withOptions([
                    'allow_redirects' => false,
                    'stream' => true,
                ])->connectTimeout(max(1, (int) config('image.fetch_connect_timeout', 5)))
                    ->timeout(max(1, (int) config('image.fetch_timeout', 30)))
                    ->get($currentUrl);

                if ($response->status() >= 300 && $response->status() < 400) {
                    $location = $response->header('Location');
                    if ($location === null || $location === '' || $redirects === $maxRedirects) {
                        return ['success' => false, 'contents' => '', 'reason' => 'Remote image redirect limit was exceeded.'];
                    }

                    $currentUrl = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));

                    continue;
                }

                if (! $response->successful()) {
                    Log::debug('Remote image request failed.', [
                        'url' => $this->redactedUrl($currentUrl),
                        'status' => $response->status(),
                    ]);

                    return ['success' => false, 'contents' => '', 'reason' => 'Remote image request failed.'];
                }

                $contentLength = $response->header('Content-Length');
                if (is_numeric($contentLength) && (int) $contentLength > $this->maxSourceBytes()) {
                    return ['success' => false, 'contents' => '', 'reason' => 'Remote image exceeds the configured size limit.'];
                }

                $stream = $response->toPsrResponse()->getBody();
                $contents = '';
                while (! $stream->eof()) {
                    $remaining = $this->maxSourceBytes() - strlen($contents);
                    if ($remaining <= 0) {
                        return ['success' => false, 'contents' => '', 'reason' => 'Remote image exceeds the configured size limit.'];
                    }

                    $contents .= $stream->read(min(8192, $remaining + 1));
                    if (strlen($contents) > $this->maxSourceBytes()) {
                        return ['success' => false, 'contents' => '', 'reason' => 'Remote image exceeds the configured size limit.'];
                    }
                }

                if ($contents === '') {
                    return ['success' => false, 'contents' => '', 'reason' => 'Remote image response was empty.'];
                }

                return ['success' => true, 'contents' => $contents, 'reason' => ''];
            }
        } catch (Throwable $e) {
            Log::debug('Unable to fetch remote image.', [
                'url' => $this->redactedUrl($currentUrl),
                'error' => $e->getMessage(),
            ]);
        }

        return ['success' => false, 'contents' => '', 'reason' => 'Unable to fetch the remote image.'];
    }

    private function processBytes(
        string $imgName,
        string $contents,
        string $destinationDirectory,
        ?int $maxWidth,
        ?int $maxHeight,
    ): ImageProcessingResult {
        if (! $this->isValidBasename($imgName)) {
            return ImageProcessingResult::failure('Image basename is invalid.');
        }

        if ($contents === '' || strlen($contents) > $this->maxSourceBytes()) {
            return ImageProcessingResult::failure('Image source size is invalid or exceeds the configured limit.');
        }

        $temporaryPath = null;

        try {
            $image = Image::fromBytes($contents);
            $width = $image->width();
            $height = $image->height();

            if ($width <= 0 || $height <= 0 || ($width * $height) > $this->maxSourcePixels()) {
                return ImageProcessingResult::failure('Decoded image dimensions exceed the configured limit.');
            }

            $image = $image->orient();
            $image = $this->resizeDown($image, $width, $height, $maxWidth, $maxHeight);
            $encoded = $this->encode($image);

            File::ensureDirectoryExists($destinationDirectory, 0775, true);
            $directory = rtrim($destinationDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            $extension = $this->outputExtension();
            $destinationPath = $directory.$imgName.'.'.$extension;
            $temporaryPath = $directory.'.'.$imgName.'.'.bin2hex(random_bytes(8)).'.tmp.'.$extension;

            if (File::put($temporaryPath, $encoded) === false) {
                return ImageProcessingResult::failure('Unable to write the processed image.');
            }

            $dimensions = getimagesize($temporaryPath);
            $mimeType = File::mimeType($temporaryPath);
            $expectedMime = $extension === 'webp' ? 'image/webp' : 'image/jpeg';

            if (! is_array($dimensions) || $mimeType !== $expectedMime || ! File::isReadable($temporaryPath)) {
                return ImageProcessingResult::failure('Processed image validation failed.');
            }

            if (! rename($temporaryPath, $destinationPath)) {
                return ImageProcessingResult::failure('Unable to atomically publish the processed image.');
            }
            $temporaryPath = null;
            File::chmod($destinationPath, 0644);

            return ImageProcessingResult::success(
                $destinationPath,
                (int) $dimensions[0],
                (int) $dimensions[1],
                $expectedMime,
            );
        } catch (Throwable $e) {
            Log::debug('Unable to process image.', [
                'destination' => $destinationDirectory,
                'name' => $imgName,
                'error' => $e->getMessage(),
            ]);

            return ImageProcessingResult::failure('Unable to decode or process the image.');
        } finally {
            if ($temporaryPath !== null) {
                File::delete($temporaryPath);
            }
        }
    }

    private function resizeDown(
        LaravelImage $image,
        int $width,
        int $height,
        ?int $maxWidth,
        ?int $maxHeight,
    ): LaravelImage {
        if ($maxWidth === null || $maxHeight === null) {
            return $image;
        }

        $ratio = min($maxHeight / $height, $maxWidth / $width, 1);
        $newWidth = (int) floor($ratio * $width);
        $newHeight = (int) floor($ratio * $height);

        if ($ratio < 1 && $newWidth > 10 && $newHeight > 10) {
            return $image->resize($newWidth, $newHeight);
        }

        return $image;
    }

    private function encode(LaravelImage $image): string
    {
        $quality = max(1, min(100, (int) config('image.output_quality', 82)));

        return match ($this->outputFormat()) {
            'webp' => $image->toWebp()->quality($quality)->toBytes(),
            default => $image->toJpeg()->quality($quality)->toBytes(),
        };
    }

    private function outputFormat(): string
    {
        $format = strtolower((string) config('image.output_format', 'webp'));

        return in_array($format, ['jpg', 'jpeg'], true) ? 'jpg' : 'webp';
    }

    private function maxSourceBytes(): int
    {
        return max(1, (int) config('image.max_source_bytes', 20 * 1024 * 1024));
    }

    private function maxSourcePixels(): int
    {
        return max(1, (int) config('image.max_source_pixels', 40_000_000));
    }

    private function isValidBasename(string $basename): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]*\z/D', $basename) === 1;
    }

    private function assertSafeRemoteUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new \InvalidArgumentException('Remote image URL is invalid.');
        }

        $addresses = ($this->hostResolver)($parts['host']);
        if ($addresses === []) {
            throw new \InvalidArgumentException('Remote image host did not resolve.');
        }

        foreach ($addresses as $address) {
            if (filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false) {
                throw new \InvalidArgumentException('Remote image host resolves to a non-public address.');
            }
        }
    }

    /** @return list<string> */
    private function defaultHostResolver(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            return [];
        }

        $addresses = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $addresses[] = $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }

    private function redactedUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '[invalid-url]';
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return strtolower($parts['scheme']).'://'.$parts['host'].$port.($parts['path'] ?? '/');
    }
}
