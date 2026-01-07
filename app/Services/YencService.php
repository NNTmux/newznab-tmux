<?php

namespace App\Services;

use RuntimeException;

/**
 * YEnc encoding/decoding service.
 *
 * Provides optimized yEnc encoder/decoder implementation for Usenet article processing.
 */
class YencService
{
    /**
     * Pre-computed decode translation table for non-escaped characters.
     * Maps yEnc encoded byte to decoded byte: (byte - 42) % 256
     *
     * @var array<string, string>|null
     */
    private ?array $decodeTable = null;

    /**
     * Pre-computed decode translation table for escaped characters.
     * Maps yEnc escaped byte to decoded byte: ((byte - 64) - 42) % 256
     *
     * @var array<string, string>|null
     */
    private ?array $escapeDecodeTable = null;

    /**
     * Initialize the decode translation tables (lazy initialization).
     */
    private function initDecodeTables(): void
    {
        if ($this->decodeTable !== null) {
            return;
        }

        $this->decodeTable = [];
        $this->escapeDecodeTable = [];

        for ($i = 0; $i < 256; $i++) {
            $char = \chr($i);
            // Standard decode: (byte - 42) % 256
            $this->decodeTable[$char] = \chr(($i - 42 + 256) % 256);
            // Escape decode: ((byte - 64) - 42) % 256
            $this->escapeDecodeTable[$char] = \chr((($i - 64 - 42) + 512) % 256);
        }
    }

    /**
     * Decode a yEnc encoded string.
     *
     * @param  string  $text  The yEnc encoded text
     * @param  bool  $ignore  Whether to ignore errors (unused, kept for compatibility)
     * @return string|false The decoded string, or false if not valid yEnc
     *
     * @throws RuntimeException If the data is corrupt or invalid
     */
    public function decode(string &$text, bool $ignore = false): string|false
    {
        $crc = '';

        // Extract the yEnc string itself.
        // The pattern needs to handle binary content between =ybegin and =yend
        if (preg_match(
            '/=ybegin[^\r\n]*size=(\d+)[^\r\n]*\r\n([\s\S]*?)\r\n=yend[^\r\n]*size=(\d+)([^\r\n]*)/i',
            $text,
            $encoded
        )) {
            if (preg_match('/crc32=([0-9a-fA-F]+)/i', $encoded[4], $trailer)) {
                $crc = trim($trailer[1]);
            }

            $headerSize = (int) $encoded[1];
            $trailerSize = (int) $encoded[3];
            $encoded = $encoded[2];
        } else {
            return false;
        }

        // Make sure the header and trailer file sizes match up.
        if ($headerSize !== $trailerSize) {
            throw new RuntimeException(
                'Header and trailer file sizes do not match. This is a violation of the yEnc specification.'
            );
        }

        // Remove line breaks from the string (use str_replace with array for speed).
        $encoded = str_replace(["\r\n", "\r", "\n"], '', $encoded);

        // Fast decode using optimized method
        $decoded = $this->fastDecode($encoded);

        // Make sure the decoded file size is the same as the size specified in the header.
        $decodedLength = \strlen($decoded);
        if ($decodedLength !== $headerSize) {
            throw new RuntimeException(
                "Header file size ({$headerSize}) and actual file size ({$decodedLength}) do not match. The file is probably corrupt."
            );
        }

        // Check the CRC value
        if ($crc !== '' && strcasecmp($crc, sprintf('%X', crc32($decoded))) !== 0) {
            throw new RuntimeException('CRC32 checksums do not match. The file is probably corrupt.');
        }

        return $decoded;
    }

    /**
     * Fast yEnc decode using strtr and optimized escape handling.
     *
     * @param  string  $encoded  The encoded string (without headers/line breaks)
     * @return string The decoded string
     */
    private function fastDecode(string $encoded): string
    {
        $this->initDecodeTables();

        /** @var array<string, string> $decodeTable */
        $decodeTable = $this->decodeTable;
        /** @var array<string, string> $escapeDecodeTable */
        $escapeDecodeTable = $this->escapeDecodeTable;

        // Check if there are any escape sequences
        $escapePos = strpos($encoded, '=');

        if ($escapePos === false) {
            // No escape sequences - use fast strtr translation
            return strtr($encoded, $decodeTable);
        }

        // Handle escape sequences
        // First, process escape sequences by splitting on '='
        $parts = explode('=', $encoded);
        $result = strtr($parts[0], $decodeTable);

        $count = \count($parts);
        for ($i = 1; $i < $count; $i++) {
            $part = $parts[$i];
            if ($part === '') {
                continue;
            }
            // First character after '=' is the escaped character
            $result .= $escapeDecodeTable[$part[0]];
            // Rest of the part is normal encoded data
            if (isset($part[1])) {
                $result .= strtr(substr($part, 1), $decodeTable);
            }
        }

        return $result;
    }

