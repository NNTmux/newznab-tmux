<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\Releases\ReleaseBrowseService;
use dariusiii\rarinfo\ArchiveInfo;
use dariusiii\rarinfo\Par2Info;
use dariusiii\rarinfo\SzipInfo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * Service for extracting and processing archive files (RAR, ZIP, 7z).
 * Handles password detection, file listing, and content extraction.
 * 7z archives are listed only; there is no extractor for them.
 */
class ArchiveExtractionService
{
    /** Largest 7z volume the start header may describe before it is treated as corrupt. */
    public const SEVENZIP_MAX_ARCHIVE_BYTES = 1 << 40;

    /** Stop reading a `7z l` listing after this many bytes or file entries. */
    public const SEVENZIP_LISTING_MAX_BYTES = 4 << 20;

    public const SEVENZIP_LISTING_MAX_ENTRIES = 1000;

    private ArchiveInfo $archiveInfo;

    private Par2Info $par2Info;

    public function __construct(
        private readonly ProcessingConfiguration $config,
        ?ArchiveInfo $archiveInfo = null,
        ?Par2Info $par2Info = null
    ) {
        $this->archiveInfo = $archiveInfo ?? new ArchiveInfo;
        $this->par2Info = $par2Info ?? new Par2Info;

        // Configure external clients for ArchiveInfo
        if ($this->config->unrarPath) {
            $this->archiveInfo->setExternalClients([ArchiveInfo::TYPE_RAR => $this->config->unrarPath]);
        }
    }

    /**
     * Process compressed data and extract file information.
     *
     * @return array<string, mixed>
     */
    public function processCompressedData(
        string $compressedData,
        ReleaseProcessingContext $context,
        string $tmpPath
    ): array {
        $result = [
            'success' => false,
            'files' => [],
            'hasPassword' => false,
            'passwordStatus' => ReleaseBrowseService::PASSWD_NONE,
            'listingOnly' => false,
        ];

        $context->compressedFilesChecked++;

        // Plain media is not an archive, even if ArchiveInfo finds a marker inside it.
        $videoType = self::hasArchiveSignature($compressedData) ? null : $this->detectStandaloneVideo($compressedData);
        if ($videoType !== null) {
            return [
                'success' => false,
                'files' => [],
                'hasPassword' => false,
                'passwordStatus' => ReleaseBrowseService::PASSWD_NONE,
                'standaloneVideoType' => $videoType,
                'standaloneVideoData' => $compressedData,
            ];
        }

        // Try ArchiveInfo for RAR/ZIP/7z
        if (! $this->archiveInfo->setData($compressedData, true)) {
            return $result;
        }

        if ($this->archiveInfo->error !== '') {
            if ($this->config->debugMode) {
                Log::debug('ArchiveInfo Error: '.$this->archiveInfo->error);
            }

            return $result;
        }

        $listingOnly = $this->archiveInfo->type === ArchiveInfo::TYPE_SZIP;
        $result['listingOnly'] = $listingOnly;

        try {
            $dataSummary = $this->archiveInfo->getSummary(! $listingOnly);
        } catch (\Exception $e) {
            if ($this->config->debugMode) {
                Log::warning($e->getTraceAsString());
            }

            return $result;
        }

        // Check for encryption
        if (! empty($this->archiveInfo->isEncrypted)
            || (isset($dataSummary['is_encrypted']) && (int) $dataSummary['is_encrypted'] !== 0)
        ) {
            if ($this->config->debugMode) {
                Log::debug('ArchiveInfo: Compressed file has a password.');
            }

            return [
                ...$result,
                'hasPassword' => true,
                'passwordStatus' => ReleaseBrowseService::PASSWD_RAR,
            ];
        }

        // Prepare extraction directories
        if (! $listingOnly) {
            $this->prepareExtractionDirectories($tmpPath);
        }

        // Process based on archive type
        $archiveMarker = $this->extractArchive($compressedData, $dataSummary, $tmpPath);

        // Get file list
        $files = $listingOnly
            ? $this->archiveInfo->getArchiveFileList(false)
            : $this->archiveInfo->getArchiveFileList();
        if (! is_array($files) || count($files) === 0) {
            return $result;
        }

        return [
            'success' => true,
            'files' => $files,
            'hasPassword' => false,
            'passwordStatus' => ReleaseBrowseService::PASSWD_NONE,
            'archiveMarker' => $archiveMarker,
            'dataSummary' => $dataSummary,
            'listingOnly' => $listingOnly,
        ];
    }

