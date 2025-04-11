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
             * Class PhpYenc for encoding and decoding yEnc format.
             */
            class PhpYenc
            {
                /**
                 * Decode a yEnc encoded string.
                 *
                 * @param string &$text The text to decode, passed by reference
                 * @param bool $ignore Whether to ignore errors (not used in this implementation)
                 * @return bool|string The decoded string on success, false on failure
                 * @throws \RuntimeException if the file size doesn't match or CRC check fails
                 */
                public static function decode(string &$text, bool $ignore = false): bool|string
                {
                    $crc = '';
                    // Extract the yEnc string itself.
                    if (!preg_match('/=ybegin.*size=([^ $]+).*\r\n(.*)\r\n=yend.*size=([^ $\r\n]+)(.*)/ims', $text, $encoded)) {
                        return false;
                    }

                    if (preg_match('/crc32=([^ $\r\n]+)/ims', $encoded[4], $trailer)) {
                        $crc = trim($trailer[1]);
                    }

                    $headerSize = (int)$encoded[1];
                    $trailerSize = (int)$encoded[3];
                    $encoded = $encoded[2];

                    // Remove line breaks from the string.
                    $encoded = trim(str_replace("\r\n", '', $encoded));

                    // Make sure the header and trailer file sizes match up.
                    if ($headerSize !== $trailerSize) {
                        throw new \RuntimeException('Header and trailer file sizes do not match. This is a violation of the yEnc specification.');
                    }

                    // Decode
                    $decoded = '';
                    $encodedLength = strlen($encoded);
                    $chr = 0;

                    while ($chr < $encodedLength) {
                        if ($encoded[$chr] === '=') {
                            // Handle escaped character
                            $chr++;
                            if ($chr < $encodedLength) {
                                $decoded .= chr(((ord($encoded[$chr]) - 64) % 256));
                            }
                        } else {
                            // Normal character
                            $decoded .= chr((ord($encoded[$chr]) - 42) % 256);
                        }
                        $chr++;
                    }

                    // Make sure the decoded file size is the same as the size specified in the header.
                    if (strlen($decoded) !== $headerSize) {
                        throw new \RuntimeException(
                            "Header file size ({$headerSize}) and actual file size (" . strlen($decoded) . ") do not match. The file is probably corrupt."
                        );
                    }

                    // Check the CRC value
                    if ($crc !== '' && (strtolower($crc) !== strtolower(sprintf('%08X', crc32($decoded))))) {
                        throw new \RuntimeException('CRC32 checksums do not match. The file is probably corrupt.');
                    }

                    return $decoded;
                }

                /**
                 * Decode a string of text encoded with yEnc. Ignores all errors.
                 *
                 * @param string &$text The encoded text to decode.
                 * @return string The decoded yEnc string, or the input string, if it's not yEnc.
                 */
                public static function decodeIgnore(string &$text): string
                {
                    if (!preg_match('/^(=yBegin.*=yEnd[^$]*)$/ims', $text, $matches)) {
                        return $text;
                    }

                    $input = $matches[1];
                    // Remove headers and footers
                    $input = preg_replace('/^=yBegin.*\r\n/im', '', $input);
                    $input = preg_replace('/^=yPart.*\r\n/im', '', $input);
                    $input = preg_replace('/^=yEnd.*/im', '', $input);
                    $input = trim(str_replace("\r\n", '', $input));

                    $result = '';
                    $length = strlen($input);
                    $chr = 0;

                    while ($chr < $length) {
                        if ($input[$chr] === '=') {
                            $chr++;
                            if ($chr < $length) {
                                $result .= chr(((ord($input[$chr]) - 64) % 256));
                            }
                        } else {
                            $result .= chr((ord($input[$chr]) - 42) % 256);
                        }
                        $chr++;
                    }

                    $text = $result;
                    return $text;
                }

                /**
                 * Check if yEnc functionality is enabled.
                 *
                 * @return bool Always returns true
                 */
                public static function enabled(): bool
                {
                    return true;
                }

                /**
                 * Encode data using yEnc format.
                 *
                 * @param string $data The data to encode
                 * @param string $filename The filename to include in the yEnc header
                 * @param int $lineLength The line length to use (max 254)
                 * @param bool $crc32 Whether to include a CRC32 checksum
                 * @return string The yEnc encoded data
                 * @throws \RuntimeException if line length is invalid
                 */
                public static function encode(string $data, string $filename, int $lineLength = 128, bool $crc32 = true): string
                {
                    // yEnc 1.3 draft doesn't allow line lengths of more than 254 bytes.
                    if ($lineLength > 254) {
                        $lineLength = 254;
                    }

                    if ($lineLength < 1) {
                        throw new \RuntimeException("$lineLength is not a valid line length.");
                    }

                    $stringLength = strlen($data);
                    $encoded = '';

                    // Encode each character of the string one at a time.
                    for ($i = 0; $i < $stringLength; $i++) {
                        $value = ((ord($data[$i]) + 42) % 256);

                        // Escape NULL, TAB, LF, CR, space, . and = characters.
                        if (in_array($value, [0, 10, 13, 61])) {
                            $encoded .= '=' . chr(($value + 64) % 256);
                        } else {
                            $encoded .= chr($value);
                        }
                    }

                    $encoded = "=ybegin line=$lineLength size=$stringLength name=" . trim($filename) . "\r\n" .
                              trim(chunk_split($encoded, $lineLength)) .
                              "\r\n=yend size=$stringLength";

                    // Add a CRC32 checksum if desired.
                    if ($crc32) {
                        $encoded .= ' crc32=' . strtolower(sprintf('%08X', crc32($data)));
                    }

                    return $encoded;
                }
            }
