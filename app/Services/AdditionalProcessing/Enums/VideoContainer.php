<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Enums;

/**
 * Video container formats, named by their ffmpeg demuxer.
 *
 * ffmpeg is told the demuxer instead of probing for one, so a downloaded file
 * can never be read as a playlist that pulls in other files.
 */
enum VideoContainer: string
{
    case Matroska = 'matroska';
    case Avi = 'avi';
    case MpegProgram = 'mpeg';
    case MpegTransport = 'mpegts';
    case Mp4 = 'mov';
    case Asf = 'asf';
    case Ogg = 'ogg';

    /**
     * Recognise a container from the first bytes of a file.
     */
    public static function detect(string $head): ?self
    {
        if (strlen($head) < 12) {
            return null;
        }

        return match (true) {
            str_starts_with($head, "\x1A\x45\xDF\xA3") => self::Matroska,
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'AVI ' => self::Avi,
            str_starts_with($head, "\x00\x00\x01\xBA"), str_starts_with($head, "\x00\x00\x01\xB3") => self::MpegProgram,
            substr($head, 4, 4) === 'ftyp', substr($head, 4, 4) === 'moov' => self::Mp4,
            str_starts_with($head, "\x30\x26\xB2\x75\x8E\x66\xCF\x11") => self::Asf,
            str_starts_with($head, 'OggS') => self::Ogg,
            self::transportStreamOffset($head) !== null => self::MpegTransport,
            default => null,
        };
    }

    /**
     * Guess a container from a file name's extension.
     */
    public static function fromFileName(string $name): ?self
    {
        if (preg_match('/\.([a-z0-9]{2,4})"?(?:\s|$)/i', trim($name), $match) !== 1) {
            return null;
        }

        return match (strtolower($match[1])) {
            'mkv', 'mk3d', 'webm' => self::Matroska,
            'avi', 'divx', 'xvid' => self::Avi,
            'mpg', 'mpeg', 'vob', 'm2v', 'mpv' => self::MpegProgram,
            'ts', 'm2ts', 'mts' => self::MpegTransport,
            'mp4', 'm4v', 'mov', 'qt' => self::Mp4,
            'wmv', 'asf' => self::Asf,
            'ogv', 'ogm' => self::Ogg,
            default => null,
        };
    }

    /**
     * Offset of the first transport stream packet, checking several packets of 188 or 192 bytes.
     */
    public static function transportStreamOffset(string $data): ?int
    {
        foreach ([188, 192] as $stride) {
            for ($offset = 0, $limit = min(strlen($data) - $stride * 4, $stride); $offset < $limit; $offset++) {
                if ($data[$offset] === "\x47" && $data[$offset + $stride] === "\x47"
                    && $data[$offset + $stride * 2] === "\x47" && $data[$offset + $stride * 3] === "\x47"
                ) {
                    return $offset;
                }
            }
        }

        return null;
    }

    public function extension(): string
    {
        return match ($this) {
            self::Matroska => 'mkv',
            self::Avi => 'avi',
            self::MpegProgram => 'mpg',
            self::MpegTransport => 'ts',
            self::Mp4 => 'mp4',
            self::Asf => 'wmv',
            self::Ogg => 'ogv',
        };
    }

    /**
     * Whether a chunk from the middle of a file can be decoded, given at most the file's opening headers.
     * MP4 keeps its index in one box, often at the end; ASF and Ogg need more than a prefix.
     */
    public function canResumeMidStream(): bool
    {
        return match ($this) {
            self::Matroska, self::Avi, self::MpegProgram, self::MpegTransport => true,
            default => false,
        };
    }
}
