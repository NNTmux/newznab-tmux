<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use App\Services\Yenc\ArticleFrame;
use App\Services\YencService;
use RuntimeException;

final readonly class VerifiedArticle
{
    private function __construct(
        public string $name,
        public int $size,
        public int $offset,
        public int $part,
        public ?int $totalParts,
        public string $bytes,
        public ?string $fileChecksum,
    ) {}

    public static function decode(string $raw, ?YencService $decoder = null): self
    {
        $frame = ArticleFrame::parse($raw);
        if ($frame === null) {
            throw new RuntimeException('Unsupported article encoding.');
        }
        $name = $frame->header['name'];
        if ($name === '' || ! mb_check_encoding($name, 'UTF-8') || strlen($name) > 200 || $name[0] === '.' || $name[0] === '-'
            || preg_match('/[\x00-\x1f\x7f\/\\\\]/', $name)) {
            throw new RuntimeException('Unsafe article filename.');
        }
        $numbers = [$frame->header['size'], $frame->header['part'] ?? '1', $frame->part['begin'] ?? '1'];
        if (isset($frame->header['total'])) {
            $numbers[] = $frame->header['total'];
        }
        foreach ($numbers as $number) {
            if (! ctype_digit($number) || strlen($number) > 18 || (int) $number < 1) {
                throw new RuntimeException('Invalid article dimensions.');
            }
        }
        $totalParts = isset($frame->header['total']) ? (int) $frame->header['total'] : null;
        if ($totalParts !== null && (int) ($frame->header['part'] ?? 1) > $totalParts) {
            throw new RuntimeException('Article part number exceeds its declared total.');
        }
        if (! isset($frame->trailer[$frame->part === null ? 'crc32' : 'pcrc32'])) {
            throw new RuntimeException('Article checksum is required.');
        }
        try {
            $decoded = ($decoder ?? new YencService)->decode($raw);
        } catch (RuntimeException $error) {
            if ($error->getMessage() === 'CRC32 checksums do not match. The file is probably corrupt.') {
                throw new ArticleIntegrityFailure($error->getMessage(), previous: $error);
            }
            throw $error;
        }
        if ($decoded === false) {
            throw new RuntimeException('Article decoding failed.');
        }

        return new self($name, (int) $frame->header['size'], (int) ($frame->part['begin'] ?? 1) - 1,
            (int) ($frame->header['part'] ?? 1), $totalParts, $decoded, isset($frame->trailer['crc32']) ? strtolower(str_pad($frame->trailer['crc32'], 8, '0', STR_PAD_LEFT)) : null);
    }
}
