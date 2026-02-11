<?php

namespace App\Services\Archives;

/**
 * Enhanced partial 7z header parser to recover file information from an in-memory buffer.
 * Supports unencoded headers directly; encoded headers are flagged so caller can fallback
 * to external 7z listing. Provides heuristics for encryption and compression detection.
 *
 * Features:
 * - Extracts file names, sizes, attributes, timestamps, and CRC values
 * - Detects compression methods (LZMA, LZMA2, PPMd, BZip2, etc.)
 * - Heuristic encryption detection (AES-256, header encryption)
 * - Fallback scanning for partial/corrupted archives
 * - Solid archive detection
 */
class SevenZipPartialParser
{
    private string $data;

    private int $len;

    /**
     * @var array<string, mixed>
     */
    private array $names = [];

    /**
     * @var array<string, mixed>
     */
    private array $files = []; // Extended file info with metadata

    /**
     * @var array<string, mixed>
     */
    private array $sizes = []; // Uncompressed sizes

    /**
     * @var array<string, mixed>
     */
    private array $packedSizes = []; // Compressed sizes

    /**
     * @var array<string, mixed>
     */
    private array $crcs = []; // CRC32 values

    /**
     * @var array<string, mixed>
     */
    private array $attributes = []; // File attributes (directory, readonly, etc.)

    /**
     * @var array<string, mixed>
     */
    private array $mtimes = []; // Modification times

    /**
     * @var array<string, mixed>
     */
    private array $ctimes = []; // Creation times

    /**
     * @var array<string, mixed>
     */
    private array $atimes = []; // Access times

    private bool $parsed = false;

    private bool $encodedHeader = false; // flag if we encountered kEncodedHeader

    private bool $encrypted = false; // heuristic flag if AES encryption detected

    private bool $headerEncrypted = false; // flag if header itself is encrypted

    private bool $solidArchive = false; // flag for solid archives

    private int $numFiles = 0; // Total number of files detected

    /**
     * @var array<string, mixed>
     */
    private array $compressionMethods = []; // Detected compression methods

    private int $totalUnpackedSize = 0; // Total unpacked size

    private int $totalPackedSize = 0; // Total packed size

    private string $lastError = ''; // Last error message for debugging

    // 7z Property IDs
    private const K_END = 0x00;

    private const K_HEADER = 0x01;

    private const K_ARCHIVE_PROPERTIES = 0x02;

    private const K_ADDITIONAL_STREAMS_INFO = 0x03;

    private const K_MAIN_STREAMS_INFO = 0x04;

    private const K_FILES_INFO = 0x05;

    private const K_PACK_INFO = 0x06;

    private const K_UNPACK_INFO = 0x07;

    private const K_SUBSTREAMS_INFO = 0x08;

    private const K_SIZE = 0x09;

    private const K_CRC = 0x0A;

    private const K_FOLDER = 0x0B;

    private const K_CODERS_UNPACK_SIZE = 0x0C;

    private const K_NUM_UNPACK_STREAM = 0x0D;

    private const K_EMPTY_STREAM = 0x0E;

    private const K_EMPTY_FILE = 0x0F;

    private const K_ANTI = 0x10;

    private const K_NAME = 0x11;

    private const K_CTIME = 0x12;

    private const K_ATIME = 0x13;

    private const K_MTIME = 0x14;

    private const K_WIN_ATTRIBUTES = 0x15;

    /** @phpstan-ignore classConstant.unused */
    private const K_COMMENT = 0x16;

    private const K_ENCODED_HEADER = 0x17;

    /** @phpstan-ignore classConstant.unused */
    private const K_START_POS = 0x18;

    /** @phpstan-ignore classConstant.unused */
    private const K_DUMMY = 0x19;

    // Compression method IDs
    private const METHOD_COPY = "\x00";

    private const METHOD_LZMA = "\x03\x01\x01";

    private const METHOD_LZMA2 = "\x21";

    private const METHOD_PPMD = "\x03\x04\x01";

    /** @phpstan-ignore classConstant.unused */
    private const METHOD_BCJ = "\x03\x03\x01\x03";

    /** @phpstan-ignore classConstant.unused */
    private const METHOD_BCJ2 = "\x03\x03\x01\x1B";

    private const METHOD_DEFLATE = "\x04\x01\x08";

    private const METHOD_BZIP2 = "\x04\x02\x02";

    private const METHOD_AES = "\x06\xF1\x07\x01";

    // Windows file attributes
    /** @phpstan-ignore classConstant.unused */
    private const FILE_ATTRIBUTE_READONLY = 0x01;

    /** @phpstan-ignore classConstant.unused */
    private const FILE_ATTRIBUTE_HIDDEN = 0x02;

    /** @phpstan-ignore classConstant.unused */
    private const FILE_ATTRIBUTE_SYSTEM = 0x04;

    private const FILE_ATTRIBUTE_DIRECTORY = 0x10;

    /** @phpstan-ignore classConstant.unused */
    private const FILE_ATTRIBUTE_ARCHIVE = 0x20;

    public function __construct(string $data)
    {
        $this->data = $data;
        $this->len = strlen($data);
    }

