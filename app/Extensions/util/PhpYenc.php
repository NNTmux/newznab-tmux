<?php

/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program (see LICENSE.txt in the base directory.  If
 * not, see:.
 *
 * @link      <http://www.gnu.org/licenses/>.
 *
 * @author    niel
 * @copyright 2016 nZEDb
 */

namespace App\Extensions\util;

/**
 * Class PhpYenc.
 * Optimized yEnc encoder/decoder implementation.
 */
class PhpYenc
{
    /**
     * Pre-computed decode translation table for non-escaped characters.
     * Maps yEnc encoded byte to decoded byte: (byte - 42) % 256
     * @var array<string, string>|null
     */
    private static ?array $decodeTable = null;

    /**
     * Pre-computed decode translation table for escaped characters.
     * Maps yEnc escaped byte to decoded byte: ((byte - 64) - 42) % 256
     * @var array<string, string>|null
     */
    private static ?array $escapeDecodeTable = null;

    /**
     * Initialize the decode translation tables (lazy initialization).
     */
    private static function initDecodeTables(): void
    {
        if (self::$decodeTable !== null) {
            return;
        }

        self::$decodeTable = [];
        self::$escapeDecodeTable = [];

        for ($i = 0; $i < 256; $i++) {
            $char = \chr($i);
            // Standard decode: (byte - 42) % 256
            self::$decodeTable[$char] = \chr(($i - 42 + 256) % 256);
            // Escape decode: ((byte - 64) - 42) % 256
            self::$escapeDecodeTable[$char] = \chr((($i - 64 - 42) + 512) % 256);
        }
    }

    public static function decode(&$text, bool $ignore = false): bool|string
    {
        $crc = '';
        // Extract the yEnc string itself.
        if (preg_match(
            '/=ybegin.*size=(\d+).*\r\n(.*)(?:\r\n)?=yend.*size=(\d+)(.*)/ims',
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
            $message = 'Header and trailer file sizes do not match. This is a violation of the yEnc specification.';
            throw new \RuntimeException($message);
        }

        // Remove line breaks from the string (use str_replace with array for speed).
        $encoded = str_replace(["\r\n", "\r", "\n"], '', $encoded);

        // Fast decode using optimized method
        $decoded = self::fastDecode($encoded);

        // Make sure the decoded file size is the same as the size specified in the header.
        $decodedLength = \strlen($decoded);
        if ($decodedLength !== $headerSize) {
            $message = 'Header file size ('.$headerSize.') and actual file size ('.$decodedLength.') do not match. The file is probably corrupt.';

            throw new \RuntimeException($message);
        }

        // Check the CRC value
        if ($crc !== '' && (strcasecmp($crc, sprintf('%X', crc32($decoded))) !== 0)) {
            $message = 'CRC32 checksums do not match. The file is probably corrupt.';

            throw new \RuntimeException($message);
        }

        return $decoded;
    }

    /**
     * Fast yEnc decode using strtr and optimized escape handling.
     *
     * @param string $encoded The encoded string (without headers/line breaks)
     * @return string The decoded string
     */
    private static function fastDecode(string $encoded): string
    {
        self::initDecodeTables();

        /** @var array<string, string> $decodeTable */
        $decodeTable = self::$decodeTable;
        /** @var array<string, string> $escapeDecodeTable */
        $escapeDecodeTable = self::$escapeDecodeTable;

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
     * @return string The decoded yEnc string, or the input string, if it's not yEnc.
     */
    public static function decodeIgnore(string &$text): string
    {
        if (preg_match('/^(=yBegin.*=yEnd[^$]*)$/ims', $text, $input)) {
            // Extract the encoded data, removing headers and line breaks
            $input = preg_replace('/(^=yBegin.*\r\n)/im', '', $input[1], 1);
            $input = preg_replace('/(^=yPart.*\r\n)/im', '', $input, 1);
            $input = preg_replace('/(^=yEnd.*)/im', '', $input, 1);
            $input = str_replace(["\r\n", "\r", "\n"], '', trim($input));

            // Use the fast decode method
            $text = self::fastDecode($input);
        }

        return $text;
    }

    public static function enabled(): bool
    {
        return true;
    }

    public static function encode($data, $filename, int $lineLength = 128, bool $crc32 = true): string
    {
        // yEnc 1.3 draft doesn't allow line lengths of more than 254 bytes.
        if ($lineLength > 254) {
            $lineLength = 254;
        }

        if ($lineLength < 1) {
            $message = $lineLength.' is not a valid line length.';

            throw new \RuntimeException($message);
        }

        $encoded = '';
        $stringLength = \strlen($data);
        // Encode each character of the string one at a time.
        foreach ($data as $i => $iValue) {
            $value = ((\ord($iValue) + 42) % 256);

            // Escape NULL, TAB, LF, CR, space, . and = characters.
            $encoded .= match ($value) {
                0, 10, 13, 61 => ('='.\chr(($value + 64) % 256)),
                default => \chr($value),
            };
        }

        $encoded =
            '=ybegin line='.
            $lineLength.
            ' size='.
            $stringLength.
            ' name='.
            trim($filename).
            "\r\n".
            trim(chunk_split($encoded, $lineLength)).
            "\r\n=yend size=".
            $stringLength;

        // Add a CRC32 checksum if desired.
        if ($crc32 === true) {
            $encoded .= ' crc32='.strtolower(sprintf('%X', crc32($data)));
        }

        return $encoded;
    }
}
