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
 * Class Php.
 */
class PhpYenc
{
    public static function decode(&$text, bool $ignore = false): bool|string
    {
        $crc = '';
        // Extract the yEnc string itself.
        if (preg_match(
            '/=ybegin.*size=([^ $]+).*\\r\\n(.*)\\r\\n=yend.*size=([^ $\\r\\n]+)(.*)/ims',
            $text,
            $encoded
        )) {
            if (preg_match('/crc32=([^ $\\r\\n]+)/ims', $encoded[4], $trailer)) {
                $crc = trim($trailer[1]);
            }

            [$headerSize, $encoded, $trailerSize] = $encoded;
        } else {
            return false;
        }

        // Remove line breaks from the string.
        $encoded = trim(str_replace("\r\n", '', $encoded));

        // Make sure the header and trailer file sizes match up.
        if ($headerSize !== $trailerSize) {
            $message = 'Header and trailer file sizes do not match. This is a violation of the yEnc specification.';
            throw new \RuntimeException($message);
        }

        // Decode.
        $decoded = '';
        $encodedLength = \strlen($encoded);
        for ($chr = 0; $chr < $encodedLength; $chr++) {
            $decoded .= (
                $encoded[$chr] === '=' ?
                    \chr((\ord($encoded[$chr]) - 42) % 256) :
                    \chr((((\ord($encoded[++$chr]) - 64) % 256) - 42) % 256)
            );
        }

        // Make sure the decoded file size is the same as the size specified in the header.
        if (\strlen($decoded) !== $headerSize) {
            $message = 'Header file size ('.$headerSize.') and actual file size ('.\strlen($decoded).') do not match. The file is probably corrupt.';

            throw new \RuntimeException($message);
        }

        // Check the CRC value
        if ($crc !== '' && (strtolower($crc) !== strtolower(sprintf('%04X', crc32($decoded))))) {
            $message = 'CRC32 checksums do not match. The file is probably corrupt.';

            throw new \RuntimeException($message);
        }

        return $decoded;
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
            $text = '';
            $input =
                trim(
                    preg_replace(
                        '/\r\n/im',
                        '',
                        preg_replace(
                            '/(^=yEnd.*)/im',
                            '',
                            preg_replace(
                                '/(^=yPart.*\\r\\n)/im',
                                '',
                                preg_replace('/(^=yBegin.*\\r\\n)/im', '', $input[1], 1),
                                1
                            ),
                            1
                        )
                    )
                );

            $length = \strlen($input);
            for ($chr = 0; $chr < $length; $chr++) {
                $text .= (
                    $input[$chr] === '=' ?
                        \chr((((\ord($input[++$chr]) - 64) % 256) - 42) % 256) :
                        \chr((\ord($input[$chr]) - 42) % 256)
                );
            }
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
