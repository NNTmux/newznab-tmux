<?php

namespace Blacklight;

use Illuminate\Support\Facades\File;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Exceptions\NotWritableException;
use Intervention\Image\ImageManager;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;

/**
 * Resize/save/delete images to disk.
 *
 *
 * Class ReleaseImage
 */
class ReleaseImage
{
    /**
     * Path to save ogg audio samples.
     */
    public string $audSavePath;

    /**
     * Path to save video preview jpg pictures.
     */
    public string $imgSavePath;

    /**
     * Path to save large jpg pictures(xxx).
     */
    public string $jpgSavePath;

    /**
     * Path to save movie jpg covers.
     */
    public string $movieImgSavePath;

    /**
     * Path to save video ogv files.
     */
    public string $vidSavePath;

    protected ColorCLI $colorCli;

    /**
     * ReleaseImage constructor.
     */
    public function __construct()
    {
        $this->colorCli = new ColorCLI;
        $this->audSavePath = storage_path('covers/audiosample/');
        $this->imgSavePath = storage_path('covers/preview/');
        $this->jpgSavePath = storage_path('covers/sample/');
        $this->movieImgSavePath = storage_path('covers/movies/');
        $this->vidSavePath = storage_path('covers/video/');
    }

    protected function fetchImage(string $imgLoc): bool|\Intervention\Image\Interfaces\ImageInterface
    {
        try {
            // Create context with timeout settings for file_get_contents
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (compatible; NNTmux/1.0)',
                    'follow_location' => true,
                    'max_redirects' => 5,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ]);

            $imageData = @file_get_contents($imgLoc, false, $context);

            if ($imageData === false) {
                $error = error_get_last();
                $errorMsg = $error !== null && isset($error['message']) ? $error['message'] : 'Unknown error fetching image';
                $this->colorCli->notice('Failed to fetch image from '.$imgLoc.': '.$errorMsg);

                return false;
            }

            if (empty($imageData)) {
                $this->colorCli->notice('Empty image data received from '.$imgLoc);

                return false;
            }

            $manager = new ImageManager(Driver::class);
            $img = $manager->read($imageData);
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                $this->colorCli->notice('Data not available on server');
            } elseif ($e->getCode() === 503) {
                $this->colorCli->notice('Service unavailable');
            } else {
                $this->colorCli->notice('Unable to fetch image: '.$e->getMessage());
            }

            $img = false;
        } catch (\Error $e) {
            $this->colorCli->info($e->getMessage());
            $img = false;
        }

        return $img;
    }

    /**
     * Save an image to disk, optionally resizing it.
     *
     * @param  string  $imgName  What to name the new image.
     * @param  string  $imgLoc  URL or location on the disk the original image is in.
     * @param  string  $imgSavePath  Folder to save the new image in.
     * @param  int  $imgMaxWidth  Max width to resize image to.   (OPTIONAL)
     * @param  int  $imgMaxHeight  Max height to resize image to.  (OPTIONAL)
     * @param  bool  $saveThumb  Save a thumbnail of this image? (OPTIONAL)
     * @return int 1 on success, 0 on failure Used on site to check if there is an image.
     */
    public function saveImage(string $imgName, string $imgLoc, string $imgSavePath, int $imgMaxWidth = 0, int $imgMaxHeight = 0, bool $saveThumb = false): int
    {
        // Guard against empty image locations to avoid 'Path cannot be empty'
        if (empty($imgLoc)) {
            return 0;
        }

        $cover = $this->fetchImage($imgLoc);

        if ($cover === false) {
            return 0;
        }

        // Check if we need to resize it.
        if ($imgMaxWidth !== 0 && $imgMaxHeight !== 0) {
            $width = $cover->width();
            $height = $cover->height();
            if ($width !== 0 || $height !== 0) {
                $ratio = min($imgMaxHeight / $height, $imgMaxWidth / $width);
                // New dimensions
                $new_width = $ratio * $width;
                $new_height = $ratio * $height;
                if ($new_width < $width && $new_width > 10 && $new_height > 10) {
                    $cover->resize($new_width, $new_height);

                    if ($saveThumb) {
                        $cover->toJpeg(100)->save($imgSavePath.$imgName.'_thumb.jpg');
                        // Optimize the thumbnail.
                        ImageOptimizer::optimize($imgSavePath.$imgName.'_thumb.jpg');
                    }
                }
            }
        }
        // Store it on the hard drive.
        $coverPath = $imgSavePath.$imgName.'.jpg';
        try {
            $cover->toJpeg(100)->save($coverPath);
            // Optimize the image.
            ImageOptimizer::optimize($coverPath);
        } catch (NotWritableException $e) {
            return 0;
        }
        // Check if it's on the drive.
        if (! File::isReadable($coverPath)) {
            return 0;
        }

        return 1;
    }

    /**
     * Delete images for the release.
     *
     * @param  string  $guid  The GUID of the release.
     */
    public function delete(string $guid): void
    {
        $thumb = $guid.'_thumb.jpg';

        File::delete([$this->audSavePath.$guid.'.ogg', $this->imgSavePath.$thumb, $this->jpgSavePath.$thumb, $this->vidSavePath.$guid.'.ogv']);
    }
}
