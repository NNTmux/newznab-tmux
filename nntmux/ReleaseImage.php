<?php

namespace nntmux;

use GuzzleHttp\Client;
use Intervention\Image\Image;
use Intervention\Image\Exception\ImageException;
use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\Exception\NotWritableException;

/**
 * Resize/save/delete images to disk.
 *
 * Class ReleaseImage
 */
class ReleaseImage
{
    /**
     * Path to save ogg audio samples.
     *
     * @var string
     */
    public $audSavePath;

    /**
     * Path to save video preview jpg pictures.
     *
     * @var string
     */
    public $imgSavePath;

    /**
     * Path to save large jpg pictures(xxx).
     *
     * @var string
     */
    public $jpgSavePath;

    /**
     * Path to save movie jpg covers.
     *
     * @var string
     */
    public $movieImgSavePath;

    /**
     * Path to save video ogv files.
     *
     * @var string
     */
    public $vidSavePath;

    /**
     * @var Client
     */
    protected $client;

    /**
     * ReleaseImage constructor.
     */
    public function __construct()
    {
        $this->client = new Client();
        // Creates the NN_COVERS constant
        //                                                       Table    |  Column
        $this->audSavePath = NN_COVERS.'audiosample'.DS; // releases    guid
        $this->imgSavePath = NN_COVERS.'preview'.DS; // releases    guid
        $this->jpgSavePath = NN_COVERS.'sample'.DS; // releases    guid
        $this->movieImgSavePath = NN_COVERS.'movies'.DS; // releases    imdbid
        $this->vidSavePath = NN_COVERS.'video'.DS; // releases    guid

        /* For reference. *
        $this->anidbImgPath   = NN_COVERS . 'anime'       . DS; // anidb       anidbid | used in populate_anidb.php, not anidb.php
        $this->bookImgPath    = NN_COVERS . 'book'        . DS; // bookinfo    id
        $this->consoleImgPath = NN_COVERS . 'console'     . DS; // consoleinfo id
        $this->musicImgPath   = NN_COVERS . 'music'       . DS; // musicinfo   id
        $this->tvRageImgPath  = NN_COVERS . 'tvrage'      . DS; // tvrage      id (not rageid)

        $this->audioImgPath   = NN_COVERS . 'audio'       . DS; // unused folder, music folder already exists.
        **/
    }

    /**
     * @param $imgLoc
     * @return bool|\Intervention\Image\Image
     */
    protected function fetchImage($imgLoc)
    {
        try {
            $img = (new Image)->make($imgLoc);
        } catch (NotReadableException $e) {
            if ($e->getCode() === 404) {
                ColorCLI::doEcho(ColorCLI::notice('Data not available on server'));
            } elseif ($e->getCode() === 503) {
                ColorCLI::doEcho(ColorCLI::notice('Service unavailable'));
            } else {
                ColorCLI::doEcho(ColorCLI::notice('Unable to fetch data, server responded with code: '.$e->getCode()));
            }

            return false;
        } catch (ImageException $e) {
            ColorCLI::doEcho(ColorCLI::notice('Image error: '.$e->getCode()));

            return false;
        }

        return $img;
    }

    /**
     * Save an image to disk, optionally resizing it.
     *
     * @param string $imgName      What to name the new image.
     * @param string $imgLoc       URL or location on the disk the original image is in.
     * @param string $imgSavePath  Folder to save the new image in.
     * @param string $imgMaxWidth  Max width to resize image to.   (OPTIONAL)
     * @param string $imgMaxHeight Max height to resize image to.  (OPTIONAL)
     * @param bool   $saveThumb    Save a thumbnail of this image? (OPTIONAL)
     *
     * @return int 1 on success, 0 on failure Used on site to check if there is an image.
     */
    public function saveImage($imgName, $imgLoc, $imgSavePath, $imgMaxWidth = '', $imgMaxHeight = '', $saveThumb = false): int
    {
        // Try to get the image as a string.
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
            $new_width = (int) ($ratio * $width);
            $new_height = (int) ($ratio * $height);
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
        if (! is_file($coverPath)) {
            return 0;
        }

        return 1;
    }

    /**
     * Delete images for the release.
     *
     * @param string $guid The GUID of the release.
     *
     * @return void
     */
    public function delete($guid)
    {
        $thumb = $guid.'_thumb.jpg';

        // Audiosample folder.
        @unlink($this->audSavePath.$guid.'.ogg');

        // Preview folder.
        @unlink($this->imgSavePath.$thumb);

        // Sample folder.
        @unlink($this->jpgSavePath.$thumb);

        // Video folder.
        @unlink($this->vidSavePath.$guid.'.ogv');
    }
}