    /**
     * Decode a string of text encoded with yEnc. Ignores all errors.
     *
     * @param  string  $text  The encoded text to decode.
     * @return string The decoded yEnc string, or the input string if it's not yEnc.
     */
    public function decodeIgnore(string &$text): string
    {
        if (preg_match('/^(=yBegin.*=yEnd[^$]*)$/ims', $text, $input)) {
            // Extract the encoded data, removing headers and line breaks
            $input = preg_replace('/(^=yBegin.*\r\n)/im', '', $input[1], 1);
            $input = preg_replace('/(^=yPart.*\r\n)/im', '', $input, 1);
            $input = preg_replace('/(^=yEnd.*)/im', '', $input, 1);
            $input = str_replace(["\r\n", "\r", "\n"], '', trim($input));

            // Use the fast decode method
            $text = $this->fastDecode($input);
        }

        return $text;
    }

    /**
     * Check if yEnc encoding/decoding is enabled.
     *
     * @return bool Always returns true (PHP implementation is always available)
     */
    public function enabled(): bool
    {
        return true;
    }

    /**
     * Check if the given text appears to be yEnc encoded.
     *
     * @param  string  $text  The text to check
     * @return bool True if the text appears to be yEnc encoded
     */
    public function isYencEncoded(string $text): bool
    {
        return (bool) preg_match('/^=yBegin.*=yEnd/ims', $text);
    }

    /**
     * Encode data using yEnc encoding.
     *
     * @param  string  $data  The data to encode
     * @param  string  $filename  The filename to include in the header
     * @param  int  $lineLength  Maximum line length (1-254, default 128)
     * @param  bool  $includeCrc32  Whether to include CRC32 checksum
     * @return string The yEnc encoded string
     *
     * @throws RuntimeException If line length is invalid
     */
    public function encode(string $data, string $filename, int $lineLength = 128, bool $includeCrc32 = true): string
    {
        // yEnc 1.3 draft doesn't allow line lengths of more than 254 bytes.
        if ($lineLength > 254) {
            $lineLength = 254;
        }

        if ($lineLength < 1) {
            throw new RuntimeException("{$lineLength} is not a valid line length.");
        }

        $encoded = '';
        $stringLength = \strlen($data);

        // Encode each character of the string one at a time.
        for ($i = 0; $i < $stringLength; $i++) {
            $value = ((\ord($data[$i]) + 42) % 256);

            // Escape NULL, TAB, LF, CR, space, . and = characters.
            $encoded .= match ($value) {
                0, 9, 10, 13, 32, 46, 61 => '='.\chr(($value + 64) % 256),
                default => \chr($value),
            };
        }

        $result = '=ybegin line='.$lineLength.
            ' size='.$stringLength.
            ' name='.trim($filename).
            "\r\n".
            trim(chunk_split($encoded, $lineLength)).
            "\r\n=yend size=".$stringLength;

        // Add a CRC32 checksum if desired.
        if ($includeCrc32) {
            $result .= ' crc32='.strtolower(sprintf('%X', crc32($data)));
        }

        return $result;
    }

    /**
     * Extract metadata from a yEnc encoded string without decoding.
     *
     * @param  string  $text  The yEnc encoded text
     * @return array{name: string|null, size: int|null, line: int|null, crc32: string|null}|null
     */
    public function extractMetadata(string $text): ?array
    {
        if (! preg_match('/=ybegin\s+(.+?)\r\n/i', $text, $headerMatch)) {
            return null;
        }

        $header = $headerMatch[1];
        $metadata = [
            'name' => null,
            'size' => null,
            'line' => null,
            'crc32' => null,
        ];

        // Extract name
        if (preg_match('/name=(.+?)(?:\s|$)/i', $header, $match)) {
            $metadata['name'] = trim($match[1]);
        }

        // Extract size
        if (preg_match('/size=(\d+)/i', $header, $match)) {
            $metadata['size'] = (int) $match[1];
        }

        // Extract line length
        if (preg_match('/line=(\d+)/i', $header, $match)) {
            $metadata['line'] = (int) $match[1];
        }

        // Extract CRC32 from trailer
        if (preg_match('/=yend.*crc32=([0-9a-fA-F]+)/i', $text, $match)) {
            $metadata['crc32'] = strtoupper($match[1]);
        }

        return $metadata;
    }
}