    /**
     * Check if a file is an NFO or info file.
     *
     * @param  string  $filename  The filename to check.
     * @return bool True if it's an NFO-like file.
     */
    public function isNfoFile(string $filename): bool
    {
        $basename = strtolower(basename($filename));

        // Standard NFO extensions
        if (preg_match('/\.(nfo|diz|inf)$/i', $basename)) {
            return true;
        }

        // Common NFO alternative names
        $nfoNames = [
            'file_id.diz', 'fileid.diz', 'file-id.diz',
            'readme.txt', 'readme.1st', 'read.me', 'readmenow.txt',
            'info.txt', 'information.txt', 'about.txt', 'notes.txt',
            'release.txt', 'release.nfo',
        ];

        if (in_array($basename, $nfoNames, true)) {
            return true;
        }

        // Scene-style NFO naming: 00-groupname.nfo, group-release.nfo
        if (preg_match('/^(?:00?-[a-z0-9_-]+|[a-z0-9]+-[a-z0-9._-]+)\.(?:nfo|txt)$/i', $basename)) {
            return true;
        }

        return false;
    }

    /**
     * Sort files to prioritize NFO files for processing.
     *
     * @param  list<array<string, mixed>>  $files  Array of file info arrays.
     * @return list<array<string, mixed>> Sorted array with NFO files first.
     */
    public function sortFilesWithNfoPriority(array $files): array
    {
        usort($files, function ($a, $b) {
            $aIsNfo = $this->isNfoFile($a['name'] ?? '');
            $bIsNfo = $this->isNfoFile($b['name'] ?? '');

            if ($aIsNfo && ! $bIsNfo) {
                return -1;
            }
            if (! $aIsNfo && $bIsNfo) {
                return 1;
            }

            return 0;
        });

        return $files;
    }

