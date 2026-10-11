<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Media;

use App\Services\AdditionalProcessing\Enums\VideoContainer;
use Illuminate\Support\Facades\Log;

/**
 * Grabs keyframes with one ffmpeg call. Only keyframes are decoded, so even a
 * frame far into a file is reached quickly, and frames are selected by
 * timestamp rather than seeking, which a partial download cannot support.
 */
class FfmpegFrameGrabber implements VideoFrameGrabber
{
    public function __construct(
        private readonly MediaToolFactory $tools,
        private readonly bool $debugMode = false,
    ) {}

    public function grab(string $videoPath, ?VideoContainer $container, float $from, float $spacing, int $count, string $outputDirectory): array
    {
        $pattern = rtrim($outputDirectory, '/').'/frame_'.bin2hex(random_bytes(4)).'_';

        try {
            $this->tools->ffmpeg()->getFFMpegDriver()->command([
                '-y',
                '-v', 'error',
                '-skip_frame', 'nokey',
                ...$this->inputFormat($container),
                '-i', $videoPath,
                '-an', '-sn', '-dn',
                // Selected frames are renumbered one second apart and written at one per second,
                // so the image muxer never repeats a frame to fill a gap, on any ffmpeg version.
                '-vf', sprintf(
                    "select='gte(t,%.3F)*(isnan(prev_selected_t)+gte(t-prev_selected_t,%.3F))',setpts=N/TB,scale='min(1280,iw)':-2",
                    max(0.0, $from),
                    max(0.0, $spacing),
                ),
                '-r', '1',
                '-frames:v', (string) max(1, $count),
                '-q:v', '3',
                $pattern.'%02d.jpg',
            ]);
        } catch (\Throwable $e) {
            // A truncated file often fails after the frames we need were written.
            if ($this->debugMode) {
                Log::debug('Frame grab ended with an error: '.$e->getMessage());
            }
        }

        $frames = glob($pattern.'*.jpg') ?: [];
        sort($frames);

        return array_values(array_filter($frames, static fn (string $frame): bool => (int) @filesize($frame) > 0));
    }

    public function probe(string $videoPath, ?VideoContainer $container): array
    {
        try {
            $output = $this->tools->ffprobe()->getFFProbeDriver()->command([
                '-v', 'error',
                ...$this->inputFormat($container),
                '-show_format',
                '-show_streams',
                '-print_format', 'json',
                $videoPath,
            ]);
        } catch (\Throwable $e) {
            if ($this->debugMode) {
                Log::debug('Probe failed: '.$e->getMessage());
            }

            return ['duration' => null, 'bitRate' => null];
        }

        $info = json_decode((string) $output, true);
        if (! is_array($info)) {
            return ['duration' => null, 'bitRate' => null];
        }

        $video = null;
        foreach ($info['streams'] ?? [] as $stream) {
            if (is_array($stream) && ($stream['codec_type'] ?? null) === 'video') {
                $video = $stream;
                break;
            }
        }

        $tags = array_change_key_case(is_array($video['tags'] ?? null) ? $video['tags'] : [], CASE_UPPER);
        $duration = self::positiveFloat($info['format']['duration'] ?? null)
            ?? self::positiveFloat($video['duration'] ?? null)
            ?? self::clockSeconds($tags['DURATION'] ?? null);
        $bitRate = self::positiveFloat($tags['BPS'] ?? null) ?? self::positiveFloat($video['bit_rate'] ?? null);

        return ['duration' => $duration, 'bitRate' => $bitRate === null ? null : (int) $bitRate];
    }

    /**
     * @return list<string>
     */
    private function inputFormat(?VideoContainer $container): array
    {
        return $container === null ? [] : ['-f', $container->value];
    }

    private static function positiveFloat(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    /**
     * Seconds in a Matroska "HH:MM:SS.nnnnnnnnn" duration tag.
     */
    private static function clockSeconds(mixed $value): ?float
    {
        if (! is_string($value) || preg_match('/^(\d+):(\d{2}):(\d{2}(?:\.\d+)?)$/', $value, $match) !== 1) {
            return null;
        }

        return self::positiveFloat((int) $match[1] * 3600 + (int) $match[2] * 60 + (float) $match[3]);
    }
}
