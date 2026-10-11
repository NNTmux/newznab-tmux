<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Media;

use App\Services\AdditionalProcessing\Enums\VideoContainer;

/**
 * Makes a chunk from the middle of a video decodable by giving it the file's headers.
 *
 * Openings are often black or show logos; a frame from the middle shows the content.
 * Only the containers whose stream can be picked up again at a known marker are supported.
 */
final class ContainerSplicer
{
    private const string MATROSKA_CLUSTER = "\x1F\x43\xB6\x75";

    private const string MPEG_PACK_START = "\x00\x00\x01\xBA";

    /**
     * @param  string  $head  the start of the file
     * @param  string  $mid  bytes from later in the same file
     */
    public function splice(string $head, string $mid, VideoContainer $container): ?string
    {
        return match ($container) {
            VideoContainer::Matroska => $this->joinAt($head, self::MATROSKA_CLUSTER, $mid, self::MATROSKA_CLUSTER),
            VideoContainer::MpegTransport => ($offset = VideoContainer::transportStreamOffset($mid)) === null ? null : substr($mid, $offset),
            VideoContainer::MpegProgram => ($offset = strpos($mid, self::MPEG_PACK_START)) === false ? null : substr($mid, $offset),
            VideoContainer::Avi => $this->spliceAvi($head, $mid),
            default => null,
        };
    }

    /**
     * Keep $head up to its first $headMarker, then continue at $mid's first $midMarker.
     */
    private function joinAt(string $head, string $headMarker, string $mid, string $midMarker): ?string
    {
        $headEnd = strpos($head, $headMarker);
        $midStart = strpos($mid, $midMarker);
        if ($headEnd === false || $midStart === false) {
            return null;
        }

        return substr($head, 0, $headEnd).substr($mid, $midStart);
    }

    /**
     * Headers up to the start of the 'movi' list, then the first stream chunk of $mid.
     */
    private function spliceAvi(string $head, string $mid): ?string
    {
        $movi = strpos($head, 'movi');
        if ($movi === false || $movi < 8 || substr($head, $movi - 8, 4) !== 'LIST'
            || preg_match('/\d\d(?:dc|db|wb)/', $mid, $chunk, PREG_OFFSET_CAPTURE) !== 1
        ) {
            return null;
        }

        return substr($head, 0, $movi + 4).substr($mid, $chunk[0][1]);
    }
}