    /**
     * Prepare extraction directories.
     *
     * @return list<mixed>
     */
    private function prepareExtractionDirectories(string $tmpPath): void
    {
        if ($this->config->extractUsingRarInfo) {
            return;
        }

        try {
            if ($this->config->unrarPath) {
                $unrarDir = $tmpPath.'unrar/';
                if (! File::isDirectory($unrarDir)) {
                    File::makeDirectory($unrarDir, 0777, true, true);
                }
            }
            $unzipDir = $tmpPath.'unzip/';
            if (! File::isDirectory($unzipDir)) {
                File::makeDirectory($unzipDir, 0777, true, true);
            }
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::warning('Failed ensuring extraction subdirectories: '.$e->getMessage());
            }
        }
    }

    /**
     * Extract archive based on type.
     *
     * @param  array<string, mixed>  $dataSummary
     */
    private function extractArchive(string $compressedData, array $dataSummary, string $tmpPath): string
    {
        switch ($dataSummary['main_type']) {
            case ArchiveInfo::TYPE_RAR:
                if (! $this->config->extractUsingRarInfo && $this->config->unrarPath) {
                    $fileName = $tmpPath.uniqid('', true).'.rar';
                    File::put($fileName, $compressedData);
                    try {
                        $this->runExternalCommand($this->config->unrarPath, [
                            'e', '-ai', '-ep', '-c-', '-id', '-inul', '-kb', '-or', '-p-', '-r', '-y', '-@', '-',
                            $fileName,
                            $tmpPath.'unrar/',
                        ]);
                    } finally {
                        File::delete($fileName);
                    }
                }

                return 'r';

            case ArchiveInfo::TYPE_ZIP:
                if (! $this->config->extractUsingRarInfo && $this->config->unzipPath) {
                    $fileName = $tmpPath.uniqid('', true).'.zip';
                    File::put($fileName, $compressedData);
                    try {
                        $this->runExternalCommand($this->config->unzipPath, [
                            '-o',
                            '-d',
                            $tmpPath.'unzip/',
                            '--',
                            $fileName,
                        ]);
                    } finally {
                        File::delete($fileName);
                    }
                }

                return 'z';

            case ArchiveInfo::TYPE_SZIP:
                return '7';
        }

        return '';
    }

    /**
     * True when the data starts with a RAR, ZIP or 7z signature.
     */
    public static function hasArchiveSignature(string $data): bool
    {
        foreach (["Rar!\x1A\x07\x00", "Rar!\x1A\x07\x01\x00", "PK\x03\x04", SzipInfo::MARKER_SIGNATURE] as $signature) {
            if (str_starts_with($data, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the data starts a 7z archive whose end header (file list) lies beyond it.
     */
    public function needsSevenZipEndHeader(string $data): bool
    {
        $start = $this->sevenZipStartHeader($data);

        return $start !== null && 32 + $start['next_offset'] + $start['next_size'] > strlen($data);
    }

    /**
     * Rebuild a listable 7z from its start header and the archive's last bytes.
     *
     * The start header gives the size and CRC of the end header, which 7-Zip writes
     * last, so it can be cut from the tail and checked. Packed data is left out.
     */
    public function withSevenZipEndHeader(string $head, string $tail): ?string
    {
        $start = $this->sevenZipStartHeader($head);
        if ($start === null || $start['next_size'] === 0 || $start['next_size'] > strlen($tail)) {
            return null;
        }

        $endHeader = substr($tail, -$start['next_size']);
        if (crc32($endHeader) !== $start['next_crc']) {
            return null;
        }

        $startHeader = pack('PPV', 0, $start['next_size'], $start['next_crc']);

        return substr($head, 0, 8).pack('V', crc32($startHeader)).$startHeader.$endHeader;
    }

    /**
     * List a 7z whose end header is compressed, which rarinfo can't decode, with the 7-Zip client.
     *
     * The client gets a sparse copy of the archive holding only the downloaded first and
     * last bytes at their real offsets; that is enough when the packed header stream lies
     * inside them, as 7-Zip writes it just before the end header. Without a tail the whole
     * archive, end header included, must be in the head.
     *
     * The listing is read as it streams and the client is stopped after
     * SEVENZIP_LISTING_MAX_ENTRIES entries or SEVENZIP_LISTING_MAX_BYTES of output; the
     * entries read until then are kept, as only a handful are stored per release anyway,
     * and the context is marked so the password status of the release stays unknown. The
     * same happens when a record can't be read unambiguously and is left out.
     *
     * @return array<string, mixed>|null result like processCompressedData(), or null to fall back
     */
    public function listEncodedSevenZip(
        string $head,
        ?string $tail,
        ReleaseProcessingContext $context,
        string $tmpPath,
    ): ?array {
        $client = $this->config->sevenZipPath;
        if (! is_string($client) || ! is_executable($client)) {
            return null;
        }

        $start = $this->sevenZipStartHeader($head);
        $length = $start === null ? null : $this->sevenZipArchiveLength($start, $context);
        if ($length === null) {
            return null;
        }
        if ($tail === null) {
            if ($length > strlen($head)) {
                return null;
            }
            $tail = substr($head, 0, $length);
        }

        $joined = $this->withSevenZipEndHeader($head, $tail);
        if ($joined === null) {
            return null;
        }

        $szip = new SzipInfo;
        $szip->setData($joined);
        $encoded = null;
        foreach ($szip->getHeaders() ?: [] as $header) {
            if ($header['type'] === SzipInfo::PROPERTY_ENCODED_HEADER) {
                $encoded = $header;
            }
        }
        if ($encoded === null || $szip->isEncrypted || ! isset($encoded['pack_offset'], $encoded['pack_sizes'])) {
            return null;
        }

        $tailStart = $length - strlen($tail);
        $packStart = 32 + (int) $encoded['pack_offset'];
        $packEnd = $packStart + (int) array_sum($encoded['pack_sizes']);
        if ($tailStart < 0 || $packStart < 32 || $packEnd > $length - $start['next_size']
            || ($packStart < $tailStart && $packEnd > strlen($head))
        ) {
            return null;
        }

        $file = $tmpPath.uniqid('7z_', true).'.7z';
        $process = null;
        try {
            $handle = @fopen($file, 'wb');
            if ($handle === false) {
                return null;
            }
            $written = fwrite($handle, $head) === strlen($head)
                && fseek($handle, $tailStart) === 0
                && fwrite($handle, $tail) === strlen($tail);
            fclose($handle);
            if (! $written) {
                return null;
            }

            // A dummy password makes an encrypted archive fail instead of prompting.
            $process = new SymfonyProcess([$client, 'l', '-slt', '-ba', '-sccUTF-8', '-pnntmux', '-t7z', '--', $file]);
            $process->setTimeout(60)->start();
            [$files, $complete] = $this->readSevenZipListing($process);
        } catch (\Throwable) {
            return null;
        } finally {
            if ($process?->isRunning()) {
                $process->stop(0);
            }
            @unlink($file);
        }

        // Marked even when no record survived, as a rejected record may be the encrypted one.
        if (! $complete) {
            $context->archiveListingIncomplete = true;
        }
        if ($files === []) {
            return null;
        }

        $context->compressedFilesChecked++;

        return [
            'success' => true,
            'files' => $files,
            'hasPassword' => false,
            'passwordStatus' => ReleaseBrowseService::PASSWD_NONE,
            'archiveMarker' => '7',
            'dataSummary' => ['main_type' => ArchiveInfo::TYPE_SZIP],
            'listingOnly' => true,
        ];
    }

    /**
     * Turn `7z l -slt` output into rarinfo-style file records, leaving out directories.
     * Records that can't be read unambiguously are left out and clear $reliable; see
     * SevenZipListingParser.
     *
     * @return list<array{name: string, size: int, date: int, pass: int, compressed: int, crc32?: string}>
     */
    public static function parseSevenZipListing(string $output, ?bool &$reliable = null): array
    {
        $parser = new SevenZipListingParser;
        $parser->feed($output);
        $parser->finish();
        $reliable = $parser->reliable;

        return $parser->files;
    }

    /**
     * Parse a running `7z l -slt` listing as it streams, stopping the client at the caps.
     * Also says whether the listing was read to the end with every record understood.
     *
     * @return array{list<array{name: string, size: int, date: int, pass: int, compressed: int, crc32?: string}>, bool}
     */
    private function readSevenZipListing(SymfonyProcess $process): array
    {
        $parser = new SevenZipListingParser;
        $bytes = 0;
        foreach ($process->getIterator(SymfonyProcess::ITER_SKIP_ERR) as $chunk) {
            // Parse no more than the cap, however much output a single read returned.
            $cut = $bytes + strlen($chunk) > self::SEVENZIP_LISTING_MAX_BYTES;
            if ($cut) {
                $chunk = substr($chunk, 0, self::SEVENZIP_LISTING_MAX_BYTES - $bytes);
            }
            $bytes += strlen($chunk);
            $parser->feed($chunk);
            if (count($parser->files) >= self::SEVENZIP_LISTING_MAX_ENTRIES) {
                $process->stop(0);

                return [array_slice($parser->files, 0, self::SEVENZIP_LISTING_MAX_ENTRIES), false];
            }
            if ($cut) {
                // The record being read when the output was cut is dropped, never half-parsed.
                $process->stop(0);

                return [$parser->files, false];
            }
        }

        if ($process->wait() !== 0) {
            return [[], true];
        }
        $parser->finish();

        return [$parser->files, $parser->reliable];
    }

    /**
     * Length of the archive the start header describes, or null when it can't belong to this
     * release: larger than the release itself (when known) or SEVENZIP_MAX_ARCHIVE_BYTES.
     *
     * @param  array{next_offset: int, next_size: int, next_crc: int}  $start
     */
    private function sevenZipArchiveLength(array $start, ReleaseProcessingContext $context): ?int
    {
        $length = 32 + $start['next_offset'] + $start['next_size'];
        $releaseSize = (int) ($context->release->size ?? 0);

        return $releaseSize > 0 && $length > $releaseSize ? null : $length;
    }

    /**
     * @return array{next_offset: int, next_size: int, next_crc: int}|null
     */
    private function sevenZipStartHeader(string $data): ?array
    {
        if (strlen($data) < 32 || ! str_starts_with($data, SzipInfo::MARKER_SIGNATURE)) {
            return null;
        }

        /** @var array{next_offset: int, next_size: int, next_crc: int} $start */
        $start = unpack('Pnext_offset/Pnext_size/Vnext_crc', $data, 12);

        // Unsigned values past PHP_INT_MAX unpack as negative; anything beyond the cap is corrupt
        // and must not reach the offset arithmetic or the sparse copy.
        $max = self::SEVENZIP_MAX_ARCHIVE_BYTES - 32;

        return $start['next_offset'] >= 0 && $start['next_size'] >= 0
            && $start['next_offset'] <= $max && $start['next_size'] <= $max - $start['next_offset']
            ? $start : null;
    }

    /**
     * Detect a plain media container (not an archive) from its leading bytes.
     */
    public function detectStandaloneVideo(string $data): ?string
    {
        if (strlen($data) < 16) {
            return null;
        }

        $sig4 = substr($data, 0, 4);

        return match (true) {
            $sig4 === 'RIFF' && substr($data, 8, 4) === 'AVI ' => 'avi',
            $sig4 === "\x1A\x45\xDF\xA3" => 'mkv',
            $sig4 === "\x00\x00\x01\xBA", $sig4 === "\x00\x00\x01\xB3" => 'mpg',
            $data[0] === "\x47" && ($data[188] ?? '') === "\x47" && ($data[376] ?? '') === "\x47" => 'ts',
            substr($data, 4, 4) === 'ftyp' && preg_match('/^[\x20-\x7E]{4}$/', substr($data, 8, 4)) === 1 => 'mp4',
            str_starts_with($data, "\x30\x26\xB2\x75\x8E\x66\xCF\x11") => 'wmv',
            str_starts_with($data, "FLV\x01") => 'flv',
            $sig4 === 'OggS' => 'ogv',
            default => null,
        };
    }

    /**
     * Get PAR2 info parser.
     */
    public function getPar2Info(): Par2Info
    {
        return $this->par2Info;
    }

    /**
     * Get archive info handler.
     */
    public function getArchiveInfo(): ArchiveInfo
    {
        return $this->archiveInfo;
    }

    /**
     * Extract a specific file from archive data by filename.
     *
     * @param  string  $compressedData  The raw archive data
     * @param  string  $filename  The filename to extract (exact match)
     * @param  string  $tmpPath  Temporary directory path
     * @return string|null The extracted file content, or null if extraction failed
     */
    public function extractSpecificFile(string $compressedData, string $filename, string $tmpPath): ?string
    {
        return $this->extractSpecificFiles($compressedData, [$filename], $tmpPath)[$filename] ?? null;
    }

    /**
     * Inspect one archive and extract several exact filenames from it.
     *
     * @param  list<string>  $filenames
     * @return array<string, string>
     */
    public function extractSpecificFiles(string $compressedData, array $filenames, string $tmpPath): array
    {
        if (str_starts_with($compressedData, SzipInfo::MARKER_SIGNATURE)) {
            return [];
        }

        $filenames = array_values(array_unique(array_filter(
            $filenames,
            static fn (string $filename): bool => $filename !== '',
        )));
        $extractedFiles = [];
        $remaining = $filenames;

        if ($this->archiveInfo->setData($compressedData, true)) {
            if ($this->archiveInfo->type === ArchiveInfo::TYPE_SZIP) {
                return [];
            }

            $entries = self::completeStoredEntries($this->archiveInfo->getArchiveFileList());

            foreach ($filenames as $filename) {
                if (! isset($entries[$filename])) {
                    continue;
                }

                try {
                    $extracted = $this->archiveInfo->getFileData($filename);
                    if (is_string($extracted) && strlen($extracted) === $entries[$filename]) {
                        $extractedFiles[$filename] = $extracted;
                        $remaining = array_values(array_diff($remaining, [$filename]));
                    }
                } catch (\Throwable $e) {
                    if ($this->config->debugMode) {
                        Log::debug('ArchiveInfo getFileData failed: '.$e->getMessage());
                    }
                }
            }
        }

        foreach ($remaining as $filename) {
            $extracted = $this->extractSpecificFileWithExternalTools($compressedData, $filename, $tmpPath);
            if ($extracted !== null) {
                $extractedFiles[$filename] = $extracted;
            }
        }

        return $extractedFiles;
    }

    /**
     * Entries whose bytes can be copied straight out of the downloaded data:
     * stored, not encrypted, and wholly inside it.
     *
     * @param  array<int|string, mixed>  $files
     * @return array<string, int> name => size
     */
    public static function completeStoredEntries(array $files): array
    {
        $entries = [];
        foreach ($files as $file) {
            if (! is_array($file) || ! isset($file['name'], $file['size'], $file['range'])
                || ($file['source'] ?? 'main') !== 'main'
                || ! empty($file['pass'])
                || ! empty($file['is_dir'])
                || ($file['compressed'] ?? null) !== 0
            ) {
                continue;
            }

            [$start, $end] = array_map(intval(...), explode('-', (string) $file['range'], 2) + [1 => -1]);
            if ((int) $file['size'] > 0 && $end - $start + 1 === (int) $file['size']) {
                $entries[(string) $file['name']] = (int) $file['size'];
            }
        }

        return $entries;
    }

    private function extractSpecificFileWithExternalTools(
        string $compressedData,
        string $filename,
        string $tmpPath,
    ): ?string {
        if ($this->config->unrarPath) {
            $extracted = $this->extractFileViaExternalTool(
                $compressedData,
                $filename,
                $tmpPath,
                'rar',
                fn (string $archiveFile, string $extractDir): array => [
                    'e', '-y', '-c-', '-inul', '-p-', '-@', '-',
                    $archiveFile,
                    $filename,
                    $extractDir,
                ],
            );
            if ($extracted !== null) {
                return $extracted;
            }
        }

        if ($this->config->unzipPath) {
            $extracted = $this->extractFileViaExternalTool(
                $compressedData,
                $filename,
                $tmpPath,
                'zip',
                fn (string $archiveFile, string $extractDir): array => [
                    '-j',
                    '-d',
                    $extractDir,
                    '--',
                    $archiveFile,
                    $filename,
                ],
            );
            if ($extracted !== null) {
                return $extracted;
            }
        }

        return null;
    }

    /**
     * Extract a specific file using unrar.
     *
     * @param  callable(string, string): list<string>  $commandBuilder
     */
    private function extractFileViaExternalTool(
        string $compressedData,
        string $filename,
        string $tmpPath,
        string $archiveExtension,
        callable $commandBuilder
    ): ?string {
        $extractDir = null;
        $archiveFile = null;

        try {
            $binary = $archiveExtension === 'rar' ? $this->config->unrarPath : $this->config->unzipPath;
            if (! is_string($binary)) {
                return null;
            }

            $extractDir = $tmpPath.'extract_'.uniqid('', true).'/';
            if (! File::isDirectory($extractDir)) {
                File::makeDirectory($extractDir, 0777, true, true);
            }

            $archiveFile = $tmpPath.'archive_'.uniqid('', true).'.'.$archiveExtension;
            File::put($archiveFile, $compressedData);

            $this->runExternalCommand($binary, $commandBuilder($archiveFile, $extractDir));

            // Look for extracted file
            $extractedPath = $extractDir.basename($filename);
            if (File::isFile($extractedPath)) {
                return File::get($extractedPath);
            }

            // Try to find it with glob
            $files = File::allFiles($extractDir);
            foreach ($files as $file) {
                if (strtolower($file->getFilename()) === strtolower(basename($filename))) {
                    return File::get($file->getPathname());
                }
            }
        } catch (\Throwable $e) {
            if ($this->config->debugMode) {
                Log::debug('External extraction failed: '.$e->getMessage());
            }
        } finally {
            if (is_string($archiveFile)) {
                File::delete($archiveFile);
            }
            if (is_string($extractDir) && File::isDirectory($extractDir)) {
                File::deleteDirectory($extractDir);
            }
        }

        return null;
    }

    /**
     * Run an external extractor without invoking a command shell.
     *
     * @param  list<string>  $arguments
     */
    private function runExternalCommand(string $binary, array $arguments): void
    {
        $command = [$binary, ...$arguments];

        if ($this->config->timeoutPath && $this->config->timeoutSeconds > 0) {
            $command = [
                $this->config->timeoutPath,
                '--foreground',
                '--signal=KILL',
                (string) $this->config->timeoutSeconds,
                ...$command,
            ];
        }

        Process::timeout(1800)->run($command);
    }
}
