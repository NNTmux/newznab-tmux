<?php

namespace Blacklight\processing\post;

/**
 * Minimal partial 7z header parser to recover file names from an in-memory buffer.
 * Supports unencoded headers directly; encoded headers are flagged so caller can fallback
 * to external 7z listing for sizes/attributes. Provides heuristics for encryption.
 */
class SevenZipPartialParser
{
    private string $data;

    private int $len;

    private array $names = [];

    private bool $parsed = false;

    private bool $encodedHeader = false; // flag if we encountered kEncodedHeader

    private bool $encrypted = false; // heuristic flag if AES encryption detected

    // 7z IDs we care about
    private const K_HEADER = 0x01;

    private const K_ARCHIVE_PROPERTIES = 0x02; // skipped

    private const K_ADDITIONAL_STREAMS_INFO = 0x03; // skipped

    private const K_MAIN_STREAMS_INFO = 0x04; // skipped

    private const K_FILES_INFO = 0x05;

    private const K_END = 0x00;

    private const K_ENCODED_HEADER = 0x17; // unsupported here

    private const K_NAME = 0x11;

    public function __construct(string $data)
    {
        $this->data = $data;
        $this->len = strlen($data);
    }

    /**
     * Public accessor: returns recovered filenames (UTF-8) or empty array.
     */
    public function getFileNames(): array
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->names;
    }

    /**
     * Public accessor: returns true if AES encryption is detected (heuristic).
     */
    public function isEncrypted(): bool
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->encrypted;
    }

    public function hasEncodedHeader(): bool
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->encodedHeader;
    }

    private function parse(): void
    {
        $this->parsed = true;
        if ($this->len < 32) { // need at least fixed header
            return;
        }
        // Signature check
        if (strncmp($this->data, "\x37\x7A\xBC\xAF\x27\x1C", 6) !== 0) {
            return;
        }
        $nextHeaderOffset = $this->readUInt64LE(12);
        $nextHeaderSize = $this->readUInt64LE(20);
        // Bounds sanity
        if ($nextHeaderSize <= 0 || $nextHeaderSize > 4 * 1024 * 1024) { // cap 4MB header
            return;
        }
        $nextHeaderStart = 32 + $nextHeaderOffset;
        $nextHeaderEnd = $nextHeaderStart + $nextHeaderSize;
        if ($nextHeaderEnd > $this->len) { // incomplete buffer
            return;
        }
        $cursor = $nextHeaderStart;
        // First byte may be kEncodedHeader (unsupported) or kHeader
        $id = ord($this->data[$cursor]);
        if ($id === self::K_ENCODED_HEADER) {
            $this->encodedHeader = true; // caller can try external 7z listing fallback
            // Heuristic: scan a limited window after this byte for AES method ID (06 F1 07 01) indicating encryption.
            $scan = substr($this->data, $cursor, min(512, $this->len - $cursor));
            if (strpos($scan, "\x06\xF1\x07\x01") !== false) {
                $this->encrypted = true;
            }

            return; // we don't decode here
        }
        if ($id !== self::K_HEADER) {
            return; // unexpected structure
        }
        $cursor++;
        // Loop until K_END looking for K_FILES_INFO (0x05)
        while ($cursor < $nextHeaderEnd) {
            $id = ord($this->data[$cursor]);
            $cursor++;
            if ($id === self::K_END) {
                break; // done
            }
            if ($id === self::K_FILES_INFO) {
                $cursor = $this->parseFilesInfo($cursor, $nextHeaderEnd);
                break; // stop after names
            } else {
                // Skip blocks we don't parse by walking their internal structure heuristically.
                // For archive/main streams info we skip until their terminating K_END.
                if (in_array($id, [self::K_ARCHIVE_PROPERTIES, self::K_ADDITIONAL_STREAMS_INFO, self::K_MAIN_STREAMS_INFO], true)) {
                    $cursor = $this->skipUntilEnd($cursor, $nextHeaderEnd);
                    if ($cursor === -1) {
                        break;
                    }
                } else {
                    // Unknown ID – bail out
                    break;
                }
            }
        }
    }

    private function parseFilesInfo(int $cursor, int $limit): int
    {
        // Number of files (VInt)
        $numFiles = $this->readVIntAt($cursor, $value, $newCursor, $limit) ? $value : null;
        if ($numFiles === null || $numFiles < 0 || $numFiles > 10000) { // sanity cap
            return $limit; // abort
        }
        $cursor = $newCursor;
        // Property loop until K_END
        $names = [];
        while ($cursor < $limit) {
            $propId = ord($this->data[$cursor]);
            $cursor++;
            if ($propId === self::K_END) {
                break;
            }
            // Size of property data (VInt)
            if (! $this->readVIntAt($cursor, $propSize, $cursor, $limit)) {
                break;
            }
            if ($propSize < 0 || $propSize > ($limit - $cursor)) {
                break;
            }
            if ($propId === self::K_NAME) {
                if ($propSize < 1) {
                    break;
                }
                $external = ord($this->data[$cursor]);
                if ($external !== 0) { // External data not supported
                    break;
                }
                $nameBytes = $propSize - 1;
                $cursor++;
                if ($nameBytes <= 0) {
                    break;
                }
                $blob = substr($this->data, $cursor, $nameBytes);
                // Ensure even length for UTF-16LE. Truncate last byte if odd.
                if (($nameBytes & 1) === 1) {
                    $blob = substr($blob, 0, -1);
                }
                // Split on UTF-16LE null terminators (00 00)
                $segments = preg_split('/\x00\x00/', $blob);
                foreach ($segments as $seg) {
                    if ($seg === '') {
                        continue;
                    }
                    $utf8 = @iconv('UTF-16LE', 'UTF-8//IGNORE', $seg); // may return false
                    if ($utf8 === false) {
                        continue;
                    }
                    $utf8 = trim($utf8);
                    if ($utf8 === '') {
                        continue;
                    }
                    // Basic filtering – exclude paths with directory separators beyond simple relative path
                    $utf8Clean = str_replace(['\\'], '/', $utf8);
                    // Remove leading './'
                    $utf8Clean = preg_replace('#^\./#', '', $utf8Clean);
                    if ($utf8Clean === '' || substr_count($utf8Clean, '/') > 8) { // excessive depth -> skip
                        continue;
                    }
                    $names[] = $utf8Clean;
                    if (count($names) >= $numFiles) {
                        break;
                    }
                }
                // Done reading this property
                $cursor += $nameBytes;
                // We collected names; we can stop early.
                $this->names = array_values(array_unique($names));

                return $limit;
            } else {
                // Skip property we don't care about
                $cursor += $propSize;
            }
        }
        // Assign if we gathered any
        if ($names) {
            $this->names = array_values(array_unique($names));
        }

        return $limit;
    }

    private function skipUntilEnd(int $cursor, int $limit): int
    {
        while ($cursor < $limit) {
            $id = ord($this->data[$cursor]);
            $cursor++;
            if ($id === self::K_END) {
                return $cursor;
            }
            // Property-like: read size then skip
            if (! $this->readVIntAt($cursor, $propSize, $cursor, $limit)) {
                return -1;
            }
            if ($propSize < 0 || $propSize > ($limit - $cursor)) {
                return -1;
            }
            $cursor += $propSize;
        }

        return -1;
    }

    /**
     * Reads a 7z variable-length integer at offset, returns value via reference.
     */
    private function readVIntAt(int $offset, ?int &$value, ?int &$newOffset, int $limit): bool
    {
        $value = 0;
        $shift = 0;
        $pos = $offset;
        while ($pos < $limit && $shift <= 63) {
            $b = ord($this->data[$pos]);
            $pos++;
            $value |= ($b & 0x7F) << $shift;
            if (($b & 0x80) === 0) {
                $newOffset = $pos;

                return true;
            }
            $shift += 7;
        }

        return false;
    }

    private function readUInt64LE(int $offset): int
    {
        if ($offset + 8 > $this->len) {
            return 0;
        }
        $v = 0;
        for ($i = 0; $i < 8; $i++) {
            $v |= ord($this->data[$offset + $i]) << ($i * 8);
        }

        // Constrain to PHP int (on 64-bit fine; on 32-bit may overflow but those environments uncommon here)
        return $v & 0xFFFFFFFFFFFFFFFF;
    }
}
