<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Yenc\ArticleFrame;
use App\Services\Yenc\NativePayloadDecoder;
use App\Services\Yenc\PayloadDecoder;
use App\Services\Yenc\PhpPayloadDecoder;
use App\Services\Yenc\RawPayloadDecoder;
use RuntimeException;

class YencService
{
    public function __construct(private readonly PayloadDecoder $decoder = new PhpPayloadDecoder) {}

    /**
     * @param  bool  $ignore  Legacy unused argument retained for compatibility.
     *
     * @throws RuntimeException
     */
    public function decode(string &$text, bool $ignore = false): string|false
    {
        $frame = ArticleFrame::parse($text);
        if ($frame === null) {
            return false;
        }
        $slice = $this->payloadSlice($text, $frame);
        if ($this->hasDanglingEscape($slice)) {
            throw new RuntimeException('Unmatched yEnc escape marker. The file is probably corrupt.');
        }
        $decoded = $this->decodePayload($slice);
        $size = $this->number($frame->header, 'size');
        $trailerSize = $this->number($frame->trailer, 'size');
        $checksum = $frame->trailer['crc32'] ?? null;
        if (isset($frame->header['part']) || isset($frame->trailer['part']) || $frame->part !== null) {
            if ($frame->part === null) {
                throw new RuntimeException('Missing yEnc multipart range.');
            }
            $part = $this->number($frame->header, 'part');
            $begin = $this->number($frame->part, 'begin');
            $end = $this->number($frame->part, 'end');
            if ($part < 1 || $part !== $this->number($frame->trailer, 'part')
                || $begin < 1 || $end < $begin || $end > $size
                || (isset($frame->header['total']) && $part > $this->number($frame->header, 'total'))
                || (isset($frame->trailer['total']) && $this->number($frame->trailer, 'total') !== $this->number($frame->header, 'total'))) {
                throw new RuntimeException('Invalid yEnc multipart range or part number.');
            }
            $size = $end - $begin + 1;
            $checksum = $frame->trailer['pcrc32'] ?? null;
            if ($checksum === null) {
                throw new RuntimeException('Missing yEnc part checksum.');
            }
        }
        if ($size !== $trailerSize || $size !== strlen($decoded)) {
            throw new RuntimeException('Declared and decoded yEnc sizes do not match. The file is probably corrupt.');
        }
        if ($checksum !== null && (! preg_match('/\A[0-9a-f]{1,8}\z/i', $checksum)
            || strcasecmp(str_pad($checksum, 8, '0', STR_PAD_LEFT), $this->crc32($decoded)) !== 0)) {
            throw new RuntimeException('CRC32 checksums do not match. The file is probably corrupt.');
        }

        return $decoded;
    }

    public function decodeIgnore(string &$text): string
    {
        $frame = ArticleFrame::parse($text);
        if ($frame !== null) {
            $slice = $this->payloadSlice($text, $frame);
            $text = $this->hasDanglingEscape($slice)
                ? (new PhpPayloadDecoder)->decode($this->stripLineEndings($slice))
                : $this->decodePayload($slice);
        }

        return $text;
    }

    public function enabled(): bool
    {
        return true;
    }

    public function decoderName(): string
    {
        return $this->decoder instanceof NativePayloadDecoder ? 'RapidYenc' : 'PHP';
    }

    /** RapidYenc version when the native decoder is active, null otherwise. */
    public function decoderVersion(): ?string
    {
        return $this->decoder instanceof NativePayloadDecoder ? $this->decoder->version() : null;
    }

    public function isYencEncoded(string $text): bool
    {
        return ArticleFrame::parse($text) !== null;
    }

    public function encode(string $data, string $filename, int $lineLength = 128, bool $includeCrc32 = true): string
    {
        $lineLength = min($lineLength, 254);
        if ($lineLength < 1) {
            throw new RuntimeException("{$lineLength} is not a valid line length.");
        }
        if (strpbrk($filename, "\r\n") !== false) {
            throw new RuntimeException('A yEnc filename cannot contain line endings.');
        }

        $size = strlen($data);
        $result = '=ybegin line='.$lineLength.' size='.$size.' name='.trim($filename)."\r\n";
        $column = 0;
        for ($offset = 0; $offset < $size; $offset++) {
            $byte = (ord($data[$offset]) + 42) & 255;
            $encoded = match ($byte) {
                0, 9, 10, 13, 32, 46, 61 => '='.chr(($byte + 64) & 255),
                default => chr($byte),
            };
            if ($column >= $lineLength) {
                $result .= "\r\n";
                $column = 0;
            }
            $result .= $encoded;
            $column += strlen($encoded);
        }
        $result .= "\r\n=yend size=".$size;
        if ($includeCrc32) {
            $result .= ' crc32='.hash('crc32b', $data);
        }

        return $result;
    }

    /** @return array{name: string|null, size: int|null, line: int|null, crc32: string|null}|null */
    public function extractMetadata(string $text): ?array
    {
        $frame = ArticleFrame::parse($text);
        if ($frame === null) {
            return null;
        }

        return [
            'name' => $frame->header['name'],
            'size' => $this->metadataNumber($frame->header['size']),
            'line' => $this->metadataNumber($frame->header['line']),
            'crc32' => isset($frame->trailer['crc32']) ? strtoupper($frame->trailer['crc32']) : null,
        ];
    }

    private function payloadSlice(string $text, ArticleFrame $frame): string
    {
        return substr($text, $frame->payloadOffset, $frame->payloadLength);
    }

    private function decodePayload(string $slice): string
    {
        if ($this->decoder instanceof RawPayloadDecoder) {
            // Search line endings rather than every escape; split escapes need stripped recovery.
            $splitEscape = preg_match('/(?<==)[\r\n]/', $slice);
            if ($splitEscape === false) {
                throw new RuntimeException('Unable to inspect yEnc line endings: '.preg_last_error_msg());
            }
            if ($splitEscape === 0) {
                return $this->decoder->decodeRaw($slice);
            }
        }

        return $this->decoder->decode($this->stripLineEndings($slice));
    }

    private function stripLineEndings(string $payload): string
    {
        return str_replace(["\r", "\n"], '', $payload);
    }

    private function crc32(string $decoded): string
    {
        if ($this->decoder instanceof NativePayloadDecoder) {
            $native = $this->decoder->crc32($decoded);
            if ($native !== null) {
                return $native;
            }
        }

        return hash('crc32b', $decoded);
    }

    private function hasDanglingEscape(string $payload): bool
    {
        $count = 0;
        for ($offset = strlen($payload) - 1; $offset >= 0; $offset--) {
            if ($payload[$offset] === '=') {
                $count++;
            } elseif ($payload[$offset] !== "\r" && $payload[$offset] !== "\n") {
                break;
            }
        }

        return ($count & 1) === 1;
    }

    /** @param array<string, string> $fields */
    private function number(array $fields, string $key): int
    {
        $number = $this->metadataNumber($fields[$key] ?? '');
        if ($number === null) {
            throw new RuntimeException("Invalid or missing yEnc {$key}.");
        }

        return $number;
    }

    private function metadataNumber(string $value): ?int
    {
        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }
        $value = ltrim($value, '0');
        $maximum = (string) PHP_INT_MAX;
        if (strlen($value) > strlen($maximum) || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) > 0)) {
            return null;
        }

        return (int) $value;
    }
}
