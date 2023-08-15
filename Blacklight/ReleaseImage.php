<?php

namespace Blacklight;

use Illuminate\Support\Facades\File;
use Intervention\Image\Exception\ImageException;
use Intervention\Image\Exception\NotFoundException;
use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\Exception\NotWritableException;
use Intervention\Image\Facades\Image;

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
        $this->colorCli = new ColorCLI();
        $this->audSavePath = storage_path('covers/audiosample/');
        $this->imgSavePath = storage_path('covers/preview/');
        $this->jpgSavePath = storage_path('covers/sample/');
        $this->movieImgSavePath = storage_path('covers/movies/');
        $this->vidSavePath = storage_path('covers/video/');
    }

    protected function fetchImage(string $imgLoc): \Intervention\Image\Image|bool
    {
        try {
            $img = Image::make($imgLoc);
        } catch (NotFoundException $e) {
            if ($e->getCode() === 404) {
                $this->colorCli->notice('Data not available on server');
            } elseif ($e->getCode() === 503) {
                $this->colorCli->notice('Service unavailable');
            } else {
                $this->colorCli->notice('Unable to fetch image: '.$e->getMessage());
            }

            $img = false;
        } catch (NotReadableException $e) {
            $this->colorCli->climate()->info($e->getMessage());

            $img = false;
        } catch (ImageException $e) {
            $this->colorCli->climate()->error('Image error: '.$e->getMessage());

            $img = false;
        } catch (\ImagickException $e) {
            $this->colorCli->climate()->error('Image error: '.$e->getMessage());

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
     * @param  string  $imgMaxWidth  Max width to resize image to.   (OPTIONAL)
     * @param  string  $imgMaxHeight  Max height to resize image to.  (OPTIONAL)
     * @param  bool  $saveThumb  Save a thumbnail of this image? (OPTIONAL)
     * @return int 1 on success, 0 on failure Used on site to check if there is an image.
     */
    public function saveImage(string $imgName, string $imgLoc, string $imgSavePath, string $imgMaxWidth = '', string $imgMaxHeight = '', bool $saveThumb = false): int
    {
        $cover = $this->fetchImage($imgLoc);

        if ($cover === false) {
            return 0;
        }

        // Check if we need to resize it.
        if ($imgMaxWidth !== '' && $imgMaxHeight !== '') {
            $width = $cover->width();
            $height = $cover->height();
            $ratio = min($imgMaxHeight / $height, $imgMaxWidth / $width);
            // New dimensions
            $new_width = $ratio * $width;
            $new_height = $ratio * $height;
            if ($new_width < $width && $new_width > 10 && $new_height > 10) {
                $cover->resize($new_width, $new_height);

                if ($saveThumb) {
                    $cover->save($imgSavePath.$imgName.'_thumb.jpg');
                }
            }
        }
        // Store it on the hard drive.
        $coverPath = $imgSavePath.$imgName.'.jpg';
        try {
            $cover->save($coverPath);
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