    /**
     * Public accessor: returns recovered filenames (UTF-8) or empty array.
     *
     * @return array<string, mixed>
     */
    public function getFileNames(): array
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->names;
    }

    /**
     * Returns detailed file information with all available metadata.
     *
     * @return array<string, mixed>
     */
    public function getFiles(): array
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->files;
    }

    /**
     * Returns uncompressed file sizes indexed by file index.
     *
     * @return array<string, mixed>
     */
    public function getSizes(): array
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->sizes;
    }

    /**
     * Returns CRC32 values as hex strings indexed by file index.
     *
     * @return array<string, mixed>
     */
    public function getCRCs(): array
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->crcs;
    }

    /**
     * Returns file attributes indexed by file index.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->attributes;
    }

    /**
     * Returns modification times (Unix timestamps) indexed by file index.
     *
     * @return array<string, mixed>
     */
    public function getModificationTimes(): array
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->mtimes;
    }

    /**
     * Returns detected compression methods used in the archive.
     *
     * @return array<string, mixed>
     */
    public function getCompressionMethods(): array
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return array_unique($this->compressionMethods);
    }

    /**
     * Returns total number of files detected.
     */
    public function getFileCount(): int
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->numFiles;
    }

    /**
     * Returns total unpacked (uncompressed) size of all files.
     */
    public function getTotalUnpackedSize(): int
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->totalUnpackedSize;
    }

    /**
     * Returns total packed (compressed) size.
     */
    public function getTotalPackedSize(): int
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->totalPackedSize;
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

    /**
     * Returns true if the header itself is encrypted.
     */
    public function isHeaderEncrypted(): bool
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->headerEncrypted;
    }

    /**
     * Returns true if this is a solid archive.
     */
    public function isSolidArchive(): bool
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->solidArchive;
    }

    public function hasEncodedHeader(): bool
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return $this->encodedHeader;
    }

    /**
     * Returns the last error message for debugging.
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Check if the data appears to be a valid 7z archive (has correct signature).
     */
    public function isValid7zSignature(): bool
    {
        return $this->len >= 6 && strncmp($this->data, "\x37\x7A\xBC\xAF\x27\x1C", 6) === 0;
    }

    /**
     * Returns compression ratio as a float (0.0 to 1.0+), or null if unknown.
     */
    public function getCompressionRatio(): ?float
    {
        if (! $this->parsed) {
            $this->parse();
        }

        if ($this->totalUnpackedSize > 0 && $this->totalPackedSize > 0) {
            return $this->totalPackedSize / $this->totalUnpackedSize;
        }

        return null;
    }

    private function parse(): void
    {
        $this->parsed = true;

        if ($this->len < 32) { // need at least fixed header
            $this->lastError = 'Data too short for 7z header (need at least 32 bytes)';
            // Try fallback scanning even for very short data
            $this->fallbackScanForFilenames();

            return;
        }

        // Signature check
        if (strncmp($this->data, "\x37\x7A\xBC\xAF\x27\x1C", 6) !== 0) {
            $this->lastError = 'Invalid 7z signature';
            // Try fallback scanning anyway - data might be partial
            $this->fallbackScanForFilenames();

            return;
        }

        // Read header version
        $majorVersion = ord($this->data[6]);
        $minorVersion = ord($this->data[7]);

        // Read next header info
        $nextHeaderOffset = $this->readUInt64LE(12);
        $nextHeaderSize = $this->readUInt64LE(20);
        $nextHeaderCRC = $this->readUInt32LE(28);

        // Scan the packed data region for compression method signatures
        $this->scanForCompressionMethods(32, min(32 + $nextHeaderOffset, $this->len));

        // Bounds sanity
        if ($nextHeaderSize <= 0 || $nextHeaderSize > 16 * 1024 * 1024) { // cap 16MB header
            $this->lastError = 'Invalid next header size: '.$nextHeaderSize;
            $this->fallbackScanForFilenames();

            return;
        }

        $nextHeaderStart = 32 + $nextHeaderOffset;
        $nextHeaderEnd = $nextHeaderStart + $nextHeaderSize;

        if ($nextHeaderEnd > $this->len) {
            // Incomplete buffer - still try to parse what we have
            $this->lastError = 'Incomplete archive data (header extends beyond buffer)';

            // Try partial parsing with available data
            if ($nextHeaderStart < $this->len) {
                $this->parsePartialHeader((int) $nextHeaderStart, $this->len);
            }

            // Also try fallback scanning
            $this->fallbackScanForFilenames();

            return;
        }

        $cursor = (int) $nextHeaderStart;

        // First byte may be kEncodedHeader (unsupported) or kHeader
        $id = ord($this->data[$cursor]);

        if ($id === self::K_ENCODED_HEADER) {
            $this->encodedHeader = true;

            // Parse encoded header for compression info and encryption detection
            $this->parseEncodedHeader($cursor, (int) $nextHeaderEnd);

            // Fallback scan for any readable filenames
            $this->fallbackScanForFilenames();

            return;
        }

        if ($id !== self::K_HEADER) {
            $this->lastError = 'Unexpected header ID: '.$id;
            $this->fallbackScanForFilenames();

            return;
        }

        $cursor++;

        // Loop until K_END looking for various info blocks
        while ($cursor < $nextHeaderEnd) {
            $id = ord($this->data[$cursor]);
            $cursor++;

            if ($id === self::K_END) {
                break;
            }

            switch ($id) {
                case self::K_ARCHIVE_PROPERTIES:
                    $cursor = $this->skipUntilEnd($cursor, (int) $nextHeaderEnd);
                    break;

                case self::K_ADDITIONAL_STREAMS_INFO:
                    $cursor = $this->skipUntilEnd($cursor, (int) $nextHeaderEnd);
                    break;

                case self::K_MAIN_STREAMS_INFO:
                    $cursor = $this->parseMainStreamsInfo($cursor, (int) $nextHeaderEnd);
                    break;

                case self::K_FILES_INFO:
                    $cursor = $this->parseFilesInfo($cursor, (int) $nextHeaderEnd);
                    break;

                default:
                    // Try to skip unknown property
                    if ($this->readVIntAt($cursor, $propSize, $cursor, (int) $nextHeaderEnd)) {
                        $cursor += $propSize;
                    } else {
                        $cursor = -1;
                    }
                    break;
            }

            if ($cursor === -1) {
                break;
            }
        }

        // Build consolidated file info array
        $this->buildFileInfo();

        // If we didn't find any files, try fallback
        if (empty($this->names)) {
            $this->fallbackScanForFilenames();
        }
    }

    /**
     * Parse encoded header to extract compression method info and detect encryption.
     */
    private function parseEncodedHeader(int $cursor, int $limit): void
    {
        $cursor++; // skip K_ENCODED_HEADER byte

        // Scan for AES encryption signature
        $scan = substr($this->data, $cursor, min(1024, $limit - $cursor));
        if (strpos($scan, self::METHOD_AES) !== false) {
            $this->encrypted = true;
            $this->headerEncrypted = true;
        }

        // Look for streams info
        while ($cursor < $limit) {
            $id = ord($this->data[$cursor]);
            $cursor++;

            if ($id === self::K_END) {
                break;
            }

            if ($id === self::K_PACK_INFO) {
                $cursor = $this->parsePackInfo($cursor, $limit);
            } elseif ($id === self::K_UNPACK_INFO) {
                $cursor = $this->parseUnpackInfo($cursor, $limit);
            } elseif ($id === self::K_SUBSTREAMS_INFO) {
                $cursor = $this->skipUntilEnd($cursor, $limit);
            } else {
                // Skip unknown
                if (! $this->readVIntAt($cursor, $propSize, $cursor, $limit)) {
                    break;
                }
                $cursor += $propSize;
            }

            if ($cursor === -1) {
                break;
            }
        }
    }

    /**
     * Parse a partial/incomplete header to extract whatever information is available.
     */
    private function parsePartialHeader(int $start, int $end): void
    {
        $cursor = $start;

        // Try to identify what kind of block this might be
        if ($cursor < $end) {
            $id = ord($this->data[$cursor]);

            if ($id === self::K_ENCODED_HEADER) {
                $this->encodedHeader = true;
                $scan = substr($this->data, $cursor, min(512, $end - $cursor));
                if (strpos($scan, self::METHOD_AES) !== false) {
                    $this->encrypted = true;
                    $this->headerEncrypted = true;
                }
            } elseif ($id === self::K_HEADER) {
                $cursor++;
                // Try to parse as much as possible
                while ($cursor < $end - 1) {
                    $blockId = ord($this->data[$cursor]);
                    if ($blockId === self::K_FILES_INFO) {
                        $cursor++;
                        $this->parseFilesInfo($cursor, $end);
                        break;
                    }
                    $cursor++;
                }
            }
        }
    }

    /**
     * Parse MainStreamsInfo to get packed/unpacked sizes and detect solid archives.
     */
    private function parseMainStreamsInfo(int $cursor, int $limit): int
    {
        while ($cursor < $limit) {
            $id = ord($this->data[$cursor]);
            $cursor++;

            if ($id === self::K_END) {
                return $cursor;
            }

            switch ($id) {
                case self::K_PACK_INFO:
                    $cursor = $this->parsePackInfo($cursor, $limit);
                    break;

                case self::K_UNPACK_INFO:
                    $cursor = $this->parseUnpackInfo($cursor, $limit);
                    break;

                case self::K_SUBSTREAMS_INFO:
                    $cursor = $this->parseSubstreamsInfo($cursor, $limit);
                    break;

                default:
                    return $this->skipUntilEnd($cursor - 1, $limit);
            }

            if ($cursor === -1) {
                return -1;
            }
        }

        return $cursor;
    }

    /**
     * Parse PackInfo to get packed sizes.
     */
    private function parsePackInfo(int $cursor, int $limit): int
    {
        // PackPos (VInt)
        if (! $this->readVIntAt($cursor, $packPos, $cursor, $limit)) {
            return -1;
        }

        // NumPackStreams (VInt)
        if (! $this->readVIntAt($cursor, $numPackStreams, $cursor, $limit)) {
            return -1;
        }

        while ($cursor < $limit) {
            $id = ord($this->data[$cursor]);
            $cursor++;

            if ($id === self::K_END) {
                return $cursor;
            }

            if ($id === self::K_SIZE) {
                // Read packed sizes
                for ($i = 0; $i < $numPackStreams && $cursor < $limit; $i++) {
                    if ($this->readVIntAt($cursor, $size, $cursor, $limit)) {
                        $this->packedSizes[] = $size;
                        $this->totalPackedSize += $size;
                    }
                }
            } elseif ($id === self::K_CRC) {
                // Skip CRC info
                $cursor = $this->skipBitVector($cursor, $limit, $numPackStreams);
            } else {
                return -1;
            }
        }

        return $cursor;
    }

    /**
     * Parse UnpackInfo to get unpack sizes and detect compression methods.
     */
    private function parseUnpackInfo(int $cursor, int $limit): int
    {
        while ($cursor < $limit) {
            $id = ord($this->data[$cursor]);
            $cursor++;

            if ($id === self::K_END) {
                return $cursor;
            }

            if ($id === self::K_FOLDER) {
                $cursor = $this->parseFolderInfo($cursor, $limit);
            } elseif ($id === self::K_CODERS_UNPACK_SIZE) {
                $cursor = $this->parseCodersUnpackSize($cursor, $limit);
            } elseif ($id === self::K_CRC) {
                if (! $this->readVIntAt($cursor, $propSize, $cursor, $limit)) {
                    return -1;
                }
                $cursor += $propSize;
            } else {
                if (! $this->readVIntAt($cursor, $propSize, $cursor, $limit)) {
                    return -1;
                }
                $cursor += $propSize;
            }

            if ($cursor === -1) {
                return -1;
            }
        }

        return $cursor;
    }

    /**
     * Parse FolderInfo to detect compression methods and solid archive structure.
     */
    private function parseFolderInfo(int $cursor, int $limit): int
    {
        // NumFolders (VInt)
        if (! $this->readVIntAt($cursor, $numFolders, $cursor, $limit)) {
            return -1;
        }

        // If there's only one folder with multiple files, it's likely solid
        if ($numFolders === 1 && $this->numFiles > 1) {
            $this->solidArchive = true;
        }

        // External flag
        if ($cursor >= $limit) {
            return -1;
        }
        $external = ord($this->data[$cursor]);
        $cursor++;

        if ($external !== 0) {
            // External data - skip
            if (! $this->readVIntAt($cursor, $dataIndex, $cursor, $limit)) {
                return -1;
            }

            return $cursor;
        }

        // Parse each folder
        for ($i = 0; $i < $numFolders && $cursor < $limit; $i++) {
            $cursor = $this->parseFolder($cursor, $limit);
            if ($cursor === -1) {
                return -1;
            }
        }

        return $cursor;
    }

    /**
     * Parse a single Folder structure to extract compression method info.
     */
    private function parseFolder(int $cursor, int $limit): int
    {
        // NumCoders (VInt)
        if (! $this->readVIntAt($cursor, $numCoders, $cursor, $limit)) {
            return -1;
        }

        $totalInputStreams = 0;
        $totalOutputStreams = 0;

        for ($i = 0; $i < $numCoders && $cursor < $limit; $i++) {
            // Coder flags
            $flags = ord($this->data[$cursor]);
            $cursor++;

            $codecIdSize = $flags & 0x0F;
            $isComplex = ($flags & 0x10) !== 0;
            $hasAttributes = ($flags & 0x20) !== 0;

            // Codec ID
            if ($cursor + $codecIdSize > $limit) {
                return -1;
            }
            $codecId = substr($this->data, $cursor, $codecIdSize);
            $cursor += $codecIdSize;

            // Identify compression method
            $method = $this->identifyCompressionMethod($codecId);
            if ($method !== null) {
                $this->compressionMethods[] = $method;

                // Check for AES encryption
                if ($method === 'AES-256') {
                    $this->encrypted = true;
                }
            }

            if ($isComplex) {
                // NumInStreams (VInt)
                if (! $this->readVIntAt($cursor, $numIn, $cursor, $limit)) {
                    return -1;
                }
                // NumOutStreams (VInt)
                if (! $this->readVIntAt($cursor, $numOut, $cursor, $limit)) {
                    return -1;
                }
                $totalInputStreams += $numIn;
                $totalOutputStreams += $numOut;
            } else {
                $totalInputStreams++;
                $totalOutputStreams++;
            }

            if ($hasAttributes) {
                // PropertiesSize (VInt)
                if (! $this->readVIntAt($cursor, $propSize, $cursor, $limit)) {
                    return -1;
                }
                $cursor += $propSize;
            }
        }

        // BindPairs
        $numBindPairs = $totalOutputStreams - 1;
        for ($i = 0; $i < $numBindPairs && $cursor < $limit; $i++) {
            if (! $this->readVIntAt($cursor, $inIndex, $cursor, $limit)) {
                return -1;
            }
            if (! $this->readVIntAt($cursor, $outIndex, $cursor, $limit)) {
                return -1;
            }
        }

        // PackedStreams
        $numPackedStreams = $totalInputStreams - $numBindPairs;
        if ($numPackedStreams > 1) {
            for ($i = 0; $i < $numPackedStreams && $cursor < $limit; $i++) {
                if (! $this->readVIntAt($cursor, $packedIndex, $cursor, $limit)) {
                    return -1;
                }
            }
        }

        return $cursor;
    }

    /**
     * Identify compression method from codec ID bytes.
     */
    private function identifyCompressionMethod(string $codecId): ?string
    {
        if ($codecId === self::METHOD_COPY || $codecId === "\x00") {
            return 'Copy';
        }
        if (str_starts_with($codecId, "\x03\x01\x01")) {
            return 'LZMA';
        }
        if ($codecId === self::METHOD_LZMA2 || str_starts_with($codecId, "\x21")) {
            return 'LZMA2';
        }
        if (str_starts_with($codecId, "\x03\x04\x01")) {
            return 'PPMd';
        }
        if (str_starts_with($codecId, "\x03\x03\x01\x03")) {
            return 'BCJ';
        }
        if (str_starts_with($codecId, "\x03\x03\x01\x1B")) {
            return 'BCJ2';
        }
        if (str_starts_with($codecId, "\x04\x01\x08")) {
            return 'Deflate';
        }
        if (str_starts_with($codecId, "\x04\x02\x02")) {
            return 'BZip2';
        }
        if (str_starts_with($codecId, "\x06\xF1\x07\x01")) {
            return 'AES-256';
        }
        if (str_starts_with($codecId, "\x03\x03\x01\x05")) {
            return 'ARM';
        }
        if (str_starts_with($codecId, "\x03\x03\x01\x08")) {
            return 'SPARC';
        }

        return null;
    }

    /**
     * Parse CodersUnpackSize.
     */
    private function parseCodersUnpackSize(int $cursor, int $limit): int
    {
        // This should read sizes for each output stream of each folder
        // For now, just read available VInts as sizes
        while ($cursor < $limit) {
            $b = ord($this->data[$cursor]);
            if ($b === self::K_END || $b === self::K_CRC) {
                break;
            }
            if ($this->readVIntAt($cursor, $size, $newCursor, $limit)) {
                $this->sizes[] = $size;
                $this->totalUnpackedSize += $size;
                $cursor = $newCursor;
            } else {
                break;
            }
        }

        return $cursor;
    }

    /**
     * Parse SubstreamsInfo for individual file sizes within folders.
     */
    private function parseSubstreamsInfo(int $cursor, int $limit): int
    {
        while ($cursor < $limit) {
            $id = ord($this->data[$cursor]);
            $cursor++;

            if ($id === self::K_END) {
                return $cursor;
            }

            switch ($id) {
                case self::K_NUM_UNPACK_STREAM:
                    // NumUnpackStreams per folder
                    if (! $this->readVIntAt($cursor, $propSize, $cursor, $limit)) {
                        return -1;
                    }
                    $cursor += $propSize;
                    break;

                case self::K_SIZE:
                    // Sizes
                    if (! $this->readVIntAt($cursor, $propSize, $cursor, $limit)) {
                        return -1;
                    }
                    $cursor += $propSize;
                    break;

                case self::K_CRC:
                    // CRCs
                    if (! $this->readVIntAt($cursor, $propSize, $cursor, $limit)) {
                        return -1;
                    }
                    $cursor += $propSize;
                    break;

                default:
                    if (! $this->readVIntAt($cursor, $propSize, $cursor, $limit)) {
                        return -1;
                    }
                    $cursor += $propSize;
                    break;
            }
        }

        return $cursor;
    }

    /**
     * Skip a bit vector structure.
     */
    private function skipBitVector(int $cursor, int $limit, int $numItems): int
    {
        $allDefined = ord($this->data[$cursor]);
        $cursor++;

        if ($allDefined === 0) {
            // Bit array follows
            $numBytes = (int) ceil($numItems / 8);
            $cursor += $numBytes;
        }

        // Then comes the actual CRC values
        for ($i = 0; $i < $numItems && $cursor + 4 <= $limit; $i++) {
            $cursor += 4;
        }

        return $cursor;
    }

    private function parseFilesInfo(int $cursor, int $limit): int
    {
        // Number of files (VInt)
        $numFiles = $this->readVIntAt($cursor, $value, $newCursor, $limit) ? $value : null;
        if ($numFiles === null || $numFiles < 0 || $numFiles > 100000) { // sanity cap
            $this->lastError = 'Invalid number of files: '.$numFiles;

            return $limit;
        }
        $cursor = $newCursor;
        $this->numFiles = $numFiles;

        // Initialize arrays for metadata
        $names = [];
        $emptyStreams = [];
        $emptyFiles = [];
        $antiFiles = [];

        // Property loop until K_END
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

            $propStart = $cursor;

            switch ($propId) {
                case self::K_EMPTY_STREAM:
                    $emptyStreams = $this->parseBitVector($cursor, $propSize, $numFiles);
                    $cursor = $propStart + $propSize;
                    break;

                case self::K_EMPTY_FILE:
                    $numEmpty = count(array_filter($emptyStreams));
                    $emptyFiles = $this->parseBitVector($cursor, $propSize, $numEmpty);
                    $cursor = $propStart + $propSize;
                    break;

                case self::K_ANTI:
                    $numEmpty = count(array_filter($emptyStreams));
                    $antiFiles = $this->parseBitVector($cursor, $propSize, $numEmpty);
                    $cursor = $propStart + $propSize;
                    break;

                case self::K_NAME:
                    $names = $this->parseNames($cursor, $propSize, $numFiles);
                    $cursor = $propStart + $propSize;
                    break;

                case self::K_MTIME:
                    $this->mtimes = $this->parseFileTimes($cursor, $propSize, $numFiles); // @phpstan-ignore assign.propertyType
                    $cursor = $propStart + $propSize;
                    break;

                case self::K_CTIME:
                    $this->ctimes = $this->parseFileTimes($cursor, $propSize, $numFiles); // @phpstan-ignore assign.propertyType
                    $cursor = $propStart + $propSize;
                    break;

                case self::K_ATIME:
                    $this->atimes = $this->parseFileTimes($cursor, $propSize, $numFiles); // @phpstan-ignore assign.propertyType
                    $cursor = $propStart + $propSize;
                    break;

                case self::K_WIN_ATTRIBUTES:
                    $this->attributes = $this->parseAttributes($cursor, $propSize, $numFiles); // @phpstan-ignore assign.propertyType
                    $cursor = $propStart + $propSize;
                    break;

                case self::K_CRC:
                    $this->crcs = $this->parseCRCs($cursor, $propSize, $numFiles); // @phpstan-ignore assign.propertyType
                    $cursor = $propStart + $propSize;
                    break;

                default:
                    // Skip unknown property
                    $cursor = $propStart + $propSize;
                    break;
            }
        }

        // Assign collected names
        if (! empty($names)) {
            $this->names = array_values(array_unique($names)); // @phpstan-ignore assign.propertyType
        }

        return $cursor;
    }

    /**
     * Parse file names from K_NAME property.
     *
     * @return array<string, mixed>
     */
    private function parseNames(int $cursor, int $propSize, int $numFiles): array
    {
        $names = [];

        if ($propSize < 1) {
            return $names;
        }

        $external = ord($this->data[$cursor]);
        if ($external !== 0) { // External data not supported
            return $names;
        }

        $nameBytes = $propSize - 1;
        $cursor++;

        if ($nameBytes <= 0) {
            return $names;
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

            $utf8 = @iconv('UTF-16LE', 'UTF-8//IGNORE', $seg);
            if ($utf8 === false) {
                continue;
            }

            $utf8 = trim($utf8);
            if ($utf8 === '') {
                continue;
            }

            // Normalize path separators
            $utf8Clean = str_replace(['\\'], '/', $utf8);
            // Remove leading './'
            $utf8Clean = preg_replace('#^\./#', '', $utf8Clean);

            if ($utf8Clean === '' || substr_count($utf8Clean, '/') > 16) { // excessive depth -> skip
                continue;
            }

            $names[] = $utf8Clean;

            if (count($names) >= $numFiles) {
                break;
            }
        }

        return $names;
    }

    /**
     * Parse a bit vector from property data.
     *
     * @return list<string|null>
     */
    private function parseBitVector(int $cursor, int $propSize, int $numItems): array
    {
        $result = array_fill(0, $numItems, false);

        if ($propSize < 1) {
            return $result;
        }

        $allDefined = ord($this->data[$cursor]);
        $cursor++;

        if ($allDefined !== 0) {
            // All items are defined
            return array_fill(0, $numItems, true);
        }

        // Parse bit array
        $numBytes = (int) ceil($numItems / 8);
        for ($i = 0; $i < $numItems; $i++) {
            $byteIndex = (int) ($i / 8);
            $bitIndex = 7 - ($i % 8);
            if ($cursor + $byteIndex < $this->len) {
                $byte = ord($this->data[$cursor + $byteIndex]);
                $result[$i] = (($byte >> $bitIndex) & 1) === 1;
            }
        }

        return $result;
    }

    /**
     * Parse file times from property data.
     *
     * @return array<int<0, max>, bool>
     */
    private function parseFileTimes(int $cursor, int $propSize, int $numFiles): array
    {
        $times = [];

        if ($propSize < 1) {
            return $times;
        }

        // Check for AllDefined byte
        $allDefined = ord($this->data[$cursor]);
        $cursor++;

        $definedBits = [];
        if ($allDefined === 0) {
            // Parse bit vector for which files have times defined
            $numBytes = (int) ceil($numFiles / 8);
            for ($i = 0; $i < $numFiles; $i++) {
                $byteIndex = (int) ($i / 8);
                $bitIndex = 7 - ($i % 8);
                if ($cursor + $byteIndex < $this->len) {
                    $byte = ord($this->data[$cursor + $byteIndex]);
                    $definedBits[$i] = (($byte >> $bitIndex) & 1) === 1;
                } else {
                    $definedBits[$i] = false;
                }
            }
            $cursor += $numBytes;
        } else {
            $definedBits = array_fill(0, $numFiles, true);
        }

        // External flag
        if ($cursor >= $this->len) {
            return $times;
        }
        $external = ord($this->data[$cursor]);
        $cursor++;

        if ($external !== 0) {
            return $times; // External data not supported
        }

        // Read times for defined files (FILETIME format - 8 bytes each)
        for ($i = 0; $i < $numFiles; $i++) {
            if (! empty($definedBits[$i]) && $cursor + 8 <= $this->len) {
                $filetime = $this->readUInt64LE($cursor);
                // Convert FILETIME (100-ns intervals since 1601) to Unix timestamp
                $times[$i] = $this->filetimeToUnix($filetime);
                $cursor += 8;
            } else {
                $times[$i] = null;
            }
        }

        return $times;
    }

    /**
     * Parse Windows attributes from property data.
     *
     * @return array<int<0, max>, int|null>
     */
    private function parseAttributes(int $cursor, int $propSize, int $numFiles): array
    {
        $attrs = [];

        if ($propSize < 1) {
            return $attrs;
        }

        // AllDefined byte
        $allDefined = ord($this->data[$cursor]);
        $cursor++;

        $definedBits = [];
        if ($allDefined === 0) {
            $numBytes = (int) ceil($numFiles / 8);
            for ($i = 0; $i < $numFiles; $i++) {
                $byteIndex = (int) ($i / 8);
                $bitIndex = 7 - ($i % 8);
                if ($cursor + $byteIndex < $this->len) {
                    $byte = ord($this->data[$cursor + $byteIndex]);
                    $definedBits[$i] = (($byte >> $bitIndex) & 1) === 1;
                } else {
                    $definedBits[$i] = false;
                }
            }
            $cursor += $numBytes;
        } else {
            $definedBits = array_fill(0, $numFiles, true);
        }

        // External flag
        if ($cursor >= $this->len) {
            return $attrs;
        }
        $external = ord($this->data[$cursor]);
        $cursor++;

        if ($external !== 0) {
            return $attrs;
        }

        // Read attributes (4 bytes each)
        for ($i = 0; $i < $numFiles; $i++) {
            if (! empty($definedBits[$i]) && $cursor + 4 <= $this->len) {
                $attrs[$i] = $this->readUInt32LE($cursor);
                $cursor += 4;
            } else {
                $attrs[$i] = null;
            }
        }

        return $attrs;
    }

    /**
     * Parse CRC values from property data.
     *
     * @return array<int, string|null>
     */
    private function parseCRCs(int $cursor, int $propSize, int $numFiles): array
    {
        $crcs = [];

        if ($propSize < 1) {
            return $crcs;
        }

        // AllDefined byte
        $allDefined = ord($this->data[$cursor]);
        $cursor++;

        $definedBits = [];
        if ($allDefined === 0) {
            $numBytes = (int) ceil($numFiles / 8);
            for ($i = 0; $i < $numFiles; $i++) {
                $byteIndex = (int) ($i / 8);
                $bitIndex = 7 - ($i % 8);
                if ($cursor + $byteIndex < $this->len) {
                    $byte = ord($this->data[$cursor + $byteIndex]);
                    $definedBits[$i] = (($byte >> $bitIndex) & 1) === 1;
                } else {
                    $definedBits[$i] = false;
                }
            }
            $cursor += $numBytes;
        } else {
            $definedBits = array_fill(0, $numFiles, true);
        }

        // Read CRC values (4 bytes each)
        for ($i = 0; $i < $numFiles; $i++) {
            if (! empty($definedBits[$i]) && $cursor + 4 <= $this->len) {
                $crc = $this->readUInt32LE($cursor);
                $crcs[$i] = sprintf('%08X', $crc);
                $cursor += 4;
            } else {
                $crcs[$i] = null;
            }
        }

        return $crcs;
    }

    /**
     * Build consolidated file info array.
     */
    private function buildFileInfo(): void
    {
        $numFiles = max(count($this->names), $this->numFiles);

        for ($i = 0; $i < $numFiles; $i++) {
            $isDir = false;
            if (isset($this->attributes[$i])) {
                $isDir = ($this->attributes[$i] & self::FILE_ATTRIBUTE_DIRECTORY) !== 0;
            }

            $this->files[$i] = [
                'name' => $this->names[$i] ?? null,
                'size' => $this->sizes[$i] ?? null,
                'packed_size' => $this->packedSizes[$i] ?? null,
                'crc' => $this->crcs[$i] ?? null,
                'attributes' => $this->attributes[$i] ?? null,
                'is_dir' => $isDir,
                'mtime' => $this->mtimes[$i] ?? null,
                'ctime' => $this->ctimes[$i] ?? null,
                'atime' => $this->atimes[$i] ?? null,
            ];
        }
    }

    /**
     * Scan raw data for compression method signatures.
     */
    private function scanForCompressionMethods(int $start, int $end): void
    {
        $data = substr($this->data, $start, $end - $start);

        // Look for common method signatures
        if (strpos($data, self::METHOD_AES) !== false) {
            $this->encrypted = true;
            $this->compressionMethods[] = 'AES-256';
        }
        if (strpos($data, self::METHOD_LZMA2) !== false) {
            $this->compressionMethods[] = 'LZMA2';
        }
        if (strpos($data, self::METHOD_LZMA) !== false) {
            $this->compressionMethods[] = 'LZMA';
        }
        if (strpos($data, self::METHOD_PPMD) !== false) {
            $this->compressionMethods[] = 'PPMd';
        }
        if (strpos($data, self::METHOD_BZIP2) !== false) {
            $this->compressionMethods[] = 'BZip2';
        }
        if (strpos($data, self::METHOD_DEFLATE) !== false) {
            $this->compressionMethods[] = 'Deflate';
        }
    }

    /**
     * Fallback scanning for filenames when normal parsing fails.
     * Attempts to find UTF-16LE encoded filenames in the raw data.
     */
    private function fallbackScanForFilenames(): void
    {
        if (! empty($this->names)) {
            return; // Already have names
        }

        // Look for common file extension patterns in UTF-16LE
        $patterns = [
            '.avi', '.mkv', '.mp4', '.wmv', '.mov',
            '.mp3', '.flac', '.wav', '.ogg',
            '.rar', '.zip', '.exe', '.dll',
            '.nfo', '.txt', '.pdf', '.doc',
            '.jpg', '.png', '.gif', '.bmp',
            '.iso', '.bin', '.img', '.nrg',
        ];

        $found = [];

        foreach ($patterns as $ext) {
            // Convert extension to UTF-16LE for searching
            $utf16Ext = @iconv('UTF-8', 'UTF-16LE', $ext);
            if ($utf16Ext === false) {
                continue;
            }

            $pos = 0;
            while (($pos = strpos($this->data, $utf16Ext, $pos)) !== false) {
                // Try to extract the full filename by looking backwards
                $start = $this->findFilenameStart($pos);
                if ($start !== false && $start < $pos) {
                    $len = ($pos - $start) + strlen($utf16Ext);
                    $nameData = substr($this->data, $start, $len);

                    // Validate it's likely a filename (reasonable length, no control chars)
                    if (strlen($nameData) > 2 && strlen($nameData) < 512) {
                        $utf8 = @iconv('UTF-16LE', 'UTF-8//IGNORE', $nameData);
                        if ($utf8 !== false && $this->isValidFilename($utf8)) {
                            $utf8 = str_replace('\\', '/', $utf8);
                            $found[] = $utf8;
                        }
                    }
                }
                $pos += strlen($utf16Ext);
            }
        }

        // Also try to find NFO files specifically (common in releases)
        $this->scanForNfoFiles($found); // @phpstan-ignore argument.type

        if (! empty($found)) {
            $this->names = array_values(array_unique($found)); // @phpstan-ignore assign.propertyType
        }
    }

    /**
     * Find the start of a UTF-16LE filename by scanning backwards.
     */
    private function findFilenameStart(int $extensionPos): int|false
    {
        // Scan backwards looking for a null terminator or invalid char
        $start = $extensionPos;
        $maxLen = 256; // Max filename length to consider

        for ($i = $extensionPos - 2; $i >= max(0, $extensionPos - $maxLen * 2); $i -= 2) {
            if ($i + 1 >= $this->len) {
                continue;
            }

            $lo = ord($this->data[$i]);
            $hi = ord($this->data[$i + 1]);

            // Check for null terminator
            if ($lo === 0 && $hi === 0) {
                $start = $i + 2;
                break;
            }

            // Check for invalid filename characters
            if ($hi === 0) {
                // ASCII range - check for path separators or invalid chars
                if ($lo === 0 || $lo < 32) {
                    $start = $i + 2;
                    break;
                }
            }

            $start = $i;
        }

        return $start >= 0 ? $start : false;
    }

    /**
     * Validate if a string looks like a valid filename.
     */
    private function isValidFilename(string $name): bool
    {
        $name = trim($name);

        if (empty($name) || strlen($name) > 260) {
            return false;
        }

        // Must have at least one printable character
        if (! preg_match('/[a-zA-Z0-9]/', $name)) {
            return false;
        }

        // Should not have too many special characters
        $specialCount = preg_match_all('/[^\w\s.\-_\/\\\\]/', $name);
        if ($specialCount > 5) {
            return false;
        }

        return true;
    }

    /**
     * Scan for NFO files which are very common in release archives.
     *
     * @param  array<string, mixed>  $found
     */
    private function scanForNfoFiles(array &$found): void
    {
        // NFO files are extremely common and usually have simple names
        $nfoMarker = @iconv('UTF-8', 'UTF-16LE', '.nfo');
        if ($nfoMarker === false) {
            return;
        }

        $pos = 0;
        while (($pos = strpos($this->data, $nfoMarker, $pos)) !== false) {
            $start = $this->findFilenameStart($pos);
            if ($start !== false && $start < $pos) {
                $len = ($pos - $start) + strlen($nfoMarker);
                $nameData = substr($this->data, $start, $len);

                if (strlen($nameData) > 2 && strlen($nameData) < 256) {
                    $utf8 = @iconv('UTF-16LE', 'UTF-8//IGNORE', $nameData);
                    if ($utf8 !== false && $this->isValidFilename($utf8)) {
                        $found[] = str_replace('\\', '/', $utf8); // @phpstan-ignore parameterByRef.type
                    }
                }
            }
            $pos += strlen($nfoMarker);
        }
    }

    /**
     * Convert Windows FILETIME to Unix timestamp.
     */
    private function filetimeToUnix(int $filetime): int
    {
        // FILETIME is 100-nanosecond intervals since January 1, 1601
        // Unix epoch is January 1, 1970
        // Difference is 116444736000000000 100-ns intervals
        $unixEpochDiff = 116444736000000000;

        if ($filetime <= $unixEpochDiff) {
            return 0;
        }

        return (int) (($filetime - $unixEpochDiff) / 10000000);
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
     *
     * @param-out int $value
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

    /**
     * Read a 32-bit unsigned little-endian integer.
     */
    private function readUInt32LE(int $offset): int
    {
        if ($offset + 4 > $this->len) {
            return 0;
        }

        return ord($this->data[$offset])
            | (ord($this->data[$offset + 1]) << 8)
            | (ord($this->data[$offset + 2]) << 16)
            | (ord($this->data[$offset + 3]) << 24);
    }

    /**
     * Extract a summary of archive information as an associative array.
     * Useful for quick inspection of archive contents.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        if (! $this->parsed) {
            $this->parse();
        }

        $dirs = 0;
        $regularFiles = 0;

        foreach ($this->files as $file) {
            if ($file['is_dir']) {
                $dirs++;
            } else {
                $regularFiles++;
            }
        }

        return [
            'valid_signature' => $this->isValid7zSignature(),
            'file_count' => $this->numFiles,
            'directory_count' => $dirs,
            'regular_file_count' => $regularFiles,
            'total_unpacked_size' => $this->totalUnpackedSize,
            'total_packed_size' => $this->totalPackedSize,
            'compression_ratio' => $this->getCompressionRatio(),
            'compression_methods' => $this->getCompressionMethods(),
            'encrypted' => $this->encrypted,
            'header_encrypted' => $this->headerEncrypted,
            'encoded_header' => $this->encodedHeader,
            'solid_archive' => $this->solidArchive,
            'last_error' => $this->lastError,
        ];
    }

    /**
     * Get files matching a specific extension.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFilesByExtension(string $extension): array
    {
        if (! $this->parsed) {
            $this->parse();
        }

        $extension = ltrim(strtolower($extension), '.');
        $matches = [];

        foreach ($this->files as $index => $file) {
            if ($file['name'] === null) {
                continue;
            }

            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($fileExt === $extension) {
                $matches[$index] = $file;
            }
        }

        return $matches;
    }

    /**
     * Check if archive contains any files with given extension.
     *
     * @return array<string, mixed>
     */
    public function hasFileWithExtension(string $extension): bool
    {
        return ! empty($this->getFilesByExtension($extension));
    }

    /**
     * Get all directory entries from the archive.
     *
     * @return array<string, mixed>
     */
    public function getDirectories(): array
    {
        if (! $this->parsed) {
            $this->parse();
        }

        return array_filter($this->files, fn ($file) => $file['is_dir']);
    }

    /**
     * Get the largest file in the archive.
     *
     * @return array<string, mixed>
     */
    public function getLargestFile(): ?array
    {
        if (! $this->parsed) {
            $this->parse();
        }

        $largest = null;
        $maxSize = -1;

        foreach ($this->files as $file) {
            if ($file['size'] !== null && $file['size'] > $maxSize && ! $file['is_dir']) {
                $maxSize = $file['size'];
                $largest = $file;
            }
        }

        return $largest;
    }
}
