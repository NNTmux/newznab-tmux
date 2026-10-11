<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Media;

use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Builds the ffmpeg and ffprobe clients from the configured binaries.
 *
 * Both clients share one probe whose cache is bounded, because a worker lives
 * through thousands of releases and php-ffmpeg's default cache never forgets.
 */
class MediaToolFactory
{
    private const int PROBE_CACHE_ITEMS = 64;

    private ?FFProbe $ffprobe = null;

    private ?FFMpeg $ffmpeg = null;

    public function __construct(private readonly ProcessingConfiguration $config) {}

    public function ffprobe(): FFProbe
    {
        return $this->ffprobe ??= FFProbe::create(
            $this->driverConfiguration(),
            null,
            new ArrayAdapter(0, false, 0, self::PROBE_CACHE_ITEMS),
        );
    }

    public function ffmpeg(): FFMpeg
    {
        return $this->ffmpeg ??= FFMpeg::create($this->driverConfiguration(), null, $this->ffprobe());
    }

    /**
     * @return array{timeout: int, 'ffmpeg.binaries'?: string, 'ffprobe.binaries'?: string}
     */
    public function driverConfiguration(): array
    {
        $configuration = ['timeout' => $this->config->mediaFfmpegTimeout];

        if (is_string($this->config->ffmpegPath) && $this->config->ffmpegPath !== '') {
            $configuration['ffmpeg.binaries'] = $this->config->ffmpegPath;
        }
        if (is_string($this->config->ffprobePath) && $this->config->ffprobePath !== '') {
            $configuration['ffprobe.binaries'] = $this->config->ffprobePath;
        }

        return $configuration;
    }
}
